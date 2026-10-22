<?php
/**
 * Main plugin class.
 *
 * @package Unstoppable_Product_Addons
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap. Rename class and prefix throughout as part of your
 * find-and-replace step when scaffolding a new plugin from this template.
 */
final class UPAD_Plugin {

	/**
	 * Hook subsystems into WordPress.
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		}
	}

	/**
	 * Register the plugin's admin page. Edit the slug, capability, and
	 * callback once you know what the plugin does.
	 */
	public static function register_admin_menu() {
		add_menu_page(
			esc_html__( 'Unstoppable Product Addons', 'unstoppable-product-addons' ),
			esc_html__( 'Unstoppable Product Addons', 'unstoppable-product-addons' ),
			'manage_options',
			'unstoppable-product-addons',
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-admin-generic',
			80
		);
	}

	/**
	 * Render the plugin's main admin page. Replace with real UI.
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'unstoppable-product-addons' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Unstoppable Product Addons', 'unstoppable-product-addons' ) . '</h1>';
		echo '<p>' . esc_html__( 'Replace this stub with your plugin\'s admin UI.', 'unstoppable-product-addons' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Fires on plugin activation. Create tables, seed options, etc.
	 */
	public static function activate() {
		// e.g. create your database tables here.
	}

	/**
	 * Fires on plugin deactivation. Clear scheduled hooks, etc.
	 */
	public static function deactivate() {
		// e.g. wp_clear_scheduled_hook( 'upad_cron' );
	}
}
