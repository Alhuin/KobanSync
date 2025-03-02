<?php
/**
 * Class: MetaUtils.php
 *
 * Utility methods for setting and retrieving Koban-related meta data on WooCommerce
 * orders, products, users, and categories.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Utils;

use WP_User;
use WP_Term;
use WC_Order;
use WC_Product;

/**
 * Class MetaUtils
 *
 * Provides static functions to manipulate Koban meta keys
 * across WooCommerce entities (orders, products, customers, categories).
 */
class MetaUtils {

	/**
	 * Set a Koban Invoice GUID on an order.
	 *
	 * @param WC_Order $order            WooCommerce order instance.
	 * @param string   $koban_invoice_guid Invoice GUID to store.
	 */
	public static function set_koban_invoice_guid_for_order( WC_Order $order, string $koban_invoice_guid ): void {
		$order->update_meta_data( KOBAN_INVOICE_GUID_META_KEY, $koban_invoice_guid );
		$order->save();
	}

	/**
	 * Set a Koban Invoice GUID on an order by its ID.
	 *
	 * @param int    $order_id           Order ID.
	 * @param string $koban_invoice_guid Invoice GUID to store.
	 */
	public static function set_koban_invoice_guid_for_order_id( int $order_id, string $koban_invoice_guid ): void {
		$order = wc_get_order( $order_id );
		self::set_koban_invoice_guid_for_order( $order, $koban_invoice_guid );
	}

	/**
	 * Get a Koban Invoice GUID from an order.
	 *
	 * @param WC_Order $order WooCommerce order instance.
	 * @return ?string Invoice GUID or null if missing.
	 */
	public static function get_koban_invoice_guid( WC_Order $order ): ?string {
		return $order->get_meta( KOBAN_INVOICE_GUID_META_KEY, true );
	}

	/**
	 * Set a Koban Payment GUID on an order.
	 *
	 * @param WC_Order $order            WooCommerce order instance.
	 * @param string   $koban_payment_guid Payment GUID to store.
	 */
	public static function set_koban_payment_guid_for_order( WC_Order $order, string $koban_payment_guid ): void {
		$order->update_meta_data( KOBAN_PAYMENT_GUID_META_KEY, $koban_payment_guid );
		$order->save();
	}

	/**
	 * Set a Koban Payment GUID on an order by its ID.
	 *
	 * @param int    $order_id           Order ID.
	 * @param string $koban_payment_guid Payment GUID to store.
	 */
	public static function set_koban_payment_guid_for_order_id( int $order_id, string $koban_payment_guid ): void {
		$order = wc_get_order( $order_id );
		self::set_koban_payment_guid_for_order( $order, $koban_payment_guid );
	}

	/**
	 * Get a Koban Payment GUID from an order.
	 *
	 * @param WC_Order $order WooCommerce order instance.
	 * @return ?string Payment GUID or null if missing.
	 */
	public static function get_koban_payment_guid( WC_Order $order ): ?string {
		return $order->get_meta( KOBAN_PAYMENT_GUID_META_KEY, true );
	}

	/**
	 * Set a Koban Invoice PDF path on an order.
	 *
	 * @param WC_Order $order                 WooCommerce order instance.
	 * @param string   $koban_invoice_pdf_path Path to store.
	 */
	public static function set_koban_invoice_pdf_path_for_order( WC_Order $order, string $koban_invoice_pdf_path ): void {
		$order->update_meta_data( KOBAN_INVOICE_PDF_PATH_META_KEY, $koban_invoice_pdf_path );
		$order->save();
	}

	/**
	 * Set a Koban Invoice PDF path on an order by its ID.
	 *
	 * @param int    $order_id               Order ID.
	 * @param string $koban_invoice_pdf_path Path to store.
	 */
	public static function set_koban_invoice_pdf_path_for_order_id( int $order_id, string $koban_invoice_pdf_path ): void {
		$order = wc_get_order( $order_id );
		self::set_koban_invoice_pdf_path_for_order( $order, $koban_invoice_pdf_path );
	}

