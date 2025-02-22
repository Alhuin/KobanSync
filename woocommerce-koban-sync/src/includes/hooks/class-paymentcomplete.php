<?php
/**
 * PaymentComplete class file.
 *
 * Handles synchronization of customer data with Koban after a WooCommerce payment is completed.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WCKoban\API;
use WCKoban\Logger;
use WCKoban\Serializers\Order;
use WCKoban\Serializers\UpsertThird;

/**
 * Class PaymentComplete
 *
 * Registers a WooCommerce hook on payment completion and send User & Order data to Koban
 */
class PaymentComplete {

	/**
	 * Register the payment complete handler with WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ) );
	}

	/**
	 * Handles synchronization of customer data with Koban after a payment completes.
	 * Checks for an existing Koban Third, creates one if not found, and then creates a Koban invoice.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function handle_payment_complete( int $order_id ): void {
		Logger::info(
			'Detected payment complete',
			array( 'order_id' => $order_id )
		);

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			Logger::error(
				'Order not found during payment complete hook',
				array( 'order_id' => $order_id )
			);

			return;
		}

		// Avoid re-sending if we already have an invoice GUID.
		if ( get_post_meta( $order_id, 'koban_invoice_guid', true ) ) {
			return;
		}

		$user_id = $order->get_user_id(); // Will be 0 for guest checkouts.
		Logger::info(
			'Processing user for payment complete',
			array( 'user_id' => $user_id )
		);

		$koban_third_guid = '';

		if ( $user_id ) {
			// Registered user.
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				Logger::error(
					'Failed to retrieve WP user by ID',
					array( 'user_id' => $user_id )
				);

				return;
			}
			$koban_third_guid = get_user_meta( $user->ID, 'koban_guid', true );
		} else {
			Logger::info( 'User is a guest, proceeding without a WP user account' );
		}

		// If no local Koban GUID, attempt to find an existing record in Koban by email.
		if ( ! $koban_third_guid ) {
			$koban_third_guid = ( new API() )->find_user_by_email( $order->get_billing_email() );

			if ( $user_id && $koban_third_guid ) {
				update_user_meta( $user_id, 'koban_guid', $koban_third_guid );
			}
		}

		// If still no Koban record, create a new one.
		if ( ! $koban_third_guid ) {
			$third_payload    = ( new UpsertThird() )->order_to_koban_third( $order );
			$koban_third_guid = ( new API() )->upsert_user( $third_payload );

			if ( ! $koban_third_guid ) {
				Logger::error(
					'Could not create Koban Third, aborting invoice creation',
					array( 'order_id' => $order_id )
				);

				return;
			}

			if ( $user_id ) {
				update_user_meta( $user_id, 'koban_guid', $koban_third_guid );
			}
		}

		// Create the invoice in Koban.
		if ( $koban_third_guid ) {
			$invoice_payload    = ( new Order() )->to_koban_invoice( $order, $koban_third_guid );
			$koban_invoice_guid = ( new API() )->create_invoice( $invoice_payload );

			if ( ! $koban_invoice_guid ) {
				Logger::error(
					'Could not create Koban Invoice, aborting Payment and PDF creation',
					array( 'order_id' => $order_id )
				);

				return;
			}

			$order->update_meta_data( 'koban_invoice_guid', $koban_invoice_guid );
			$order->save();

			$payment_payload = ( new Order() )->to_koban_payment( $order, $koban_invoice_guid );

			if ( ( new API() )->create_payment( $payment_payload ) ) {
				$pdf_url = ( new API() )->get_invoice_pdf( $koban_invoice_guid );

				if ( $pdf_url ) {
					$order->update_meta_data( 'koban_invoice_pdf_path', $pdf_url );
					$order->save();
				}
			}
		}
	}
}
