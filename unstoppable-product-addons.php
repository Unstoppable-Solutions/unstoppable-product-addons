<?php
/**
 * Plugin Name: Unstoppable Product Addons
 * Plugin URI: https://unstoppablesolutions.app/product-addons/
 * Description: Create custom product options for WooCommerce. Add text fields, dropdowns, checkboxes, file uploads, date pickers, and more to any product.
 * Version: 1.0.0
 * Author: Unstoppable Solutions
 * Author URI: https://unstoppablesolutions.app/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unstoppable-product-addons
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.4
 *
 * @package Unstoppable_Product_Addons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WCCA_VERSION', '1.0.0' );
define( 'WCCA_PLUGIN_FILE', __FILE__ );
define( 'WCCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCCA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // unstoppable-product-addons/unstoppable-product-addons.php

// Legacy constant aliases for backwards compatibility with includes
define( 'WCOS_VERSION', WCCA_VERSION );
define( 'WCOS_PLUGIN_FILE', WCCA_PLUGIN_FILE );
define( 'WCOS_PLUGIN_DIR', WCCA_PLUGIN_DIR );
define( 'WCOS_PLUGIN_URL', WCCA_PLUGIN_URL );
define( 'WCOS_PLUGIN_BASENAME', WCCA_PLUGIN_BASENAME );

/*
 * ============================================================================
 * UNSTOPPABLE OPTIONS STUDIO - SHARED SHELL SYSTEM
 * ============================================================================
 * 
 * This code creates the "Options Studio" parent menu that all Unstoppable Solutions
 * plugins share. Each plugin includes this same code block - the first plugin
 * to load creates the shell, others just add their submenus.
 * ============================================================================
 */

if ( ! function_exists( 'unstoppable_options_studio_init' ) ) {
    /**
     * Initialize the Options Studio shell menu.
     * This function is shared across all Unstoppable Solutions plugins.
     * Only the first plugin to load will create the parent menu.
     */
    function unstoppable_options_studio_init() {
        add_action( 'admin_menu', 'unstoppable_options_studio_create_menu', 9 );
        add_action( 'admin_enqueue_scripts', 'unstoppable_options_studio_enqueue_styles' );
    }

    /**
     * Create the Options Studio parent menu.
     */
    function unstoppable_options_studio_create_menu() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Check if parent menu already exists (another Unstoppable plugin may have created it)
        global $menu;
        foreach ( $menu as $item ) {
            if ( isset( $item[2] ) && $item[2] === 'unstoppable-studio' ) {
                return; // Menu already exists
            }
        }

        // Create the parent menu
        add_menu_page(
            __( 'Options Studio', 'unstoppable-studio' ),
            __( 'Options Studio', 'unstoppable-studio' ),
            'manage_woocommerce',
            'unstoppable-studio',
            'unstoppable_options_studio_dashboard',
            'dashicons-screenoptions',
            56 // Position after WooCommerce
        );

        // Add dashboard as first submenu (replaces the duplicate parent link)
        add_submenu_page(
            'unstoppable-studio',
            __( 'Dashboard', 'unstoppable-studio' ),
            __( 'Dashboard', 'unstoppable-studio' ),
            'manage_woocommerce',
            'unstoppable-studio',
            'unstoppable_options_studio_dashboard'
        );
    }

    /**
     * Options Studio Dashboard page.
     * Shows overview of all installed Unstoppable Solutions plugins.
     */
    function unstoppable_options_studio_dashboard() {
        $plugins = apply_filters( 'unstoppable_studio_registered_plugins', array() );
        ?>
        <div class="wrap unstoppable-studio-dashboard">
            <h1><?php esc_html_e( 'Options Studio', 'unstoppable-studio' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Your suite of WooCommerce enhancement tools by Unstoppable Solutions.', 'unstoppable-studio' ); ?></p>
            
            <div class="unstoppable-studio-plugins" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
                <?php if ( empty( $plugins ) ) : ?>
                    <p><?php esc_html_e( 'No plugins registered yet.', 'unstoppable-studio' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $plugins as $plugin ) : ?>
                        <div class="unstoppable-plugin-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; width: 300px;">
                            <h3 style="margin-top: 0;"><?php echo esc_html( $plugin['name'] ); ?></h3>
                            <p style="color: #666;"><?php echo esc_html( $plugin['description'] ); ?></p>
                            <?php if ( ! empty( $plugin['menu_slug'] ) ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $plugin['menu_slug'] ) ); ?>" class="button button-primary">
                                    <?php esc_html_e( 'Open', 'unstoppable-studio' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="unstoppable-studio-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
                <p>
                    <a href="https://unstoppablesolutions.app" target="_blank"><?php esc_html_e( 'Visit Unstoppable Solutions', 'unstoppable-studio' ); ?></a> | 
                    <a href="https://unstoppablesolutions.app/support" target="_blank"><?php esc_html_e( 'Get Support', 'unstoppable-studio' ); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin styles for Options Studio menu icon.
     * Uses wp_add_inline_style instead of inline <style> per WordPress.org guidelines.
     */
    function unstoppable_options_studio_enqueue_styles() {
        // Register an empty stylesheet handle for inline styles
        wp_register_style( 'unstoppable-studio-shell', false );
        wp_enqueue_style( 'unstoppable-studio-shell' );

        $css = '
            #adminmenu .toplevel_page_unstoppable-studio .wp-menu-image:before {
                color: #f0b849;
            }
            #adminmenu .toplevel_page_unstoppable-studio:hover .wp-menu-image:before,
            #adminmenu .toplevel_page_unstoppable-studio.current .wp-menu-image:before {
                color: #fff;
            }
        ';

        wp_add_inline_style( 'unstoppable-studio-shell', $css );
    }
}

