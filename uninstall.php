<?php
/**
 * Uninstall handler for Unstoppable Product Addons.
 *
 * Runs when the plugin is deleted from Plugins → Installed Plugins. Deactivation
 * alone does NOT trigger this file. Clean up all plugin data (database tables,
 * options, transients, cron hooks) so the site is returned to the state it
 * was in before the plugin was installed.
 *
 * @package Unstoppable_Product_Addons
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Example cleanup — adjust as the plugin grows:
//
// delete_option( 'upad_settings' );
// delete_option( 'upad_db_version' );
//
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}upad_my_table" );
