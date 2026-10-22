<?php
/**
 * Per-Product Add-ons Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Product_Addons {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        // Initialize hooks
    }

    /**
     * Get all addons for a product (both per-product and global)
     */
    public function get_product_addons( $product_id, $include_global = true ) {
        $addons = array();

        // Get per-product addons
        $product_addons = $this->get_addons( $product_id );
        foreach ( $product_addons as $addon ) {
            $addon->source = 'product';
            $addons[] = $addon;
        }

        // Get global addons
        if ( $include_global ) {
            $use_global = get_post_meta( $product_id, '_upad_use_global_addons', true );
            if ( $use_global !== 'no' ) {
                $global_addons = UPAD_Global_Addons::instance()->get_addons_for_product( $product_id );
                $addons = array_merge( $addons, $global_addons );
            }
        }

        // Sort by position
        usort( $addons, function( $a, $b ) {
            return $a->position - $b->position;
        } );

        return apply_filters( 'upad_product_addons', $addons, $product_id );
    }

    /**
     * Get per-product addons only
     */
    public function get_addons( $product_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $addons = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addons
             WHERE product_id = %d AND enabled = 1
             ORDER BY position ASC",
            $product_id
        ) );

        foreach ( $addons as $key => $addon ) {
            // Sanitize null values
            $addons[ $key ]->title = $addon->title ?? '';
            $addons[ $key ]->description = $addon->description ?? '';
            $addons[ $key ]->addon_type = $addon->addon_type ?? 'multiple_choice';
            $addons[ $key ]->title_format = $addon->title_format ?? 'label';
            $addons[ $key ]->required = $addon->required ?? 0;
            $addons[ $key ]->enabled = $addon->enabled ?? 1;
            $addons[ $key ]->options = $this->get_addon_options( $addon->id );
            $addons[ $key ]->settings = maybe_unserialize( $addon->settings ?? '' );
            if ( ! is_array( $addons[ $key ]->settings ) ) {
                $addons[ $key ]->settings = array();
            }
        }

        return $addons;
    }

    /**
     * Get options for an addon
     */
    public function get_addon_options( $addon_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $options = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addon_options
             WHERE addon_id = %d AND is_hidden = 0
             ORDER BY position ASC",
            $addon_id
        ) );

        // Sanitize null values
        foreach ( $options as $key => $option ) {
            $options[ $key ]->label = $option->label ?? '';
            $options[ $key ]->price = $option->price ?? '';
            $options[ $key ]->price_type = $option->price_type ?? 'flat';
            $options[ $key ]->is_default = $option->is_default ?? 0;
        }

        return $options;
    }

    /**
     * Create a new addon for a product
     */
    public function create_addon( $product_id, $data ) {
        global $wpdb;

        $defaults = array(
            'addon_type'   => 'multiple_choice',
            'title'        => '',
            'title_format' => 'label',
            'description'  => '',
            'required'     => 0,
            'position'     => 0,
            'settings'     => array(),
            'enabled'      => 1,
        );

        $data = wp_parse_args( $data, $defaults );

        // Get next position
        if ( empty( $data['position'] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $max_position = $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(position) FROM {$wpdb->prefix}upad_addons WHERE product_id = %d",
                $product_id
            ) );
            $data['position'] = ( $max_position !== null ) ? $max_position + 1 : 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
        $result = $wpdb->insert(
            $wpdb->prefix . 'upad_addons',
            array(
                'group_id'     => null,
                'product_id'   => $product_id,
                'addon_type'   => sanitize_key( $data['addon_type'] ),
                'title'        => sanitize_text_field( $data['title'] ),
                'title_format' => sanitize_key( $data['title_format'] ),
                'description'  => wp_kses_post( $data['description'] ),
                'required'     => $data['required'] ? 1 : 0,
                'position'     => absint( $data['position'] ),
                'settings'     => maybe_serialize( $data['settings'] ),
                'enabled'      => $data['enabled'] ? 1 : 0,
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to create addon.', 'unstoppable-product-addons' ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update an addon
     */
    public function update_addon( $addon_id, $data ) {
        global $wpdb;

        $update_data = array();
        $format = array();

        $allowed_fields = array(
            'addon_type'   => '%s',
            'title'        => '%s',
            'title_format' => '%s',
            'description'  => '%s',
            'required'     => '%d',
            'position'     => '%d',
            'settings'     => '%s',
            'enabled'      => '%d',
        );

        foreach ( $allowed_fields as $field => $field_format ) {
            if ( isset( $data[ $field ] ) ) {
                $value = $data[ $field ];

                switch ( $field ) {
                    case 'addon_type':
                    case 'title_format':
                        $value = sanitize_key( $value );
                        break;
                    case 'title':
                        $value = sanitize_text_field( $value );
                        break;
                    case 'description':
                        $value = wp_kses_post( $value );
                        break;
                    case 'required':
                    case 'enabled':
                        $value = $value ? 1 : 0;
                        break;
                    case 'position':
                        $value = absint( $value );
                        break;
                    case 'settings':
                        $value = maybe_serialize( $value );
                        break;
                }

                $update_data[ $field ] = $value;
                $format[] = $field_format;
            }
        }

        if ( empty( $update_data ) ) {
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        return $wpdb->update(
            $wpdb->prefix . 'upad_addons',
            $update_data,
            array( 'id' => $addon_id ),
            $format,
            array( '%d' )
        );
    }

    /**
     * Delete an addon
     */
    public function delete_addon( $addon_id ) {
        global $wpdb;

        // Delete options first
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $wpdb->delete(
            $wpdb->prefix . 'upad_addon_options',
            array( 'addon_id' => $addon_id ),
            array( '%d' )
        );

        // Delete addon
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        return $wpdb->delete(
            $wpdb->prefix . 'upad_addons',
            array( 'id' => $addon_id ),
            array( '%d' )
        );
    }

    /**
     * Add option to an addon
     */
    public function add_option( $addon_id, $data ) {
        global $wpdb;

        $defaults = array(
            'label'             => '',
            'price'             => null,
            'price_type'        => 'flat',
            'image_id'          => null,
            'linked_product_id' => null,
            'is_default'        => 0,
            'is_hidden'         => 0,
            'position'          => 0,
        );

        $data = wp_parse_args( $data, $defaults );

        // Get next position
        if ( empty( $data['position'] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $max_position = $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(position) FROM {$wpdb->prefix}upad_addon_options WHERE addon_id = %d",
                $addon_id
            ) );
            $data['position'] = ( $max_position !== null ) ? $max_position + 1 : 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
        $result = $wpdb->insert(
            $wpdb->prefix . 'upad_addon_options',
            array(
                'addon_id'          => $addon_id,
                'label'             => sanitize_text_field( $data['label'] ),
                'price'             => $data['price'] !== null ? floatval( $data['price'] ) : null,
                'price_type'        => sanitize_key( $data['price_type'] ),
                'image_id'          => $data['image_id'] ? absint( $data['image_id'] ) : null,
                'linked_product_id' => $data['linked_product_id'] ? absint( $data['linked_product_id'] ) : null,
                'is_default'        => $data['is_default'] ? 1 : 0,
                'is_hidden'         => $data['is_hidden'] ? 1 : 0,
                'position'          => absint( $data['position'] ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to add option.', 'unstoppable-product-addons' ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update an option
     */
    public function update_option( $option_id, $data ) {
        global $wpdb;

        $update_data = array();
        $format = array();

        if ( isset( $data['label'] ) ) {
            $update_data['label'] = sanitize_text_field( $data['label'] );
            $format[] = '%s';
        }

        if ( isset( $data['price'] ) ) {
            $update_data['price'] = $data['price'] !== '' ? floatval( $data['price'] ) : null;
            $format[] = '%s';
        }

        if ( isset( $data['price_type'] ) ) {
            $update_data['price_type'] = sanitize_key( $data['price_type'] );
            $format[] = '%s';
        }

        if ( isset( $data['image_id'] ) ) {
            $update_data['image_id'] = $data['image_id'] ? absint( $data['image_id'] ) : null;
            $format[] = '%d';
        }

        if ( isset( $data['linked_product_id'] ) ) {
            $update_data['linked_product_id'] = $data['linked_product_id'] ? absint( $data['linked_product_id'] ) : null;
            $format[] = '%d';
        }

        if ( isset( $data['is_default'] ) ) {
            $update_data['is_default'] = $data['is_default'] ? 1 : 0;
            $format[] = '%d';
        }

        if ( isset( $data['is_hidden'] ) ) {
            $update_data['is_hidden'] = $data['is_hidden'] ? 1 : 0;
            $format[] = '%d';
        }

        if ( isset( $data['position'] ) ) {
            $update_data['position'] = absint( $data['position'] );
            $format[] = '%d';
        }

        if ( empty( $update_data ) ) {
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        return $wpdb->update(
            $wpdb->prefix . 'upad_addon_options',
            $update_data,
            array( 'id' => $option_id ),
            $format,
            array( '%d' )
        );
    }

    /**
     * Delete an option
     */
    public function delete_option( $option_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        return $wpdb->delete(
            $wpdb->prefix . 'upad_addon_options',
            array( 'id' => $option_id ),
            array( '%d' )
        );
    }

    /**
     * Reorder addons
     */
    public function reorder_addons( $product_id, $order ) {
        global $wpdb;

        foreach ( $order as $position => $addon_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $wpdb->update(
                $wpdb->prefix . 'upad_addons',
                array( 'position' => $position ),
                array( 'id' => $addon_id, 'product_id' => $product_id ),
                array( '%d' ),
                array( '%d', '%d' )
            );
        }

        return true;
    }

    /**
     * Reorder options
     */
    public function reorder_options( $addon_id, $order ) {
        global $wpdb;

        foreach ( $order as $position => $option_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $wpdb->update(
                $wpdb->prefix . 'upad_addon_options',
                array( 'position' => $position ),
                array( 'id' => $option_id, 'addon_id' => $addon_id ),
                array( '%d' ),
                array( '%d', '%d' )
            );
        }

        return true;
    }

    /**
     * Copy addons from one product to another
     */
    public function copy_addons( $from_product_id, $to_product_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $addons = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addons WHERE product_id = %d",
            $from_product_id
        ) );

        foreach ( $addons as $addon ) {
            $new_addon_id = $this->create_addon( $to_product_id, array(
                'addon_type'   => $addon->addon_type,
                'title'        => $addon->title,
                'title_format' => $addon->title_format,
                'description'  => $addon->description,
                'required'     => $addon->required,
                'position'     => $addon->position,
                'settings'     => maybe_unserialize( $addon->settings ),
                'enabled'      => $addon->enabled,
            ) );

            if ( ! is_wp_error( $new_addon_id ) ) {
                // Copy options
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
                $options = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE addon_id = %d",
                    $addon->id
                ) );

                foreach ( $options as $option ) {
                    $this->add_option( $new_addon_id, array(
                        'label'             => $option->label,
                        'price'             => $option->price,
                        'price_type'        => $option->price_type,
                        'image_id'          => $option->image_id,
                        'linked_product_id' => $option->linked_product_id,
                        'is_default'        => $option->is_default,
                        'is_hidden'         => $option->is_hidden,
                        'position'          => $option->position,
                    ) );
                }
            }
        }

        return true;
    }

    /**
     * Check if product has addons
     */
    public function product_has_addons( $product_id ) {
        $addons = $this->get_product_addons( $product_id );
        return ! empty( $addons );
    }
}
