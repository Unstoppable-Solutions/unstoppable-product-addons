<?php
/**
 * Inventory Tracking for Add-on Options
 * Addresses competitor weakness: add-ons can now be linked to real products for stock management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Inventory {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        // Stock checks during add to cart
        add_filter( 'upad_validate_addon_option', array( $this, 'check_linked_product_stock' ), 10, 4 );
        
        // Reduce stock when order is placed
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'reduce_linked_product_stock' ), 10, 3 );
        
        // Restore stock when order is cancelled
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'restore_linked_product_stock' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'restore_linked_product_stock' ) );
        
        // Display stock status on frontend
        add_filter( 'upad_option_data', array( $this, 'add_stock_status_to_option' ), 10, 2 );
        
        // Admin column for linked products
        add_filter( 'upad_admin_option_columns', array( $this, 'add_linked_product_column' ) );
    }

    /**
     * Check if linked product has stock before allowing selection
     */
    public function check_linked_product_stock( $valid, $option_id, $addon, $product ) {
        if ( ! $valid ) {
            return $valid;
        }

        // Check if inventory tracking is enabled
        if ( 'yes' !== get_option( 'upad_enable_inventory_tracking', 'no' ) ) {
            return $valid;
        }

        $option = $this->get_option( $option_id );
        if ( ! $option || empty( $option->linked_product_id ) ) {
            return $valid;
        }

        $linked_product = wc_get_product( $option->linked_product_id );
        if ( ! $linked_product ) {
            return $valid;
        }

        // Check if product is in stock
        if ( ! $linked_product->is_in_stock() ) {
            return new WP_Error(
                'out_of_stock',
                sprintf(
                    __( '"%s" is currently out of stock.', 'wc-options-studio' ),
                    $option->label
                )
            );
        }

        // Check if product has enough stock
        if ( $linked_product->managing_stock() ) {
            $stock_quantity = $linked_product->get_stock_quantity();
            $cart_quantity = $this->get_cart_quantity_for_linked_product( $option->linked_product_id );
            
            if ( $stock_quantity <= $cart_quantity ) {
                return new WP_Error(
                    'insufficient_stock',
                    sprintf(
                        __( 'Not enough stock for "%s". Available: %d', 'wc-options-studio' ),
                        $option->label,
                        max( 0, $stock_quantity - $cart_quantity )
                    )
                );
            }
        }

        return $valid;
    }

    /**
     * Reduce stock of linked products when order is placed
     */
    public function reduce_linked_product_stock( $order_id, $posted_data, $order ) {
        if ( 'yes' !== get_option( 'upad_enable_inventory_tracking', 'no' ) ) {
            return;
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $addon_data = $item->get_meta( '_upad_addon_data' );
            
            if ( empty( $addon_data ) || ! is_array( $addon_data ) ) {
                continue;
            }

            foreach ( $addon_data as $addon ) {
                if ( empty( $addon['options'] ) ) {
                    continue;
                }

                foreach ( $addon['options'] as $option ) {
                    if ( empty( $option['linked_product_id'] ) ) {
                        continue;
                    }

                    $linked_product = wc_get_product( $option['linked_product_id'] );
                    if ( ! $linked_product || ! $linked_product->managing_stock() ) {
                        continue;
                    }

                    $quantity = isset( $option['quantity'] ) ? absint( $option['quantity'] ) : 1;
                    $quantity *= $item->get_quantity(); // Multiply by order item quantity

                    wc_reduce_stock_levels( $linked_product->get_id(), $quantity );

                    // Add order note
                    $order->add_order_note(
                        sprintf(
                            __( 'Reduced stock for linked product "%s" (Add-on: %s) by %d', 'wc-options-studio' ),
                            $linked_product->get_name(),
                            $addon['title'],
                            $quantity
                        )
                    );
                }
            }
        }
    }

    /**
     * Restore stock of linked products when order is cancelled/refunded
     */
    public function restore_linked_product_stock( $order_id ) {
        if ( 'yes' !== get_option( 'upad_enable_inventory_tracking', 'no' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if stock was already restored
        if ( $order->get_meta( '_upad_stock_restored' ) ) {
            return;
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $addon_data = $item->get_meta( '_upad_addon_data' );
            
            if ( empty( $addon_data ) || ! is_array( $addon_data ) ) {
                continue;
            }

            foreach ( $addon_data as $addon ) {
                if ( empty( $addon['options'] ) ) {
                    continue;
                }

                foreach ( $addon['options'] as $option ) {
                    if ( empty( $option['linked_product_id'] ) ) {
                        continue;
                    }

                    $linked_product = wc_get_product( $option['linked_product_id'] );
                    if ( ! $linked_product || ! $linked_product->managing_stock() ) {
                        continue;
                    }

                    $quantity = isset( $option['quantity'] ) ? absint( $option['quantity'] ) : 1;
                    $quantity *= $item->get_quantity();

                    wc_update_product_stock( $linked_product, $quantity, 'increase' );

                    $order->add_order_note(
                        sprintf(
                            __( 'Restored stock for linked product "%s" (Add-on: %s) by %d', 'wc-options-studio' ),
                            $linked_product->get_name(),
                            $addon['title'],
                            $quantity
                        )
                    );
                }
            }
        }

        $order->update_meta_data( '_upad_stock_restored', true );
        $order->save();
    }

    /**
     * Add stock status to option data for frontend display
     */
    public function add_stock_status_to_option( $option_data, $option ) {
        if ( 'yes' !== get_option( 'upad_enable_inventory_tracking', 'no' ) ) {
            return $option_data;
        }

        if ( empty( $option->linked_product_id ) ) {
            return $option_data;
        }

        $linked_product = wc_get_product( $option->linked_product_id );
        if ( ! $linked_product ) {
            return $option_data;
        }

        $option_data['in_stock'] = $linked_product->is_in_stock();
        $option_data['stock_status'] = $linked_product->get_stock_status();
        
        if ( $linked_product->managing_stock() ) {
            $option_data['stock_quantity'] = $linked_product->get_stock_quantity();
            $option_data['low_stock'] = $linked_product->get_stock_quantity() <= get_option( 'woocommerce_notify_low_stock_amount', 2 );
        }

        return $option_data;
    }

    /**
     * Get quantity of linked product already in cart
     */
    private function get_cart_quantity_for_linked_product( $product_id ) {
        if ( ! WC()->cart ) {
            return 0;
        }

        $quantity = 0;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            // Check direct product
            if ( $cart_item['product_id'] == $product_id || 
                 ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] == $product_id ) ) {
                $quantity += $cart_item['quantity'];
            }

            // Check linked products in addons
            if ( ! empty( $cart_item['upad_addons'] ) ) {
                foreach ( $cart_item['upad_addons'] as $addon ) {
                    if ( ! empty( $addon['options'] ) ) {
                        foreach ( $addon['options'] as $option ) {
                            if ( ! empty( $option['linked_product_id'] ) && 
                                 $option['linked_product_id'] == $product_id ) {
                                $qty = isset( $option['quantity'] ) ? absint( $option['quantity'] ) : 1;
                                $quantity += $qty * $cart_item['quantity'];
                            }
                        }
                    }
                }
            }
        }

        return $quantity;
    }

    /**
     * Get option from database
     */
    private function get_option( $option_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE id = %d",
            $option_id
        ) );
    }

    /**
     * Link an option to a product
     */
    public function link_option_to_product( $option_id, $product_id ) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'upad_addon_options',
            array( 'linked_product_id' => $product_id ),
            array( 'id' => $option_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Unlink an option from a product
     */
    public function unlink_option( $option_id ) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'upad_addon_options',
            array( 'linked_product_id' => null ),
            array( 'id' => $option_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Get all options linked to a product
     */
    public function get_options_linked_to_product( $product_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT o.*, a.title as addon_title 
             FROM {$wpdb->prefix}upad_addon_options o
             LEFT JOIN {$wpdb->prefix}upad_addons a ON o.addon_id = a.id
             WHERE o.linked_product_id = %d",
            $product_id
        ) );
    }

    /**
     * Search products for linking
     */
    public function search_products( $search_term, $limit = 20 ) {
        $args = array(
            'status'  => 'publish',
            'limit'   => $limit,
            's'       => $search_term,
            'orderby' => 'title',
            'order'   => 'ASC',
        );

        $products = wc_get_products( $args );
        $results = array();

        foreach ( $products as $product ) {
            $stock_status = '';
            if ( $product->managing_stock() ) {
                $stock_status = sprintf( ' (%s in stock)', $product->get_stock_quantity() );
            } elseif ( ! $product->is_in_stock() ) {
                $stock_status = ' (Out of stock)';
            }

            $results[] = array(
                'id'    => $product->get_id(),
                'text'  => $product->get_name() . ' (#' . $product->get_id() . ')' . $stock_status,
                'sku'   => $product->get_sku(),
                'price' => $product->get_price(),
            );
        }

        return $results;
    }

    /**
     * Add linked product column to admin
     */
    public function add_linked_product_column( $columns ) {
        $columns['linked_product'] = __( 'Linked Product', 'wc-options-studio' );
        return $columns;
    }
}
