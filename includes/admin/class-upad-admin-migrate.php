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
class UPAD_Admin_Migrate {

    /**
     * Single instance of the class.
     *
     * @var UPAD_Admin_Migrate
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
     * @return UPAD_Admin_Migrate
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
                'name'        => __( 'WooCommerce Product Add-Ons (Official)', 'unstoppable-product-addons' ),
                'detect'      => array( $this, 'detect_wc_product_addons' ),
                'migrate'     => array( $this, 'migrate_wc_product_addons' ),
                'description' => __( 'Import from the official WooCommerce Product Add-Ons extension.', 'unstoppable-product-addons' ),
            ),
            'orderable' => array(
                'name'        => __( 'Orderable Product Addons', 'unstoppable-product-addons' ),
                'detect'      => array( $this, 'detect_orderable' ),
                'migrate'     => array( $this, 'migrate_orderable' ),
                'description' => __( 'Import from Orderable plugin product addons.', 'unstoppable-product-addons' ),
            ),
        );

        // AJAX handlers only - no menu (handled by main admin class via tabs)
        add_action( 'wp_ajax_upad_scan_migration', array( $this, 'ajax_scan_migration' ) );
        add_action( 'wp_ajax_upad_run_migration', array( $this, 'ajax_run_migration' ) );
    }

    /**
     * Render the migration page content (called from admin class tab).
     */
    public function render_page_content() {
        ?>
        <div class="upad-migrate-page">
            <h2><?php esc_html_e( 'Migrate Product Add-ons', 'unstoppable-product-addons' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Import product add-ons from other plugins. This tool will detect existing add-ons and convert them to Unstoppable Product Addons format.', 'unstoppable-product-addons' ); ?></p>

            <div class="upad-migrate-sources">
                <h3><?php esc_html_e( 'Select Source Plugin', 'unstoppable-product-addons' ); ?></h3>
                
                <?php foreach ( $this->supported_sources as $key => $source ) : ?>
                    <?php 
                    $detected = call_user_func( $source['detect'] );
                    $status_class = $detected['found'] ? 'detected' : 'not-detected';
                    ?>
                    <div class="upad-source-card <?php echo esc_attr( $status_class ); ?>" data-source="<?php echo esc_attr( $key ); ?>">
                        <h3><?php echo esc_html( $source['name'] ); ?></h3>
                        <p><?php echo esc_html( $source['description'] ); ?></p>
                        
                        <?php if ( $detected['found'] ) : ?>
                            <div class="upad-detected-info">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php
                                printf(
                                    /* translators: %1$d: number of products, %2$d: number of global groups */
                                    esc_html__( 'Detected: %1$d products with add-ons, %2$d global add-on groups', 'unstoppable-product-addons' ),
                                    absint( $detected['products'] ),
                                    absint( $detected['globals'] )
                                );
                                ?>
                            </div>
                            <button type="button" class="button button-primary upad-scan-btn" data-source="<?php echo esc_attr( $key ); ?>">
                                <?php esc_html_e( 'Scan & Preview', 'unstoppable-product-addons' ); ?>
                            </button>
                        <?php else : ?>
                            <div class="upad-not-detected-info">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e( 'No add-ons detected from this plugin.', 'unstoppable-product-addons' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="upad-migrate-preview" style="display: none;">
                <h2><?php esc_html_e( 'Migration Preview', 'unstoppable-product-addons' ); ?></h2>
                <div class="upad-preview-content"></div>
                
                <div class="upad-migrate-options">
                    <label>
                        <input type="checkbox" name="delete_source" value="1">
                        <?php esc_html_e( 'Delete original add-ons after migration (not recommended until verified)', 'unstoppable-product-addons' ); ?>
                    </label>
                </div>
                
                <div class="upad-migrate-actions">
                    <button type="button" class="button upad-cancel-btn"><?php esc_html_e( 'Cancel', 'unstoppable-product-addons' ); ?></button>
                    <button type="button" class="button button-primary upad-migrate-btn"><?php esc_html_e( 'Run Migration', 'unstoppable-product-addons' ); ?></button>
                </div>
                
                <div class="upad-migrate-progress" style="display: none;">
                    <div class="upad-progress-bar">
                        <div class="upad-progress-fill"></div>
                    </div>
                    <div class="upad-progress-text"></div>
                </div>
            </div>

            <div class="upad-migrate-results" style="display: none;">
                <h2><?php esc_html_e( 'Migration Complete', 'unstoppable-product-addons' ); ?></h2>
                <div class="upad-results-content"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var currentSource = '';
            var scanData = null;

            // Scan button
            $('.upad-scan-btn').on('click', function() {
                currentSource = $(this).data('source');
                var $btn = $(this);
                
                $btn.prop('disabled', true).text('<?php esc_html_e( 'Scanning...', 'unstoppable-product-addons' ); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'upad_scan_migration',
                        source: currentSource,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'upad_migrate_nonce' ) ); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('<?php esc_html_e( 'Scan & Preview', 'unstoppable-product-addons' ); ?>');
                        
                        if (response.success) {
                            scanData = response.data;
                            showPreview(response.data);
                        } else {
                            alert(response.data.message || '<?php esc_html_e( 'Scan failed.', 'unstoppable-product-addons' ); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php esc_html_e( 'Scan & Preview', 'unstoppable-product-addons' ); ?>');
                        alert('<?php esc_html_e( 'Request failed. Please try again.', 'unstoppable-product-addons' ); ?>');
                    }
                });
            });

