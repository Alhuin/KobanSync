<?php
/**
 * WCKoban_CustomerSaveAddress class file.
 *
 * Handles synchronization of customer address changes to Koban CRM when a customer billing address is updated.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WCKoban\API;
use WCKoban\Logger;
use WCKoban\Serializers\WCKoban_UpsertThird;

/**
 * Class WCKoban_CustomerSaveAddress
 *
 * Registers a WooCommerce hook on customer save address and send Address data to Koban
 */
class WCKoban_CustomerSaveAddress {

	/**
	 * Register the customer save address handler with WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_customer_save_address', array( $this, 'handle_customer_save_address' ), 20, 2 );
	}

	/**
	 * Handles updates to a customer's billing address by updating the corresponding Koban record, if one exists.
	 *
	 * @param int    $customer_id      WordPress User ID.
	 * @param string $address_type  The type of the updated address ("billing"|"shipping").
	 */
	public function handle_customer_save_address( int $customer_id, string $address_type ): void {
		$koban_third_guid = get_user_meta( $customer_id, 'koban_guid', true );

		if ( $koban_third_guid && 'billing' === $address_type ) {
			$third_payload = ( new WCKoban_UpsertThird() )->user_to_koban_third( get_user_by( 'id', $customer_id ) );

			Logger::info(
				'Customer billing address updated, syncing to Koban',
				array(
					'address_type' => $address_type,
					'koban_guid'   => $koban_third_guid,
					'payload'      => $third_payload,
				)
			);
			( new API() )->upsert_user( $third_payload, $koban_third_guid );
		}
	}
}
