<?php
/**
 * Admin Settings
 *
 * Handles plugin settings including license management.
 *
 * @package WC_Options_Studio
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class UPAD_Admin_Settings
 *
 * @since 1.0.0
 */
class UPAD_Admin_Settings {

    /**
     * Single instance of the class.
     *
     * @var UPAD_Admin_Settings
     */
    protected static $_instance = null;

    /**
     * Main Instance.
     *
     * @return UPAD_Admin_Settings
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
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        // File Upload Settings
        register_setting( 'upad_settings', 'upad_file_upload_enabled' );
        register_setting( 'upad_settings', 'upad_allowed_file_types' );
        register_setting( 'upad_settings', 'upad_max_file_size' );
        register_setting( 'upad_settings', 'upad_secure_uploads' );

        // Display Settings
        register_setting( 'upad_settings', 'upad_display_option_prices' );
        register_setting( 'upad_settings', 'upad_display_totals' );

        // Inventory Settings
        register_setting( 'upad_settings', 'upad_enable_inventory_tracking' );
        register_setting( 'upad_settings', 'upad_add_as_line_items' );

        // General Settings
        register_setting( 'upad_settings', 'upad_inherit_global_addons' );
        register_setting( 'upad_settings', 'upad_delete_data_on_uninstall' );
    }

    /**
     * Get settings sections.
     *
     * @return array
     */
    public function get_sections() {
        return array(
            'license'    => __( 'License', 'wc-options-studio' ),
            'general'    => __( 'General', 'wc-options-studio' ),
            'file'       => __( 'File Upload', 'wc-options-studio' ),
            'display'    => __( 'Display', 'wc-options-studio' ),
            'inventory'  => __( 'Inventory', 'wc-options-studio' ),
        );
    }

    /**
     * Get settings fields.
     *
     * @return array
     */
    public function get_fields() {
        return array(
            'general' => array(
                array(
                    'id'      => 'upad_inherit_global_addons',
                    'title'   => __( 'Inherit Global Add-ons', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'yes',
                    'desc'    => __( 'Products inherit global add-ons by default.', 'wc-options-studio' ),
                ),
                array(
                    'id'      => 'upad_delete_data_on_uninstall',
                    'title'   => __( 'Delete Data on Uninstall', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'no',
                    'desc'    => __( 'Remove all plugin data when uninstalled.', 'wc-options-studio' ),
                ),
            ),
            'file' => array(
                array(
                    'id'      => 'upad_file_upload_enabled',
                    'title'   => __( 'Enable File Uploads', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'yes',
                    'desc'    => __( 'Allow file upload add-ons.', 'wc-options-studio' ),
                ),
                array(
                    'id'      => 'upad_allowed_file_types',
                    'title'   => __( 'Default Allowed File Types', 'wc-options-studio' ),
                    'type'    => 'text',
                    'default' => 'jpg,jpeg,png,gif,pdf,doc,docx',
                    'desc'    => __( 'Comma-separated list of allowed extensions.', 'wc-options-studio' ),
                ),
                array(
                    'id'      => 'upad_max_file_size',
                    'title'   => __( 'Default Max File Size (MB)', 'wc-options-studio' ),
                    'type'    => 'number',
                    'default' => 5,
                    'min'     => 0.1,
                    'max'     => 100,
                    'step'    => 0.1,
                    'desc'    => __( 'Maximum file upload size in megabytes.', 'wc-options-studio' ),
                ),
                array(
                    'id'      => 'upad_secure_uploads',
                    'title'   => __( 'Secure Uploads', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'yes',
                    'desc'    => __( 'Store uploads in protected directories.', 'wc-options-studio' ),
                ),
            ),
            'display' => array(
                array(
                    'id'      => 'upad_display_option_prices',
                    'title'   => __( 'Display Option Prices', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'yes',
                    'desc'    => __( 'Show prices next to add-on options.', 'wc-options-studio' ),
                ),
                array(
                    'id'      => 'upad_display_totals',
                    'title'   => __( 'Display Totals', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'yes',
                    'desc'    => __( 'Show options total and grand total on product pages.', 'wc-options-studio' ),
                ),
            ),
            'inventory' => array(
                array(
                    'id'      => 'upad_enable_inventory_tracking',
                    'title'   => __( 'Inventory Tracking', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'no',
                    'desc'    => __( 'Track stock for options linked to products.', 'wc-options-studio' ),
                ),
                array(
                    'id'      => 'upad_add_as_line_items',
                    'title'   => __( 'Add as Line Items', 'wc-options-studio' ),
                    'type'    => 'checkbox',
                    'default' => 'no',
                    'desc'    => __( 'Add linked products as separate order line items (for customs/shipping).', 'wc-options-studio' ),
                ),
            ),
        );
    }
}
