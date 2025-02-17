<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

global $wpdb;

/**
 * On plugin uninstall, remove the custom logs table and stored plugin options.
 */

$table_name = $wpdb->prefix . 'koban_sync_logs';

$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

delete_option( 'wckoban_sync_options' );
