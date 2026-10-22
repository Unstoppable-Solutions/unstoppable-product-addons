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
        add_action( 'wp_ajax_upad_check_updates', array( $this, 'ajax_check_updates' ) );
    }

    /**
     * Get current tab.
     *
     * @return string Current tab slug.
     */
    private function get_current_tab() {
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
        // Check if we're on a plugin page
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
            '<a href="#" class="upad-check-updates-link" style="color: #2271b1;">' . esc_html__( 'Check for Updates', 'unstoppable-product-addons' ) . '</a>',
        );
        
        // Add inline script for the check updates link
        add_action( 'admin_footer', array( $this, 'plugin_page_update_script' ) );
        
        return array_merge( $plugin_links, $links );
    }

    /**
     * Output update check script on plugins page.
     */
    public function plugin_page_update_script() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'plugins' ) {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            $('.upad-check-updates-link').on('click', function(e) {
                e.preventDefault();
                var $link = $(this);
                var originalText = $link.text();
                
                $link.text('<?php echo esc_js( __( 'Checking...', 'unstoppable-product-addons' ) ); ?>');
                
                $.post(ajaxurl, {
                    action: 'upad_check_updates',
                    nonce: '<?php echo esc_js( wp_create_nonce( 'upad_check_updates' ) ); ?>'
                }, function(response) {
                    if (response.success) {
                        if (response.data.update_available) {
                            $link.text('<?php echo esc_js( __( 'Update Available!', 'unstoppable-product-addons' ) ); ?>').css('color', '#d63638');
                            alert('<?php echo esc_js( __( 'Update available! Version', 'unstoppable-product-addons' ) ); ?> ' + response.data.new_version + ' <?php echo esc_js( __( 'is ready. Refresh this page to see the update.', 'unstoppable-product-addons' ) ); ?>');
                            location.reload();
                        } else {
                            $link.text('<?php echo esc_js( __( 'Up to date ✓', 'unstoppable-product-addons' ) ); ?>').css('color', '#00a32a');
                            setTimeout(function() {
                                $link.text(originalText).css('color', '#2271b1');
                            }, 3000);
                        }
                    } else {
                        $link.text('<?php echo esc_js( __( 'Check failed', 'unstoppable-product-addons' ) ); ?>').css('color', '#d63638');
                        setTimeout(function() {
                            $link.text(originalText).css('color', '#2271b1');
                        }, 3000);
                    }
                }).fail(function() {
                    $link.text(originalText);
                    alert('<?php echo esc_js( __( 'Connection failed. Please try again.', 'unstoppable-product-addons' ) ); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for checking updates.
     */
    public function ajax_check_updates() {
        check_ajax_referer( 'upad_check_updates', 'nonce' );

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-product-addons' ) ) );
        }

        // Clear the update cache
        delete_site_transient( 'update_plugins' );
        delete_transient( 'upad_update_data' );

        // Force WordPress to check for updates
        wp_clean_plugins_cache();

        // Make direct API request to check
        $response = wp_remote_post( 'https://fm-web-solutions-plugin-hub-d05f2182.base44.app/functions/pluginUpdateChecker', array(
            'timeout'   => 15,
            'sslverify' => true,
            'headers'   => array(
                'Content-Type' => 'application/json',
            ),
            'body'      => wp_json_encode( array(
                'plugin_slug'     => 'unstoppable-product-addons',
                'current_version' => UPAD_VERSION,
                'site_url'        => home_url(),
                'license_key'     => get_option( 'upad_license_key', '' ),
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
            wp_send_json_error( array( 'message' => __( 'Invalid API response.', 'unstoppable-product-addons' ) ) );
        }

        $new_version = isset( $data['version'] ) ? $data['version'] : '';
        $update_available = version_compare( UPAD_VERSION, $new_version, '<' );

        wp_send_json_success( array(
            'update_available' => $update_available,
            'current_version'  => UPAD_VERSION,
            'new_version'      => $new_version,
            'update_url'       => admin_url( 'update-core.php' ),
        ) );
    }
}
