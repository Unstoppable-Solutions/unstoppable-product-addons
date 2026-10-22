<?php
/**
 * Installation related functions and actions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Install {

    /**
     * DB updates and callbacks
     */
    private static $db_updates = array(
        '1.0.0' => array(
            'upad_update_100_create_tables',
            'upad_update_100_db_version',
        ),
    );

    /**
     * Activation
     */
    public static function activate() {
        self::create_tables();
        self::create_options();
        self::create_upload_directory();
        self::update_db_version();

        // Clear any cached data
        wp_cache_flush();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Deactivation
     */
    public static function deactivate() {
        // Cleanup if needed
        flush_rewrite_rules();
    }

    /**
     * Create required database tables
     */
    private static function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Global add-ons groups table
        $sql_groups = "CREATE TABLE {$wpdb->prefix}upad_global_groups (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            priority int(11) NOT NULL DEFAULT 10,
            restrict_to_categories longtext,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY priority (priority),
            KEY enabled (enabled)
        ) $charset_collate;";

        // Add-ons table (for both global and per-product)
        $sql_addons = "CREATE TABLE {$wpdb->prefix}upad_addons (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id bigint(20) UNSIGNED DEFAULT NULL,
            product_id bigint(20) UNSIGNED DEFAULT NULL,
            addon_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            title_format varchar(20) NOT NULL DEFAULT 'label',
            description longtext,
            required tinyint(1) NOT NULL DEFAULT 0,
            position int(11) NOT NULL DEFAULT 0,
            settings longtext,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY group_id (group_id),
            KEY product_id (product_id),
            KEY addon_type (addon_type),
            KEY position (position),
            KEY enabled (enabled)
        ) $charset_collate;";

        // Add-on options table (for multiple choice, checkboxes, etc.)
        $sql_options = "CREATE TABLE {$wpdb->prefix}upad_addon_options (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            addon_id bigint(20) UNSIGNED NOT NULL,
            parent_option_id bigint(20) UNSIGNED DEFAULT NULL,
            label varchar(255) NOT NULL,
            price decimal(19,4) DEFAULT NULL,
            price_type varchar(20) NOT NULL DEFAULT 'flat',
            tiered_prices text DEFAULT NULL,
            image_id bigint(20) UNSIGNED DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            color varchar(20) DEFAULT NULL,
            linked_product_id bigint(20) UNSIGNED DEFAULT NULL,
            linked_image_url varchar(500) DEFAULT NULL,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            is_hidden tinyint(1) NOT NULL DEFAULT 0,
            position int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY addon_id (addon_id),
            KEY parent_option_id (parent_option_id),
            KEY linked_product_id (linked_product_id),
            KEY position (position)
        ) $charset_collate;";

        // File upload tracking table
        $sql_uploads = "CREATE TABLE {$wpdb->prefix}upad_uploads (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            order_item_id bigint(20) UNSIGNED DEFAULT NULL,
            product_id bigint(20) UNSIGNED NOT NULL,
            addon_id bigint(20) UNSIGNED NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_size bigint(20) UNSIGNED NOT NULL,
            upload_hash varchar(64) NOT NULL,
            session_key varchar(64) DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY addon_id (addon_id),
            KEY upload_hash (upload_hash),
            KEY session_key (session_key),
            KEY status (status)
        ) $charset_collate;";

        dbDelta( $sql_groups );
        dbDelta( $sql_addons );
        dbDelta( $sql_options );
        dbDelta( $sql_uploads );
    }

    /**
     * Create default plugin options
     */
    private static function create_options() {
        $default_options = array(
            'upad_version'                    => UPAD_VERSION,
            'upad_db_version'                 => UPAD_VERSION,
            'upad_file_upload_enabled'        => 'yes',
            'upad_allowed_file_types'         => 'jpg,jpeg,png,gif,pdf,doc,docx',
            'upad_max_file_size'              => 5, // MB
            'upad_secure_uploads'             => 'yes',
            'upad_display_option_prices'      => 'yes',
            'upad_display_totals'             => 'yes',
            'upad_enable_inventory_tracking'  => 'no',
            'upad_add_as_line_items'          => 'no',
            'upad_inherit_global_addons'      => 'yes',
        );

        foreach ( $default_options as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Create secure upload directory
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $upad_upload_dir = $upload_dir['basedir'] . '/upad-uploads';

        if ( ! file_exists( $upad_upload_dir ) ) {
            wp_mkdir_p( $upad_upload_dir );
        }

        // Create .htaccess to restrict direct access
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "<Files \"*\">\n";
        $htaccess_content .= "    <IfModule mod_authz_core.c>\n";
        $htaccess_content .= "        Require all denied\n";
        $htaccess_content .= "    </IfModule>\n";
        $htaccess_content .= "    <IfModule !mod_authz_core.c>\n";
        $htaccess_content .= "        Order deny,allow\n";
        $htaccess_content .= "        Deny from all\n";
        $htaccess_content .= "    </IfModule>\n";
        $htaccess_content .= "</Files>\n";

        $htaccess_file = $upad_upload_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            file_put_contents( $htaccess_file, $htaccess_content );
        }

        // Create index.php to prevent directory listing
        $index_file = $upad_upload_dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden' );
        }
    }

    /**
     * Update DB version
     */
    private static function update_db_version() {
        update_option( 'upad_db_version', UPAD_VERSION );
    }

    /**
     * Uninstall - called from uninstall.php
     */
    public static function uninstall() {
        global $wpdb;

        // Only run if settings say to delete data
        if ( 'yes' !== get_option( 'upad_delete_data_on_uninstall', 'no' ) ) {
            return;
        }

        // Delete tables
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}upad_global_groups" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}upad_addons" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}upad_addon_options" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}upad_uploads" );

        // Delete options
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'upad_%'" );

        // Delete product meta
        $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_upad_%'" );

        // Delete upload directory
        $upload_dir = wp_upload_dir();
        $upad_upload_dir = $upload_dir['basedir'] . '/upad-uploads';
        if ( file_exists( $upad_upload_dir ) ) {
            self::delete_directory( $upad_upload_dir );
        }

        // Clear scheduled events
        wp_clear_scheduled_hook( 'upad_cleanup_temp_uploads' );

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Recursively delete a directory
     */
    private static function delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = array_diff( scandir( $dir ), array( '.', '..' ) );
        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            is_dir( $path ) ? self::delete_directory( $path ) : unlink( $path );
        }
        rmdir( $dir );
    }
}