            function showPreview(data) {
                // Debug: log raw structure to console
                console.log('Migration scan data:', data);
                if (data.globals && data.globals.length && data.globals[0].debug_structure) {
                    console.log('First field structure:', data.globals[0].debug_structure);
                }
                
                var html = '<table class="upad-preview-table">';
                html += '<thead><tr><th><?php esc_html_e( 'Type', 'unstoppable-product-addons' ); ?></th><th><?php esc_html_e( 'Name', 'unstoppable-product-addons' ); ?></th><th><?php esc_html_e( 'Add-ons', 'unstoppable-product-addons' ); ?></th><th><?php esc_html_e( 'Product', 'unstoppable-product-addons' ); ?></th></tr></thead>';
                html += '<tbody>';
                
                if (data.globals && data.globals.length) {
                    data.globals.forEach(function(item) {
                        html += '<tr>';
                        html += '<td><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e( 'Global', 'unstoppable-product-addons' ); ?></td>';
                        html += '<td>' + item.name + '</td>';
                        html += '<td>' + item.addon_count + '</td>';
                        html += '<td>—</td>';
                        html += '</tr>';
                        // Show debug structure if available
                        if (item.debug_structure) {
                            html += '<tr><td colspan="4"><details><summary><?php esc_html_e( 'Debug: Raw Field Structure', 'unstoppable-product-addons' ); ?></summary><pre style="background:#f5f5f5;padding:10px;font-size:11px;max-height:300px;overflow:auto;">' + item.debug_structure + '</pre></details></td></tr>';
                        }
                    });
                }
                
                if (data.products && data.products.length) {
                    data.products.forEach(function(item) {
                        html += '<tr>';
                        html += '<td><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Product', 'unstoppable-product-addons' ); ?></td>';
                        html += '<td>' + item.product_name + '</td>';
                        html += '<td>' + item.addon_count + '</td>';
                        html += '<td>#' + item.product_id + '</td>';
                        html += '</tr>';
                    });
                }
                
                html += '</tbody></table>';
                
                if ((!data.globals || !data.globals.length) && (!data.products || !data.products.length)) {
                    html = '<p><?php esc_html_e( 'No add-ons found to migrate.', 'unstoppable-product-addons' ); ?></p>';
                }
                
