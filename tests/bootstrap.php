<?php
/**
 * PHPUnit bootstrap file that sets up the testing environment for the WooCommerce Koban Sync plugin.
 * Loads WordPress test libraries, sets up mocks, and prepares the environment for running tests.
 *
 * @package WooCommerceKobanSync\Tests
 */

if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

// Load WordPress test functions.
require_once $_tests_dir . '/includes/functions.php';
require_once $_tests_dir . '/includes/bootstrap.php';

// Load mock logging and mock response functions for testing.
require_once __DIR__ . '/mocks/class-mocklogger.php';
class_alias( 'mocks\MockLogger', 'WCKoban_Logger' );

// Load mock responses and mock HTTP configuration.
require_once __DIR__ . '/mocks/class-mockresponse.php';
require_once __DIR__ . '/mocks/mock-http.php';

// Update plugin settings with API credentials for tests.
update_option(
	'wckoban_sync_options',
	array(
		'koban_url'      => 'https://test.app-koban.com',
		'koban_api_key'  => 'YpbWgwwZlvpnGtbNmb5lavFO',
		'koban_user_key' => 'qATXjM4kYPTMRnFUWDAAGD18',
	)
);

// Ensure WooCommerce is loaded.
if ( ! class_exists( 'WooCommerce' ) ) {
	$woo_path = WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';
	if ( file_exists( $woo_path ) ) {
		include_once $woo_path;
	} else {
		die( 'WooCommerce n’est pas installé dans l’environnement de test.' );
	}
}

// Run WooCommerce installation.
if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();
}

do_action( 'init' );

// Load and activate the plugin.
require_once WP_PLUGIN_DIR . '/woocommerce-koban-sync/woocommerce-koban-sync.php';

// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
function _manually_load_plugin() {
	include WP_CONTENT_DIR . '/plugins/woocommerce-koban-sync/woocommerce-koban-sync.php';
}

activate_plugin( WP_CONTENT_DIR . '/plugins/woocommerce-koban-sync/woocommerce-koban-sync.php' );
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
