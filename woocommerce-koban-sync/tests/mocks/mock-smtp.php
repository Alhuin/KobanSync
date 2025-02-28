<?php
/**
 * Simulates HTTP requests by using queued mock responses instead of making real API calls.
 * This helps keep test runs predictable and not dependent on external services.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests\Mocks;

global $sent_emails;

$sent_emails = array();

add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) {
		global $sent_emails;
		$sent_emails[] = $atts;

		return true;
	},
	10,
	2
);
