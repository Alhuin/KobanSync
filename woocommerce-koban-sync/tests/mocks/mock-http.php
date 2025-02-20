<?php
/**
 * Simulates HTTP requests by using queued mock responses instead of making real API calls.
 * This helps keep test runs predictable and not dependent on external services.
 *
 * @package WooCommerceKobanSync\Tests
 */

namespace WCKoban\Tests\Mocks;

global $mock_response_queue, $wp_remote_requests;

$mock_response_queue = array();
$wp_remote_requests  = array();


/**
 * Adds a list of mock responses to the queue, which will be returned on the next HTTP request.
 *
 * @param array $responses The mock response objects to enqueue.
 */
function set_next_responses( array $responses ): void {
	global $mock_response_queue;

	foreach ( $responses as $response ) {
		$mock_response_queue[] = $response->response;
	}
}

/**
 * Intercepts WordPress HTTP requests and serves a mock response if available.
 * Stores the intercepted request in a global array for testsing purpose
 *
 * @param  mixed  $pre  Default return value for the filter (unused).
 * @param  array  $args The args passed to wp_remote_request().
 * @param  string $url  The request URL.
 * @return array|mixed If a mock response is queued, returns that array. Otherwise returns the original $pre.
 */
add_filter(
	'pre_http_request',
	function ( $pre, array $args, $url ) {
		global $mock_response_queue, $wp_remote_requests;

		$wp_remote_requests[] = array(
			'url'    => $url,
			'method' => $args['method'],
			'body'   => $args['body'],
		);

		if ( ! empty( $mock_response_queue ) ) {
			$response = array_shift( $mock_response_queue );

			return $response;
		}

		return $pre;
	},
	10,
	3
);
