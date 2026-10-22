<?php
/**
 * Migration Tool for importing add-ons from other plugins.
 *
 * @package WC_Custom_Addons
 * @since 1.4.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Migration Tool Class
 */
class WCOS_Admin_Migrate {

    /**
     * Single instance of the class.
     *
     * @var WCOS_Admin_Migrate
     */
    protected static $_instance = null;

    /**
     * Supported source plugins.
     *
     * @var array
     */
    private $supported_sources = array();

    /**
     * Main Instance.
     *
     * @return WCOS_Admin_Migrate
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
        $this->supported_sources = array(
            'wc_product_addons' => array(
                'name'        => __( 'WooCommerce Product Add-Ons (Official)', 'wc-custom-addons' ),
                'detect'      => array( $this, 'detect_wc_product_addons' ),
                'migrate'     => array( $this, 'migrate_wc_product_addons' ),
                'description' => __( 'Import from the official WooCommerce Product Add-Ons extension.', 'wc-custom-addons' ),
            ),
            'orderable' => array(
                'name'        => __( 'Orderable Product Addons', 'wc-custom-addons' ),
                'detect'      => array( $this, 'detect_orderable' ),
                'migrate'     => array( $this, 'migrate_orderable' ),
                'description' => __( 'Import from Orderable plugin product addons.', 'wc-custom-addons' ),
            ),
        );

        // AJAX handlers only - no menu (handled by main admin class via tabs)
        add_action( 'wp_ajax_wcos_scan_migration', array( $this, 'ajax_scan_migration' ) );
        add_action( 'wp_ajax_wcos_run_migration', array( $this, 'ajax_run_migration' ) );
    }

    /**
     * Render the migration page content (called from admin class tab).
     */
    public function render_page_content() {
        ?>
        <div class="wcca-migrate-page">
            <h2><?php esc_html_e( 'Migrate Product Add-ons', 'wc-custom-addons' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Import product add-ons from other plugins. This tool will detect existing add-ons and convert them to Unstoppable Product Addons format.', 'wc-custom-addons' ); ?></p>

            <div class="wcca-migrate-sources">
                <h3><?php esc_html_e( 'Select Source Plugin', 'wc-custom-addons' ); ?></h3>
                
                <?php foreach ( $this->supported_sources as $key => $source ) : ?>
                    <?php 
                    $detected = call_user_func( $source['detect'] );
                    $status_class = $detected['found'] ? 'detected' : 'not-detected';
                    ?>
                    <div class="wcca-source-card <?php echo esc_attr( $status_class ); ?>" data-source="<?php echo esc_attr( $key ); ?>">
                        <h3><?php echo esc_html( $source['name'] ); ?></h3>
                        <p><?php echo esc_html( $source['description'] ); ?></p>
                        
                        <?php if ( $detected['found'] ) : ?>
                            <div class="wcca-detected-info">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php 
                                printf( 
                                    esc_html__( 'Detected: %d products with add-ons, %d global add-on groups', 'wc-custom-addons' ),
                                    $detected['products'],
                                    $detected['globals']
                                ); 
                                ?>
                            </div>
                            <button type="button" class="button button-primary wcca-scan-btn" data-source="<?php echo esc_attr( $key ); ?>">
                                <?php esc_html_e( 'Scan & Preview', 'wc-custom-addons' ); ?>
                            </button>
                        <?php else : ?>
                            <div class="wcca-not-detected-info">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e( 'No add-ons detected from this plugin.', 'wc-custom-addons' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wcca-migrate-preview" style="display: none;">
                <h2><?php esc_html_e( 'Migration Preview', 'wc-custom-addons' ); ?></h2>
                <div class="wcca-preview-content"></div>
                
                <div class="wcca-migrate-options">
                    <label>
                        <input type="checkbox" name="delete_source" value="1">
                        <?php esc_html_e( 'Delete original add-ons after migration (not recommended until verified)', 'wc-custom-addons' ); ?>
                    </label>
                </div>
                
                <div class="wcca-migrate-actions">
                    <button type="button" class="button wcca-cancel-btn"><?php esc_html_e( 'Cancel', 'wc-custom-addons' ); ?></button>
                    <button type="button" class="button button-primary wcca-migrate-btn"><?php esc_html_e( 'Run Migration', 'wc-custom-addons' ); ?></button>
                </div>
                
                <div class="wcca-migrate-progress" style="display: none;">
                    <div class="wcca-progress-bar">
                        <div class="wcca-progress-fill"></div>
                    </div>
                    <div class="wcca-progress-text"></div>
                </div>
            </div>

            <div class="wcca-migrate-results" style="display: none;">
                <h2><?php esc_html_e( 'Migration Complete', 'wc-custom-addons' ); ?></h2>
                <div class="wcca-results-content"></div>
            </div>
        </div>

        <style>
            .wcca-migrate-page { max-width: 800px; }
            .wcca-source-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 15px;
            }
            .wcca-source-card h3 { margin-top: 0; }
            .wcca-source-card.detected { border-left: 4px solid #00a32a; }
            .wcca-source-card.not-detected { border-left: 4px solid #dba617; opacity: 0.7; }
            .wcca-detected-info { color: #00a32a; margin: 10px 0; }
            .wcca-not-detected-info { color: #996800; margin: 10px 0; }
            .wcca-detected-info .dashicons, .wcca-not-detected-info .dashicons { vertical-align: middle; margin-right: 5px; }
            .wcca-migrate-preview { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px; }
            .wcca-preview-content { max-height: 400px; overflow-y: auto; margin: 15px 0; }
            .wcca-preview-table { width: 100%; border-collapse: collapse; }
            .wcca-preview-table th, .wcca-preview-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
            .wcca-preview-table th { background: #f6f7f7; }
            .wcca-migrate-options { margin: 15px 0; padding: 15px; background: #fff8e5; border-radius: 4px; }
            .wcca-migrate-actions { margin-top: 20px; }
            .wcca-migrate-actions .button { margin-right: 10px; }
            .wcca-progress-bar { height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden; margin: 15px 0; }
            .wcca-progress-fill { height: 100%; background: #0073aa; width: 0%; transition: width 0.3s; }
            .wcca-progress-text { text-align: center; color: #666; }
            .wcca-migrate-results { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px; }
            .wcca-results-content .success { color: #00a32a; }
            .wcca-results-content .error { color: #d63638; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var currentSource = '';
            var scanData = null;

            // Scan button
            $('.wcca-scan-btn').on('click', function() {
                currentSource = $(this).data('source');
                var $btn = $(this);
                
                $btn.prop('disabled', true).text('<?php esc_html_e( 'Scanning...', 'wc-custom-addons' ); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wcos_scan_migration',
                        source: currentSource,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'wcos_migrate_nonce' ) ); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('<?php esc_html_e( 'Scan & Preview', 'wc-custom-addons' ); ?>');
                        
                        if (response.success) {
                            scanData = response.data;
                            showPreview(response.data);
                        } else {
                            alert(response.data.message || '<?php esc_html_e( 'Scan failed.', 'wc-custom-addons' ); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php esc_html_e( 'Scan & Preview', 'wc-custom-addons' ); ?>');
                        alert('<?php esc_html_e( 'Request failed. Please try again.', 'wc-custom-addons' ); ?>');
                    }
                });
            });

            function showPreview(data) {
                // Debug: log raw structure to console
                console.log('Migration scan data:', data);
                if (data.globals && data.globals.length && data.globals[0].debug_structure) {
                    console.log('First field structure:', data.globals[0].debug_structure);
                }
                
                var html = '<table class="wcca-preview-table">';
                html += '<thead><tr><th><?php esc_html_e( 'Type', 'wc-custom-addons' ); ?></th><th><?php esc_html_e( 'Name', 'wc-custom-addons' ); ?></th><th><?php esc_html_e( 'Add-ons', 'wc-custom-addons' ); ?></th><th><?php esc_html_e( 'Product', 'wc-custom-addons' ); ?></th></tr></thead>';
                html += '<tbody>';
                
                if (data.globals && data.globals.length) {
                    data.globals.forEach(function(item) {
                        html += '<tr>';
                        html += '<td><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e( 'Global', 'wc-custom-addons' ); ?></td>';
                        html += '<td>' + item.name + '</td>';
                        html += '<td>' + item.addon_count + '</td>';
                        html += '<td>—</td>';
                        html += '</tr>';
                        // Show debug structure if available
                        if (item.debug_structure) {
                            html += '<tr><td colspan="4"><details><summary><?php esc_html_e( 'Debug: Raw Field Structure', 'wc-custom-addons' ); ?></summary><pre style="background:#f5f5f5;padding:10px;font-size:11px;max-height:300px;overflow:auto;">' + item.debug_structure + '</pre></details></td></tr>';
                        }
                    });
                }
                
                if (data.products && data.products.length) {
                    data.products.forEach(function(item) {
                        html += '<tr>';
                        html += '<td><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Product', 'wc-custom-addons' ); ?></td>';
                        html += '<td>' + item.product_name + '</td>';
                        html += '<td>' + item.addon_count + '</td>';
                        html += '<td>#' + item.product_id + '</td>';
                        html += '</tr>';
                    });
                }
                
                html += '</tbody></table>';
                
                if ((!data.globals || !data.globals.length) && (!data.products || !data.products.length)) {
                    html = '<p><?php esc_html_e( 'No add-ons found to migrate.', 'wc-custom-addons' ); ?></p>';
                }
                
                $('.wcca-preview-content').html(html);
                $('.wcca-migrate-preview').show();
                $('.wcca-migrate-results').hide();
            }

            // Cancel button
            $('.wcca-cancel-btn').on('click', function() {
                $('.wcca-migrate-preview').hide();
                scanData = null;
            });

            // Migrate button
            $('.wcca-migrate-btn').on('click', function() {
                if (!scanData) return;
                
                var deleteSource = $('input[name="delete_source"]').is(':checked') ? 1 : 0;
                
                if (!confirm('<?php esc_html_e( 'Are you sure you want to run the migration? This will create new add-ons in Unstoppable Product Addons.', 'wc-custom-addons' ); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true);
                $('.wcca-migrate-progress').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wcos_run_migration',
                        source: currentSource,
                        delete_source: deleteSource,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'wcos_migrate_nonce' ) ); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        $('.wcca-migrate-progress').hide();
                        
                        if (response.success) {
                            showResults(response.data);
                        } else {
                            alert(response.data.message || '<?php esc_html_e( 'Migration failed.', 'wc-custom-addons' ); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        $('.wcca-migrate-progress').hide();
                        alert('<?php esc_html_e( 'Request failed. Please try again.', 'wc-custom-addons' ); ?>');
                    }
                });
            });

            function showResults(data) {
                var html = '<div class="success">';
                html += '<p><strong><?php esc_html_e( 'Migration completed successfully!', 'wc-custom-addons' ); ?></strong></p>';
                html += '<ul>';
                html += '<li>' + data.globals_migrated + ' <?php esc_html_e( 'global add-on groups migrated', 'wc-custom-addons' ); ?></li>';
                html += '<li>' + data.products_migrated + ' <?php esc_html_e( 'product add-ons migrated', 'wc-custom-addons' ); ?></li>';
                html += '<li>' + data.total_addons + ' <?php esc_html_e( 'total add-on fields created', 'wc-custom-addons' ); ?></li>';
                html += '</ul>';
                html += '</div>';
                
                if (data.errors && data.errors.length) {
                    html += '<div class="error">';
                    html += '<p><strong><?php esc_html_e( 'Some items had errors:', 'wc-custom-addons' ); ?></strong></p>';
                    html += '<ul>';
                    data.errors.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }
                
                html += '<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wcca-addons' ) ); ?>" class="button button-primary"><?php esc_html_e( 'View Migrated Add-ons', 'wc-custom-addons' ); ?></a></p>';
                
                $('.wcca-results-content').html(html);
                $('.wcca-migrate-preview').hide();
                $('.wcca-migrate-results').show();
            }
        });
        </script>
        <?php
    }

    /**
     * Detect WooCommerce Product Add-Ons.
     *
     * @return array Detection results.
     */
    public function detect_wc_product_addons() {
        global $wpdb;

        $result = array(
            'found'    => false,
            'products' => 0,
            'globals'  => 0,
        );

        // Check for per-product add-ons
        $products_with_addons = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_product_addons' AND meta_value != '' AND meta_value != 'a:0:{}'"
        );

        // Check for global add-ons (custom post type)
        $global_addons = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'global_product_addon' AND post_status = 'publish'"
        );

        if ( $products_with_addons > 0 || $global_addons > 0 ) {
            $result['found']    = true;
            $result['products'] = absint( $products_with_addons );
            $result['globals']  = absint( $global_addons );
        }

        return $result;
    }

