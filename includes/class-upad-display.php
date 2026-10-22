<?php
/**
 * Frontend Display Handler
 * Addresses competitor weakness: Native WooCommerce styling, clear UX
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Display {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        // Display addons on product page - multiple hooks for compatibility
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_addons' ), 10 );
        
        // Orderable compatibility - these hooks may be used by side cart/drawer plugins
        add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'display_addons' ), 5 );
        add_action( 'orderable_before_product_options', array( $this, 'display_addons' ), 10 );
        add_action( 'orderable_product_card_content', array( $this, 'display_addons' ), 10 );
        
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // Add totals display
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_totals' ), 15 );
        
        // Register shortcode for manual placement
        add_shortcode( 'upad_product_addons', array( $this, 'shortcode_product_addons' ) );
        
        // AJAX handler to get addons HTML for a product (for side carts/drawers)
        add_action( 'wp_ajax_upad_get_product_addons_html', array( $this, 'ajax_get_product_addons_html' ) );
        add_action( 'wp_ajax_nopriv_upad_get_product_addons_html', array( $this, 'ajax_get_product_addons_html' ) );
        
        // Change "Add to Cart" to "Select Options" on shop/archive pages for products with required addons
        add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'change_add_to_cart_text' ), 10, 2 );
        add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'change_add_to_cart_url' ), 10, 2 );
        add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'change_add_to_cart_args' ), 10, 2 );
        add_filter( 'woocommerce_product_supports', array( $this, 'disable_ajax_add_to_cart' ), 10, 3 );
    }

    /**
     * Check if product has required addons
     *
     * @param int|WC_Product $product Product ID or object.
     * @return bool
     */
    public function product_has_required_addons( $product ) {
        if ( is_numeric( $product ) ) {
            $product = wc_get_product( $product );
        }

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return false;
        }

        $addons = UPAD_Product_Addons::instance()->get_product_addons( $product->get_id() );

        if ( empty( $addons ) ) {
            return false;
        }

        foreach ( $addons as $addon ) {
            if ( ! empty( $addon->required ) && $addon->required == 1 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Change "Add to Cart" text to "Select Options" for products with required addons
     *
     * @param string $text Button text.
     * @param WC_Product $product Product object.
     * @return string
     */
    public function change_add_to_cart_text( $text, $product ) {
        // Only change on shop/archive pages, not single product pages
        if ( is_product() ) {
            return $text;
        }

        // Only for simple products (variable products already show "Select Options")
        if ( ! $product->is_type( 'simple' ) ) {
            return $text;
        }

        if ( $this->product_has_required_addons( $product ) ) {
            return __( 'Select options', 'unstoppable-product-addons' );
        }

        return $text;
    }

    /**
     * Change Add to Cart URL to product permalink for products with required addons
     *
     * @param string $url Add to cart URL.
     * @param WC_Product $product Product object.
     * @return string
     */
    public function change_add_to_cart_url( $url, $product ) {
        // Only change on shop/archive pages
        if ( is_product() ) {
            return $url;
        }

        // Only for simple products
        if ( ! $product->is_type( 'simple' ) ) {
            return $url;
        }

        if ( $this->product_has_required_addons( $product ) ) {
            return $product->get_permalink();
        }

        return $url;
    }

    /**
     * Change add to cart button args to remove AJAX class for products with required addons
     *
     * @param array $args Button arguments.
     * @param WC_Product $product Product object.
     * @return array
     */
    public function change_add_to_cart_args( $args, $product ) {
        // Only change on shop/archive pages
        if ( is_product() ) {
            return $args;
        }

        // Only for simple products
        if ( ! $product->is_type( 'simple' ) ) {
            return $args;
        }

        if ( $this->product_has_required_addons( $product ) ) {
            // Remove ajax_add_to_cart class to prevent AJAX add to cart
            if ( isset( $args['class'] ) ) {
                $args['class'] = str_replace( 'ajax_add_to_cart', '', $args['class'] );
            }
            // Remove data attributes that trigger AJAX
            unset( $args['attributes']['data-product_id'] );
            unset( $args['attributes']['data-product_sku'] );
            unset( $args['attributes']['data-quantity'] );
        }

        return $args;
    }

    /**
     * Disable AJAX add to cart for products with required addons
     *
     * @param bool $supports Whether the product supports the feature.
     * @param string $feature The feature being checked.
     * @param WC_Product $product Product object.
     * @return bool
     */
    public function disable_ajax_add_to_cart( $supports, $feature, $product ) {
        if ( $feature === 'ajax_add_to_cart' && $this->product_has_required_addons( $product ) ) {
            return false;
        }

        return $supports;
    }

    /**
     * Shortcode to display product addons
     * Usage: [upad_product_addons product_id="123"]
     */
    public function shortcode_product_addons( $atts ) {
        $atts = shortcode_atts( array(
            'product_id' => 0,
        ), $atts );

        $product_id = absint( $atts['product_id'] );
        
        if ( ! $product_id ) {
            global $product;
            if ( $product && is_a( $product, 'WC_Product' ) ) {
                $product_id = $product->get_id();
            }
        }

        if ( ! $product_id ) {
            return '';
        }

        $upad_product = wc_get_product( $product_id );
        if ( ! $upad_product ) {
            return '';
        }

        // Enqueue scripts.
        $this->enqueue_frontend_assets();

        ob_start();
        $this->render_addons_for_product( $upad_product );
        return ob_get_clean();
    }

    /**
     * AJAX handler to get product addons HTML.
     */
    public function ajax_get_product_addons_html() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only public product display, no state change.
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'Invalid product ID' ) );
            return;
        }

        $upad_product = wc_get_product( $product_id );
        if ( ! $upad_product ) {
            wp_send_json_error( array( 'message' => 'Product not found' ) );
            return;
        }

        ob_start();
        $this->render_addons_for_product( $upad_product );
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Render addons for a specific product
     */
    public function render_addons_for_product( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $addons = UPAD_Product_Addons::instance()->get_product_addons( $product->get_id() );

        if ( empty( $addons ) ) {
            return;
        }

        $is_variable = $product->is_type( 'variable' ) ? 'true' : 'false';
        echo '<div class="upad-addons-wrapper" data-product-id="' . esc_attr( $product->get_id() ) . '" data-product-price="' . esc_attr( $product->get_price() ) . '" data-is-variable="' . esc_attr( $is_variable ) . '">';

        foreach ( $addons as $addon ) {
            $this->render_addon( $addon, $product );
        }

        echo '</div>';
    }

    /**
     * Enqueue frontend assets (can be called manually)
     */
    public function enqueue_frontend_assets() {
        // Styles
        wp_enqueue_style(
            'upad-frontend',
            UPAD_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            UPAD_VERSION
        );

        // Date picker
        wp_enqueue_style( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'jquery-ui-datepicker' );

        // Main script
        wp_enqueue_script(
            'upad-frontend',
            UPAD_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery', 'jquery-ui-datepicker', 'wp-util' ),
            UPAD_VERSION,
            true
        );

        wp_localize_script( 'upad-frontend', 'upad_params', array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'upload_nonce'   => wp_create_nonce( 'upad_upload_nonce' ),
            'currency'       => get_woocommerce_currency_symbol(),
            'price_format'   => get_woocommerce_price_format(),
            'decimal_sep'    => wc_get_price_decimal_separator(),
            'thousand_sep'   => wc_get_price_thousand_separator(),
            'decimals'       => wc_get_price_decimals(),
            'i18n'           => array(
                'required'          => __( 'This field is required.', 'unstoppable-product-addons' ),
                'uploading'         => __( 'Uploading...', 'unstoppable-product-addons' ),
                'upload_error'      => __( 'Upload failed. Please try again.', 'unstoppable-product-addons' ),
                'remove'            => __( 'Remove', 'unstoppable-product-addons' ),
                'options_total'     => __( 'Options Total', 'unstoppable-product-addons' ),
                'grand_total'       => __( 'Grand Total', 'unstoppable-product-addons' ),
                'select_option'     => __( 'Select an option...', 'unstoppable-product-addons' ),
                'none'              => __( 'None', 'unstoppable-product-addons' ),
                'chars_remaining'   => __( 'characters remaining', 'unstoppable-product-addons' ),
                'out_of_stock'      => __( 'Out of stock', 'unstoppable-product-addons' ),
                /* translators: %d: quantity in stock */
                'low_stock'         => __( 'Only %d left', 'unstoppable-product-addons' ),
                /* translators: %d: minimum number of options */
                'select_minimum'    => __( 'Please select at least %d option(s)', 'unstoppable-product-addons' ),
                /* translators: %d: exact number of options to select */
                'select_exactly'    => __( 'Please select exactly %d option(s)', 'unstoppable-product-addons' ),
                /* translators: %d: maximum number of options */
                'select_maximum'    => __( 'You can select up to %d option(s)', 'unstoppable-product-addons' ),
                /* translators: %d: number of options to select */
                'select_qty_options' => __( 'Select %d option(s) to match your quantity', 'unstoppable-product-addons' ),
                /* translators: %d: required quantity, %n: selected quantity (JS placeholder) */
                'select_qty_total'  => __( 'Please select %d total (you selected %n)', 'unstoppable-product-addons' ),
            ),
        ) );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Load on product pages
        if ( is_product() ) {
            global $post;
            $product = wc_get_product( $post );
            
            if ( $product && is_a( $product, 'WC_Product' ) && UPAD_Product_Addons::instance()->product_has_addons( $product->get_id() ) ) {
                $this->enqueue_frontend_assets();
                return;
            }
        }
        
        // Also load on shop/archive pages if Orderable or similar plugins are active
        // This ensures scripts are available when products are loaded via AJAX
        if ( is_shop() || is_product_category() || is_product_tag() || is_woocommerce() ) {
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Track if addons have been displayed for a product to prevent duplicates
     */
    private $displayed_products = array();

    /**
     * Display addons on product page.
     */
    public function display_addons() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce globals.
        global $post, $product;

        // Ensure we have a proper product object.
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global.
            $product = wc_get_product( $post );
        }

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        // Prevent duplicate display on the same page.
        $product_id = $product->get_id();
        if ( isset( $this->displayed_products[ $product_id ] ) ) {
            return;
        }
        $this->displayed_products[ $product_id ] = true;

        $this->render_addons_for_product( $product );
    }

    /**
     * Get addon settings as array
     */
    private function get_addon_settings( $addon ) {
        $settings = $addon->settings;
        
        if ( empty( $settings ) ) {
            return array();
        }
        
        if ( is_string( $settings ) ) {
            $settings = maybe_unserialize( $settings );
        }
        
        return is_array( $settings ) ? $settings : array();
    }

    /**
     * Render a single addon
     */
    private function render_addon( $addon, $product ) {
        $type_class = 'upad-addon-' . sanitize_html_class( $addon->addon_type );
        $required_class = $addon->required ? 'upad-addon-required' : '';
        
        // Ensure settings is an array
        $addon->settings = $this->get_addon_settings( $addon );
        
        // Ensure options is an array
        if ( empty( $addon->options ) ) {
            $addon->options = array();
        }
        
        // Build variation targeting attribute
        $variation_attr = '';
        if ( ! empty( $addon->settings['apply_to_variations'] ) && is_array( $addon->settings['apply_to_variations'] ) ) {
            $variation_ids = array_map( 'absint', $addon->settings['apply_to_variations'] );
            $variation_attr = ' data-variations="' . esc_attr( implode( ',', $variation_ids ) ) . '"';
        }
        
        // Build conditional logic attributes
        $condition_attrs = '';
        $condition_class = '';
        if ( ! empty( $addon->settings['conditional_logic']['enabled'] ) ) {
            $cond = $addon->settings['conditional_logic'];
            $condition_attrs = ' data-conditions="' . esc_attr( wp_json_encode( array(
                'action'   => isset( $cond['action'] ) ? $cond['action'] : 'show',
                'relation' => isset( $cond['relation'] ) ? $cond['relation'] : 'all',
                'rules'    => isset( $cond['rules'] ) ? $cond['rules'] : array(),
            ) ) ) . '"';
            
            // Start hidden if action is 'show' (will be shown when conditions match)
            if ( isset( $cond['action'] ) && $cond['action'] === 'show' ) {
                $condition_class = ' upad-condition-hidden';
            }
        }
        
        echo '<div class="upad-addon ' . esc_attr( $type_class ) . ' ' . esc_attr( $required_class ) . esc_attr( $condition_class ) . '" data-addon-id="' . esc_attr( $addon->id ) . '" data-addon-type="' . esc_attr( $addon->addon_type ) . '"' . $variation_attr . $condition_attrs . '>';

        // Title
        $title_format = ! empty( $addon->title_format ) ? $addon->title_format : 'label';
        if ( $title_format !== 'hide' ) {
            $tag = $title_format === 'heading' ? 'h4' : 'label';
            echo '<' . $tag . ' class="upad-addon-title">';
            echo esc_html( $addon->title );
            if ( $addon->required ) {
                echo '<span class="upad-required">*</span>';
            }
            echo '</' . $tag . '>';
        }

        // Description
        if ( ! empty( $addon->description ) ) {
            echo '<div class="upad-addon-description">' . wp_kses_post( $addon->description ) . '</div>';
        }

        // Render field based on type
        $method = 'render_' . $addon->addon_type;
        if ( method_exists( $this, $method ) ) {
            $this->$method( $addon, $product );
        }

        echo '</div>';
    }

    /**
     * Render multiple choice addon
     */
    private function render_multiple_choice( $addon, $product ) {
        $settings = $addon->settings;
        $display_type = isset( $settings['display_type'] ) ? $settings['display_type'] : 'dropdown';
        $field_name = 'upad_addon[' . $addon->id . ']';

        // Find default option
        $default_value = '';
        foreach ( $addon->options as $option ) {
            if ( ! empty( $option->is_default ) ) {
                $default_value = $option->id;
                break;
            }
        }

        switch ( $display_type ) {
            case 'dropdown':
                echo '<select name="' . esc_attr( $field_name ) . '" class="upad-field upad-dropdown">';
                echo '<option value="">' . esc_html__( 'Select an option...', 'unstoppable-product-addons' ) . '</option>';
                
                foreach ( $addon->options as $option ) {
                    $price_display = $this->get_price_display( $option );
                    $selected = selected( $default_value, $option->id, false );
                    $stock_attrs = $this->get_stock_attributes( $option );
                    
                    echo '<option value="' . esc_attr( $option->id ) . '" data-price="' . esc_attr( $option->price ) . '" data-price-type="' . esc_attr( $option->price_type ) . '" ' . $selected . ' ' . $stock_attrs . '>';
                    echo esc_html( $option->label );
                    if ( $price_display ) {
                        echo ' ' . $price_display;
                    }
                    echo '</option>';
                }
                
                echo '</select>';
                break;

            case 'radio':
                echo '<div class="upad-radio-group">';
                
                // None option
                if ( ! empty( $settings['show_none_option'] ) && ! $addon->required ) {
                    $checked = empty( $default_value ) ? 'checked' : '';
                    echo '<label class="upad-radio-label">';
                    echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="" class="upad-field upad-radio" ' . $checked . '>';
                    echo '<span class="upad-radio-text">' . esc_html__( 'None', 'unstoppable-product-addons' ) . '</span>';
                    echo '</label>';
                }

                foreach ( $addon->options as $option ) {
                    $price_display = $this->get_price_display( $option );
                    $checked = checked( $default_value, $option->id, false );
                    $stock_class = $this->get_stock_class( $option );
                    
                    echo '<label class="upad-radio-label ' . esc_attr( $stock_class ) . '">';
                    echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $option->id ) . '" class="upad-field upad-radio" data-price="' . esc_attr( $option->price ) . '" data-price-type="' . esc_attr( $option->price_type ) . '" ' . $checked . '>';
                    echo '<span class="upad-radio-text">' . esc_html( $option->label ) . '</span>';
                    if ( $price_display ) {
                        echo '<span class="upad-option-price">' . $price_display . '</span>';
                    }
                    echo $this->get_stock_badge( $option );
                    echo '</label>';
                }
                
                echo '</div>';
                break;

            case 'images':
                echo '<div class="upad-image-group">';
                
                foreach ( $addon->options as $option ) {
                    $checked = checked( $default_value, $option->id, false );
                    $image_url = $option->image_id ? wp_get_attachment_image_url( $option->image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
                    $stock_class = $this->get_stock_class( $option );
                    
                    echo '<label class="upad-image-label ' . esc_attr( $stock_class ) . '">';
                    echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $option->id ) . '" class="upad-field upad-radio" data-price="' . esc_attr( $option->price ) . '" data-price-type="' . esc_attr( $option->price_type ) . '" ' . $checked . '>';
                    echo '<span class="upad-image-wrap">';
                    echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $option->label ) . '">';
                    echo '</span>';
                    echo '<span class="upad-image-text">' . esc_html( $option->label ) . '</span>';
                    echo $this->get_stock_badge( $option );
                    echo '</label>';
                }
                
                echo '</div>';
                break;
        }
    }

    /**
     * Render checkboxes addon
     */
    private function render_checkboxes( $addon, $product ) {
        $field_name = 'upad_addon[' . $addon->id . '][]';
        $settings = $addon->settings;
        
        // Get settings
        $min_selections = isset( $settings['min_selections'] ) ? absint( $settings['min_selections'] ) : 0;
        $max_selections = isset( $settings['max_selections'] ) ? absint( $settings['max_selections'] ) : 0;
        $link_to_quantity = ! empty( $settings['link_to_quantity'] );
        $allow_same_option = ! empty( $settings['allow_same_option'] );
        
        // Build data attributes for JS validation
        $data_attrs = '';
        if ( $min_selections > 0 ) {
            $data_attrs .= ' data-min-selections="' . esc_attr( $min_selections ) . '"';
        }
        if ( $max_selections > 0 ) {
            $data_attrs .= ' data-max-selections="' . esc_attr( $max_selections ) . '"';
        }
        if ( $link_to_quantity ) {
            $data_attrs .= ' data-link-to-quantity="1"';
        }
        if ( $allow_same_option ) {
            $data_attrs .= ' data-allow-same="1"';
        }

        echo '<div class="upad-checkbox-group"' . $data_attrs . '>';
        
        // Show selection hint
        if ( $link_to_quantity ) {
            echo '<p class="upad-selection-hint upad-qty-linked-hint">';
            if ( $allow_same_option ) {
                esc_html_e( 'Select flavors to match your quantity (can repeat)', 'unstoppable-product-addons' );
            } else {
                esc_html_e( 'Select one option per item in your quantity', 'unstoppable-product-addons' );
            }
            echo '</p>';
        } elseif ( $min_selections > 0 || $max_selections > 0 ) {
            echo '<p class="upad-selection-hint">';
            if ( $min_selections > 0 && $max_selections > 0 ) {
                if ( $min_selections === $max_selections ) {
                    /* translators: %d: number of options to select */
                    printf(
                        esc_html__( 'Select exactly %d option(s)', 'unstoppable-product-addons' ),
                        $min_selections
                    );
                } else {
                    /* translators: %1$d: minimum options, %2$d: maximum options */
                    printf(
                        esc_html__( 'Select %1$d to %2$d options', 'unstoppable-product-addons' ),
                        $min_selections,
                        $max_selections
                    );
                }
            } elseif ( $min_selections > 0 ) {
                /* translators: %d: minimum number of options to select */
                printf(
                    esc_html__( 'Select at least %d option(s)', 'unstoppable-product-addons' ),
                    $min_selections
                );
            } elseif ( $max_selections > 0 ) {
                /* translators: %d: maximum number of options to select */
                printf(
                    esc_html__( 'Select up to %d option(s)', 'unstoppable-product-addons' ),
                    $max_selections
                );
            }
            echo '</p>';
        }

        // Quantity-linked with same option allowed = quantity inputs per option
        if ( $link_to_quantity && $allow_same_option ) {
            $qty_field_name = 'upad_addon_qty[' . $addon->id . ']';
            
            foreach ( $addon->options as $option ) {
                $price_display = $this->get_price_display( $option );
                $stock_class = $this->get_stock_class( $option );
                
                echo '<div class="upad-qty-option ' . esc_attr( $stock_class ) . '" data-option-id="' . esc_attr( $option->id ) . '">';
                echo '<span class="upad-option-label">' . esc_html( $option->label ) . '</span>';
                if ( $price_display ) {
                    echo '<span class="upad-option-price">' . $price_display . ' ' . esc_html__( 'each', 'unstoppable-product-addons' ) . '</span>';
                }
                echo '<div class="upad-qty-selector">';
                echo '<button type="button" class="upad-option-qty-minus">−</button>';
                echo '<input type="number" name="' . esc_attr( $qty_field_name ) . '[' . esc_attr( $option->id ) . ']" value="0" min="0" class="upad-option-qty-input upad-field" data-option-id="' . esc_attr( $option->id ) . '" data-price="' . esc_attr( $option->price ) . '" data-price-type="' . esc_attr( $option->price_type ) . '">';
                echo '<button type="button" class="upad-option-qty-plus">+</button>';
                echo '</div>';
                echo $this->get_stock_badge( $option );
                echo '</div>';
            }
            
            // Total counter
            echo '<div class="upad-qty-total">';
            echo '<span class="upad-qty-total-label">' . esc_html__( 'Selected:', 'unstoppable-product-addons' ) . '</span> ';
            echo '<span class="upad-qty-total-count">0</span> / <span class="upad-qty-total-required">1</span>';
            echo '</div>';
        } else {
            // Standard checkboxes
            foreach ( $addon->options as $option ) {
                $price_display = $this->get_price_display( $option );
                $checked = $option->is_default ? 'checked' : '';
                $stock_class = $this->get_stock_class( $option );
                
                echo '<label class="upad-checkbox-label ' . esc_attr( $stock_class ) . '">';
                echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $option->id ) . '" class="upad-field upad-checkbox" data-price="' . esc_attr( $option->price ) . '" data-price-type="' . esc_attr( $option->price_type ) . '" ' . $checked . '>';
                echo '<span class="upad-checkbox-text">' . esc_html( $option->label ) . '</span>';
                if ( $price_display ) {
                    echo '<span class="upad-option-price">' . $price_display . '</span>';
                }
                echo $this->get_stock_badge( $option );
                echo '</label>';
            }
        }

        echo '</div>';
    }

    /**
     * Render short text addon
     */
    private function render_short_text( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        $placeholder = isset( $settings['placeholder'] ) ? $settings['placeholder'] : '';
        $max_length = isset( $settings['max_length'] ) ? absint( $settings['max_length'] ) : '';
        
        $attrs = '';
        if ( $max_length ) {
            $attrs .= ' maxlength="' . $max_length . '"';
        }
        if ( $addon->required ) {
            $attrs .= ' required';
        }

        echo '<input type="text" name="' . esc_attr( $field_name ) . '" class="upad-field upad-text-input" placeholder="' . esc_attr( $placeholder ) . '"' . $attrs . '>';
        
        $this->render_price_info( $addon );
    }

    /**
     * Render long text addon
     */
    private function render_long_text( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        $placeholder = isset( $settings['placeholder'] ) ? $settings['placeholder'] : '';
        $rows = isset( $settings['rows'] ) ? absint( $settings['rows'] ) : 4;
        $max_length = isset( $settings['max_length'] ) ? absint( $settings['max_length'] ) : '';
        $show_counter = isset( $settings['show_char_count'] ) ? $settings['show_char_count'] : true;

        $attrs = '';
        if ( $max_length ) {
            $attrs .= ' maxlength="' . $max_length . '"';
        }
        if ( $addon->required ) {
            $attrs .= ' required';
        }

        echo '<textarea name="' . esc_attr( $field_name ) . '" class="upad-field upad-textarea" placeholder="' . esc_attr( $placeholder ) . '" rows="' . esc_attr( $rows ) . '"' . $attrs . '></textarea>';
        
        if ( $show_counter && $max_length ) {
            echo '<div class="upad-char-counter"><span class="upad-chars-remaining">' . $max_length . '</span> ' . esc_html__( 'characters remaining', 'unstoppable-product-addons' ) . '</div>';
        }

        $this->render_price_info( $addon );
    }

    /**
     * Render file upload addon
     */
    private function render_file_upload( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . '][]';
        $allowed_types = isset( $settings['allowed_extensions'] ) ? $settings['allowed_extensions'] : '';
        $max_size = isset( $settings['max_file_size'] ) ? floatval( $settings['max_file_size'] ) : 5;
        $max_files = isset( $settings['max_files'] ) ? absint( $settings['max_files'] ) : 1;

        echo '<div class="upad-file-upload-wrapper" data-max-files="' . esc_attr( $max_files ) . '" data-addon-id="' . esc_attr( $addon->id ) . '">';
        
        echo '<div class="upad-file-dropzone">';
        echo '<input type="file" name="upad_file_' . esc_attr( $addon->id ) . '" class="upad-file-input" accept="' . esc_attr( $this->get_accept_string( $allowed_types ) ) . '" ' . ( $max_files > 1 ? 'multiple' : '' ) . '>';
        echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" class="upad-file-hash">';
        echo '<div class="upad-dropzone-content">';
        echo '<span class="upad-dropzone-icon dashicons dashicons-upload"></span>';
        echo '<span class="upad-dropzone-text">' . esc_html__( 'Drop files here or click to upload', 'unstoppable-product-addons' ) . '</span>';
        echo '<span class="upad-dropzone-info">';
        if ( $allowed_types ) {
            /* translators: %s: allowed file extensions */
            echo esc_html( sprintf( __( 'Allowed: %s', 'unstoppable-product-addons' ), strtoupper( $allowed_types ) ) ) . '<br>';
        }
        /* translators: %s: maximum file size in megabytes */
        echo esc_html( sprintf( __( 'Max size: %s MB', 'unstoppable-product-addons' ), $max_size ) );
        echo '</span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="upad-uploaded-files-list"></div>';
        
        echo '</div>';

        $this->render_price_info( $addon );
    }

    /**
     * Render customer price addon
     */
    private function render_customer_price( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        $default = isset( $settings['default_price'] ) ? floatval( $settings['default_price'] ) : '';
        $min = isset( $settings['min_price'] ) ? floatval( $settings['min_price'] ) : '';
        $max = isset( $settings['max_price'] ) ? floatval( $settings['max_price'] ) : '';

        $attrs = 'step="0.01"';
        if ( $min !== '' ) {
            $attrs .= ' min="' . $min . '"';
        }
        if ( $max !== '' ) {
            $attrs .= ' max="' . $max . '"';
        }
        if ( $addon->required ) {
            $attrs .= ' required';
        }

        echo '<div class="upad-price-input-wrapper">';
        echo '<span class="upad-currency-symbol">' . get_woocommerce_currency_symbol() . '</span>';
        echo '<input type="number" name="' . esc_attr( $field_name ) . '" class="upad-field upad-price-input" value="' . esc_attr( $default ) . '" ' . $attrs . '>';
        echo '</div>';
    }

    /**
     * Render quantity addon
     */
    private function render_quantity( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        $default = isset( $settings['default_quantity'] ) ? absint( $settings['default_quantity'] ) : 1;
        $min = isset( $settings['min_quantity'] ) ? absint( $settings['min_quantity'] ) : 0;
        $max = isset( $settings['max_quantity'] ) ? absint( $settings['max_quantity'] ) : '';
        $step = isset( $settings['step'] ) ? absint( $settings['step'] ) : 1;

        $attrs = 'step="' . $step . '" min="' . $min . '"';
        if ( $max ) {
            $attrs .= ' max="' . $max . '"';
        }
        if ( $addon->required ) {
            $attrs .= ' required';
        }

        echo '<div class="upad-quantity-wrapper">';
        echo '<button type="button" class="upad-qty-btn upad-qty-minus">-</button>';
        echo '<input type="number" name="' . esc_attr( $field_name ) . '" class="upad-field upad-quantity-input" value="' . esc_attr( $default ) . '" ' . $attrs . '>';
        echo '<button type="button" class="upad-qty-btn upad-qty-plus">+</button>';
        echo '</div>';

        $this->render_price_info( $addon );
    }

    /**
     * Render heading addon
     */
    private function render_heading( $addon, $product ) {
        // Heading is rendered in the title section
    }

    /**
     * Render date picker addon
     */
    private function render_date_picker( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';

        $attrs = '';
        if ( $addon->required ) {
            $attrs .= ' required';
        }

        echo '<input type="text" name="' . esc_attr( $field_name ) . '" class="upad-field upad-datepicker" ' . $attrs . ' readonly>';
        
        $this->render_price_info( $addon );
    }

    /**
     * Render color picker addon
     */
    private function render_color_picker( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        $default = isset( $settings['default_color'] ) ? $settings['default_color'] : '#000000';

        echo '<input type="color" name="' . esc_attr( $field_name ) . '" class="upad-field upad-color-picker" value="' . esc_attr( $default ) . '">';
        
        $this->render_price_info( $addon );
    }

    /**
     * Render image swatches addon
     */
    private function render_image_swatches( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        $size = isset( $settings['swatch_size'] ) ? $settings['swatch_size'] : 'medium';
        $style = isset( $settings['swatch_style'] ) ? $settings['swatch_style'] : 'square';
        $show_label = ! empty( $settings['show_label'] );
        $show_tooltip = ! empty( $settings['show_tooltip'] );
        $multiple = ! empty( $settings['multiple'] );

        $size_map = array(
            'small'  => '40px',
            'medium' => '60px',
            'large'  => '80px',
            'xlarge' => '100px',
        );
        $swatch_size = isset( $size_map[ $size ] ) ? $size_map[ $size ] : '60px';

        $input_type = $multiple ? 'checkbox' : 'radio';
        $name = $multiple ? $field_name . '[]' : $field_name;

        echo '<div class="upad-swatches-group upad-swatches-' . esc_attr( $style ) . '" data-size="' . esc_attr( $swatch_size ) . '">';

        foreach ( $addon->options as $option ) {
            $price_display = $this->get_price_display( $option );
            $checked = $option->is_default ? 'checked' : '';
            $stock_class = $this->get_stock_class( $option );
            
            // Get image or color
            $swatch_content = '';
            if ( ! empty( $option->image_url ) ) {
                $swatch_content = '<img src="' . esc_url( $option->image_url ) . '" alt="' . esc_attr( $option->label ) . '">';
            } elseif ( ! empty( $option->color ) ) {
                $swatch_content = '<span class="upad-swatch-color" style="background-color:' . esc_attr( $option->color ) . '"></span>';
            } else {
                $swatch_content = '<span class="upad-swatch-placeholder">' . esc_html( substr( $option->label, 0, 2 ) ) . '</span>';
            }

            echo '<label class="upad-swatch-label ' . esc_attr( $stock_class ) . '"';
            if ( $show_tooltip ) {
                echo ' title="' . esc_attr( $option->label . ( $price_display ? ' ' . wp_strip_all_tags( $price_display ) : '' ) ) . '"';
            }
            echo ' style="width:' . esc_attr( $swatch_size ) . ';height:' . esc_attr( $swatch_size ) . '">';
            echo '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $option->id ) . '" class="upad-field upad-swatch-input" data-price="' . esc_attr( $option->price ) . '" data-price-type="' . esc_attr( $option->price_type ) . '" ' . $checked . '>';
            echo '<span class="upad-swatch-visual">' . $swatch_content . '</span>';
            if ( $show_label ) {
                echo '<span class="upad-swatch-text">' . esc_html( $option->label ) . '</span>';
            }
            if ( $price_display ) {
                echo '<span class="upad-swatch-price">' . $price_display . '</span>';
            }
            echo '</label>';
        }

        echo '</div>';
    }

    /**
     * Render datetime picker addon
     */
    private function render_datetime_picker( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        
        $date_format = isset( $settings['date_format'] ) ? $settings['date_format'] : 'Y-m-d';
        $time_format = isset( $settings['time_format'] ) ? $settings['time_format'] : 'H:i';
        $time_interval = isset( $settings['time_interval'] ) ? absint( $settings['time_interval'] ) : 30;
        $min_date = isset( $settings['min_date'] ) ? $settings['min_date'] : 'today';
        $min_days_ahead = isset( $settings['min_days_ahead'] ) ? absint( $settings['min_days_ahead'] ) : 1;
        $time_start = isset( $settings['time_start'] ) ? $settings['time_start'] : '09:00';
        $time_end = isset( $settings['time_end'] ) ? $settings['time_end'] : '17:00';
        $disabled_days = isset( $settings['disabled_days'] ) ? (array) $settings['disabled_days'] : array();

        echo '<div class="upad-datetime-wrapper" data-time-interval="' . esc_attr( $time_interval ) . '" data-time-start="' . esc_attr( $time_start ) . '" data-time-end="' . esc_attr( $time_end ) . '" data-disabled-days="' . esc_attr( implode( ',', $disabled_days ) ) . '">';
        
        // Date input
        echo '<div class="upad-datetime-date">';
        echo '<label>' . esc_html__( 'Date', 'unstoppable-product-addons' ) . '</label>';
        
        $min_date_val = '';
        if ( $min_date === 'today' ) {
            $min_date_val = wp_date( 'Y-m-d' );
        } elseif ( $min_date === 'tomorrow' ) {
            $min_date_val = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
        } elseif ( $min_date === 'days_ahead' ) {
            $min_date_val = wp_date( 'Y-m-d', strtotime( '+' . $min_days_ahead . ' days' ) );
        }
        
        echo '<input type="date" name="' . esc_attr( $field_name ) . '[date]" class="upad-field upad-datetime-date-input" min="' . esc_attr( $min_date_val ) . '"' . ( $addon->required ? ' required' : '' ) . '>';
        echo '</div>';
        
        // Time input
        echo '<div class="upad-datetime-time">';
        echo '<label>' . esc_html__( 'Time', 'unstoppable-product-addons' ) . '</label>';
        echo '<select name="' . esc_attr( $field_name ) . '[time]" class="upad-field upad-datetime-time-input">';
        echo '<option value="">' . esc_html__( 'Select time...', 'unstoppable-product-addons' ) . '</option>';
        
        // Generate time slots
        $start = strtotime( $time_start );
        $end = strtotime( $time_end );
        $interval = $time_interval * 60;
        
        for ( $time = $start; $time <= $end; $time += $interval ) {
            // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- Time slots for form value; local timezone is correct.
            $time_value = date( 'H:i', $time );
            // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- Time slots for form display; local timezone is correct.
            $time_display = date( $time_format, $time );
            echo '<option value="' . esc_attr( $time_value ) . '">' . esc_html( $time_display ) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        
        echo '</div>';
        
        $this->render_price_info( $addon );
    }

    /**
     * Render dimensions/formula addon
     */
    private function render_dimensions( $addon, $product ) {
        $settings = $addon->settings;
        $field_name = 'upad_addon[' . $addon->id . ']';
        
        $inputs = isset( $settings['inputs'] ) ? $settings['inputs'] : 'length_width';
        $unit_label = isset( $settings['unit_label'] ) ? $settings['unit_label'] : 'inches';
        $min_value = isset( $settings['min_value'] ) ? floatval( $settings['min_value'] ) : 1;
        $max_value = isset( $settings['max_value'] ) ? floatval( $settings['max_value'] ) : 100;
        $step = isset( $settings['step'] ) ? floatval( $settings['step'] ) : 0.5;
        $formula = isset( $settings['pricing_formula'] ) ? $settings['pricing_formula'] : 'per_sq_unit';
        $price_per_unit = isset( $settings['price_per_unit'] ) ? floatval( $settings['price_per_unit'] ) : 1;
        $custom_formula = isset( $settings['custom_formula'] ) ? $settings['custom_formula'] : '';
        $min_price = isset( $settings['min_price'] ) ? floatval( $settings['min_price'] ) : 0;

        echo '<div class="upad-dimensions-wrapper" data-formula="' . esc_attr( $formula ) . '" data-price-per-unit="' . esc_attr( $price_per_unit ) . '" data-custom-formula="' . esc_attr( $custom_formula ) . '" data-min-price="' . esc_attr( $min_price ) . '">';
        
        // Length input
        echo '<div class="upad-dimension-input">';
        echo '<label>' . esc_html__( 'Length', 'unstoppable-product-addons' ) . ' (' . esc_html( $unit_label ) . ')</label>';
        echo '<input type="number" name="' . esc_attr( $field_name ) . '[length]" class="upad-field upad-dimension-length" min="' . esc_attr( $min_value ) . '" max="' . esc_attr( $max_value ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $min_value ) . '"' . ( $addon->required ? ' required' : '' ) . '>';
        echo '</div>';
        
        // Width input
        if ( $inputs === 'length_width' || $inputs === 'length_width_height' ) {
            echo '<div class="upad-dimension-input">';
            echo '<label>' . esc_html__( 'Width', 'unstoppable-product-addons' ) . ' (' . esc_html( $unit_label ) . ')</label>';
            echo '<input type="number" name="' . esc_attr( $field_name ) . '[width]" class="upad-field upad-dimension-width" min="' . esc_attr( $min_value ) . '" max="' . esc_attr( $max_value ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $min_value ) . '"' . ( $addon->required ? ' required' : '' ) . '>';
            echo '</div>';
        }
        
        // Height input
        if ( $inputs === 'length_width_height' ) {
            echo '<div class="upad-dimension-input">';
            echo '<label>' . esc_html__( 'Height', 'unstoppable-product-addons' ) . ' (' . esc_html( $unit_label ) . ')</label>';
            echo '<input type="number" name="' . esc_attr( $field_name ) . '[height]" class="upad-field upad-dimension-height" min="' . esc_attr( $min_value ) . '" max="' . esc_attr( $max_value ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $min_value ) . '"' . ( $addon->required ? ' required' : '' ) . '>';
            echo '</div>';
        }
        
        // Calculated price display
        echo '<div class="upad-dimension-price">';
        echo '<span class="upad-dimension-price-label">' . esc_html__( 'Price:', 'unstoppable-product-addons' ) . '</span> ';
        echo '<span class="upad-dimension-price-value">' . wc_price( 0 ) . '</span>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render section/group addon
     */
    private function render_section( $addon, $product ) {
        $settings = $addon->settings;
        
        $collapsed = ! empty( $settings['collapsed_default'] );
        $show_count = ! empty( $settings['show_count'] );
        $style = isset( $settings['section_style'] ) ? $settings['section_style'] : 'accordion';
        
        // Sections are rendered differently - they wrap other addons
        // This is a marker that frontend JS will use to group addons
        echo '<div class="upad-section-marker" data-section-id="' . esc_attr( $addon->id ) . '" data-collapsed="' . ( $collapsed ? '1' : '0' ) . '" data-style="' . esc_attr( $style ) . '" data-show-count="' . ( $show_count ? '1' : '0' ) . '"></div>';
    }

    /**
     * Get price display string for option
     */
    private function get_price_display( $option ) {
        if ( empty( $option->price ) ) {
            return '';
        }

        $price = floatval( $option->price );
        $sign = $price >= 0 ? '+' : '';

        switch ( $option->price_type ) {
            case 'percentage':
                return '(' . $sign . $price . '%)';
            case 'quantity':
                return '(' . $sign . wc_price( $price ) . ' ' . __( 'each', 'unstoppable-product-addons' ) . ')';
            default:
                return '(' . $sign . wc_price( $price ) . ')';
        }
    }

    /**
     * Render price info for field addons
     */
    private function render_price_info( $addon ) {
        $settings = $addon->settings;
        
        if ( empty( $settings['price'] ) ) {
            return;
        }

        $price = floatval( $settings['price'] );
        $price_type = isset( $settings['price_type'] ) ? $settings['price_type'] : 'flat';
        $sign = $price >= 0 ? '+' : '';

        echo '<div class="upad-field-price-info">';
        
        switch ( $price_type ) {
            case 'percentage':
                echo $sign . $price . '%';
                break;
            case 'quantity':
                echo $sign . wc_price( $price ) . ' ' . __( 'per item', 'unstoppable-product-addons' );
                break;
            default:
                echo $sign . wc_price( $price );
        }
        
        echo '</div>';
    }

    /**
     * Get accept string for file input
     */
    private function get_accept_string( $extensions ) {
        if ( empty( $extensions ) ) {
            return '';
        }

        $parts = array_map( function( $ext ) {
            return '.' . trim( $ext );
        }, explode( ',', $extensions ) );

        return implode( ',', $parts );
    }

    /**
     * Get stock attributes for option
     */
    private function get_stock_attributes( $option ) {
        $data = apply_filters( 'upad_option_data', array(), $option );
        
        if ( isset( $data['in_stock'] ) && ! $data['in_stock'] ) {
            return 'disabled data-out-of-stock="1"';
        }
        
        return '';
    }

    /**
     * Get stock class for option
     */
    private function get_stock_class( $option ) {
        $data = apply_filters( 'upad_option_data', array(), $option );
        
        if ( isset( $data['in_stock'] ) && ! $data['in_stock'] ) {
            return 'upad-out-of-stock';
        }
        
        if ( isset( $data['low_stock'] ) && $data['low_stock'] ) {
            return 'upad-low-stock';
        }
        
        return '';
    }

    /**
     * Get stock badge for option
     */
    private function get_stock_badge( $option ) {
        $data = apply_filters( 'upad_option_data', array(), $option );
        
        if ( isset( $data['in_stock'] ) && ! $data['in_stock'] ) {
            return '<span class="upad-stock-badge upad-out-of-stock-badge">' . __( 'Out of stock', 'unstoppable-product-addons' ) . '</span>';
        }
        
        if ( isset( $data['low_stock'] ) && $data['low_stock'] && isset( $data['stock_quantity'] ) ) {
            /* translators: %d: quantity remaining in stock */
            return '<span class="upad-stock-badge upad-low-stock-badge">' . sprintf( __( 'Only %d left', 'unstoppable-product-addons' ), $data['stock_quantity'] ) . '</span>';
        }
        
        return '';
    }

    /**
     * Display totals section
     */
    public function display_totals() {
        if ( 'yes' !== get_option( 'upad_display_totals', 'yes' ) ) {
            return;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce globals.
        global $post, $product;

        // Ensure we have a proper product object.
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global.
            $product = wc_get_product( $post );
        }

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        if ( ! UPAD_Product_Addons::instance()->product_has_addons( $product->get_id() ) ) {
            return;
        }

        echo '<div class="upad-totals-wrapper" style="display:none;">';
        echo '<dl class="upad-product-addon-totals">';
        echo '<dt class="upad-options-total-label">' . esc_html__( 'Options Total', 'unstoppable-product-addons' ) . '</dt>';
        echo '<dd class="upad-options-total-value">' . wc_price( 0 ) . '</dd>';
        echo '<dt class="upad-grand-total-label">' . esc_html__( 'Grand Total', 'unstoppable-product-addons' ) . '</dt>';
        echo '<dd class="upad-grand-total-value">' . wc_price( $product->get_price() ) . '</dd>';
        echo '</dl>';
        echo '</div>';
    }
}
