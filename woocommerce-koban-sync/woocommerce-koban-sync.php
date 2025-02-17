<?php
/**
 * Plugin Name: WooCommerce Koban Sync
 * Description: Integrates WooCommerce with Koban CRM for user, order, and product data synchronization.
 * Version: 1.0.0
 * Author: Alhuin
 * Text Domain: woocommerce-koban-sync
 * Domain Path: /languages
 *
 * @package WooCommerceKobanSync
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wckoban-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/hooks.php';


/**
 * Checks if WooCommerce is active. If not, the plugin is deactivated.
 */
function wckoban_sync_check_woocommerce(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		deactivate_plugins( plugin_basename( __FILE__ ) );
		add_action(
			'admin_notices',
			function () {
				echo '<div class="error"><p>';
				echo 'WooCommerce Koban Sync requires WooCommerce to be installed and active.';
				echo '</p></div>';
			}
		);
	}
}
add_action( 'plugins_loaded', 'wckoban_sync_check_woocommerce' );

/**
 * Creates the Koban sync logs table on plugin activation, if it doesn't already exist.
 */
function wckoban_sync_create_logs_table(): void {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'koban_sync_logs';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        time DATETIME NOT NULL,
        level VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        context TEXT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'wckoban_sync_create_logs_table' );

/**
 * Schedules a daily event to purge logs older than 30 days.
 */
function wckoban_sync_schedule_cron(): void {
	if ( ! wp_next_scheduled( 'wckoban_sync_purge_logs' ) ) {
		wp_schedule_event( time(), 'daily', 'wckoban_sync_purge_logs' );
	}
}
register_activation_hook( __FILE__, 'wckoban_sync_schedule_cron' );

/**
 * Deletes logs older than 30 days from the database.
 */
function wckoban_sync_delete_old_logs(): void {
	global $wpdb;
	$table_name = $wpdb->prefix . 'koban_sync_logs';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$table_name} WHERE time < %s",
			gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
		)
	);
}
add_action( 'wckoban_sync_purge_logs', 'wckoban_sync_delete_old_logs' );

/**
 * Clears the scheduled cron job when the plugin is deactivated.
 */
function wckoban_sync_deactivate(): void {
	wp_clear_scheduled_hook( 'wckoban_sync_purge_logs' );
}
register_deactivation_hook( __FILE__, 'wckoban_sync_deactivate' );

require_once plugin_dir_path( __FILE__ ) . 'admin/class-wckoban-admin.php';

if ( is_admin() ) {
	new WCKoban_Admin();
}

/**
 * Adds a shortcut link to the settings page on the Plugins screen.
 *
 * @param array $links Existing plugin action links.
 *
 * @return array Modified action links.
 */
function wckoban_sync_action_links( array $links ): array {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wckoban-sync-settings' ) ) . '">Settings</a>';
	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wckoban_sync_action_links' );
