<?php
/**
 * Order Handler
 * Addresses competitor weakness: Option to add add-ons as separate line items for customs/shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Order {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        // Add addon data to order items
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
        
        // Optionally add as separate line items
        add_action( 'woocommerce_checkout_create_order', array( $this, 'maybe_add_separate_line_items' ), 10, 2 );
        
        // Display in order details
        add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_meta' ), 10, 2 );
        
        // Display in admin order
        add_action( 'woocommerce_before_order_itemmeta', array( $this, 'display_admin_order_item_addons' ), 10, 3 );
        
        // Add to order emails
        add_filter( 'woocommerce_order_item_name', array( $this, 'order_item_name' ), 10, 2 );
        
        // Handle order item addition from admin
        add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'ajax_add_order_item_meta' ), 10, 3 );
    }

    /**
     * Add addon data to order item meta
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['upad_addons'] ) ) {
            return;
        }

        // Store full addon data
        $item->add_meta_data( '_upad_addon_data', $values['upad_addons'], true );

        // Store individual addons for display
        foreach ( $values['upad_addons'] as $addon ) {
            $display_value = $addon['display'];
            
            if ( 'yes' === get_option( 'upad_display_option_prices', 'yes' ) && ! empty( $addon['price'] ) ) {
                if ( apply_filters( 'woocommerce_addons_add_order_price_to_value', false ) ) {
                    $display_value .= ' (' . wc_price( $addon['price'] ) . ')';
                }
            }

            $item->add_meta_data( $addon['title'], $display_value );
        }

        // Handle file uploads
        $this->process_file_uploads( $item, $values['upad_addons'], $order );
    }

    /**
     * Process file uploads for order
     */
    private function process_file_uploads( $item, $addons, $order ) {
        foreach ( $addons as $addon ) {
            if ( $addon['type'] !== 'file_upload' || empty( $addon['value'] ) ) {
                continue;
            }

            foreach ( (array) $addon['value'] as $upload_hash ) {
                UPAD_File_Upload::instance()->associate_uploads_with_order(
                    $order->get_id(),
                    $item->get_id(),
                    $addon['addon_id'],
                    $upload_hash
                );
            }
        }
    }

    /**
     * Optionally add add-on options as separate line items
     * This addresses the competitor weakness for international shipping/customs
     */
    public function maybe_add_separate_line_items( $order, $data ) {
        if ( 'yes' !== get_option( 'upad_add_as_line_items', 'no' ) ) {
            return;
        }

        $items_to_add = array();

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
                    // Only add linked products as separate items
                    if ( empty( $option['linked_product_id'] ) ) {
                        continue;
                    }

                    $linked_product = wc_get_product( $option['linked_product_id'] );
                    if ( ! $linked_product ) {
                        continue;
                    }

                    $items_to_add[] = array(
                        'product'      => $linked_product,
                        'quantity'     => $item->get_quantity(),
                        'parent_item'  => $item_id,
                        'addon_title'  => $addon['title'],
                        'option_label' => $option['label'],
                    );
                }
            }
        }

        // Add the linked products as separate line items
        foreach ( $items_to_add as $item_data ) {
            $line_item = new WC_Order_Item_Product();
            $line_item->set_product( $item_data['product'] );
            $line_item->set_quantity( $item_data['quantity'] );
            $line_item->set_subtotal( 0 ); // Price already included in parent
            $line_item->set_total( 0 );
            $line_item->add_meta_data( '_upad_linked_from', $item_data['parent_item'] );
            $line_item->add_meta_data( '_upad_addon_source', $item_data['addon_title'] . ': ' . $item_data['option_label'] );
            
            $order->add_item( $line_item );
        }
    }

    /**
     * Format order item meta for display
     */
    public function format_order_item_meta( $formatted_meta, $item ) {
        // Hide internal meta
        $hidden_keys = array( '_upad_addon_data', '_upad_linked_from', '_upad_addon_source' );
        
        foreach ( $formatted_meta as $key => $meta ) {
            if ( in_array( $meta->key, $hidden_keys, true ) ) {
                unset( $formatted_meta[ $key ] );
            }
        }

        return $formatted_meta;
    }

    /**
     * Display addon details in admin order view
     */
    public function display_admin_order_item_addons( $item_id, $item, $product ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return;
        }

        $addon_data = $item->get_meta( '_upad_addon_data' );
        
        if ( empty( $addon_data ) || ! is_array( $addon_data ) ) {
            return;
        }

        echo '<div class="upad-order-item-addons">';
        
        foreach ( $addon_data as $addon ) {
            echo '<div class="upad-addon-item">';
            echo '<strong>' . esc_html( $addon['title'] ) . ':</strong> ';
            echo esc_html( $addon['display'] );
            
            if ( ! empty( $addon['price'] ) ) {
                echo ' <span class="upad-addon-price">(' . wc_price( $addon['price'] ) . ')</span>';
            }

            // Show file upload links
            if ( $addon['type'] === 'file_upload' && ! empty( $addon['value'] ) ) {
                echo '<div class="upad-uploaded-files">';
                foreach ( (array) $addon['value'] as $upload_hash ) {
                    $download_url = UPAD_File_Upload::instance()->get_download_url( $upload_hash );
                    echo '<a href="' . esc_url( $download_url ) . '" target="_blank" class="button button-small">';
                    echo '<span class="dashicons dashicons-download"></span> ' . __( 'Download File', 'unstoppable-product-addons' );
                    echo '</a> ';
                }
                echo '</div>';
            }

            // Show linked product info
            if ( ! empty( $addon['options'] ) ) {
                foreach ( $addon['options'] as $option ) {
                    if ( ! empty( $option['linked_product_id'] ) ) {
                        $linked = wc_get_product( $option['linked_product_id'] );
                        if ( $linked ) {
                            echo '<div class="upad-linked-product">';
                            echo '<small>' . __( 'Linked to:', 'unstoppable-product-addons' ) . ' ';
                            echo '<a href="' . get_edit_post_link( $linked->get_id() ) . '">';
                            echo esc_html( $linked->get_name() );
                            if ( $linked->get_sku() ) {
                                echo ' (SKU: ' . esc_html( $linked->get_sku() ) . ')';
                            }
                            echo '</a></small>';
                            echo '</div>';
                        }
                    }
                }
            }

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Add addon info to order item name in emails
     */
    public function order_item_name( $name, $item ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            return $name;
        }

        // Check if this is a linked item
        $linked_from = $item->get_meta( '_upad_linked_from' );
        if ( $linked_from ) {
            $source = $item->get_meta( '_upad_addon_source' );
            if ( $source ) {
                $name .= '<br><small>' . __( 'Added via:', 'unstoppable-product-addons' ) . ' ' . esc_html( $source ) . '</small>';
            }
        }

        return $name;
    }

    /**
     * Handle adding order item meta from admin
     */
    public function ajax_add_order_item_meta( $item_id, $item, $order ) {
        // This allows configuring addons when manually adding items in admin
        // Implementation depends on admin UI
    }

    /**
     * Get addon totals for an order
     */
    public function get_order_addon_totals( $order ) {
        $totals = array(
            'count' => 0,
            'total' => 0,
        );

        foreach ( $order->get_items() as $item ) {
            $addon_data = $item->get_meta( '_upad_addon_data' );
            
            if ( empty( $addon_data ) || ! is_array( $addon_data ) ) {
                continue;
            }

            foreach ( $addon_data as $addon ) {
                $totals['count']++;
                $totals['total'] += floatval( $addon['price'] ) * $item->get_quantity();
            }
        }

        return $totals;
    }

    /**
     * Get uploaded files for an order
     */
    public function get_order_uploads( $order_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_uploads 
             WHERE order_id = %d 
             ORDER BY order_item_id, created_at",
            $order_id
        ) );
    }
}
