<?php
/**
 * Uninstall script for the WooCommerce Koban Sync plugin.
 *
 * This file is called when the plugin is uninstalled (via WordPress's uninstall system).
 * It removes the custom logs table and any associated plugin options.
 *
 * @package WooCommerceKobanSync
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

global $wpdb;

/**
 * On plugin uninstall, remove the custom logs table and stored plugin options.
 */

$table_name = $wpdb->prefix . 'koban_sync_logs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

delete_option( 'wckoban_sync_options' );
