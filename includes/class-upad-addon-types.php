<?php
/**
 * Add-on Types Registry
 * Defines all available add-on field types and their configurations
 * 
 * Free version includes: Multiple Choice, Checkboxes, Short Text, Long Text,
 * File Upload, Customer Price, Quantity, Heading, Date Picker, Color Picker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Addon_Types {

    protected static $_instance = null;

    /**
     * Registered addon types
     */
    private $types = array();

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'init', array( $this, 'register_default_types' ), 5 );
        add_action( 'init', array( $this, 'allow_custom_types' ), 20 );
    }

    /**
     * Register all default add-on types
     */
    public function register_default_types() {
        // Multiple Choice
        $this->types['multiple_choice'] = array(
            'name'        => __( 'Multiple Choice', 'unstoppable-product-addons' ),
            'description' => __( 'Dropdown, radio buttons, or image selection. Single selection only.', 'unstoppable-product-addons' ),
            'icon'        => 'list-ul',
            'has_options' => true,
            'settings'    => array(
                'display_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display as', 'unstoppable-product-addons' ),
                    'default' => 'dropdown',
                    'options' => array(
                        'dropdown' => __( 'Dropdown', 'unstoppable-product-addons' ),
                        'radio'    => __( 'Radio Buttons', 'unstoppable-product-addons' ),
                        'images'   => __( 'Images', 'unstoppable-product-addons' ),
                    ),
                ),
                'show_none_option' => array(
                    'type'    => 'checkbox',
                    'label'   => __( 'Show "None" option for radio buttons', 'unstoppable-product-addons' ),
                    'default' => true,
                ),
            ),
        );

        // Checkboxes
        $this->types['checkboxes'] = array(
            'name'        => __( 'Checkboxes', 'unstoppable-product-addons' ),
            'description' => __( 'Multiple optional checkboxes. Customers can select multiple options.', 'unstoppable-product-addons' ),
            'icon'        => 'check-square',
            'has_options' => true,
            'settings'    => array(
                'min_selections' => array(
                    'type'    => 'number',
                    'label'   => __( 'Minimum selections', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                ),
                'max_selections' => array(
                    'type'    => 'number',
                    'label'   => __( 'Maximum selections', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                    'desc'    => __( '0 = unlimited', 'unstoppable-product-addons' ),
                ),
            ),
        );

        // Short Text
        $this->types['short_text'] = array(
            'name'        => __( 'Short Text', 'unstoppable-product-addons' ),
            'description' => __( 'Single line text input with optional validation.', 'unstoppable-product-addons' ),
            'icon'        => 'text-width',
            'has_options' => false,
            'settings'    => array(
                'restriction' => array(
                    'type'    => 'select',
                    'label'   => __( 'Input restriction', 'unstoppable-product-addons' ),
                    'default' => 'any',
                    'options' => array(
                        'any'          => __( 'Any text', 'unstoppable-product-addons' ),
                        'letters'      => __( 'Only letters', 'unstoppable-product-addons' ),
                        'numbers'      => __( 'Only numbers', 'unstoppable-product-addons' ),
                        'alphanumeric' => __( 'Letters and numbers', 'unstoppable-product-addons' ),
                        'email'        => __( 'Email address', 'unstoppable-product-addons' ),
                    ),
                ),
                'placeholder' => array(
                    'type'    => 'text',
                    'label'   => __( 'Placeholder text', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'min_length' => array(
                    'type'    => 'number',
                    'label'   => __( 'Minimum characters', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                ),
                'max_length' => array(
                    'type'    => 'number',
                    'label'   => __( 'Maximum characters', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                    'desc'    => __( '0 = unlimited', 'unstoppable-product-addons' ),
                ),
                'price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Adjust price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'price_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Price type', 'unstoppable-product-addons' ),
                    'default' => 'flat',
                    'options' => array(
                        'flat'       => __( 'Flat fee', 'unstoppable-product-addons' ),
                        'quantity'   => __( 'Quantity based', 'unstoppable-product-addons' ),
                        'percentage' => __( 'Percentage of price', 'unstoppable-product-addons' ),
                    ),
                ),
            ),
        );

        // Long Text
        $this->types['long_text'] = array(
            'name'        => __( 'Long Text', 'unstoppable-product-addons' ),
            'description' => __( 'Multi-line textarea for longer content.', 'unstoppable-product-addons' ),
            'icon'        => 'align-left',
            'has_options' => false,
            'settings'    => array(
                'placeholder' => array(
                    'type'    => 'text',
                    'label'   => __( 'Placeholder text', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'rows' => array(
                    'type'    => 'number',
                    'label'   => __( 'Number of rows', 'unstoppable-product-addons' ),
                    'default' => 4,
                    'min'     => 2,
                    'max'     => 20,
                ),
                'min_length' => array(
                    'type'    => 'number',
                    'label'   => __( 'Minimum characters', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                ),
                'max_length' => array(
                    'type'    => 'number',
                    'label'   => __( 'Maximum characters', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                    'desc'    => __( '0 = unlimited', 'unstoppable-product-addons' ),
                ),
                'show_char_count' => array(
                    'type'    => 'checkbox',
                    'label'   => __( 'Show character counter', 'unstoppable-product-addons' ),
                    'default' => true,
                ),
                'price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Adjust price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'price_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Price type', 'unstoppable-product-addons' ),
                    'default' => 'flat',
                    'options' => array(
                        'flat'       => __( 'Flat fee', 'unstoppable-product-addons' ),
                        'quantity'   => __( 'Quantity based', 'unstoppable-product-addons' ),
                        'percentage' => __( 'Percentage of price', 'unstoppable-product-addons' ),
                    ),
                ),
            ),
        );

        // File Upload
        $this->types['file_upload'] = array(
            'name'        => __( 'File Upload', 'unstoppable-product-addons' ),
            'description' => __( 'Secure file upload with type and size restrictions.', 'unstoppable-product-addons' ),
            'icon'        => 'upload',
            'has_options' => false,
            'settings'    => array(
                'allowed_extensions' => array(
                    'type'    => 'text',
                    'label'   => __( 'Allowed file extensions', 'unstoppable-product-addons' ),
                    'default' => 'jpg,jpeg,png,gif,pdf',
                    'desc'    => __( 'Comma-separated list (e.g., jpg,png,pdf). Leave empty to use global settings.', 'unstoppable-product-addons' ),
                ),
                'max_file_size' => array(
                    'type'    => 'number',
                    'label'   => __( 'Maximum file size (MB)', 'unstoppable-product-addons' ),
                    'default' => 5,
                    'min'     => 0.1,
                    'max'     => 100,
                    'step'    => 0.1,
                    'desc'    => __( 'Maximum upload size in megabytes.', 'unstoppable-product-addons' ),
                ),
                'max_files' => array(
                    'type'    => 'number',
                    'label'   => __( 'Maximum number of files', 'unstoppable-product-addons' ),
                    'default' => 1,
                    'min'     => 1,
                    'max'     => 20,
                ),
                'min_dimensions' => array(
                    'type'    => 'text',
                    'label'   => __( 'Minimum image dimensions', 'unstoppable-product-addons' ),
                    'default' => '',
                    'desc'    => __( 'Format: widthxheight (e.g., 300x300). Leave empty for no minimum.', 'unstoppable-product-addons' ),
                ),
                'max_dimensions' => array(
                    'type'    => 'text',
                    'label'   => __( 'Maximum image dimensions', 'unstoppable-product-addons' ),
                    'default' => '',
                    'desc'    => __( 'Format: widthxheight (e.g., 4000x4000). Leave empty for no maximum.', 'unstoppable-product-addons' ),
                ),
                'price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Adjust price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'price_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Price type', 'unstoppable-product-addons' ),
                    'default' => 'flat',
                    'options' => array(
                        'flat'       => __( 'Flat fee', 'unstoppable-product-addons' ),
                        'quantity'   => __( 'Quantity based', 'unstoppable-product-addons' ),
                        'percentage' => __( 'Percentage of price', 'unstoppable-product-addons' ),
                    ),
                ),
            ),
        );

        // Customer Defined Price
        $this->types['customer_price'] = array(
            'name'        => __( 'Customer Defined Price', 'unstoppable-product-addons' ),
            'description' => __( 'Let customers enter their own price (donations, tips).', 'unstoppable-product-addons' ),
            'icon'        => 'dollar-sign',
            'has_options' => false,
            'settings'    => array(
                'default_price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Pre-filled price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'min_price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Minimum price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'max_price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Maximum price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
            ),
        );

        // Quantity
        $this->types['quantity'] = array(
            'name'        => __( 'Quantity', 'unstoppable-product-addons' ),
            'description' => __( 'Custom quantity input field.', 'unstoppable-product-addons' ),
            'icon'        => 'sort-numeric-up',
            'has_options' => false,
            'settings'    => array(
                'default_quantity' => array(
                    'type'    => 'number',
                    'label'   => __( 'Pre-filled quantity', 'unstoppable-product-addons' ),
                    'default' => 1,
                    'min'     => 0,
                ),
                'min_quantity' => array(
                    'type'    => 'number',
                    'label'   => __( 'Minimum quantity', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                ),
                'max_quantity' => array(
                    'type'    => 'number',
                    'label'   => __( 'Maximum quantity', 'unstoppable-product-addons' ),
                    'default' => 0,
                    'min'     => 0,
                    'desc'    => __( '0 = unlimited', 'unstoppable-product-addons' ),
                ),
                'step' => array(
                    'type'    => 'number',
                    'label'   => __( 'Step increment', 'unstoppable-product-addons' ),
                    'default' => 1,
                    'min'     => 1,
                ),
                'price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Price per unit', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'price_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Price type', 'unstoppable-product-addons' ),
                    'default' => 'flat',
                    'options' => array(
                        'flat'       => __( 'Flat fee per unit', 'unstoppable-product-addons' ),
                        'percentage' => __( 'Percentage of price per unit', 'unstoppable-product-addons' ),
                    ),
                ),
            ),
        );

        // Heading
        $this->types['heading'] = array(
            'name'        => __( 'Heading', 'unstoppable-product-addons' ),
            'description' => __( 'Section heading to organize add-ons.', 'unstoppable-product-addons' ),
            'icon'        => 'heading',
            'has_options' => false,
            'is_heading'  => true,
            'settings'    => array(
                'heading_level' => array(
                    'type'    => 'select',
                    'label'   => __( 'Heading level', 'unstoppable-product-addons' ),
                    'default' => 'h3',
                    'options' => array(
                        'h2' => 'H2',
                        'h3' => 'H3',
                        'h4' => 'H4',
                        'h5' => 'H5',
                    ),
                ),
            ),
        );

        // Date Picker
        $this->types['date_picker'] = array(
            'name'        => __( 'Date Picker', 'unstoppable-product-addons' ),
            'description' => __( 'Calendar date selection.', 'unstoppable-product-addons' ),
            'icon'        => 'calendar',
            'has_options' => false,
            'settings'    => array(
                'date_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Date format', 'unstoppable-product-addons' ),
                    'default' => 'Y-m-d',
                    'options' => array(
                        'Y-m-d'   => date( 'Y-m-d' ) . ' (YYYY-MM-DD)',
                        'm/d/Y'   => date( 'm/d/Y' ) . ' (MM/DD/YYYY)',
                        'd/m/Y'   => date( 'd/m/Y' ) . ' (DD/MM/YYYY)',
                        'F j, Y'  => date( 'F j, Y' ),
                        'j F Y'   => date( 'j F Y' ),
                    ),
                ),
                'min_date' => array(
                    'type'    => 'select',
                    'label'   => __( 'Minimum date', 'unstoppable-product-addons' ),
                    'default' => 'today',
                    'options' => array(
                        'none'     => __( 'No minimum', 'unstoppable-product-addons' ),
                        'today'    => __( 'Today', 'unstoppable-product-addons' ),
                        'tomorrow' => __( 'Tomorrow', 'unstoppable-product-addons' ),
                        'custom'   => __( 'Custom date', 'unstoppable-product-addons' ),
                    ),
                ),
                'min_date_custom' => array(
                    'type'    => 'date',
                    'label'   => __( 'Custom minimum date', 'unstoppable-product-addons' ),
                    'default' => '',
                    'condition' => array( 'min_date' => 'custom' ),
                ),
                'max_date' => array(
                    'type'    => 'select',
                    'label'   => __( 'Maximum date', 'unstoppable-product-addons' ),
                    'default' => 'none',
                    'options' => array(
                        'none'   => __( 'No maximum', 'unstoppable-product-addons' ),
                        'custom' => __( 'Custom date', 'unstoppable-product-addons' ),
                    ),
                ),
                'max_date_custom' => array(
                    'type'    => 'date',
                    'label'   => __( 'Custom maximum date', 'unstoppable-product-addons' ),
                    'default' => '',
                    'condition' => array( 'max_date' => 'custom' ),
                ),
                'disabled_days' => array(
                    'type'    => 'multiselect',
                    'label'   => __( 'Disabled days of week', 'unstoppable-product-addons' ),
                    'default' => array(),
                    'options' => array(
                        '0' => __( 'Sunday', 'unstoppable-product-addons' ),
                        '1' => __( 'Monday', 'unstoppable-product-addons' ),
                        '2' => __( 'Tuesday', 'unstoppable-product-addons' ),
                        '3' => __( 'Wednesday', 'unstoppable-product-addons' ),
                        '4' => __( 'Thursday', 'unstoppable-product-addons' ),
                        '5' => __( 'Friday', 'unstoppable-product-addons' ),
                        '6' => __( 'Saturday', 'unstoppable-product-addons' ),
                    ),
                ),
                'price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Adjust price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'price_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Price type', 'unstoppable-product-addons' ),
                    'default' => 'flat',
                    'options' => array(
                        'flat'       => __( 'Flat fee', 'unstoppable-product-addons' ),
                        'quantity'   => __( 'Quantity based', 'unstoppable-product-addons' ),
                        'percentage' => __( 'Percentage of price', 'unstoppable-product-addons' ),
                    ),
                ),
            ),
        );

        // Color Picker
        $this->types['color_picker'] = array(
            'name'        => __( 'Color Picker', 'unstoppable-product-addons' ),
            'description' => __( 'Color selection for customizable products.', 'unstoppable-product-addons' ),
            'icon'        => 'palette',
            'has_options' => false,
            'settings'    => array(
                'default_color' => array(
                    'type'    => 'color',
                    'label'   => __( 'Default color', 'unstoppable-product-addons' ),
                    'default' => '#000000',
                ),
                'allowed_colors' => array(
                    'type'    => 'textarea',
                    'label'   => __( 'Restrict to colors', 'unstoppable-product-addons' ),
                    'default' => '',
                    'desc'    => __( 'One hex color per line. Leave empty for any color.', 'unstoppable-product-addons' ),
                ),
                'price' => array(
                    'type'    => 'price',
                    'label'   => __( 'Adjust price', 'unstoppable-product-addons' ),
                    'default' => '',
                ),
                'price_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Price type', 'unstoppable-product-addons' ),
                    'default' => 'flat',
                    'options' => array(
                        'flat'       => __( 'Flat fee', 'unstoppable-product-addons' ),
                        'quantity'   => __( 'Quantity based', 'unstoppable-product-addons' ),
                        'percentage' => __( 'Percentage of price', 'unstoppable-product-addons' ),
                    ),
                ),
            ),
        );

        // Allow filtering of default types
        $this->types = apply_filters( 'upad_addon_types', $this->types );
    }

    /**
     * Allow plugins/themes to register custom types
     */
    public function allow_custom_types() {
        do_action( 'upad_register_addon_types', $this );
    }

    /**
     * Register a new addon type
     */
    public function register_type( $key, $config ) {
        $this->types[ $key ] = wp_parse_args( $config, array(
            'name'        => '',
            'description' => '',
            'icon'        => 'cog',
            'has_options' => false,
            'settings'    => array(),
        ) );
    }

    /**
     * Get all registered types
     */
    public function get_types() {
        // Ensure types are registered
        if ( empty( $this->types ) ) {
            $this->register_default_types();
        }
        return $this->types;
    }

    /**
     * Get a specific type
     */
    public function get_type( $key ) {
        // Ensure types are registered
        if ( empty( $this->types ) ) {
            $this->register_default_types();
        }
        return isset( $this->types[ $key ] ) ? $this->types[ $key ] : null;
    }

    /**
     * Check if type exists
     */
    public function type_exists( $key ) {
        // Ensure types are registered
        if ( empty( $this->types ) ) {
            $this->register_default_types();
        }
        return isset( $this->types[ $key ] );
    }

    /**
     * Get types that have options
     */
    public function get_types_with_options() {
        return array_filter( $this->types, function( $type ) {
            return ! empty( $type['has_options'] );
        } );
    }
}
