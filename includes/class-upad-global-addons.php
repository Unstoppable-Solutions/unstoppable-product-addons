<?php
/**
 * Global Add-ons Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Global_Addons {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        // Hooks initialized here
    }

    /**
     * Get all global addon groups
     */
    public function get_groups( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'enabled' => null,
            'orderby' => 'priority',
            'order'   => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, object cache not applicable.
        $sql = "SELECT * FROM {$wpdb->prefix}upad_global_groups WHERE 1=1";

        if ( $args['enabled'] !== null ) {
            $sql .= $wpdb->prepare( " AND enabled = %d", $args['enabled'] ? 1 : 0 );
        }

        // Orderby and order are from hardcoded defaults, not user input.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe identifier from defaults array.
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        $results = $wpdb->get_results( $sql );

        // Sanitize null values
        foreach ( $results as $key => $group ) {
            $results[ $key ]->name = $group->name ?? '';
            $results[ $key ]->priority = $group->priority ?? 10;
            $results[ $key ]->restrict_to_categories = $group->restrict_to_categories ?? '';
            $results[ $key ]->enabled = $group->enabled ?? 1;
        }

        return $results;
    }

    /**
     * Get a single group
     */
    public function get_group( $group_id ) {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_global_groups WHERE id = %d",
            $group_id
        ) );
    }

    /**
     * Create a new group
     */
    public function create_group( $data ) {
        global $wpdb;

        $defaults = array(
            'name'                   => '',
            'priority'               => 10,
            'restrict_to_categories' => '',
            'enabled'                => 1,
        );

        $data = wp_parse_args( $data, $defaults );

        if ( is_array( $data['restrict_to_categories'] ) ) {
            $data['restrict_to_categories'] = maybe_serialize( $data['restrict_to_categories'] );
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'upad_global_groups',
            array(
                'name'                   => sanitize_text_field( $data['name'] ),
                'priority'               => absint( $data['priority'] ),
                'restrict_to_categories' => $data['restrict_to_categories'],
                'enabled'                => $data['enabled'] ? 1 : 0,
            ),
            array( '%s', '%d', '%s', '%d' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to create group.', 'unstoppable-product-addons' ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a group
     */
    public function update_group( $group_id, $data ) {
        global $wpdb;

        $update_data = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $format[] = '%s';
        }

        if ( isset( $data['priority'] ) ) {
            $update_data['priority'] = absint( $data['priority'] );
            $format[] = '%d';
        }

        if ( isset( $data['restrict_to_categories'] ) ) {
            $update_data['restrict_to_categories'] = is_array( $data['restrict_to_categories'] ) 
                ? maybe_serialize( $data['restrict_to_categories'] ) 
                : $data['restrict_to_categories'];
            $format[] = '%s';
        }

        if ( isset( $data['enabled'] ) ) {
            $update_data['enabled'] = $data['enabled'] ? 1 : 0;
            $format[] = '%d';
        }

        if ( empty( $update_data ) ) {
            return true;
        }

        return $wpdb->update(
            $wpdb->prefix . 'upad_global_groups',
            $update_data,
            array( 'id' => $group_id ),
            $format,
            array( '%d' )
        );
    }

    /**
     * Delete a group and its addons
     */
    public function delete_group( $group_id ) {
        global $wpdb;

        // Delete addon options first
        $wpdb->query( $wpdb->prepare(
            "DELETE o FROM {$wpdb->prefix}upad_addon_options o
             INNER JOIN {$wpdb->prefix}upad_addons a ON o.addon_id = a.id
             WHERE a.group_id = %d",
            $group_id
        ) );

        // Delete addons
        $wpdb->delete(
            $wpdb->prefix . 'upad_addons',
            array( 'group_id' => $group_id ),
            array( '%d' )
        );

        // Delete group
        return $wpdb->delete(
            $wpdb->prefix . 'upad_global_groups',
            array( 'id' => $group_id ),
            array( '%d' )
        );
    }

    /**
     * Get addons for a group
     */
    public function get_group_addons( $group_id ) {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addons 
             WHERE group_id = %d AND enabled = 1
             ORDER BY position ASC",
            $group_id
        ) );

        // Sanitize null values
        foreach ( $results as $key => $addon ) {
            $results[ $key ]->title = $addon->title ?? '';
            $results[ $key ]->description = $addon->description ?? '';
            $results[ $key ]->addon_type = $addon->addon_type ?? 'multiple_choice';
            $results[ $key ]->title_format = $addon->title_format ?? 'label';
            $results[ $key ]->settings = $addon->settings ?? '';
            $results[ $key ]->required = $addon->required ?? 0;
            $results[ $key ]->enabled = $addon->enabled ?? 1;
        }

        return $results;
    }

    /**
     * Get global addons applicable to a product
     */
    public function get_addons_for_product( $product ) {
        if ( is_numeric( $product ) ) {
            $product = wc_get_product( $product );
        }

        if ( ! $product ) {
            return array();
        }

        // Check if product excludes global addons
        if ( 'no' === get_post_meta( $product->get_id(), '_upad_use_global_addons', true ) ) {
            return array();
        }

        $product_categories = $product->get_category_ids();
        $groups = $this->get_groups( array( 'enabled' => true ) );
        $addons = array();

        foreach ( $groups as $group ) {
            $restricted_categories = maybe_unserialize( $group->restrict_to_categories ?? '' );

            // If no category restriction, or product is in restricted categories
            if ( empty( $restricted_categories ) || 
                 ( is_array( $restricted_categories ) && array_intersect( $product_categories, $restricted_categories ) ) ) {
                
                $group_addons = $this->get_group_addons( $group->id );
                foreach ( $group_addons as $addon ) {
                    $addon->options = $this->get_addon_options( $addon->id );
                    $settings = maybe_unserialize( $addon->settings ?? '' );
                    $addon->settings = is_array( $settings ) ? $settings : array();
                    $addon->source = 'global';
                    $addon->group_name = $group->name ?? '';
                    $addons[] = $addon;
                }
            }
        }

        return $addons;
    }

    /**
     * Get options for an addon
     */
    public function get_addon_options( $addon_id ) {
        global $wpdb;

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
     * Duplicate a group
     */
    public function duplicate_group( $group_id ) {
        $group = $this->get_group( $group_id );
        if ( ! $group ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'unstoppable-product-addons' ) );
        }

        // Create new group
        $new_group_id = $this->create_group( array(
            'name'                   => $group->name . ' (Copy)',
            'priority'               => $group->priority,
            'restrict_to_categories' => $group->restrict_to_categories,
            'enabled'                => 0, // Disabled by default
        ) );

        if ( is_wp_error( $new_group_id ) ) {
            return $new_group_id;
        }

        // Duplicate addons
        global $wpdb;
        $addons = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addons WHERE group_id = %d",
            $group_id
        ) );

        foreach ( $addons as $addon ) {
            $new_addon_id = $wpdb->insert(
                $wpdb->prefix . 'upad_addons',
                array(
                    'group_id'     => $new_group_id,
                    'product_id'   => null,
                    'addon_type'   => $addon->addon_type,
                    'title'        => $addon->title,
                    'title_format' => $addon->title_format,
                    'description'  => $addon->description,
                    'required'     => $addon->required,
                    'position'     => $addon->position,
                    'settings'     => $addon->settings,
                    'enabled'      => $addon->enabled,
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
            );

            if ( $new_addon_id ) {
                $new_addon_id = $wpdb->insert_id;

                // Duplicate options
                $options = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE addon_id = %d",
                    $addon->id
                ) );

                foreach ( $options as $option ) {
                    $wpdb->insert(
                        $wpdb->prefix . 'upad_addon_options',
                        array(
                            'addon_id'          => $new_addon_id,
                            'label'             => $option->label,
                            'price'             => $option->price,
                            'price_type'        => $option->price_type,
                            'image_id'          => $option->image_id,
                            'linked_product_id' => $option->linked_product_id,
                            'is_default'        => $option->is_default,
                            'is_hidden'         => $option->is_hidden,
                            'position'          => $option->position,
                        ),
                        array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
                    );
                }
            }
        }

        return $new_group_id;
    }
}
