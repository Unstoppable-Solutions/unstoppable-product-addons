<?php
/**
 * Uninstall Unstoppable Product Addons
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load install class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-upad-install.php';

// Run uninstall
WCOS_Install::uninstall();
