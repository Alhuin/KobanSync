<?php
/**
 * Plugin Name: WooCommerce Koban Sync
 * Description: Integrates WooCommerce with Koban CRM for user, order, and product data synchronization.
 * Version: 0.0.1
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

// Require plugin constants and base config.
require_once __DIR__ . '/config.php';

// Include core Admin, API, and utility classes.
require_once __DIR__ . '/admin/class-admin.php';
require_once __DIR__ . '/admin/protected-pdf.php';
require_once __DIR__ . '/includes/class-api.php';
require_once __DIR__ . '/includes/utils/class-metautils.php';

// If not in testing mode, load the real Logger class.
if ( ! defined( 'WCKOBAN_TESTING' ) ) {
	require_once __DIR__ . '/includes/class-logger.php';
}

// Include the hooks and serializers.
require_once __DIR__ . '/includes/hooks/class-statemachine.php';
require_once __DIR__ . '/includes/hooks/class-paymentcomplete.php';
require_once __DIR__ . '/includes/hooks/class-customersaveaddress.php';
require_once __DIR__ . '/includes/hooks/class-productupdate.php';

require_once __DIR__ . '/includes/serializers/class-order.php';
require_once __DIR__ . '/includes/serializers/class-upsertproduct.php';
require_once __DIR__ . '/includes/serializers/class-upsertthird.php';

use WCKoban\Admin\Admin;
use WCKoban\Hooks\CustomerSaveAddress;
use WCKoban\Hooks\PaymentComplete;
use WCKoban\Hooks\ProductUpdate;

/**
 * Checks if WooCommerce is active. If not, the plugin is deactivated immediately.
 */
function wckoban_sync_check_woocommerce(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			// If running in WP-CLI, avoid unexpected deactivation messages.
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
 * Check if the plugin’s Koban settings are complete (non-empty).
 *
 * @return bool True if the user has configured all required Koban fields
 */
function wckoban_sync_has_valid_config(): bool {
	$options = get_option( 'wckoban_sync_options', array() );

	return ! empty( $options['koban_api_url'] )
			&& ! empty( $options['koban_url'] )
			&& ! empty( $options['koban_api_key'] )
			&& ! empty( $options['koban_user_key'] );
}

/**
 * If Koban config is valid, load everything; otherwise, show an admin notice.
 */
if ( wckoban_sync_has_valid_config() ) {
	// Only load meta-fields if we’re in the admin, to avoid overhead on the frontend.
	if ( is_admin() ) {
		new Admin();
		require_once __DIR__ . '/admin/meta-fields.php';
	}

	( new CustomerSaveAddress() )->register();
	( new PaymentComplete() )->register();
	( new ProductUpdate() )->register();

} else {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-warning">
			<p>
			<?php
			esc_html_e(
				'WooCommerce Koban Sync is inactive because Koban credentials are missing. Please visit Koban Sync → Settings.',
				'woocommerce-koban-sync'
			);
			?>
				</p>
		</div>
			<?php
		}
	);

	// Still load Admin to show the Settings page, so the user can fix their credentials.
	if ( is_admin() ) {
		new Admin();
	}
}

/**
 * Creates the Koban sync logs table on plugin activation, if it doesn't already exist.
 */
function wckoban_sync_create_logs_table(): void {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'koban_sync_logs';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		workflow_id VARCHAR(64) DEFAULT NULL,
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
 * Deletes logs older than 30 days from the database (runs daily).
 */
function wckoban_sync_delete_old_logs(): void {
	global $wpdb;
	$table_name = $wpdb->prefix . 'koban_sync_logs';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$table_name}
			 WHERE time < %s",
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

/**
 * Adds a shortcut link to the settings page on the Plugins screen.
 *
 * @param array $links Existing plugin action links.
 * @return array
 */
function wckoban_sync_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=wckoban-sync-settings' ) ),
		esc_html__( 'Settings', 'woocommerce-koban-sync' )
	);

	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wckoban_sync_action_links' );
