<?php
/**
 * Hooks related to WooCommerce order processing, product updates, and customer updates
 * that synchronize data with Koban CRM.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'serializers.php';
require_once plugin_dir_path( __FILE__ ) . 'koban-api.php';

/**
 * Extracts relevant billing fields from a WooCommerce Order.
 *
 * @param \WC_Order $order The WooCommerce order object.
 *
 * @return array An associative array of billing data.
 */
function wckoban_billing_data_from_order( \WC_Order $order ): array {
	return [
		'first_name' => $order->get_billing_first_name(),
		'last_name'  => $order->get_billing_last_name(),
		'email'      => $order->get_billing_email(),
		'phone'      => $order->get_billing_phone(),
		'address_1'  => $order->get_billing_address_1(),
		'address_2'  => $order->get_billing_address_2(),
		'city'       => $order->get_billing_city(),
		'state'      => $order->get_billing_state(),
		'postcode'   => $order->get_billing_postcode(),
		'country'    => $order->get_billing_country(),
	];
}

/**
 * Retrieves saved billing fields from a WordPress user by ID.
 *
 * @param int $user_id The WordPress user ID.
 *
 * @return array An associative array of billing data.
 */
function wckoban_billing_data_from_user_id( int $user_id ): array {
	return [
		'first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
		'last_name'  => get_user_meta( $user_id, 'billing_last_name', true ),
		'email'      => get_user_meta( $user_id, 'billing_email', true ),
		'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
		'address_1'  => get_user_meta( $user_id, 'billing_address_1', true ),
		'address_2'  => get_user_meta( $user_id, 'billing_address_2', true ),
		'city'       => get_user_meta( $user_id, 'billing_city', true ),
		'state'      => get_user_meta( $user_id, 'billing_state', true ),
		'postcode'   => get_user_meta( $user_id, 'billing_postcode', true ),
		'country'    => get_user_meta( $user_id, 'billing_country', true ),
	];
}


/**
 * Creates or updates a "Third" record in Koban.
 * If $koban_third_guid_to_update is null, creates a new record. Otherwise updates existing.
 *
 * @param array $thirdPayload Koban-compatible user data.
 * @param string|null $koban_third_guid_to_update Existing Koban GUID if available.
 *
 * @return string|null Returns the Koban GUID if successful, or null otherwise.
 */
function wckoban_upsert_koban_third( array $thirdPayload, ?string $koban_third_guid_to_update = null ): ?string {
	$koban_api        = new WCKoban_API();
	$koban_third_guid = $koban_api->upsert_user( $thirdPayload, $koban_third_guid_to_update );

	if ( ! $koban_third_guid ) {
		WCKoban_Logger::error(
			'Failed to upsert Koban Third',
			[ 'payload' => $thirdPayload ]
		);
	} else {
		WCKoban_Logger::info(
			'Successfully upserted the Koban Third',
			[ 'payload' => $thirdPayload ]
		);
	}

	return $koban_third_guid;
}

/**
 * Creates an invoice in Koban and returns its GUID.
 *
 * @param array $invoicePayload Koban-compatible invoice data.
 *
 * @return string|null The Koban invoice GUID if successful, or null otherwise.
 */
function wckoban_create_invoice( array $invoicePayload ): ?string {
	$koban_api          = new WCKoban_API();
	$koban_invoice_guid = $koban_api->create_invoice( $invoicePayload );

	if ( ! $koban_invoice_guid ) {
		WCKoban_Logger::error(
			'Failed to create the Koban Invoice',
			[ 'payload' => $invoicePayload ]
		);
	} else {
		WCKoban_Logger::info(
			'Successfully created the Koban Invoice',
			[ 'payload' => $invoicePayload ]
		);
	}

	return $koban_invoice_guid;
}

/**
 * Fetches the PDF link of an invoice from Koban by its GUID.
 *
 * @param string $koban_invoice_guid The invoice GUID.
 *
 * @return string|null The PDF link if successful, or null otherwise.
 */