	/**
	 * Get a Koban Invoice PDF path from an order.
	 *
	 * @param WC_Order $order WooCommerce order instance.
	 * @return ?string PDF path or null if missing.
	 */
	public static function get_koban_invoice_pdf_path( WC_Order $order ): ?string {
		return $order->get_meta( KOBAN_INVOICE_PDF_PATH_META_KEY, true );
	}

	/**
	 * Set a Koban Third GUID on a customer.
	 *
	 * @param int    $user_id          User ID of the customer.
	 * @param string $koban_third_guid Third GUID to store.
	 */
	public static function set_koban_third_guid( int $user_id, string $koban_third_guid ): void {
		update_user_meta( $user_id, KOBAN_THIRD_GUID_META_KEY, $koban_third_guid );
	}

	/**
	 * Get a Koban Third GUID from a customer.
	 *
	 * @param int $user_id User ID of the customer.
	 * @return ?string The stored Third GUID or null if missing.
	 */
	public static function get_koban_third_guid( int $user_id ): ?string {
		return get_user_meta( $user_id, KOBAN_THIRD_GUID_META_KEY, true );
	}

	/**
	 * Set a Koban Product GUID on a product object.
	 *
	 * @param WC_Product $product            WooCommerce product instance.
	 * @param string     $koban_product_guid Product GUID to store.
	 */
	public static function set_koban_product_guid_for_product( WC_Product $product, string $koban_product_guid ): void {
		$product->update_meta_data( KOBAN_PRODUCT_GUID_META_KEY, $koban_product_guid );
		$product->save();
	}

	/**
	 * Set a Koban Product GUID by product ID.
	 *
	 * @param int    $product_id         Product ID.
	 * @param string $koban_product_guid Product GUID to store.
	 */
	public static function set_koban_product_guid_for_product_id( int $product_id, string $koban_product_guid ): void {
		$product = wc_get_product( $product_id );
		self::set_koban_product_guid_for_product( $product, $koban_product_guid );
	}

	/**
	 * Get a Koban Product GUID from a product.
	 *
	 * @param WC_Product $product WooCommerce product instance.
	 * @return ?string           The stored Product GUID or null if missing.
	 */
	public static function get_koban_product_guid( WC_Product $product ): ?string {
		return $product->get_meta( KOBAN_PRODUCT_GUID_META_KEY, true );
	}

	/**
	 * Set a Koban Category code on a category term.
	 *
	 * @param int    $category_id        Category term ID.
	 * @param string $koban_category_code Category code to store.
	 */
	public static function set_koban_category_code( int $category_id, string $koban_category_code ): void {
		update_term_meta( $category_id, KOBAN_CATEGORY_CODE_META_KEY, $koban_category_code );
	}

	/**
	 * Get a Koban Category code from a category term.
	 *
	 * @param int $category_id Category term ID.
	 * @return ?string         The stored category code or null if missing.
	 */
	public static function get_koban_category_code( int $category_id ): ?string {
		return get_term_meta( $category_id, KOBAN_CATEGORY_CODE_META_KEY, true );
	}

	/**
	 * Set a StateMachine failed step on an oder.
	 *
	 * @param WC_Order $order The WooCommerce Oder.
	 * @param ?string  $failed_step The failed_step, if any.
	 */
	public static function set_koban_workflow_failed_step_for_order( WC_Order $order, ?string $failed_step ): void {
		$order->update_meta_data( KOBAN_WORKFLOW_FAILED_STEP_META_KEY, $failed_step );
		$order->save();
	}

	/**
	 * Get a StateMachine failed step from an order.
	 *
	 * @param WC_Order $order The WooCommerce Order.
	 * @return ?string The failed step, if any.
	 */
	public static function get_koban_workflow_failed_step_for_order( WC_Order $order ): ?string {
		return $order->get_meta( KOBAN_WORKFLOW_FAILED_STEP_META_KEY, true );
	}

