<?php
/**
 * Import/Export Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Import_Export {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_upad_export_addons', array( $this, 'export_addons' ) );
        add_action( 'wp_ajax_upad_import_addons', array( $this, 'import_addons' ) );
    }

    /**
     * Export addons to JSON
     */
    public function export_addons() {
        check_ajax_referer( 'upad_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-product-addons' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;

        global $wpdb;

        $where = '';
        if ( $product_id ) {
            $where = $wpdb->prepare( "WHERE product_id = %d", $product_id );
        } elseif ( $group_id ) {
            $where = $wpdb->prepare( "WHERE group_id = %d", $group_id );
        } else {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'unstoppable-product-addons' ) ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table requires direct query; $where is prepared above.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe prefix + constant, $where is prepared.
        $addons = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}upad_addons {$where} ORDER BY position" );

        $export_data = array(
            'version' => UPAD_VERSION,
            'addons'  => array(),
        );

        foreach ( $addons as $addon ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
            $options = $wpdb->get_results( $wpdb->prepare(
                "SELECT label, price, price_type, is_default, is_hidden, position
                 FROM {$wpdb->prefix}upad_addon_options WHERE addon_id = %d ORDER BY position",
                $addon->id
            ) );

            $export_data['addons'][] = array(
                'addon_type'   => $addon->addon_type,
                'title'        => $addon->title,
                'title_format' => $addon->title_format,
                'description'  => $addon->description,
                'required'     => (int) $addon->required,
                'position'     => (int) $addon->position,
                'settings'     => maybe_unserialize( $addon->settings ),
                'enabled'      => (int) $addon->enabled,
                'options'      => $options,
            );
        }

        wp_send_json_success( array(
            'data' => base64_encode( json_encode( $export_data ) ),
        ) );
    }

    /**
     * Import addons from JSON
     */
    public function import_addons() {
        check_ajax_referer( 'upad_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-product-addons' ) ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is base64 decoded and JSON parsed, not used directly.
        $import_data = isset( $_POST['import_data'] ) ? wp_unslash( $_POST['import_data'] ) : '';

        if ( empty( $import_data ) ) {
            wp_send_json_error( array( 'message' => __( 'No import data provided.', 'unstoppable-product-addons' ) ) );
        }

        // Decode
        $decoded = json_decode( base64_decode( $import_data ), true );

        if ( ! $decoded || ! isset( $decoded['addons'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid import data format.', 'unstoppable-product-addons' ) ) );
        }

        global $wpdb;
        $imported = 0;

        foreach ( $decoded['addons'] as $addon_data ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
            $wpdb->insert(
                $wpdb->prefix . 'upad_addons',
                array(
                    'group_id'     => $group_id ?: null,
                    'product_id'   => $product_id ?: null,
                    'addon_type'   => sanitize_key( $addon_data['addon_type'] ),
                    'title'        => sanitize_text_field( $addon_data['title'] ),
                    'title_format' => sanitize_key( $addon_data['title_format'] ),
                    'description'  => wp_kses_post( $addon_data['description'] ),
                    'required'     => (int) $addon_data['required'],
                    'position'     => (int) $addon_data['position'],
                    'settings'     => maybe_serialize( $addon_data['settings'] ),
                    'enabled'      => (int) $addon_data['enabled'],
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
            );

            $addon_id = $wpdb->insert_id;

            // Insert options
            if ( ! empty( $addon_data['options'] ) && $addon_id ) {
                foreach ( $addon_data['options'] as $option ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
                    $wpdb->insert(
                        $wpdb->prefix . 'upad_addon_options',
                        array(
                            'addon_id'   => $addon_id,
                            'label'      => sanitize_text_field( $option['label'] ),
                            'price'      => isset( $option['price'] ) ? floatval( $option['price'] ) : null,
                            'price_type' => sanitize_key( $option['price_type'] ),
                            'is_default' => (int) $option['is_default'],
                            'is_hidden'  => (int) $option['is_hidden'],
                            'position'   => (int) $option['position'],
                        ),
                        array( '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
                    );
                }
            }

            $imported++;
        }

        wp_send_json_success( array(
            /* translators: %d: number of imported addons */
            'message' => sprintf( __( 'Successfully imported %d addon(s).', 'unstoppable-product-addons' ), $imported ),
        ) );
    }
}