    /**
     * Detect Orderable Product Addons.
     *
     * @return array Detection results.
     */
    public function detect_orderable() {
        global $wpdb;

        $result = array(
            'found'    => false,
            'products' => 0,
            'globals'  => 0,
        );

        // Check if Orderable addon post type exists
        $orderable_addons = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'orderable_addons' AND post_status = 'publish'"
        );

        if ( $orderable_addons > 0 ) {
            $result['found']   = true;
            $result['globals'] = absint( $orderable_addons );
        }

        return $result;
    }

    /**
     * AJAX: Scan for migration.
     */
    public function ajax_scan_migration() {
        check_ajax_referer( 'wcos_migrate_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-custom-addons' ) ) );
        }

        $source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : '';

        if ( ! isset( $this->supported_sources[ $source ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid source.', 'wc-custom-addons' ) ) );
        }

        $scan_method = 'scan_' . $source;
        if ( method_exists( $this, $scan_method ) ) {
            $data = $this->$scan_method();
            wp_send_json_success( $data );
        } else {
            wp_send_json_error( array( 'message' => __( 'Scan method not found.', 'wc-custom-addons' ) ) );
        }
    }

    /**
     * Scan WooCommerce Product Add-Ons.
     *
     * @return array Scan results.
     */
    private function scan_wc_product_addons() {
        global $wpdb;

        $data = array(
            'globals'  => array(),
            'products' => array(),
        );

        // Scan global add-ons
        $global_posts = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'global_product_addon' AND post_status = 'publish'"
        );

        foreach ( $global_posts as $post ) {
            $addons = get_post_meta( $post->ID, '_product_addons', true );
            $addon_count = is_array( $addons ) ? count( $addons ) : 0;
            
            $data['globals'][] = array(
                'id'          => $post->ID,
                'name'        => $post->post_title,
                'addon_count' => $addon_count,
            );
        }

        // Scan per-product add-ons
        $products = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value, p.post_title 
             FROM {$wpdb->postmeta} pm 
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_product_addons' 
             AND pm.meta_value != '' 
             AND pm.meta_value != 'a:0:{}'"
        );

        foreach ( $products as $product ) {
            $addons = maybe_unserialize( $product->meta_value );
            $addon_count = is_array( $addons ) ? count( $addons ) : 0;
            
            if ( $addon_count > 0 ) {
                $data['products'][] = array(
                    'product_id'   => $product->post_id,
                    'product_name' => $product->post_title,
                    'addon_count'  => $addon_count,
                );
            }
        }

        return $data;
    }

    /**
     * Scan Orderable Product Addons.
     *
     * @return array Scan results.
     */
    private function scan_orderable() {
        global $wpdb;

        $data = array(
            'globals'  => array(),
            'products' => array(),
        );

        // Scan Orderable addon groups
        $addon_posts = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'orderable_addons' AND post_status = 'publish'"
        );

        foreach ( $addon_posts as $post ) {
            $fields = get_post_meta( $post->ID, '_orderable_addon_fields', true );
            $addon_count = is_array( $fields ) ? count( $fields ) : 0;
            
            // Debug: capture the raw structure of the first field
            $debug_structure = '';
            if ( is_array( $fields ) && ! empty( $fields ) ) {
                $first_field = reset( $fields );
                $debug_structure = print_r( $first_field, true );
            }
            
            $data['globals'][] = array(
                'id'              => $post->ID,
                'name'            => $post->post_title,
                'addon_count'     => $addon_count,
                'debug_structure' => $debug_structure,
            );
        }

        return $data;
    }

    /**
     * AJAX: Run migration.
     */
    public function ajax_run_migration() {
        check_ajax_referer( 'wcos_migrate_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-custom-addons' ) ) );
        }

        $source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : '';
        $delete_source = isset( $_POST['delete_source'] ) && $_POST['delete_source'] === '1';

        if ( ! isset( $this->supported_sources[ $source ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid source.', 'wc-custom-addons' ) ) );
        }

        $migrate_callback = $this->supported_sources[ $source ]['migrate'];
        if ( is_callable( $migrate_callback ) ) {
            $result = call_user_func( $migrate_callback, $delete_source );
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( array( 'message' => __( 'Migration method not found.', 'wc-custom-addons' ) ) );
        }
    }

    /**
     * Migrate from WooCommerce Product Add-Ons.
     *
     * @param bool $delete_source Whether to delete source data.
     * @return array Migration results.
     */
    public function migrate_wc_product_addons( $delete_source = false ) {
        global $wpdb;

        $result = array(
            'globals_migrated'  => 0,
            'products_migrated' => 0,
            'total_addons'      => 0,
            'errors'            => array(),
        );

        // Type mapping
        $type_map = array(
            'multiple_choice'  => 'multiple_choice',
            'checkbox'         => 'checkboxes',
            'custom_text'      => 'text_input',
            'custom_textarea'  => 'text_area',
            'file_upload'      => 'file_upload',
            'custom_price'     => 'number',
            'input_multiplier' => 'number',
            'heading'          => 'heading',
        );

        // Migrate global add-ons
        $global_posts = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'global_product_addon' AND post_status = 'publish'"
        );

        foreach ( $global_posts as $post ) {
            $addons = get_post_meta( $post->ID, '_product_addons', true );
            $categories = get_post_meta( $post->ID, '_product_addons_categories', true );
            
            if ( ! is_array( $addons ) || empty( $addons ) ) {
                continue;
            }

            // Create global group
            $group_id = WCOS_Global_Addons::instance()->create_group( array(
                'name'       => $post->post_title . ' (Migrated)',
                'priority'   => 10,
                'enabled'    => 1,
                'categories' => is_array( $categories ) ? $categories : array(),
            ) );

            if ( ! $group_id ) {
                $result['errors'][] = sprintf( __( 'Failed to create group: %s', 'wc-custom-addons' ), $post->post_title );
                continue;
            }

            // Migrate each addon
            foreach ( $addons as $addon ) {
                $migrated = $this->migrate_single_addon_wc( $addon, $group_id, null, $type_map );
                if ( $migrated ) {
                    $result['total_addons']++;
                }
            }

            $result['globals_migrated']++;

            if ( $delete_source ) {
                wp_delete_post( $post->ID, true );
            }
        }

        // Migrate per-product add-ons
        $products = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value 
             FROM {$wpdb->postmeta} pm 
             WHERE pm.meta_key = '_product_addons' 
             AND pm.meta_value != '' 
             AND pm.meta_value != 'a:0:{}'"
        );

        foreach ( $products as $product ) {
            $addons = maybe_unserialize( $product->meta_value );
            
            if ( ! is_array( $addons ) || empty( $addons ) ) {
                continue;
            }

            $product_id = $product->post_id;
            $migrated_count = 0;

            foreach ( $addons as $addon ) {
                $migrated = $this->migrate_single_addon_wc( $addon, null, $product_id, $type_map );
                if ( $migrated ) {
                    $result['total_addons']++;
                    $migrated_count++;
                }
            }

            if ( $migrated_count > 0 ) {
                $result['products_migrated']++;
            }

            if ( $delete_source ) {
                delete_post_meta( $product_id, '_product_addons' );
            }
        }

        return $result;
    }

    /**
     * Migrate a single addon from WC Product Add-Ons format.
     *
     * @param array    $addon      Source addon data.
     * @param int|null $group_id   Group ID (for global addons).
     * @param int|null $product_id Product ID (for per-product addons).
     * @param array    $type_map   Type mapping array.
     * @return bool Whether migration succeeded.
     */
    private function migrate_single_addon_wc( $addon, $group_id, $product_id, $type_map ) {
        global $wpdb;

        $source_type = isset( $addon['type'] ) ? $addon['type'] : 'multiple_choice';
        $addon_type = isset( $type_map[ $source_type ] ) ? $type_map[ $source_type ] : 'text_input';

        // Determine title format
        $title_format = 'label';
        if ( isset( $addon['display'] ) ) {
            if ( $addon['display'] === 'heading' ) {
                $title_format = 'heading';
            }
        }

        // Build addon data
        $addon_data = array(
            'group_id'     => $group_id,
            'product_id'   => $product_id,
            'addon_type'   => $addon_type,
            'title'        => isset( $addon['name'] ) ? sanitize_text_field( $addon['name'] ) : __( 'Untitled', 'wc-custom-addons' ),
            'title_format' => $title_format,
            'description'  => isset( $addon['description'] ) ? wp_kses_post( $addon['description'] ) : '',
            'required'     => isset( $addon['required'] ) && $addon['required'] ? 1 : 0,
            'position'     => isset( $addon['position'] ) ? absint( $addon['position'] ) : 0,
            'settings'     => maybe_serialize( array() ),
            'enabled'      => 1,
        );

        // Insert addon
        $table_name = $wpdb->prefix . 'wcos_addons';
        $inserted = $wpdb->insert(
            $table_name,
            $addon_data,
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
        );

        if ( ! $inserted ) {
            return false;
        }

        $addon_id = $wpdb->insert_id;

        // Migrate options if present
        if ( isset( $addon['options'] ) && is_array( $addon['options'] ) ) {
            $options_table = $wpdb->prefix . 'wcos_addon_options';
            $position = 0;

            foreach ( $addon['options'] as $option ) {
                $price = 0;
                $price_type = 'flat';

                if ( isset( $option['price'] ) ) {
                    $price = floatval( $option['price'] );
                }
                if ( isset( $option['price_type'] ) ) {
                    if ( $option['price_type'] === 'quantity_based' ) {
                        $price_type = 'quantity';
                    } elseif ( $option['price_type'] === 'percentage_based' ) {
                        $price_type = 'percentage';
                    }
                }

                $wpdb->insert(
                    $options_table,
                    array(
                        'addon_id'   => $addon_id,
                        'label'      => isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '',
                        'price'      => $price,
                        'price_type' => $price_type,
                        'position'   => $position++,
                        'is_default' => 0,
                    ),
                    array( '%d', '%s', '%f', '%s', '%d', '%d' )
                );
            }
        }

        return true;
    }

    /**
     * Migrate from Orderable Product Addons.
     *
     * @param bool $delete_source Whether to delete source data.
     * @return array Migration results.
     */
    public function migrate_orderable( $delete_source = false ) {
        global $wpdb;

        $result = array(
            'globals_migrated'  => 0,
            'products_migrated' => 0,
            'total_addons'      => 0,
            'errors'            => array(),
        );

        // Type mapping for Orderable
        $type_map = array(
            'checkbox'   => 'checkboxes',
            'radio'      => 'multiple_choice',
            'select'     => 'multiple_choice',
            'text'       => 'text_input',
            'textarea'   => 'text_area',
            'number'     => 'number',
        );

        // Get Orderable addon posts
        $addon_posts = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'orderable_addons' AND post_status = 'publish'"
        );

        foreach ( $addon_posts as $post ) {
            $fields = get_post_meta( $post->ID, '_orderable_addon_fields', true );
            
            if ( ! is_array( $fields ) || empty( $fields ) ) {
                continue;
            }

            // Get assigned products/categories from Orderable
            $rules = get_post_meta( $post->ID, '_orderable_addon_rules', true );
            $categories = array();
            
            // Create global group
            $group_id = WCOS_Global_Addons::instance()->create_group( array(
                'name'       => $post->post_title . ' (Migrated from Orderable)',
                'priority'   => 10,
                'enabled'    => 1,
                'categories' => $categories,
            ) );

            if ( ! $group_id ) {
                $result['errors'][] = sprintf( __( 'Failed to create group: %s', 'wc-custom-addons' ), $post->post_title );
                continue;
            }

            // Migrate each field
            foreach ( $fields as $field ) {
                $migrated = $this->migrate_single_addon_orderable( $field, $group_id, $type_map );
                if ( $migrated ) {
                    $result['total_addons']++;
                }
            }

            $result['globals_migrated']++;

            if ( $delete_source ) {
                wp_delete_post( $post->ID, true );
            }
        }

        return $result;
    }

    /**
     * Migrate a single addon from Orderable format.
     *
     * @param array $field    Source field data.
     * @param int   $group_id Group ID.
     * @param array $type_map Type mapping array.
     * @return bool Whether migration succeeded.
     */
    private function migrate_single_addon_orderable( $field, $group_id, $type_map ) {
        global $wpdb;

        $source_type = isset( $field['type'] ) ? $field['type'] : 'text';
        $addon_type = isset( $type_map[ $source_type ] ) ? $type_map[ $source_type ] : 'text_input';

        // Build addon data
        $addon_data = array(
            'group_id'     => $group_id,
            'product_id'   => null,
            'addon_type'   => $addon_type,
            'title'        => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : __( 'Untitled', 'wc-custom-addons' ),
            'title_format' => 'label',
            'description'  => isset( $field['description'] ) ? wp_kses_post( $field['description'] ) : '',
            'required'     => isset( $field['required'] ) && $field['required'] ? 1 : 0,
            'position'     => 0,
            'settings'     => maybe_serialize( array() ),
            'enabled'      => 1,
        );

        // Insert addon
        $table_name = $wpdb->prefix . 'wcos_addons';
        $inserted = $wpdb->insert(
            $table_name,
            $addon_data,
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
        );

        if ( ! $inserted ) {
            return false;
        }

        $addon_id = $wpdb->insert_id;

        // Try multiple possible keys for options/choices
        $options_data = null;
        $possible_keys = array( 'choices', 'options', 'values', 'items', 'fields', 'selections' );
        
        foreach ( $possible_keys as $key ) {
            if ( isset( $field[ $key ] ) && is_array( $field[ $key ] ) && ! empty( $field[ $key ] ) ) {
                $options_data = $field[ $key ];
                break;
            }
        }
        
        // Also check if options are nested under a 'settings' or 'config' key
        if ( ! $options_data ) {
            if ( isset( $field['settings']['choices'] ) && is_array( $field['settings']['choices'] ) ) {
                $options_data = $field['settings']['choices'];
            } elseif ( isset( $field['settings']['options'] ) && is_array( $field['settings']['options'] ) ) {
                $options_data = $field['settings']['options'];
            } elseif ( isset( $field['config']['choices'] ) && is_array( $field['config']['choices'] ) ) {
                $options_data = $field['config']['choices'];
            } elseif ( isset( $field['config']['options'] ) && is_array( $field['config']['options'] ) ) {
                $options_data = $field['config']['options'];
            }
        }

        // Migrate options if found
        if ( $options_data && is_array( $options_data ) ) {
            $options_table = $wpdb->prefix . 'wcos_addon_options';
            $position = 0;

            foreach ( $options_data as $choice ) {
                // Handle different formats - could be array or just string
                if ( is_string( $choice ) ) {
                    $label = $choice;
                    $price = 0;
                } else {
                    // Try multiple possible label keys
                    $label = '';
                    $label_keys = array( 'label', 'name', 'title', 'text', 'value' );
                    foreach ( $label_keys as $lk ) {
                        if ( isset( $choice[ $lk ] ) && ! empty( $choice[ $lk ] ) ) {
                            $label = $choice[ $lk ];
                            break;
                        }
                    }
                    
                    // Try multiple possible price keys
                    $price = 0;
                    $price_keys = array( 'price', 'cost', 'amount', 'addon_price', 'extra_price' );
                    foreach ( $price_keys as $pk ) {
                        if ( isset( $choice[ $pk ] ) ) {
                            $price = floatval( $choice[ $pk ] );
                            break;
                        }
                    }
                }
                
                if ( empty( $label ) ) {
                    continue;
                }

                $wpdb->insert(
                    $options_table,
                    array(
                        'addon_id'   => $addon_id,
                        'label'      => sanitize_text_field( $label ),
                        'price'      => $price,
                        'price_type' => 'flat',
                        'position'   => $position++,
                        'is_default' => 0,
                    ),
                    array( '%d', '%s', '%f', '%s', '%d', '%d' )
                );
            }
        }

        return true;
    }
}

// Initialize
WCOS_Admin_Migrate::instance();
