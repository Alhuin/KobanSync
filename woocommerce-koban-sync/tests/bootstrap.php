<?php
/**
 * PHPUnit bootstrap file that sets up the testing environment for the WooCommerce Koban Sync plugin.
 * Loads WordPress test libraries, sets up mocks, and prepares the environment for running tests.
 *
 * @package WooCommerceKobanSync\Tests
 */

define( 'WCKOBAN_TESTING', true );

if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

// Include WP Tests Lib.
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
require_once $_tests_dir . '/includes/functions.php';
require_once $_tests_dir . '/includes/bootstrap.php';

// Includes Mocks.
require_once __DIR__ . '/mocks/mock-http.php';
require_once __DIR__ . '/mocks/class-mocklogger.php';
require_once __DIR__ . '/mocks/class-mockresponse.php';

class_alias( 'WCKoban\\Tests\\Mocks\\MockLogger', 'WCKoban\\Logger' );

update_option(
	'wckoban_sync_options',
	array(
		'koban_api_url'  => 'https://test.app-koban.com',
		'koban_url'      => 'http://rct.itsonlyleads.com/v5#',
		'koban_api_key'  => 'YpbWgwwZlvpnGtbNmb5lavFO',
		'koban_user_key' => 'qATXjM4kYPTMRnFUWDAAGD18',
	)
);

if ( ! class_exists( 'WooCommerce' ) ) {
	$woo_path = WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';
	if ( file_exists( $woo_path ) ) {
		include_once $woo_path;
	} else {
		die( 'WooCommerce n’est pas installé dans l’environnement de test.' );
	}
}

if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();
}

require_once WP_PLUGIN_DIR . '/woocommerce-koban-sync/src/woocommerce-koban-sync.php';
activate_plugin( 'woocommerce-koban-sync/woocommerce-koban-sync.php' );

do_action( 'init' );
