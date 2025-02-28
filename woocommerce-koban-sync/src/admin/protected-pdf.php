<?php
/**
 * This file contains functions related to serving protected PDF invoices and adding invoice actions
 * in the "My Orders" section of the WooCommerce account page. It handles access control, security checks,
 * and URL generation for PDF invoices.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Admin;

use WC_Order;
use WCKoban\Utils\MetaUtils;

add_action(
	'init',
	function () {
		add_rewrite_tag( '%wckoban_invoice_pdf%', '([^&]+)' );
	}
);

/**
 * Checks if we have ?wckoban_invoice_pdf=1, then serves the corresponding PDF
 * if the current user is allowed (admin or the order’s owner).
 */
function wckoban_serve_protected_pdf() {
	if ( ! isset( $_GET['wckoban_invoice_pdf'] ) ) {
		return;
	}

	$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
	if ( ! $order_id ) {
		wp_die(
			esc_html__( 'Invalid Order ID.', 'woocommerce-koban-sync' ),
			esc_html__( 'Error', 'woocommerce-koban-sync' ),
			array( 'response' => 400 )
		);
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_die(
			esc_html__( 'Order not found.', 'woocommerce-koban-sync' ),
			esc_html__( 'Error', 'woocommerce-koban-sync' ),
			array( 'response' => 404 )
		);
	}

	$current_user_id = get_current_user_id();
	$order_user_id   = $order->get_user_id();

	if ( ! current_user_can( 'manage_options' ) && ( $current_user_id !== $order_user_id ) ) {
		wp_die(
			esc_html__( 'You are not allowed to view this file.', 'woocommerce-koban-sync' ),
			esc_html__( 'Forbidden', 'woocommerce-koban-sync' ),
			array( 'response' => 403 )
		);
	}

	$pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );
	if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
		wp_die(
			esc_html__( 'File not found.', 'woocommerce-koban-sync' ),
			esc_html__( 'Error', 'woocommerce-koban-sync' ),
			array( 'response' => 404 )
		);
	}

	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . basename( $pdf_path ) . '"' );
	header( 'Content-Length: ' . filesize( $pdf_path ) );
	readfile( $pdf_path );
	exit;
}
add_action( 'template_redirect', __NAMESPACE__ . '\\wckoban_serve_protected_pdf' );

/**
 * Add a "View Invoice" action button in the My Account -> My Orders list
 * if the order has a PDF invoice path.
 *
 * @param array    $actions    Array containing the WC actions.
 * @param WC_Order $order   The WC_Order.
 *
 * @return array
 */
function wckoban_add_invoice_action_to_my_orders( array $actions, WC_Order $order ): array {
	$pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

	if ( $pdf_path ) {
		$protected_url = add_query_arg(
			array(
				'wckoban_invoice_pdf' => 1,
				'order_id'            => $order->get_id(),
			),
			wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) )
		);

		$actions['invoice_pdf'] = array(
			'url'  => $protected_url,
			'name' => __( 'View Invoice PDF', 'woocommerce-koban-sync' ),
			// 'icon' => 'some-css-class'
		);
	}

	return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', __NAMESPACE__ . '\\wckoban_add_invoice_action_to_my_orders', 10, 2 );
