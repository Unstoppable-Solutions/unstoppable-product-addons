<?php
/**
 * Plugin Name:          Unstoppable Product Addons for WooCommerce
 * Plugin URI:           https://unstoppablesolutions.app/plugins/unstoppable-product-addons/
 * Description:          Create custom product options for WooCommerce. Add text fields, dropdowns, checkboxes, file uploads, date pickers, and more to any product.
 * Version:              1.0.1
 * Author:               Unstoppable Solutions
 * Author URI:           https://unstoppablesolutions.app
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          unstoppable-product-addons
 * Domain Path:          /languages
 * Requires at least:    6.5
 * Tested up to:         7.1
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      11.2
 *
 * @package Unstoppable_Product_Addons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants using UPAD_ prefix (not wc/wp/woo).
define( 'UPAD_VERSION', '1.0.1' );
define( 'UPAD_PLUGIN_FILE', __FILE__ );
define( 'UPAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UPAD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*
 * ============================================================================
 * UNSTOPPABLE OPTIONS STUDIO - SHARED SHELL (v1.2.0)
 * ============================================================================
 * Shared by every Unstoppable Solutions plugin. The first plugin to load
 * defines these functions; the function_exists() guard makes the others skip
 * them. Uses the host plugin's text domain (unstoppable-product-addons).
 * ============================================================================
 */