function wckoban_get_invoice_pdf( string $koban_invoice_guid ): ?string {
	$koban_api = new WCKoban_API();
	$pdf_link  = $koban_api->get_invoice_pdf( $koban_invoice_guid );

	if ( ! $pdf_link ) {
		WCKoban_Logger::error(
			'Failed to retrieve Koban Invoice PDF',
			[ 'invoice_guid' => $koban_invoice_guid ]
		);
	} else {
		WCKoban_Logger::info(
			'Successfully retrieved Koban Invoice PDF',
			[ 'pdf_link' => $pdf_link ]
		);
	}

	return $pdf_link;
}


/**
 * Creates or updates a product record in Koban and returns a success boolean.
 *
 * @param array $productPayload Product data serialized for Koban.
 *
 * @return bool  True if upsert was successful, false otherwise.
 */
function wckoban_upsert_product( array $productPayload ): bool {
	$koban_api = new WCKoban_API();
	$success   = $koban_api->upsert_product( $productPayload );

	if ( ! $success ) {
		WCKoban_Logger::error(
			'Failed to upsert Koban Product',
			[ 'payload' => $productPayload ]
		);
	} else {
		WCKoban_Logger::info(
			'Successfully upserted the Koban Product',
			[ 'payload' => $productPayload ]
		);
	}

	return $success;
}

/**
 * Stores a Koban Third GUID in user meta.
 *
 * @param int $user_id WordPress user ID.
 * @param string $koban_third_guid Koban Third GUID.
 */
function wckoban_add_koban_third_guid_to_user_meta( int $user_id, string $koban_third_guid ): void {
	update_user_meta( $user_id, 'koban_guid', $koban_third_guid );
}

/**
 * Stores the Koban Invoice GUID in order meta.
 *
 * @param \WC_Order $order The WooCommerce order object.
 * @param string $koban_invoice_guid Koban Invoice GUID.
 */
function wckoban_add_koban_invoice_guid_to_order_meta( WC_Order $order, string $koban_invoice_guid ): void {
	$order->update_meta_data( 'koban_invoice_guid', $koban_invoice_guid );
	$order->save();
}

/**
 * Adds a Koban Product GUID to product meta.
 *
 * @param \WC_Product $product The WooCommerce product object.
 * @param string $koban_product_guid The Koban Product GUID.
 */
function wckoban_add_koban_product_guid_to_product_meta( WC_Product $product, string $koban_product_guid ): void {
	$product->update_meta_data( 'koban_guid', $koban_product_guid );
	$product->save();
}


/**
 * Handles synchronization of customer data with Koban after a payment completes.
 * Checks for an existing Koban Third, creates one if not found, and then creates a Koban invoice.
 *
 * @param int $order_id The WooCommerce order ID.
 */
