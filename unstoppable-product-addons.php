<?php
/**
 * Plugin Name: Unstoppable Product Addons
 * Plugin URI: https://unstoppablesolutions.app/plugins/unstoppable-product-addons
 * Description: Add optional extras to WooCommerce products at purchase — gift wrap, engraving, personalization, custom text fields. One admin page, no theme edits, no per-product configuration slog.
 * Version: 0.1.0
 * Author: Unstoppable Solutions
 * Author URI: https://unstoppablesolutions.app
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unstoppable-product-addons
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 7.1
 * Requires PHP: 7.4
 *
 * @package Unstoppable_Product_Addons
 */

defined( 'ABSPATH' ) || exit;

define( 'UPAD_VERSION', '0.1.0' );
define( 'UPAD_FILE', __FILE__ );
define( 'UPAD_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPAD_URL', plugin_dir_url( __FILE__ ) );

require_once UPAD_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'UPAD_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'UPAD_Plugin', 'deactivate' ) );

UPAD_Plugin::init();