if ( ! function_exists( 'unstoppable_options_studio_init' ) ) {

    /**
     * Register the shell hooks.
     */
    function unstoppable_options_studio_init() {
        add_action( 'admin_menu', 'unstoppable_options_studio_create_menu', 9 );
        add_action( 'admin_enqueue_scripts', 'unstoppable_options_studio_admin_css' );
        add_action( 'admin_bar_menu', 'unstoppable_options_studio_admin_bar', 100 );
        // The admin bar appears in the admin and on the front end.
        add_action( 'admin_enqueue_scripts', 'unstoppable_options_studio_admin_bar_css' );
        add_action( 'wp_enqueue_scripts', 'unstoppable_options_studio_admin_bar_css' );
    }

    /**
     * Create the Options Studio parent menu and its Dashboard page.
     */
    function unstoppable_options_studio_create_menu() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // An older copy of the shell in another plugin may already have created it.
        global $menu;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'unstoppable-studio' === $item[2] ) {
                    return;
                }
            }
        }

        add_menu_page(
            esc_html__( 'Options Studio', 'unstoppable-product-addons' ),
            esc_html__( 'Options Studio', 'unstoppable-product-addons' ),
            'manage_woocommerce',
            'unstoppable-studio',
            'unstoppable_options_studio_dashboard',
            'dashicons-screenoptions',
            56
        );

        add_submenu_page(
            'unstoppable-studio',
            esc_html__( 'Dashboard', 'unstoppable-product-addons' ),
            esc_html__( 'Dashboard', 'unstoppable-product-addons' ),
            'manage_woocommerce',
            'unstoppable-studio',
            'unstoppable_options_studio_dashboard'
        );
    }

    /**
     * Render the Options Studio Dashboard.
     */
    function unstoppable_options_studio_dashboard() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'unstoppable-product-addons' ) );
        }

        $plugins = apply_filters( 'unstoppable_studio_registered_plugins', array() );
        ?>
        <div class="wrap unstoppable-studio-dashboard">
            <h1><?php esc_html_e( 'Options Studio', 'unstoppable-product-addons' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Your suite of WooCommerce tools by Unstoppable Solutions.', 'unstoppable-product-addons' ); ?></p>

            <div class="unstoppable-studio-plugins">
                <?php if ( empty( $plugins ) || ! is_array( $plugins ) ) : ?>
                    <p><?php esc_html_e( 'No plugins registered yet.', 'unstoppable-product-addons' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $plugins as $plugin ) : ?>
                        <div class="unstoppable-plugin-card">
                            <h3><?php echo esc_html( isset( $plugin['name'] ) ? $plugin['name'] : '' ); ?></h3>
                            <p><?php echo esc_html( isset( $plugin['description'] ) ? $plugin['description'] : '' ); ?></p>
                            <?php if ( ! empty( $plugin['menu_slug'] ) ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . sanitize_key( $plugin['menu_slug'] ) ) ); ?>" class="button button-primary">
                                    <?php esc_html_e( 'Open', 'unstoppable-product-addons' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="unstoppable-studio-footer">
                <p>
                    <a href="<?php echo esc_url( 'https://unstoppablesolutions.app' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visit Unstoppable Solutions', 'unstoppable-product-addons' ); ?></a>
                    |
                    <a href="<?php echo esc_url( 'https://unstoppablesolutions.app/support' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Support', 'unstoppable-product-addons' ); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Admin styles: gold menu icon and Dashboard cards.
     *
     * An empty registered handle plus wp_add_inline_style() is the supported
     * way to attach small generated CSS. Never print a <style> tag.
     */
    function unstoppable_options_studio_admin_css() {
        wp_register_style( 'unstoppable-studio-admin', false, array(), '1.2.0' );
        wp_enqueue_style( 'unstoppable-studio-admin' );

        $css = '
            #adminmenu .toplevel_page_unstoppable-studio .wp-menu-image:before { color: #f0b849; }
            #adminmenu .toplevel_page_unstoppable-studio:hover .wp-menu-image:before,
            #adminmenu .toplevel_page_unstoppable-studio.current .wp-menu-image:before { color: #fff; }
            .unstoppable-studio-plugins { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
            .unstoppable-plugin-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; width: 300px; max-width: 100%; box-sizing: border-box; }
            .unstoppable-plugin-card h3 { margin-top: 0; }
            .unstoppable-plugin-card p { color: #646970; }
            .unstoppable-studio-footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccd0d4; }
        ';

        wp_add_inline_style( 'unstoppable-studio-admin', $css );
    }

    /**
     * Add Options Studio to the admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
     */
    function unstoppable_options_studio_admin_bar( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $wp_admin_bar->add_node(
            array(
                'id'    => 'unstoppable-studio',
                'title' => '<span class="ab-icon dashicons dashicons-screenoptions" aria-hidden="true"></span>' . esc_html__( 'Options Studio', 'unstoppable-product-addons' ),
                'href'  => admin_url( 'admin.php?page=unstoppable-studio' ),
                'meta'  => array(
                    'title' => esc_attr__( 'Options Studio Dashboard', 'unstoppable-product-addons' ),
                ),
            )
        );

        $plugins = apply_filters( 'unstoppable_studio_registered_plugins', array() );

        if ( ! is_array( $plugins ) ) {
            return;
        }

        foreach ( $plugins as $plugin_id => $plugin ) {
            if ( empty( $plugin['menu_slug'] ) || empty( $plugin['name'] ) ) {
                continue;
            }

            $wp_admin_bar->add_node(
                array(
                    'id'     => 'unstoppable-studio-' . sanitize_key( $plugin_id ),
                    'parent' => 'unstoppable-studio',
                    'title'  => esc_html( $plugin['name'] ),
                    'href'   => admin_url( 'admin.php?page=' . sanitize_key( $plugin['menu_slug'] ) ),
                )
            );
        }
    }

    /**
     * Admin bar icon styles, enqueued in the admin and on the front end.
     */
    function unstoppable_options_studio_admin_bar_css() {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        wp_register_style( 'unstoppable-studio-adminbar', false, array( 'admin-bar' ), '1.2.0' );
        wp_enqueue_style( 'unstoppable-studio-adminbar' );

        $css = '
            #wpadminbar #wp-admin-bar-unstoppable-studio > .ab-item .ab-icon:before { content: "\f180"; top: 2px; }
            #wpadminbar #wp-admin-bar-unstoppable-studio > .ab-item .ab-icon { margin-right: 6px; }
            #wpadminbar #wp-admin-bar-unstoppable-studio > .ab-item { color: #f0b849; }
            #wpadminbar #wp-admin-bar-unstoppable-studio:hover > .ab-item { color: #fff; background: #32373c; }
        ';

        wp_add_inline_style( 'unstoppable-studio-adminbar', $css );
    }

    unstoppable_options_studio_init();
}