add_action( 'woocommerce_payment_complete', 'wckoban_on_payment_complete' );
function wckoban_on_payment_complete( int $order_id ): void {
	WCKoban_Logger::info(
		'Payment complete hook triggered',
		[ 'order_id' => $order_id ]
	);

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		WCKoban_Logger::error(
			'Order not found during payment complete hook',
			[ 'order_id' => $order_id ]
		);

		return;
	}

	// Avoid re-sending if we already have an invoice GUID.
	if ( get_post_meta( $order_id, 'koban_invoice_guid', true ) ) {
		return;
	}

	$user_id = $order->get_user_id(); // Will be 0 for guest checkouts.
	WCKoban_Logger::info(
		'Processing user for payment complete',
		[ 'user_id' => $user_id ]
	);

	$koban_third_guid = '';

	if ( $user_id ) {
		// Registered user
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			WCKoban_Logger::error(
				'Failed to retrieve WP user by ID',
				[ 'user_id' => $user_id ]
			);

			return;
		}
		$koban_third_guid = get_user_meta( $user->ID, 'koban_guid', true );
	} else {
		WCKoban_Logger::info( 'User is a guest, proceeding without a WP user account' );
	}

	// If no local Koban GUID, attempt to find an existing record in Koban by email.
	if ( ! $koban_third_guid ) {
		$koban_api        = new WCKoban_API();
		$email            = $order->get_billing_email();
		$koban_third_guid = $koban_api->find_user_by_email( $email );

		if ( $user_id && $koban_third_guid ) {
			wckoban_add_koban_third_guid_to_user_meta( $user->ID, $koban_third_guid );
		}
	}

	// If still no Koban record, create a new one.
	if ( ! $koban_third_guid ) {
		$thirdPayload     = WCKoban_Serializer::billing_to_koban_third(
			wckoban_billing_data_from_order( $order )
		);
		$koban_third_guid = wckoban_upsert_koban_third( $thirdPayload );

		if ( ! $koban_third_guid ) {
			WCKoban_Logger::error(
				'Could not create Koban Third, aborting invoice creation',
				[ 'order_id' => $order_id ]
			);

			return;
		}

		if ( $user_id ) {
			wckoban_add_koban_third_guid_to_user_meta( $user_id, $koban_third_guid );
		}
	}

	// Create the invoice in Koban
	if ( $koban_third_guid ) {
		$invoicePayload     = WCKoban_Serializer::order_to_koban_invoice( $order, $koban_third_guid );
		$koban_invoice_guid = wckoban_create_invoice( $invoicePayload );

		if ( ! $koban_invoice_guid ) {
			WCKoban_Logger::error(
				'Could not create Koban Invoice, aborting PDF retrieval',
				[ 'order_id' => $order_id ]
			);

			return;
		}
		wckoban_add_koban_invoice_guid_to_order_meta( $order, $koban_invoice_guid );
		wckoban_get_invoice_pdf( $koban_invoice_guid );
	}
}

/**
 * Handles updates to a customer's billing address by updating the corresponding Koban record, if one exists.
 */
add_action( 'woocommerce_customer_save_address', 'wckoban_on_customer_save_address', 20, 2 );
function wckoban_on_customer_save_address( $customer_id, $address_type ): void {
	$koban_third_guid = get_user_meta( $customer_id, "koban_guid", true );

	if ( $koban_third_guid && $address_type === "billing" ) {
		$thirdPayload = WCKoban_Serializer::billing_to_koban_third(
			wckoban_billing_data_from_user_id( $customer_id )
		);

		WCKoban_Logger::info( 'Customer billing address updated, syncing to Koban', [
			'address_type' => $address_type,
			'koban_guid'   => $koban_third_guid,
			'payload'      => $thirdPayload,
		] );
		wckoban_upsert_koban_third( $thirdPayload, $koban_third_guid );
	}
}


/**
 * Syncs or updates a product in Koban when a WooCommerce product is created or updated.
 */
add_action( 'woocommerce_new_product', 'wckoban_on_product_update', 10, 1 );
add_action( 'woocommerce_update_product', 'wckoban_on_product_update', 10, 1 );
function wckoban_on_product_update( $product_id ): void {
	WCKoban_Logger::info(
		'Detected product create/update',
		[ 'product_id' => $product_id ]
	);

	$transient_key = 'wckoban_product_processing_' . $product_id;
	if ( get_transient( $transient_key ) ) {
		WCKoban_Logger::info(
			'Skipping repeated product update trigger due to transient lock',
			[ 'product_id' => $product_id ]
		);

		return;
	}

	set_transient( $transient_key, true, 2 );
	$product = wc_get_product( $product_id );

	if ( ! $product ) {
		WCKoban_Logger::error(
			'Could not retrieve WooCommerce product',
			[ 'product_id' => $product_id ]
		);

		return;
	}

	$productPayload = WCKoban_Serializer::product_to_koban( $product );
	WCKoban_Logger::info(
		'Serializing product data for Koban upsert',
		[ 'serialized' => $productPayload ]
	);

	wckoban_upsert_product( $productPayload );
}

// TODO: Listen to Koban event customer updated / created