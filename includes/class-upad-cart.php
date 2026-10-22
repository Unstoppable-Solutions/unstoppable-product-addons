<?php
/**
 * Cart Handler - Manages add-ons in cart
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Cart {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        // Add addon data to cart
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        
        // Display addons in cart
        add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
        
        // Calculate addon prices
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_totals' ), 20, 1 );
        
        // Validate before adding to cart
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
        
        // Handle unique cart items
        add_filter( 'woocommerce_cart_item_key', array( $this, 'generate_cart_item_key' ), 10, 3 );
        
        // Restore cart item data from session
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 3 );
    }

    /**
     * Validate addon selections before adding to cart
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
        $addons = UPAD_Product_Addons::instance()->get_product_addons( $product_id );
        
        if ( empty( $addons ) ) {
            return $passed;
        }

        $posted_addons = isset( $_POST['upad_addon'] ) ? $_POST['upad_addon'] : array();
        $posted_addon_qty = isset( $_POST['upad_addon_qty'] ) ? $_POST['upad_addon_qty'] : array();

        foreach ( $addons as $addon ) {
            // Check if addon applies to this variation
            if ( ! $this->addon_applies_to_variation( $addon, $variation_id ) ) {
                continue; // Skip validation for addons that don't apply to this variation
            }
            
            $settings = is_array( $addon->settings ) ? $addon->settings : array();
            $link_to_quantity = ! empty( $settings['link_to_quantity'] );
            $allow_same_option = ! empty( $settings['allow_same_option'] );
            
            // Get addon value - use qty format if applicable
            if ( $addon->addon_type === 'checkboxes' && $link_to_quantity && $allow_same_option ) {
                $addon_value = isset( $posted_addon_qty[ $addon->id ] ) ? $posted_addon_qty[ $addon->id ] : array();
            } else {
                $addon_value = isset( $posted_addons[ $addon->id ] ) ? $posted_addons[ $addon->id ] : '';
            }
            
            // Check required fields
            if ( $addon->required ) {
                $is_empty = $this->is_addon_value_empty( $addon_value, $addon->addon_type, $settings );
                
                if ( $is_empty ) {
                    wc_add_notice(
                        sprintf(
                            __( '"%s" is a required field.', 'wc-options-studio' ),
                            $addon->title
                        ),
                        'error'
                    );
                    return false;
                }
            }

            // Validate based on addon type
            $validation = $this->validate_addon_value( $addon_value, $addon, $product_id, $quantity );
            if ( is_wp_error( $validation ) ) {
                wc_add_notice( $validation->get_error_message(), 'error' );
                return false;
            }
        }

        return $passed;
    }

    /**
     * Check if addon value is empty
     */
    private function is_addon_value_empty( $value, $type, $settings = array() ) {
        if ( $type === 'checkboxes' ) {
            // Handle quantity-linked with allow_same (qty format)
            if ( ! empty( $settings['link_to_quantity'] ) && ! empty( $settings['allow_same_option'] ) ) {
                if ( ! is_array( $value ) ) {
                    return true;
                }
                $total = 0;
                foreach ( $value as $option_qty ) {
                    $total += absint( $option_qty );
                }
                return $total === 0;
            }
            return empty( $value ) || ! is_array( $value );
        }
        
        if ( $type === 'file_upload' ) {
            return empty( $value ) || ( is_array( $value ) && empty( array_filter( $value ) ) );
        }

        return $value === '' || $value === null;
    }
    
    /**
     * Check if addon applies to a specific variation
     * 
     * @param object $addon       The addon object.
     * @param int    $variation_id The variation ID (0 for non-variable products).
     * @return bool True if addon applies, false otherwise.
     */
    private function addon_applies_to_variation( $addon, $variation_id ) {
        // Get addon settings
        $settings = $addon->settings;
        if ( is_string( $settings ) ) {
            $settings = maybe_unserialize( $settings );
        }
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        
        // If no variation restrictions, addon applies to all variations
        if ( empty( $settings['apply_to_variations'] ) ) {
            return true;
        }
        
        // If not a variable product (no variation_id), and restrictions exist, skip
        if ( ! $variation_id ) {
            return false;
        }
        
        // Check if variation_id is in the allowed list
        $allowed_variations = array_map( 'absint', (array) $settings['apply_to_variations'] );
        return in_array( absint( $variation_id ), $allowed_variations, true );
    }

    /**
     * Validate addon value based on type
     */
    private function validate_addon_value( $value, $addon, $product_id, $quantity = 1 ) {
        $settings = is_array( $addon->settings ) ? $addon->settings : array();
        
        if ( $this->is_addon_value_empty( $value, $addon->addon_type, $settings ) ) {
            return true; // Empty values handled by required check
        }

        switch ( $addon->addon_type ) {
            case 'short_text':
                return $this->validate_text_field( $value, $settings, 'short' );
                
            case 'long_text':
                return $this->validate_text_field( $value, $settings, 'long' );
                
            case 'customer_price':
                return $this->validate_customer_price( $value, $settings );
                
            case 'quantity':
                return $this->validate_quantity_field( $value, $settings );
                
            case 'checkboxes':
                return $this->validate_checkboxes( $value, $addon, $settings, $quantity );
                
            case 'multiple_choice':
                return $this->validate_multiple_choice( $value, $addon );
                
            case 'date_picker':
                return $this->validate_date( $value, $settings );
        }

        return apply_filters( 'upad_validate_addon_value', true, $value, $addon, $product_id );
    }

    /**
     * Validate text fields
     */
    private function validate_text_field( $value, $settings, $type ) {
        $value = sanitize_textarea_field( $value );
        $length = mb_strlen( $value );

        // Check min length
        if ( ! empty( $settings['min_length'] ) && $length < absint( $settings['min_length'] ) ) {
            return new WP_Error(
                'min_length',
                sprintf(
                    __( 'Please enter at least %d characters.', 'wc-options-studio' ),
                    $settings['min_length']
                )
            );
        }

        // Check max length
        if ( ! empty( $settings['max_length'] ) && $length > absint( $settings['max_length'] ) ) {
            return new WP_Error(
                'max_length',
                sprintf(
                    __( 'Please enter no more than %d characters.', 'wc-options-studio' ),
                    $settings['max_length']
                )
            );
        }

        // Check restriction
        if ( ! empty( $settings['restriction'] ) ) {
            switch ( $settings['restriction'] ) {
                case 'letters':
                    if ( ! preg_match( '/^[\p{L}\s]+$/u', $value ) ) {
                        return new WP_Error( 'letters_only', __( 'Please enter only letters.', 'wc-options-studio' ) );
                    }
                    break;
                    
                case 'numbers':
                    if ( ! preg_match( '/^[0-9]+$/', $value ) ) {
                        return new WP_Error( 'numbers_only', __( 'Please enter only numbers.', 'wc-options-studio' ) );
                    }
                    break;
                    
                case 'alphanumeric':
                    if ( ! preg_match( '/^[\p{L}0-9\s]+$/u', $value ) ) {
                        return new WP_Error( 'alphanumeric_only', __( 'Please enter only letters and numbers.', 'wc-options-studio' ) );
                    }
                    break;
                    
                case 'email':
                    if ( ! is_email( $value ) ) {
                        return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'wc-options-studio' ) );
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Validate customer defined price
     */
    private function validate_customer_price( $value, $settings ) {
        $price = floatval( $value );

        if ( $price < 0 ) {
            return new WP_Error( 'negative_price', __( 'Please enter a positive amount.', 'wc-options-studio' ) );
        }

        if ( ! empty( $settings['min_price'] ) && $price < floatval( $settings['min_price'] ) ) {
            return new WP_Error(
                'min_price',
                sprintf(
                    __( 'Minimum amount is %s.', 'wc-options-studio' ),
                    wc_price( $settings['min_price'] )
                )
            );
        }

        if ( ! empty( $settings['max_price'] ) && $price > floatval( $settings['max_price'] ) ) {
            return new WP_Error(
                'max_price',
                sprintf(
                    __( 'Maximum amount is %s.', 'wc-options-studio' ),
                    wc_price( $settings['max_price'] )
                )
            );
        }

        return true;
    }

    /**
     * Validate quantity field
     */
    private function validate_quantity_field( $value, $settings ) {
        $quantity = absint( $value );

        if ( ! empty( $settings['min_quantity'] ) && $quantity < absint( $settings['min_quantity'] ) ) {
            return new WP_Error(
                'min_quantity',
                sprintf(
                    __( 'Minimum quantity is %d.', 'wc-options-studio' ),
                    $settings['min_quantity']
                )
            );
        }

        if ( ! empty( $settings['max_quantity'] ) && $quantity > absint( $settings['max_quantity'] ) ) {
            return new WP_Error(
                'max_quantity',
                sprintf(
                    __( 'Maximum quantity is %d.', 'wc-options-studio' ),
                    $settings['max_quantity']
                )
            );
        }

        return true;
    }

    /**
     * Validate checkboxes
     */
    private function validate_checkboxes( $value, $addon, $settings, $quantity = 1 ) {
        $link_to_quantity = ! empty( $settings['link_to_quantity'] );
        $allow_same_option = ! empty( $settings['allow_same_option'] );
        
        // Handle quantity-linked with allow_same (quantity per option format)
        if ( $link_to_quantity && $allow_same_option ) {
            // Value should be array of option_id => quantity
            if ( ! is_array( $value ) ) {
                $value = array();
            }
            
            $total_selected = 0;
            foreach ( $value as $option_id => $option_qty ) {
                $total_selected += absint( $option_qty );
            }
            
            if ( $total_selected !== $quantity ) {
                return new WP_Error(
                    'qty_mismatch',
                    sprintf(
                        __( 'Please select exactly %d total for "%s" (you selected %d).', 'wc-options-studio' ),
                        $quantity,
                        $addon->title,
                        $total_selected
                    )
                );
            }
            
            return true;
        }
        
        // Standard checkbox array handling
        if ( ! is_array( $value ) ) {
            $value = array( $value );
        }

        $count = count( array_filter( $value ) );
        
        // Quantity-linked validation (standard checkboxes)
        if ( $link_to_quantity ) {
            if ( $count !== $quantity ) {
                return new WP_Error(
                    'qty_mismatch',
                    sprintf(
                        __( 'Please select exactly %d options for "%s" to match your quantity.', 'wc-options-studio' ),
                        $quantity,
                        $addon->title
                    )
                );
            }
            return true;
        }

        // Standard min/max validation
        if ( ! empty( $settings['min_selections'] ) && $count < absint( $settings['min_selections'] ) ) {
            return new WP_Error(
                'min_selections',
                sprintf(
                    __( 'Please select at least %d options for "%s".', 'wc-options-studio' ),
                    $settings['min_selections'],
                    $addon->title
                )
            );
        }

        if ( ! empty( $settings['max_selections'] ) && $count > absint( $settings['max_selections'] ) ) {
            return new WP_Error(
                'max_selections',
                sprintf(
                    __( 'Please select no more than %d options for "%s".', 'wc-options-studio' ),
                    $settings['max_selections'],
                    $addon->title
                )
            );
        }

        return true;
    }

    /**
     * Validate multiple choice
     */
    private function validate_multiple_choice( $value, $addon ) {
        // Verify option exists
        global $wpdb;
        
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}upad_addon_options 
             WHERE addon_id = %d AND id = %d AND is_hidden = 0",
            $addon->id,
            absint( $value )
        ) );

        if ( ! $exists ) {
            return new WP_Error( 'invalid_option', __( 'Please select a valid option.', 'wc-options-studio' ) );
        }

        return true;
    }

    /**
     * Validate date
     */
    private function validate_date( $value, $settings ) {
        $date = strtotime( $value );
        
        if ( ! $date ) {
            return new WP_Error( 'invalid_date', __( 'Please enter a valid date.', 'wc-options-studio' ) );
        }

        $today = strtotime( 'today' );

        // Check min date
        if ( ! empty( $settings['min_date'] ) ) {
            switch ( $settings['min_date'] ) {
                case 'today':
                    if ( $date < $today ) {
                        return new WP_Error( 'past_date', __( 'Please select today or a future date.', 'wc-options-studio' ) );
                    }
                    break;
                    
                case 'tomorrow':
                    if ( $date < strtotime( 'tomorrow' ) ) {
                        return new WP_Error( 'past_date', __( 'Please select a date starting from tomorrow.', 'wc-options-studio' ) );
                    }
                    break;
                    
                case 'custom':
                    if ( ! empty( $settings['min_date_custom'] ) && $date < strtotime( $settings['min_date_custom'] ) ) {
                        return new WP_Error(
                            'before_min_date',
                            sprintf(
                                __( 'Please select a date on or after %s.', 'wc-options-studio' ),
                                date_i18n( get_option( 'date_format' ), strtotime( $settings['min_date_custom'] ) )
                            )
                        );
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Add addon data to cart item
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        $addons = UPAD_Product_Addons::instance()->get_product_addons( $product_id );
        
        if ( empty( $addons ) ) {
            return $cart_item_data;
        }

        $posted_addons = isset( $_POST['upad_addon'] ) ? $_POST['upad_addon'] : array();
        $posted_addon_qty = isset( $_POST['upad_addon_qty'] ) ? $_POST['upad_addon_qty'] : array();
        $addon_data = array();

        foreach ( $addons as $addon ) {
            // Skip addons that don't apply to this variation
            if ( ! $this->addon_applies_to_variation( $addon, $variation_id ) ) {
                continue;
            }
            
            $settings = is_array( $addon->settings ) ? $addon->settings : array();
            $link_to_quantity = ! empty( $settings['link_to_quantity'] );
            $allow_same_option = ! empty( $settings['allow_same_option'] );
            
            // Get value based on addon type
            if ( $addon->addon_type === 'checkboxes' && $link_to_quantity && $allow_same_option ) {
                $value = isset( $posted_addon_qty[ $addon->id ] ) ? $posted_addon_qty[ $addon->id ] : array();
            } else {
                $value = isset( $posted_addons[ $addon->id ] ) ? $posted_addons[ $addon->id ] : '';
            }
            
            if ( $this->is_addon_value_empty( $value, $addon->addon_type, $settings ) ) {
                continue;
            }

            $processed = $this->process_addon_value( $value, $addon, $settings );
            if ( $processed ) {
                $addon_data[] = $processed;
            }
        }

        if ( ! empty( $addon_data ) ) {
            $cart_item_data['upad_addons'] = $addon_data;
        }

        return $cart_item_data;
    }

    /**
     * Process addon value for cart
     */
    private function process_addon_value( $value, $addon, $settings = array() ) {
        global $wpdb;

        $data = array(
            'addon_id'   => $addon->id,
            'title'      => $addon->title,
            'type'       => $addon->addon_type,
            'value'      => '',
            'display'    => '',
            'price'      => 0,
            'options'    => array(),
        );

        switch ( $addon->addon_type ) {
            case 'multiple_choice':
                $option = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE id = %d",
                    absint( $value )
                ) );
                
                if ( $option ) {
                    $data['value'] = $option->id;
                    $data['display'] = $option->label;
                    $data['price'] = $this->calculate_option_price( $option );
                    $data['options'][] = array(
                        'id'                => $option->id,
                        'label'             => $option->label,
                        'price'             => $data['price'],
                        'linked_product_id' => $option->linked_product_id,
                    );
                }
                break;

            case 'checkboxes':
                $link_to_quantity = ! empty( $settings['link_to_quantity'] );
                $allow_same_option = ! empty( $settings['allow_same_option'] );
                
                // Handle quantity-per-option format (option_id => qty)
                if ( $link_to_quantity && $allow_same_option && is_array( $value ) ) {
                    $labels = array();
                    foreach ( $value as $option_id => $option_qty ) {
                        $option_qty = absint( $option_qty );
                        if ( $option_qty <= 0 ) {
                            continue;
                        }
                        
                        $option = $wpdb->get_row( $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE id = %d",
                            absint( $option_id )
                        ) );
                        
                        if ( $option ) {
                            $option_price = $this->calculate_option_price( $option );
                            $total_option_price = $option_price * $option_qty;
                            
                            if ( $option_qty > 1 ) {
                                $labels[] = $option->label . ' ×' . $option_qty;
                            } else {
                                $labels[] = $option->label;
                            }
                            
                            $data['price'] += $total_option_price;
                            $data['options'][] = array(
                                'id'                => $option->id,
                                'label'             => $option->label,
                                'price'             => $option_price,
                                'quantity'          => $option_qty,
                                'linked_product_id' => $option->linked_product_id,
                            );
                        }
                    }
                    $data['value'] = $value;
                    $data['display'] = implode( ', ', $labels );
                }
                // Standard checkbox array format
                elseif ( is_array( $value ) ) {
                    $labels = array();
                    foreach ( $value as $option_id ) {
                        $option = $wpdb->get_row( $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE id = %d",
                            absint( $option_id )
                        ) );
                        
                        if ( $option ) {
                            $option_price = $this->calculate_option_price( $option );
                            $labels[] = $option->label;
                            $data['price'] += $option_price;
                            $data['options'][] = array(
                                'id'                => $option->id,
                                'label'             => $option->label,
                                'price'             => $option_price,
                                'linked_product_id' => $option->linked_product_id,
                            );
                        }
                    }
                    $data['value'] = $value;
                    $data['display'] = implode( ', ', $labels );
                }
                break;

            case 'short_text':
            case 'long_text':
                $data['value'] = sanitize_textarea_field( $value );
                $data['display'] = $data['value'];
                $data['price'] = $this->calculate_field_price( $addon, $data['value'] );
                break;

            case 'customer_price':
                $data['value'] = floatval( $value );
                $data['display'] = wc_price( $data['value'] );
                $data['price'] = $data['value'];
                break;

            case 'quantity':
                $data['value'] = absint( $value );
                $data['display'] = $data['value'];
                $data['price'] = $this->calculate_quantity_price( $data['value'], $addon );
                break;

            case 'date_picker':
                $data['value'] = sanitize_text_field( $value );
                $format = isset( $addon->settings['date_format'] ) ? $addon->settings['date_format'] : 'Y-m-d';
                $data['display'] = date_i18n( $format, strtotime( $data['value'] ) );
                $data['price'] = $this->calculate_field_price( $addon );
                break;

            case 'color_picker':
                $data['value'] = sanitize_hex_color( $value );
                $data['display'] = $data['value'];
                $data['price'] = $this->calculate_field_price( $addon );
                break;

            case 'file_upload':
                if ( is_array( $value ) ) {
                    $data['value'] = array_map( 'sanitize_text_field', $value );
                    $data['display'] = count( $data['value'] ) . ' ' . _n( 'file', 'files', count( $data['value'] ), 'wc-options-studio' );
                    $data['price'] = $this->calculate_field_price( $addon );
                }
                break;
            
            case 'image_swatches':
                // Handle similar to multiple_choice or checkboxes depending on settings
                $settings = is_array( $addon->settings ) ? $addon->settings : array();
                $multiple = ! empty( $settings['multiple'] );
                
                if ( $multiple && is_array( $value ) ) {
                    $labels = array();
                    foreach ( $value as $option_id ) {
                        $option = $wpdb->get_row( $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE id = %d",
                            absint( $option_id )
                        ) );
                        if ( $option ) {
                            $labels[] = $option->label;
                            $data['price'] += $this->calculate_option_price( $option );
                            $data['options'][] = array(
                                'id'    => $option->id,
                                'label' => $option->label,
                                'price' => $this->calculate_option_price( $option ),
                            );
                        }
                    }
                    $data['value'] = $value;
                    $data['display'] = implode( ', ', $labels );
                } else {
                    $option = $wpdb->get_row( $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE id = %d",
                        absint( $value )
                    ) );
                    if ( $option ) {
                        $data['value'] = $option->id;
                        $data['display'] = $option->label;
                        $data['price'] = $this->calculate_option_price( $option );
                    }
                }
                break;
            
            case 'datetime_picker':
                // Handle array with date and time
                if ( is_array( $value ) ) {
                    $date = isset( $value['date'] ) ? sanitize_text_field( $value['date'] ) : '';
                    $time = isset( $value['time'] ) ? sanitize_text_field( $value['time'] ) : '';
                    $data['value'] = $date . ' ' . $time;
                    
                    // Format display
                    $date_format = isset( $addon->settings['date_format'] ) ? $addon->settings['date_format'] : 'Y-m-d';
                    $time_format = isset( $addon->settings['time_format'] ) ? $addon->settings['time_format'] : 'H:i';
                    
                    $display_date = $date ? date_i18n( $date_format, strtotime( $date ) ) : '';
                    $display_time = $time ? date_i18n( $time_format, strtotime( $time ) ) : '';
                    $data['display'] = trim( $display_date . ' ' . $display_time );
                    $data['price'] = $this->calculate_field_price( $addon );
                }
                break;
            
            case 'dimensions':
                // Handle array with length, width, height
                if ( is_array( $value ) ) {
                    $length = isset( $value['length'] ) ? floatval( $value['length'] ) : 0;
                    $width = isset( $value['width'] ) ? floatval( $value['width'] ) : 0;
                    $height = isset( $value['height'] ) ? floatval( $value['height'] ) : 0;
                    
                    $data['value'] = $value;
                    
                    // Format display
                    $unit = isset( $addon->settings['unit_label'] ) ? $addon->settings['unit_label'] : 'inches';
                    $parts = array( $length . ' ' . $unit );
                    if ( $width > 0 ) {
                        $parts[] = $width . ' ' . $unit;
                    }
                    if ( $height > 0 ) {
                        $parts[] = $height . ' ' . $unit;
                    }
                    $data['display'] = implode( ' × ', $parts );
                    $data['price'] = $this->calculate_dimensions_price( $addon, $value );
                }
                break;
        }

        return $data;
    }

    /**
     * Calculate option price
     */
    private function calculate_option_price( $option, $quantity = 1 ) {
        if ( empty( $option->price ) ) {
            return 0;
        }
        
        $base_price = floatval( $option->price );
        
        // Check for tiered pricing
        if ( ! empty( $option->tiered_prices ) ) {
            $tiered_prices = maybe_unserialize( $option->tiered_prices );
            if ( is_array( $tiered_prices ) && ! empty( $tiered_prices ) ) {
                // Sort tiers by min_qty descending
                usort( $tiered_prices, function( $a, $b ) {
                    return $b['min_qty'] - $a['min_qty'];
                } );
                
                foreach ( $tiered_prices as $tier ) {
                    if ( $quantity >= absint( $tier['min_qty'] ) ) {
                        return floatval( $tier['price'] );
                    }
                }
            }
        }

        return $base_price;
    }

    /**
     * Calculate field price from settings
     */
    private function calculate_field_price( $addon, $value = '' ) {
        $settings = is_array( $addon->settings ) ? $addon->settings : array();
        
        if ( empty( $settings['price'] ) ) {
            return 0;
        }

        $base_price = floatval( $settings['price'] );
        $price_type = isset( $settings['price_type'] ) ? $settings['price_type'] : 'flat';
        $free_chars = isset( $settings['free_chars'] ) ? absint( $settings['free_chars'] ) : 0;
        
        switch ( $price_type ) {
            case 'per_character':
                $char_count = strlen( $value );
                $chargeable = max( 0, $char_count - $free_chars );
                return $base_price * $chargeable;
                
            case 'per_word':
                $word_count = str_word_count( $value );
                $chargeable = max( 0, $word_count - $free_chars );
                return $base_price * $chargeable;
                
            case 'per_line':
                $line_count = substr_count( $value, "\n" ) + 1;
                $chargeable = max( 0, $line_count - $free_chars );
                return $base_price * $chargeable;
                
            case 'flat':
            default:
                return $base_price;
        }
    }
    
    /**
     * Calculate dimensions/formula-based price
     */
    private function calculate_dimensions_price( $addon, $dimensions ) {
        $settings = is_array( $addon->settings ) ? $addon->settings : array();
        
        $length = isset( $dimensions['length'] ) ? floatval( $dimensions['length'] ) : 0;
        $width = isset( $dimensions['width'] ) ? floatval( $dimensions['width'] ) : 0;
        $height = isset( $dimensions['height'] ) ? floatval( $dimensions['height'] ) : 0;
        
        $formula = isset( $settings['pricing_formula'] ) ? $settings['pricing_formula'] : 'per_sq_unit';
        $price_per_unit = isset( $settings['price_per_unit'] ) ? floatval( $settings['price_per_unit'] ) : 0;
        $min_price = isset( $settings['min_price'] ) ? floatval( $settings['min_price'] ) : 0;
        
        $price = 0;
        
        switch ( $formula ) {
            case 'per_unit':
                $price = $length * $price_per_unit;
                break;
                
            case 'per_sq_unit':
                $price = ( $length * $width ) * $price_per_unit;
                break;
                
            case 'per_cubic_unit':
                $price = ( $length * $width * $height ) * $price_per_unit;
                break;
                
            case 'custom':
                $custom_formula = isset( $settings['custom_formula'] ) ? $settings['custom_formula'] : '';
                if ( $custom_formula ) {
                    $formula_str = str_replace(
                        array( '{length}', '{width}', '{height}' ),
                        array( $length, $width, $height ),
                        $custom_formula
                    );
                    // Safe evaluation - only allow numbers and basic math operators
                    if ( preg_match( '/^[\d\.\s\+\-\*\/\(\)]+$/', $formula_str ) ) {
                        $price = @eval( 'return ' . $formula_str . ';' );
                    }
                }
                break;
        }
        
        // Apply minimum price
        if ( $min_price > 0 && $price < $min_price ) {
            $price = $min_price;
        }
        
        return $price;
    }

    /**
     * Calculate quantity-based price
     */
    private function calculate_quantity_price( $quantity, $addon ) {
        $settings = is_array( $addon->settings ) ? $addon->settings : array();
        
        if ( empty( $settings['price'] ) ) {
            return 0;
        }

        return floatval( $settings['price'] ) * $quantity;
    }

    /**
     * Display addon data in cart
     */
    public function get_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['upad_addons'] ) ) {
            return $item_data;
        }

        foreach ( $cart_item['upad_addons'] as $addon ) {
            $display = $addon['display'];
            
            // Optionally show price
            if ( 'yes' === get_option( 'upad_display_option_prices', 'yes' ) && ! empty( $addon['price'] ) ) {
                $display .= ' (' . wc_price( $addon['price'] ) . ')';
            }

            $item_data[] = array(
                'key'   => $addon['title'],
                'value' => $display,
            );
        }

        return $item_data;
    }

    /**
     * Calculate cart totals with addons
     */
    public function calculate_totals( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        // Prevent running multiple times
        static $running = false;
        if ( $running ) {
            return;
        }
        $running = true;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['upad_addons'] ) ) {
                continue;
            }

            $product = $cart_item['data'];
            
            // Get the ORIGINAL base price, not the modified one
            // Use the product ID to get fresh price from database
            $original_product = wc_get_product( $product->get_id() );
            if ( ! $original_product ) {
                continue;
            }
            
            $base_price = floatval( $original_product->get_price( 'edit' ) );
            $addon_total = 0;

            foreach ( $cart_item['upad_addons'] as $addon ) {
                $addon_price = floatval( $addon['price'] );
                $addon_total += $addon_price;
            }

            if ( $addon_total != 0 ) {
                $product->set_price( $base_price + $addon_total );
            }
        }
        
        $running = false;
    }

    /**
     * Generate unique cart item key including addons
     */
    public function generate_cart_item_key( $cart_id, $product_id, $cart_item_data ) {
        if ( ! empty( $cart_item_data['upad_addons'] ) ) {
            $cart_id = md5( $cart_id . serialize( $cart_item_data['upad_addons'] ) );
        }
        return $cart_id;
    }

    /**
     * Restore addon data from session
     */
    public function get_cart_item_from_session( $cart_item, $values, $key ) {
        if ( ! empty( $values['upad_addons'] ) ) {
            $cart_item['upad_addons'] = $values['upad_addons'];
        }
        return $cart_item;
    }
}