/*
 * ============================================================================
 * END SHARED SHELL
 * ============================================================================
 */

/**
 * Main plugin class.
 */
final class UPAD_Plugin {

    /**
     * Singleton instance.
     *
     * @var UPAD_Plugin
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return UPAD_Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        // Core classes.
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-install.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-addon-types.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-global-addons.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-product-addons.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-display.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-cart.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-order.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-ajax.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-file-upload.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-inventory.php';
        require_once UPAD_PLUGIN_DIR . 'includes/class-upad-import-export.php';

        // Admin classes.
        if ( is_admin() ) {
            require_once UPAD_PLUGIN_DIR . 'includes/admin/class-upad-admin.php';
            require_once UPAD_PLUGIN_DIR . 'includes/admin/class-upad-admin-settings.php';
            require_once UPAD_PLUGIN_DIR . 'includes/admin/class-upad-admin-global-addons.php';
            require_once UPAD_PLUGIN_DIR . 'includes/admin/class-upad-admin-product-addons.php';
            require_once UPAD_PLUGIN_DIR . 'includes/admin/class-upad-admin-migrate.php';
        }

        // REST API.
        require_once UPAD_PLUGIN_DIR . 'includes/api/class-upad-rest-api.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook( UPAD_PLUGIN_FILE, array( 'UPAD_Install', 'activate' ) );
        register_deactivation_hook( UPAD_PLUGIN_FILE, array( 'UPAD_Install', 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'init' ), 10 );
        add_filter( 'unstoppable_studio_registered_plugins', array( $this, 'register_with_studio' ) );
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Initialize classes.
        UPAD_Addon_Types::instance();
        UPAD_Global_Addons::instance();
        UPAD_Product_Addons::instance();
        UPAD_Display::instance();
        UPAD_Cart::instance();
        UPAD_Order::instance();
        UPAD_Ajax::instance();
        UPAD_File_Upload::instance();
        UPAD_Inventory::instance();
        UPAD_Import_Export::instance();
        UPAD_REST_API::instance();

        if ( is_admin() ) {
            UPAD_Admin::instance();
        }

        // Declare HPOS compatibility.
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }

    /**
     * Register with Options Studio Dashboard.
     *
     * @param array $plugins Registered plugins.
     * @return array
     */
    public function register_with_studio( $plugins ) {
        $plugins['unstoppable-product-addons'] = array(
            'name'        => __( 'Product Addons', 'unstoppable-product-addons' ),
            'description' => __( 'Add custom option fields to WooCommerce products.', 'unstoppable-product-addons' ),
            'menu_slug'   => 'upad-settings',
            'version'     => UPAD_VERSION,
        );
        return $plugins;
    }

    /**
     * Show notice when WooCommerce is not active.
     */
    public function woocommerce_missing_notice() {
        $screen = get_current_screen();
        if ( $screen && false !== strpos( $screen->id, 'upad' ) ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e( 'Unstoppable Product Addons', 'unstoppable-product-addons' ); ?></strong>
                    <?php esc_html_e( 'requires WooCommerce to be installed and active.', 'unstoppable-product-addons' ); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Declare compatibility with WooCommerce HPOS.
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', UPAD_PLUGIN_FILE, true );
        }
    }
}

/**
 * Main instance of the plugin.
 *
 * @return UPAD_Plugin
 */
function upad() {
    return UPAD_Plugin::instance();
}

// Initialize the plugin.
upad();