	/**
	 * Set a StateMachine status on an oder.
	 *
	 * @param WC_Order $order The WooCommerce Oder.
	 * @param string   $status The status.
	 */
	public static function set_koban_workflow_status_for_order( WC_Order $order, string $status ): void {
		$order->update_meta_data( KOBAN_WORKFLOW_STATUS_META_KEY, $status );
		$order->save();
	}

	/**
	 * Get a StateMachine status from an order.
	 *
	 * @param WC_Order $order The WooCommerce Order.
	 * @return ?string The status, if any.
	 */
	public static function get_koban_workflow_status_for_order( WC_Order $order ): ?string {
		return $order->get_meta( KOBAN_WORKFLOW_STATUS_META_KEY, true );
	}

	/**
	 * Set a StateMachine failed step on a user from its ID.
	 *
	 * @param int     $user_id The user ID.
	 * @param ?string $failed_step The failed_step, if any.
	 */
	public static function set_koban_workflow_failed_step_for_user_id( int $user_id, ?string $failed_step ): void {
		update_user_meta( $user_id, KOBAN_WORKFLOW_FAILED_STEP_META_KEY, $failed_step );
	}

	/**
	 * Get a StateMachine failed step from a user ID.
	 *
	 * @param int $user_id the user ID.
	 * @return ?string The failed step, if any.
	 */
	public static function get_koban_workflow_failed_step_for_user_id( int $user_id ): ?string {
		return get_user_meta( $user_id, KOBAN_WORKFLOW_FAILED_STEP_META_KEY, true );
	}

	/**
	 * Set a StateMachine status on a user from its ID.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $status The status.
	 */
	public static function set_koban_workflow_status_for_user_id( int $user_id, string $status ): void {
		update_user_meta( $user_id, KOBAN_WORKFLOW_STATUS_META_KEY, $status );
	}

	/**
	 * Get a StateMachine status from a user ID.
	 *
	 * @param int $user_id the user ID.
	 * @return string The status, if any.
	 */
	public static function get_koban_workflow_status_for_user_id( int $user_id ): ?string {
		return get_user_meta( $user_id, KOBAN_WORKFLOW_STATUS_META_KEY, true );
	}

	/**
	 * Set a StateMachine failed step on a product from its ID.
	 *
	 * @param int     $product_id The product ID.
	 * @param ?string $failed_step The failed_step, if any.
	 */
	public static function set_koban_workflow_failed_step_for_product_id( int $product_id, ?string $failed_step ): void {
		$product = wc_get_product( $product_id );

		if ( $product ) {
			$product->update_meta_data( KOBAN_WORKFLOW_FAILED_STEP_META_KEY, $failed_step );
			$product->save();
		}
	}

	/**
	 * Get a StateMachine failed step from a product ID.
	 *
	 * @param int $product_id the product ID.
	 * @return ?string The failed step, if any.
	 */
	public static function get_koban_workflow_failed_step_for_product_id( int $product_id ): ?string {
		$product = wc_get_product( $product_id );
		return $product ? $product->get_meta( KOBAN_WORKFLOW_FAILED_STEP_META_KEY, true ) : null;
	}

	/**
	 * Set a StateMachine status on a product from its ID.
	 *
	 * @param int    $product_id The product ID.
	 * @param string $status The status.
	 */
	public static function set_koban_workflow_status_for_product_id( $product_id, string $status ): void {
		$product = wc_get_product( $product_id );

		if ( $product ) {
			$product->update_meta_data( KOBAN_WORKFLOW_STATUS_META_KEY, $status );
			$product->save();
		}
	}

	/**
	 * Get a StateMachine status from a user ID.
	 *
	 * @param int $product_id the user ID.
	 * @return ?string The status, if any.
	 */
	public static function get_koban_workflow_status_for_product_id( $product_id ): ?string {
		$product = wc_get_product( $product_id );

		return $product ? $product->get_meta( KOBAN_WORKFLOW_STATUS_META_KEY, true ) : null;
	}
}
