<?php
/**
 * Class: WCKoban_UnitTestCase.php
 *
 * Provides a base test case class with shared utilities for mocking requests,
 * creating WooCommerce entities, and asserting Koban request flows.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests;

use WP_UnitTestCase;
use WC_Customer;
use WC_Product;

/**
 * Class WCKoban_UnitTestCase
 *
 * Base test case class with helper methods for creating and testing
 * WooCommerce orders, products, customers, and mock HTTP responses.
 */
class WCKoban_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Reset global mock response and request tracking.
	 *
	 * Clears the mock response queue and tracked requests before each test run.
	 */
	public function reset_mocks(): void {
		global $wp_remote_requests, $mock_response_queue, $request_index;

		$request_index       = 0;
		$mock_response_queue = array();
		$wp_remote_requests  = array();
	}

	/**
	 * Verify each expected mock response matches the actual outgoing request.
	 *
	 * @param array $mock_responses List of expected mock response objects.
	 */
	public function assertRequests( array $mock_responses ): void {
		global $wp_remote_requests, $request_index;

		foreach ( $mock_responses as $expected_request ) {
			$remote_request = $wp_remote_requests[ $request_index ];

			$this->assertStringContainsString(
				$expected_request->endpoint,
				$remote_request['url'],
				"Expected request {$request_index} to match endpoint {$expected_request->endpoint}; got {$remote_request['url']}."
			);
			$this->assertSame(
				$expected_request->method,
				$remote_request['method'],
				"Expected method {$expected_request->method}; got {$remote_request['method']}."
			);
			++$request_index;
		}
	}

	/**
	 * Assert that the number of performed HTTP requests matches a given count.
	 *
	 * @param int $expected Number of requests expected.
	 */
	public function assertRequestsCount( int $expected ): void {
		global $wp_remote_requests;

		$this->assertCount( $expected, $wp_remote_requests, "Expected {$expected} HTTP requests in total." );
	}

	/**
	 * Create a product post in WordPress and return its ID.
	 *
	 * @param array $data Optional overrides for post fields.
	 * @return int        The newly created post ID.
	 */
	public function create_wp_post_product( array $data = array() ): int {
		$defaults = array(
			'post_type'    => 'product',
			'post_status'  => 'publish',
			'post_title'   => 'Product Title',
			'post_content' => 'Product Description',
		);
		return wp_insert_post( array_merge( $defaults, $data ) );
	}

	/**
	 * Create a basic WooCommerce customer and return the user ID.
	 *
	 * @param array $data Optional overrides for customer fields.
	 * @return int        The created customer's user ID.
	 */
	public function create_wc_customer( array $data = array() ): int {
		$customer = new WC_Customer();
		$customer->set_billing_country( 'US' );
		$customer->set_first_name( 'Justin' );
		$customer->set_billing_state( 'PA' );
		$customer->set_billing_postcode( '19123' );
		$customer->set_billing_city( 'Philadelphia' );
		$customer->set_billing_address( '123 South Street' );
		$customer->set_billing_address_2( 'Apt 1' );
		$customer->set_shipping_country( 'US' );
		$customer->set_shipping_state( 'PA' );
		$customer->set_shipping_postcode( '19123' );
		$customer->set_shipping_city( 'Philadelphia' );
		$customer->set_shipping_address( '123 South Street' );
		$customer->set_shipping_address_2( 'Apt 1' );
		$customer->set_username( 'username' );
		$customer->set_password( 'password' );
		$customer->set_email( 'email@example.com' );
		$customer->save();

		return $customer->get_id();
	}

	/**
	 * Create a WooCommerce order and return its ID.
	 *
	 * Merges default billing fields with optional overrides.
	 *
	 * @param array $data Optional overrides for order fields.
	 * @return int        The newly created order ID.
	 */
	public function create_wc_order( array $data = array() ): int {
		$defaults = array(
			'billing_first_name' => 'Alice',
			'billing_last_name'  => 'Wonderland',
			'billing_email'      => 'alice@example.com',
			'billing_phone'      => '0606060606',
			'billing_address_1'  => '4 place marc sangnier',
			'billing_city'       => 'Lyon',
			'billing_country'    => 'FR',
		);
		$order    = wc_create_order( array_merge( $defaults, $data ) );

		return $order->get_id();
	}
}