                $('.upad-preview-content').html(html);
                $('.upad-migrate-preview').show();
                $('.upad-migrate-results').hide();
            }

            // Cancel button
            $('.upad-cancel-btn').on('click', function() {
                $('.upad-migrate-preview').hide();
                scanData = null;
            });

            // Migrate button
            $('.upad-migrate-btn').on('click', function() {
                if (!scanData) return;
                
                var deleteSource = $('input[name="delete_source"]').is(':checked') ? 1 : 0;
                
                if (!confirm('<?php esc_html_e( 'Are you sure you want to run the migration? This will create new add-ons in Unstoppable Product Addons.', 'unstoppable-product-addons' ); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true);
                $('.upad-migrate-progress').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'upad_run_migration',
                        source: currentSource,
                        delete_source: deleteSource,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'upad_migrate_nonce' ) ); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        $('.upad-migrate-progress').hide();
                        
                        if (response.success) {
                            showResults(response.data);
                        } else {
                            alert(response.data.message || '<?php esc_html_e( 'Migration failed.', 'unstoppable-product-addons' ); ?>');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        $('.upad-migrate-progress').hide();
                        alert('<?php esc_html_e( 'Request failed. Please try again.', 'unstoppable-product-addons' ); ?>');
                    }
                });
            });

            function showResults(data) {
                var html = '<div class="success">';
                html += '<p><strong><?php esc_html_e( 'Migration completed successfully!', 'unstoppable-product-addons' ); ?></strong></p>';
                html += '<ul>';
                html += '<li>' + data.globals_migrated + ' <?php esc_html_e( 'global add-on groups migrated', 'unstoppable-product-addons' ); ?></li>';
                html += '<li>' + data.products_migrated + ' <?php esc_html_e( 'product add-ons migrated', 'unstoppable-product-addons' ); ?></li>';
                html += '<li>' + data.total_addons + ' <?php esc_html_e( 'total add-on fields created', 'unstoppable-product-addons' ); ?></li>';
                html += '</ul>';
                html += '</div>';
                
                if (data.errors && data.errors.length) {
                    html += '<div class="error">';
                    html += '<p><strong><?php esc_html_e( 'Some items had errors:', 'unstoppable-product-addons' ); ?></strong></p>';
                    html += '<ul>';
                    data.errors.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }
                
                html += '<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=upad-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'View Migrated Add-ons', 'unstoppable-product-addons' ); ?></a></p>';
                
                $('.upad-results-content').html(html);
                $('.upad-migrate-preview').hide();
                $('.upad-migrate-results').show();
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration detection query.
        $products_with_addons = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_product_addons' AND meta_value != '' AND meta_value != 'a:0:{}'"
        );

        // Check for global add-ons (custom post type)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration detection query.
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration detection query.
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
        check_ajax_referer( 'upad_migrate_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-product-addons' ) ) );
        }

        $source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : '';

        if ( ! isset( $this->supported_sources[ $source ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid source.', 'unstoppable-product-addons' ) ) );
        }

        $scan_method = 'scan_' . $source;
        if ( method_exists( $this, $scan_method ) ) {
            $data = $this->$scan_method();
            wp_send_json_success( $data );
        } else {
            wp_send_json_error( array( 'message' => __( 'Scan method not found.', 'unstoppable-product-addons' ) ) );
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration scan query.
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration scan query.
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration scan query.
        $addon_posts = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'orderable_addons' AND post_status = 'publish'"
        );

        foreach ( $addon_posts as $post ) {
            $fields = get_post_meta( $post->ID, '_orderable_addon_fields', true );
            $addon_count = is_array( $fields ) ? count( $fields ) : 0;
            
            // Debug: capture the raw structure of the first field for migration preview.
            $debug_structure = '';
            if ( is_array( $fields ) && ! empty( $fields ) ) {
                $first_field = reset( $fields );
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Used for admin migration preview, not logging.
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
        check_ajax_referer( 'upad_migrate_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'unstoppable-product-addons' ) ) );
        }

        $source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : '';
        $delete_source = isset( $_POST['delete_source'] ) && $_POST['delete_source'] === '1';

        if ( ! isset( $this->supported_sources[ $source ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid source.', 'unstoppable-product-addons' ) ) );
        }

        $migrate_callback = $this->supported_sources[ $source ]['migrate'];
        if ( is_callable( $migrate_callback ) ) {
            $result = call_user_func( $migrate_callback, $delete_source );
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( array( 'message' => __( 'Migration method not found.', 'unstoppable-product-addons' ) ) );
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration query.
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
            $group_id = UPAD_Global_Addons::instance()->create_group( array(
                'name'       => $post->post_title . ' (Migrated)',
                'priority'   => 10,
                'enabled'    => 1,
                'categories' => is_array( $categories ) ? $categories : array(),
            ) );

            if ( ! $group_id ) {
                /* translators: %s: group name */
                $result['errors'][] = sprintf( __( 'Failed to create group: %s', 'unstoppable-product-addons' ), $post->post_title );
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration query.
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
            'title'        => isset( $addon['name'] ) ? sanitize_text_field( $addon['name'] ) : __( 'Untitled', 'unstoppable-product-addons' ),
            'title_format' => $title_format,
            'description'  => isset( $addon['description'] ) ? wp_kses_post( $addon['description'] ) : '',
            'required'     => isset( $addon['required'] ) && $addon['required'] ? 1 : 0,
            'position'     => isset( $addon['position'] ) ? absint( $addon['position'] ) : 0,
            'settings'     => maybe_serialize( array() ),
            'enabled'      => 1,
        );

        // Insert addon
        $table_name = $wpdb->prefix . 'upad_addons';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
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
            $options_table = $wpdb->prefix . 'upad_addon_options';
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

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration query.
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
            $group_id = UPAD_Global_Addons::instance()->create_group( array(
                'name'       => $post->post_title . ' (Migrated from Orderable)',
                'priority'   => 10,
                'enabled'    => 1,
                'categories' => $categories,
            ) );

            if ( ! $group_id ) {
                /* translators: %s: group name */
                $result['errors'][] = sprintf( __( 'Failed to create group: %s', 'unstoppable-product-addons' ), $post->post_title );
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
            'title'        => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : __( 'Untitled', 'unstoppable-product-addons' ),
            'title_format' => 'label',
            'description'  => isset( $field['description'] ) ? wp_kses_post( $field['description'] ) : '',
            'required'     => isset( $field['required'] ) && $field['required'] ? 1 : 0,
            'position'     => 0,
            'settings'     => maybe_serialize( array() ),
            'enabled'      => 1,
        );

        // Insert addon
        $table_name = $wpdb->prefix . 'upad_addons';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
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
            $options_table = $wpdb->prefix . 'upad_addon_options';
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

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table requires direct query.
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
UPAD_Admin_Migrate::instance();
