<?php
/**
 * Admin Main Class
 *
 * Implements the "One Plugin = One Menu Item" pattern with internal tabs.
 *
 * @package WC_Custom_Addons
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Main Class
 */
class UPAD_Admin {

    /**
     * Single instance of the class.
     *
     * @var UPAD_Admin
     */
    protected static $_instance = null;

    /**
     * Main Instance.
     *
     * @return UPAD_Admin
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
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'plugin_action_links_' . UPAD_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Get current tab.
     *
     * @return string Current tab slug.
     */
    private function get_current_tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab detection for navigation, no data processing.
        return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'addons';
    }

    /**
     * Get all available tabs.
     *
     * @return array Tabs configuration.
     */
    private function get_tabs() {
        return array(
            'addons' => array(
                'label' => __( 'Add-ons', 'unstoppable-product-addons' ),
                'icon'  => 'dashicons-plus-alt',
            ),
            'settings' => array(
                'label' => __( 'Settings', 'unstoppable-product-addons' ),
                'icon'  => 'dashicons-admin-generic',
            ),
            'migrate' => array(
                'label' => __( 'Migrate', 'unstoppable-product-addons' ),
                'icon'  => 'dashicons-migrate',
            ),
        );
    }

    /**
     * Add admin menu under Options Studio parent.
     * ONE menu item only - all sections use tabs.
     */
    public function admin_menu() {
        add_submenu_page(
            'unstoppable-studio',
            __( 'Product Addons', 'unstoppable-product-addons' ),
            __( 'Product Addons', 'unstoppable-product-addons' ),
            'manage_woocommerce',
            'upad-settings',
            array( $this, 'render_main_page' )
        );
    }

    /**
     * Render the main page with tab navigation.
     */
    public function render_main_page() {
        $current_tab = $this->get_current_tab();
        $tabs = $this->get_tabs();
        ?>
        <div class="wrap upad-wrap">
            <h1><?php esc_html_e( 'Unstoppable Product Addons', 'unstoppable-product-addons' ); ?></h1>
            
            <nav class="upad-tabs-nav">
                <?php foreach ( $tabs as $tab_id => $tab ) : 
                    $url = admin_url( 'admin.php?page=upad-settings&tab=' . $tab_id );
                    $is_active = $current_tab === $tab_id;
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" 
                       class="upad-tab-link <?php echo $is_active ? 'active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                        <?php echo esc_html( $tab['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <div class="upad-tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'migrate':
                        $this->render_migrate_tab();
                        break;
                    case 'addons':
                    default:
                        $this->render_addons_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Add-ons tab content.
     */
    public function render_addons_tab() {
        include UPAD_PLUGIN_DIR . 'includes/admin/views/global-addons.php';
    }

    /**
     * Render Settings tab content.
     */
    public function render_settings_tab() {
        include UPAD_PLUGIN_DIR . 'includes/admin/views/settings.php';
    }

    /**
     * Render Migrate tab content.
     */
    public function render_migrate_tab() {
        // Render migration content inline
        $this->render_migrate_content();
    }

    /**
     * Render migration content.
     */
    private function render_migrate_content() {
        if ( class_exists( 'UPAD_Admin_Migrate' ) ) {
            $migrator = UPAD_Admin_Migrate::instance();
            $migrator->render_page_content();
        } else {
            echo '<p>' . esc_html__( 'Migration tool is not available.', 'unstoppable-product-addons' ) . '</p>';
        }
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        // Check if we're on a plugin page.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page detection for conditional asset loading, no data processing.
        $current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $is_plugin_page = in_array( $current_page, array( 'upad-settings', 'unstoppable-studio' ), true );
        
        $screen = get_current_screen();
        $is_product_edit = $screen && $screen->id === 'product';
        
        if ( ! $is_plugin_page && ! $is_product_edit ) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'upad-admin',
            UPAD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            UPAD_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'upad-admin',
            UPAD_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable', 'wp-util', 'select2' ),
            UPAD_VERSION,
            true
        );

        wp_localize_script( 'upad-admin', 'upad_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'upad_admin_nonce' ),
            'i18n'     => array(
                'confirm_delete'       => __( 'Are you sure you want to delete this addon?', 'unstoppable-product-addons' ),
                'confirm_delete_group' => __( 'Are you sure you want to delete this group and all its addons?', 'unstoppable-product-addons' ),
                'saving'               => __( 'Saving...', 'unstoppable-product-addons' ),
                'saved'                => __( 'Saved!', 'unstoppable-product-addons' ),
                'error'                => __( 'Error occurred.', 'unstoppable-product-addons' ),
                'add_option'           => __( 'Add Option', 'unstoppable-product-addons' ),
                'remove'               => __( 'Remove', 'unstoppable-product-addons' ),
                'search_product'       => __( 'Search for a product...', 'unstoppable-product-addons' ),
                // Conditional logic
                'select_addon'         => __( 'Select add-on...', 'unstoppable-product-addons' ),
                'is'                   => __( 'is', 'unstoppable-product-addons' ),
                'is_not'               => __( 'is not', 'unstoppable-product-addons' ),
                'is_empty'             => __( 'is empty', 'unstoppable-product-addons' ),
                'is_not_empty'         => __( 'is not empty', 'unstoppable-product-addons' ),
                'contains'             => __( 'contains', 'unstoppable-product-addons' ),
                'greater_than'         => __( 'is greater than', 'unstoppable-product-addons' ),
                'less_than'            => __( 'is less than', 'unstoppable-product-addons' ),
                'value'                => __( 'Value', 'unstoppable-product-addons' ),
            ),
        ) );

        // Select2 - use WooCommerce's if available.
        if ( function_exists( 'WC' ) && WC()->plugin_url() ) {
            wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC()->version );
        }
        wp_enqueue_script( 'select2' );

        // Media uploader
        wp_enqueue_media();
    }

    /**
     * Plugin action links.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function plugin_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . esc_url( admin_url( 'admin.php?page=upad-settings&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'unstoppable-product-addons' ) . '</a>',
            '<a href="' . esc_url( admin_url( 'admin.php?page=upad-settings' ) ) . '">' . esc_html__( 'Add-ons', 'unstoppable-product-addons' ) . '</a>',
        );

        return array_merge( $plugin_links, $links );
    }
}
