<?php

namespace WCKoban\Admin;

use WCKoban\Logger;

add_action(
	'init',
	function () {
		// Register a custom query variable
		add_rewrite_tag( '%wckoban_invoice_pdf%', '([^&]+)' );
	}
);

/**
 * Checks if we have ?wckoban_invoice_pdf=1, then serves the corresponding PDF
 * if the current user is allowed (admin or the order’s owner).
 */
function wckoban_serve_protected_pdf() {
	if ( ! isset( $_GET['wckoban_invoice_pdf'] ) ) {
		return; // Not our query, do nothing
	}

	// Typically you'll have an 'order_id' param, e.g. ...?wckoban_invoice_pdf=1&order_id=123
	$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
	if ( ! $order_id ) {
		wp_die( 'Invalid order_id.', 'Error', array( 'response' => 400 ) );
	}

	// Load the order
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_die( 'Order not found.', 'Error', array( 'response' => 404 ) );
	}

	// Check if user is admin or order owner
	$current_user_id = get_current_user_id();
	$order_user_id   = $order->get_user_id(); // 0 for guest

	// If no user is logged in or doesn't match & not admin, block
	if ( ! current_user_can( 'administrator' ) && ( $current_user_id !== (int) $order_user_id ) ) {
		wp_die( 'You are not allowed to view this file.', 'Forbidden', array( 'response' => 403 ) );
	}

	// Retrieve the local disk path from meta
	$pdf_path = $order->get_meta( 'koban_invoice_pdf_path', true );
	if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
		wp_die( 'File not found.', 'Error', array( 'response' => 404 ) );
	}

	// All good. Output it as PDF.
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . basename( $pdf_path ) . '"' );
	header( 'Content-Length: ' . filesize( $pdf_path ) );
	readfile( $pdf_path );
	exit; // always exit after raw file output
}
add_action( 'template_redirect', __NAMESPACE__ . '\\wckoban_serve_protected_pdf' );

/**
 * Add a "View Invoice" action button in the My Account -> My Orders list
 * if the order has a PDF invoice path.
 */
function wckoban_add_invoice_action_to_my_orders( $actions, $order ) {
	Logger::info( 'INVOICE LINK' );
	// Check if there's an invoice PDF path stored in post meta
	// $pdf_path = get_post_meta( $order->get_id(), 'koban_invoice_pdf_path', true );
	$pdf_path = $order->get_meta( 'koban_invoice_pdf_path', true );
	Logger::info(
		'pdf path',
		array(
			'id'   => $order->get_id(),
			'path' => $pdf_path,
		)
	);
	if ( $pdf_path ) {
		// Build the protected link
		// e.g. https://mysite.com/my-account/orders/?wckoban_invoice_pdf=1&order_id=123
		$protected_url = add_query_arg(
			array(
				'wckoban_invoice_pdf' => 1,
				'order_id'            => $order->get_id(),
			),
			wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) )
		);

		Logger::info(
			'url',
			array(
				'url' => $protected_url,
			)
		);
		// Add an action button
		$actions['invoice_pdf'] = array(
			'url'  => $protected_url,
			'name' => __( 'View Invoice PDF', 'textdomain' ),
			// You can also specify an icon if you like:
			// 'icon' => 'some-css-class'
		);
	}

	return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', __NAMESPACE__ . '\\wckoban_add_invoice_action_to_my_orders', 10, 2 );