// Initialize the Options Studio shell
unstoppable_options_studio_init();

/*
 * ============================================================================
 * END SHARED SHELL SYSTEM
 * ============================================================================
 */


/**
 * Main WC_Custom_Addons Class
 *
 * @since 1.0.0
 */
final class WC_Custom_Addons {

    /**
     * Single instance of the class.
     *
     * @var WC_Custom_Addons
     */
    protected static $_instance = null;

    /**
     * Main Instance.
     *
     * @return WC_Custom_Addons
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook( WCCA_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( WCCA_PLUGIN_FILE, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
        
        // Register this plugin with Options Studio dashboard
        add_filter( 'unstoppable_studio_registered_plugins', array( $this, 'register_with_studio' ) );
    }

    /**
     * Register this plugin with the Options Studio dashboard.
     *
     * @param array $plugins Registered plugins.
     * @return array
     */
    public function register_with_studio( $plugins ) {
        $plugins['wcca-addons'] = array(
            'name'        => __( 'Product Addons', 'wc-custom-addons' ),
            'description' => __( 'Add custom options, file uploads, and inventory-tracked add-ons to your products.', 'wc-custom-addons' ),
            'menu_slug'   => 'wcca-addons',
            'version'     => WCCA_VERSION,
        );
        return $plugins;
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Load text domain
        load_plugin_textdomain( 'wc-custom-addons', false, dirname( WCCA_PLUGIN_BASENAME ) . '/languages' );

        // Include required files
        $this->includes();

        // Initialize components
        $this->init_components();
    }

    /**
     * Include required files.
     */
    private function includes() {
        // Core classes
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-install.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-addon-types.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-global-addons.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-product-addons.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-cart.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-order.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-display.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-ajax.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-file-upload.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-inventory.php';
        require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-import-export.php';

        // Admin classes
        if ( is_admin() ) {
            require_once WCCA_PLUGIN_DIR . 'includes/admin/class-wcos-admin.php';
            require_once WCCA_PLUGIN_DIR . 'includes/admin/class-wcos-admin-global-addons.php';
            require_once WCCA_PLUGIN_DIR . 'includes/admin/class-wcos-admin-product-addons.php';
            require_once WCCA_PLUGIN_DIR . 'includes/admin/class-wcos-admin-settings.php';
            require_once WCCA_PLUGIN_DIR . 'includes/admin/class-wcos-admin-migrate.php';
        }

        // REST API
        require_once WCCA_PLUGIN_DIR . 'includes/api/class-wcos-rest-api.php';
    }

    /**
     * Initialize plugin components.
     */
    private function init_components() {
        // Initialize addon types
        WCOS_Addon_Types::instance();
        
        // Initialize global and product addons
        WCOS_Global_Addons::instance();
        WCOS_Product_Addons::instance();
        
        // Initialize all components (no license required in free version)
        WCOS_Cart::instance();
        WCOS_Order::instance();
        WCOS_Display::instance();
        WCOS_File_Upload::instance();
        WCOS_Inventory::instance();
        WCOS_Import_Export::instance();
        WCOS_REST_API::instance();
        
        // Initialize AJAX for admin
        WCOS_Ajax::instance();

        if ( is_admin() ) {
            WCOS_Admin::instance();
            WCOS_Admin_Settings::instance();
            WCOS_Admin_Global_Addons::instance();
            WCOS_Admin_Product_Addons::instance();
            
            // Initialize migration tool
            WCOS_Admin_Migrate::instance();
        }
    }

    /**
     * Activation hook.
     */
    public function activate() {
        // Load install class if not already loaded
        if ( ! class_exists( 'WCOS_Install' ) ) {
            require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-install.php';
        }
        WCOS_Install::activate();

        // Update version
        update_option( 'wcca_version', WCCA_VERSION );

        // Clear cache
        wp_cache_flush();
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook.
     */
    public function deactivate() {
        if ( ! class_exists( 'WCOS_Install' ) ) {
            require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-install.php';
        }
        WCOS_Install::deactivate();

        // Clear license scheduled events
        if ( ! class_exists( 'WCOS_License' ) ) {
            require_once WCCA_PLUGIN_DIR . 'includes/class-wcos-license.php';
        }
        WCOS_License::clear_scheduled_events();

        flush_rewrite_rules();
    }

    /**
     * Declare HPOS compatibility.
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCCA_PLUGIN_FILE, true );
        }
    }

    /**
     * Admin notice for missing WooCommerce.
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Unstoppable Product Addons requires WooCommerce to be installed and active.', 'wc-custom-addons' ); ?></p>
        </div>
        <?php
    }

    /**
     * Get plugin URL.
     *
     * @return string
     */
    public function plugin_url() {
        return WCCA_PLUGIN_URL;
    }

    /**
     * Get plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return WCCA_PLUGIN_DIR;
    }
}

/**
 * Main instance of WC_Custom_Addons.
 *
 * @return WC_Custom_Addons
 */
function WCCA() {
    return WC_Custom_Addons::instance();
}

// Legacy function alias
function WCOS() {
    return WCCA();
}

// Initialize
WCCA();
