<?php
/**
 * AJAX Handler
 *
 * Handles all AJAX requests for the plugin.
 *
 * @package WC_Options_Studio
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class UPAD_Ajax
 *
 * @since 1.0.0
 */
class UPAD_Ajax {

    /**
     * Single instance of the class.
     *
     * @var UPAD_Ajax
     */
    protected static $_instance = null;

    /**
     * Main Instance.
     *
     * @return UPAD_Ajax
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        // Admin-only AJAX actions
        add_action( 'wp_ajax_upad_save_addon', array( $this, 'save_addon' ) );
        add_action( 'wp_ajax_upad_delete_addon', array( $this, 'delete_addon' ) );
        add_action( 'wp_ajax_upad_duplicate_addon', array( $this, 'duplicate_addon' ) );
        add_action( 'wp_ajax_upad_reorder_addons', array( $this, 'reorder_addons' ) );
        add_action( 'wp_ajax_upad_get_addon', array( $this, 'get_addon' ) );
        add_action( 'wp_ajax_upad_get_group', array( $this, 'get_group' ) );
        add_action( 'wp_ajax_upad_search_products', array( $this, 'search_products' ) );
        
        // Frontend AJAX actions (with and without auth)
        add_action( 'wp_ajax_upad_calculate_price', array( $this, 'calculate_price' ) );
        add_action( 'wp_ajax_nopriv_upad_calculate_price', array( $this, 'calculate_price' ) );
    }

    /**
     * Verify admin request.
     *
     * @param string $nonce_action Nonce action name.
     * @param string $nonce_name   Nonce field name.
     * @return bool
     */
    private function verify_admin_request( $nonce_action = 'upad_admin_nonce', $nonce_name = 'nonce' ) {
        // Verify nonce
        if ( ! check_ajax_referer( $nonce_action, $nonce_name, false ) ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'unstoppable-product-addons' ) 
            ) );
            return false;
        }

        // Check capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'You do not have permission to perform this action.', 'unstoppable-product-addons' ) 
            ) );
            return false;
        }

        return true;
    }

    /**
     * Get group data.
     *
     * @since 1.0.9
     */
    public function get_group() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }
        // Nonce verified in verify_admin_request() above.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

        $group_id = isset( $_GET['group_id'] ) ? absint( wp_unslash( $_GET['group_id'] ) ) : 0;

        if ( ! $group_id ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Invalid group ID.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        $group = UPAD_Global_Addons::instance()->get_group( $group_id );

        if ( ! $group ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Group not found.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        // Parse categories safely
        $categories = maybe_unserialize( $group->restrict_to_categories ?? '' );
        if ( ! is_array( $categories ) ) {
            $categories = array();
        }
        // Sanitize category IDs
        $categories = array_map( 'absint', $categories );
        $categories = array_filter( $categories ); // Remove zeros

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success( array(
            'id'         => absint( $group->id ),
            'name'       => sanitize_text_field( $group->name ?? '' ),
            'priority'   => absint( $group->priority ?? 10 ),
            'enabled'    => absint( $group->enabled ?? 1 ),
            'categories' => array_map( 'strval', $categories ),
        ) );
    }

    /**
     * Save addon.
     *
     * @since 1.0.0
     */
    public function save_addon() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }

        // Nonce verified in verify_admin_request() above.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        global $wpdb;

        // Sanitize input.
        $addon_id   = isset( $_POST['addon_id'] ) ? absint( $_POST['addon_id'] ) : 0;
        $group_id   = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        // Validate addon type against whitelist
        $valid_types = array_keys( UPAD_Addon_Types::instance()->get_types() );
        $addon_type  = isset( $_POST['addon_type'] ) ? sanitize_key( $_POST['addon_type'] ) : 'multiple_choice';
        if ( ! in_array( $addon_type, $valid_types, true ) ) {
            $addon_type = 'multiple_choice';
        }

        // Validate title format against whitelist
        $valid_formats = array( 'label', 'heading', 'hide' );
        $title_format  = isset( $_POST['title_format'] ) ? sanitize_key( $_POST['title_format'] ) : 'label';
        if ( ! in_array( $title_format, $valid_formats, true ) ) {
            $title_format = 'label';
        }

        $data = array(
            'addon_type'   => $addon_type,
            'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
            'title_format' => $title_format,
            'description'  => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
            'required'     => isset( $_POST['required'] ) && sanitize_text_field( wp_unslash( $_POST['required'] ) ) ? 1 : 0,
            'enabled'      => isset( $_POST['enabled'] ) && sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) ? 1 : 0,
        );

        // Handle settings.
        $settings = array();
        if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
            $raw_settings = map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' );
            foreach ( $raw_settings as $key => $value ) {
                $settings[ sanitize_key( $key ) ] = $value;
            }
        }

        // Handle apply_to_variations (for variable products).
        if ( isset( $_POST['apply_to_variations'] ) && is_array( $_POST['apply_to_variations'] ) ) {
            $variation_ids = array_map( 'absint', wp_unslash( $_POST['apply_to_variations'] ) );
            $variation_ids = array_filter( $variation_ids ); // Remove zeros.
            if ( ! empty( $variation_ids ) ) {
                $settings['apply_to_variations'] = $variation_ids;
            }
        }

        // Handle conditional logic.
        if ( isset( $_POST['conditional_logic'] ) && is_array( $_POST['conditional_logic'] ) ) {
            $cond = map_deep( wp_unslash( $_POST['conditional_logic'] ), 'sanitize_text_field' );
            $conditional_logic = array(
                'enabled'  => ! empty( $cond['enabled'] ) ? 1 : 0,
                'action'   => isset( $cond['action'] ) && in_array( $cond['action'], array( 'show', 'hide' ) ) ? $cond['action'] : 'show',
                'relation' => isset( $cond['relation'] ) && in_array( $cond['relation'], array( 'all', 'any' ) ) ? $cond['relation'] : 'all',
                'rules'    => array(),
            );
            
            if ( ! empty( $cond['rules'] ) && is_array( $cond['rules'] ) ) {
                foreach ( $cond['rules'] as $rule ) {
                    if ( ! empty( $rule['addon_id'] ) ) {
                        $conditional_logic['rules'][] = array(
                            'addon_id' => absint( $rule['addon_id'] ),
                            'operator' => isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : 'is',
                            'value'    => isset( $rule['value'] ) ? sanitize_text_field( $rule['value'] ) : '',
                        );
                    }
                }
            }
            
            $settings['conditional_logic'] = $conditional_logic;
        }

        // Validate required fields
        if ( empty( $data['title'] ) ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Title is required.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        // Verify group or product exists
        if ( $group_id ) {
            $group = UPAD_Global_Addons::instance()->get_group( $group_id );
            if ( ! $group ) {
                wp_send_json_error( array( 
                    'message' => esc_html__( 'Invalid group.', 'unstoppable-product-addons' ) 
                ) );
                return;
            }
        } elseif ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                wp_send_json_error( array( 
                    'message' => esc_html__( 'Invalid product.', 'unstoppable-product-addons' ) 
                ) );
                return;
            }
        }

        $table_name = $wpdb->prefix . 'upad_addons';

        if ( $addon_id ) {
            // Verify addon exists before updating
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query; table name is safe prefix + constant.
            $existing = $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
                $wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE id = %d",
                    $addon_id
                )
            );

            if ( ! $existing ) {
                wp_send_json_error( array( 
                    'message' => esc_html__( 'Add-on not found.', 'unstoppable-product-addons' ) 
                ) );
                return;
            }

            // Update existing addon
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $wpdb->update(
                $table_name,
                array(
                    'addon_type'   => $data['addon_type'],
                    'title'        => $data['title'],
                    'title_format' => $data['title_format'],
                    'description'  => $data['description'],
                    'required'     => $data['required'],
                    'enabled'      => $data['enabled'],
                    'settings'     => maybe_serialize( $settings ),
                ),
                array( 'id' => $addon_id ),
                array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            // Get next position
            if ( $group_id ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query; table name is safe prefix + constant.
                $max_position = $wpdb->get_var(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
                    $wpdb->prepare(
                        "SELECT MAX(position) FROM {$table_name} WHERE group_id = %d",
                        $group_id
                    )
                );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query; table name is safe prefix + constant.
                $max_position = $wpdb->get_var(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
                    $wpdb->prepare(
                        "SELECT MAX(position) FROM {$table_name} WHERE product_id = %d",
                        $product_id
                    )
                );
            }
            $position = ( $max_position !== null ) ? absint( $max_position ) + 1 : 0;

            // Insert new addon
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
            $wpdb->insert(
                $table_name,
                array(
                    'group_id'     => $group_id ? $group_id : null,
                    'product_id'   => $product_id ? $product_id : null,
                    'addon_type'   => $data['addon_type'],
                    'title'        => $data['title'],
                    'title_format' => $data['title_format'],
                    'description'  => $data['description'],
                    'required'     => $data['required'],
                    'position'     => $position,
                    'settings'     => maybe_serialize( $settings ),
                    'enabled'      => $data['enabled'],
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
            );

            $addon_id = $wpdb->insert_id;

            if ( ! $addon_id ) {
                wp_send_json_error( array( 
                    'message' => esc_html__( 'Failed to create add-on. Please try again.', 'unstoppable-product-addons' ) 
                ) );
                return;
            }
        }

        // Handle options.
        if ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
            $this->save_addon_options( $addon_id, map_deep( wp_unslash( $_POST['options'] ), 'sanitize_text_field' ) );
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success( array(
            'message'  => esc_html__( 'Add-on saved successfully.', 'unstoppable-product-addons' ),
            'addon_id' => absint( $addon_id ),
        ) );
    }

    /**
     * Save addon options.
     *
     * @param int   $addon_id Addon ID.
     * @param array $options  Options data.
     * @since 1.0.0
     */
    private function save_addon_options( $addon_id, $options ) {
        global $wpdb;

        $addon_id = absint( $addon_id );
        if ( ! $addon_id ) {
            return;
        }

        $table_name = $wpdb->prefix . 'upad_addon_options';

        // Get existing option IDs.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query; table name is safe prefix + constant.
        $existing = $wpdb->get_col(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
            $wpdb->prepare( "SELECT id FROM {$table_name} WHERE addon_id = %d", $addon_id )
        );
        $existing = array_map( 'absint', $existing );

        $submitted_ids = array();

        // Validate price types whitelist
        $valid_price_types = array( 'flat', 'quantity', 'percentage' );

        foreach ( $options as $position => $option ) {
            $option_id = isset( $option['id'] ) && ! empty( $option['id'] ) ? absint( $option['id'] ) : 0;
            $label     = isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '';

            if ( empty( $label ) ) {
                continue;
            }

            // Validate price type
            $price_type = isset( $option['price_type'] ) ? sanitize_key( $option['price_type'] ) : 'flat';
            if ( ! in_array( $price_type, $valid_price_types, true ) ) {
                $price_type = 'flat';
            }

            $option_data = array(
                'addon_id'   => $addon_id,
                'label'      => $label,
                'price'      => isset( $option['price'] ) && $option['price'] !== '' ? floatval( $option['price'] ) : null,
                'price_type' => $price_type,
                'is_default' => isset( $option['is_default'] ) && $option['is_default'] ? 1 : 0,
                'is_hidden'  => 0,
                'position'   => absint( $position ),
            );

            if ( $option_id && in_array( $option_id, $existing, true ) ) {
                // Update existing
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
                $wpdb->update(
                    $table_name,
                    $option_data,
                    array( 'id' => $option_id ),
                    array( '%d', '%s', '%s', '%s', '%d', '%d', '%d' ),
                    array( '%d' )
                );
                $submitted_ids[] = $option_id;
            } else {
                // Insert new
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
                $wpdb->insert(
                    $table_name,
                    $option_data,
                    array( '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
                );
                if ( $wpdb->insert_id ) {
                    $submitted_ids[] = $wpdb->insert_id;
                }
            }
        }

        // Delete removed options
        $to_delete = array_diff( $existing, $submitted_ids );
        foreach ( $to_delete as $delete_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $wpdb->delete(
                $table_name,
                array( 'id' => absint( $delete_id ) ),
                array( '%d' )
            );
        }
    }

    /**
     * Get addon data.
     *
     * @since 1.0.0
     */
    public function get_addon() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }
        // Nonce verified in verify_admin_request() above.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

        $addon_id = isset( $_GET['addon_id'] ) ? absint( wp_unslash( $_GET['addon_id'] ) ) : 0;

        if ( ! $addon_id ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Invalid add-on ID.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $addon = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}upad_addons WHERE id = %d",
                $addon_id
            )
        );

        if ( ! $addon ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Add-on not found.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        // Sanitize output
        $addon->id          = absint( $addon->id );
        $addon->group_id    = absint( $addon->group_id ?? 0 );
        $addon->product_id  = absint( $addon->product_id ?? 0 );
        $addon->title       = sanitize_text_field( $addon->title ?? '' );
        $addon->description = wp_kses_post( $addon->description ?? '' );
        $addon->addon_type  = sanitize_key( $addon->addon_type ?? 'multiple_choice' );
        $addon->required    = absint( $addon->required ?? 0 );
        $addon->enabled     = absint( $addon->enabled ?? 1 );
        $addon->settings    = maybe_unserialize( $addon->settings ?? '' );
        
        // Sanitize title_format with whitelist validation
        $valid_formats = array( 'label', 'heading', 'hide' );
        $addon->title_format = isset( $addon->title_format ) && in_array( $addon->title_format, $valid_formats, true ) 
            ? $addon->title_format 
            : 'label';
        
        if ( ! is_array( $addon->settings ) ) {
            $addon->settings = array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $addon->options = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE addon_id = %d ORDER BY position",
                $addon_id
            )
        );

        // Sanitize options output
        foreach ( $addon->options as $key => $option ) {
            $addon->options[ $key ]->id         = absint( $option->id );
            $addon->options[ $key ]->label      = sanitize_text_field( $option->label ?? '' );
            $addon->options[ $key ]->price      = $option->price !== null ? floatval( $option->price ) : '';
            $addon->options[ $key ]->price_type = sanitize_key( $option->price_type ?? 'flat' );
            $addon->options[ $key ]->is_default = absint( $option->is_default ?? 0 );
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success( $addon );
    }

    /**
     * Delete addon.
     *
     * @since 1.0.0
     */
    public function delete_addon() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }
        // Nonce verified in verify_admin_request() above.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $addon_id = isset( $_POST['addon_id'] ) ? absint( wp_unslash( $_POST['addon_id'] ) ) : 0;

        if ( ! $addon_id ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Invalid add-on ID.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        global $wpdb;

        // Verify addon exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $existing = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}upad_addons WHERE id = %d",
                $addon_id
            )
        );

        if ( ! $existing ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Add-on not found.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        // Delete options first
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $wpdb->delete(
            $wpdb->prefix . 'upad_addon_options',
            array( 'addon_id' => $addon_id ),
            array( '%d' )
        );

        // Delete addon
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $wpdb->delete(
            $wpdb->prefix . 'upad_addons',
            array( 'id' => $addon_id ),
            array( '%d' )
        );

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success( array(
            'message' => esc_html__( 'Add-on deleted successfully.', 'unstoppable-product-addons' ),
        ) );
    }

    /**
     * Duplicate an addon.
     *
     * @since 1.6.0
     */
    public function duplicate_addon() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }
        // Nonce verified in verify_admin_request() above.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $addon_id = isset( $_POST['addon_id'] ) ? absint( wp_unslash( $_POST['addon_id'] ) ) : 0;

        if ( ! $addon_id ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Invalid add-on ID.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        global $wpdb;

        // Get the original addon
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $addon = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}upad_addons WHERE id = %d",
                $addon_id
            ),
            ARRAY_A
        );

        if ( ! $addon ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Add-on not found.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        // Get the original options
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $options = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}upad_addon_options WHERE addon_id = %d ORDER BY position ASC",
                $addon_id
            ),
            ARRAY_A
        );

        // Store original type for response
        $addon_type = $addon['addon_type'];
        $required = $addon['required'];
        $group_id = $addon['group_id'];
        $product_id = $addon['product_id'];

        // Get max position for this group/product
        if ( $group_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $max_position = $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
                $wpdb->prepare(
                    "SELECT MAX(position) FROM {$wpdb->prefix}upad_addons WHERE group_id = %d",
                    $group_id
                )
            );
        } elseif ( $product_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $max_position = $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant.
                $wpdb->prepare(
                    "SELECT MAX(position) FROM {$wpdb->prefix}upad_addons WHERE product_id = %d",
                    $product_id
                )
            );
        } else {
            $max_position = $addon['position'];
        }

        // Prepare new addon data
        unset( $addon['id'] );
        $new_title = $addon['title'] . ' ' . __( '(Copy)', 'unstoppable-product-addons' );
        $addon['title'] = $new_title;
        $addon['position'] = ( $max_position !== null ) ? absint( $max_position ) + 1 : 0;

        // Insert the new addon
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'upad_addons',
            $addon
        );

        if ( ! $inserted ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Failed to duplicate add-on.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        $new_addon_id = $wpdb->insert_id;

        // Duplicate options
        if ( ! empty( $options ) ) {
            foreach ( $options as $option ) {
                unset( $option['id'] );
                $option['addon_id'] = $new_addon_id;

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
                $wpdb->insert(
                    $wpdb->prefix . 'upad_addon_options',
                    $option
                );
            }
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success( array(
            'message'      => esc_html__( 'Add-on duplicated successfully.', 'unstoppable-product-addons' ),
            'new_addon_id' => $new_addon_id,
            'addon'        => array(
                'id'       => $new_addon_id,
                'title'    => $new_title,
                'type'     => $addon_type,
                'required' => $required,
            ),
        ) );
    }

    /**
     * Reorder addons.
     *
     * @since 1.6.10
     */
    public function reorder_addons() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }
        // Nonce verified in verify_admin_request() above.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        $order = isset( $_POST['order'] ) ? array_map( 'absint', wp_unslash( $_POST['order'] ) ) : array();

        if ( empty( $order ) ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'No order data provided.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'upad_addons';

        foreach ( $order as $position => $addon_id ) {
            if ( $addon_id > 0 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
                $wpdb->update(
                    $table_name,
                    array( 'position' => absint( $position ) ),
                    array( 'id' => absint( $addon_id ) ),
                    array( '%d' ),
                    array( '%d' )
                );
            }
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success( array(
            'message' => esc_html__( 'Order saved.', 'unstoppable-product-addons' ),
        ) );
    }

    /**
     * Search products.
     *
     * @since 1.0.0
     */
    public function search_products() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }
        // Nonce verified in verify_admin_request() above.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

        $term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
        
        if ( empty( $term ) ) {
            wp_send_json( array() );
            return;
        }

        $results = UPAD_Inventory::instance()->search_products( $term );
        
        // Sanitize results
        $sanitized = array();
        foreach ( $results as $result ) {
            $sanitized[] = array(
                'id'   => absint( $result['id'] ?? 0 ),
                'text' => sanitize_text_field( $result['text'] ?? '' ),
            );
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json( $sanitized );
    }

    /**
     * Calculate price.
     *
     * @since 1.0.0
     */
    public function calculate_price() {
        // Frontend AJAX action - nonce verification is optional but recommended.
        // phpcs:disable WordPress.Security.NonceVerification.Missing

        // Verify nonce for frontend (optional but recommended)
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
        
        if ( ! $product_id ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Invalid product.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error( array(
                'message' => esc_html__( 'Product not found.', 'unstoppable-product-addons' )
            ) );
            return;
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing

        wp_send_json_success( array(
            'base_price' => floatval( $product->get_price() ),
            'formatted'  => wp_kses_post( wc_price( $product->get_price() ) ),
        ) );
    }
}
