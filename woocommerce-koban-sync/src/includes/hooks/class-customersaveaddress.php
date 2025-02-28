<?php
/**
 * CustomerSaveAddress class file.
 *
 * Handles synchronization of customer address changes to Koban CRM when a customer billing address is updated.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WCKoban\API;
use WCKoban\Logger;
use WCKoban\Serializers\UpsertThird;
use WCKoban\Utils\MetaUtils;

/**
 * Class CustomerSaveAddress
 *
 * Registers a WooCommerce hook to capture and sync a user's address changes to Koban CRM.
 */
class CustomerSaveAddress {

	/**
	 * An instance of the Koban API client.
	 *
	 * @var API $api
	 */
	private $api;

	/**
	 * Adds a WooCommerce hook to detect when a user saves their address.
	 */
	public function register(): void {
		add_action( 'woocommerce_customer_save_address', array( $this, 'schedule_customer_save_address' ), 20, 2 );
		add_action( 'wckoban_handle_customer_save_address', array( $this, 'handle_customer_save_address' ), 10, 3 );
	}

	/**
	 * Registers the handler as a background task.
	 *
	 * @param int    $customer_id      The WP_User id.
	 * @param string $address_type  The address type ('shipping' | 'billing').
	 */
	public function schedule_customer_save_address( int $customer_id, string $address_type ): void {
		$workflow_id = uniqid( 'wkf_', true );

		Logger::debug( $workflow_id, "Scheduling background billing address sync for customer: {$customer_id}" );

		as_enqueue_async_action(
			'wckoban_handle_customer_save_address',
			array(
				'customer_id'  => $customer_id,
				'address_type' => $address_type,
				'workflow_id'  => $workflow_id,
			),
			'koban-sync'
		);
	}

	/**
	 * Called whenever a user saves an address in WooCommerce.
	 * Initiates a small workflow to update Koban if it's a billing address.
	 *
	 * @param int    $customer_id WordPress User ID.
	 * @param string $address_type Address type being saved (e.g., 'billing' or 'shipping').
	 * @param string $workflow_id The Workflow ID.
	 */
	public function handle_customer_save_address( int $customer_id, string $address_type, string $workflow_id ): void {
		Logger::debug(
			$workflow_id,
			'Detected customer save address',
			array(
				'customer_id'  => $customer_id,
				'address_type' => $address_type,
			)
		);

		$steps = array(
			array( $this, 'check_data_integrity' ),
			array( $this, 'update_koban_third' ),
		);
		$data  = array(
			'customer_id'  => $customer_id,
			'address_type' => $address_type,
		);

		// TODO: Get last failed step.
		$last_failed_step = null;
		// Run the workflow through our state machine.
		$state     = new StateMachine( $steps, $workflow_id, $data, $last_failed_step );
		$this->api = new API( $workflow_id );
		$state->process_steps();
	}

	/**
	 * Ensures that the customer ID is valid.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True if valid, false otherwise.
	 */
	public function check_data_integrity( StateMachine $state ): bool {
		$customer_id = $state->get_data( 'customer_id' );

		if ( ! get_user_by( 'id', $customer_id ) ) {
			/* translators: %s: the WooCommerce Customer ID */
			return $state->stop( sprintf( __( 'Invalid customer ID: %s', 'woocommerce-koban-sync' ), $customer_id ) );
		}
		return $state->success();
	}

	/**
	 * Updates the Koban "Third" record if the user is synced and the address is billing.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True on success, false on failure.
	 */
	public function update_koban_third( StateMachine $state ): bool {
		$customer_id      = $state->get_data( 'customer_id' );
		$address_type     = $state->get_data( 'address_type' );
		$koban_third_guid = MetaUtils::get_koban_third_guid( $customer_id );

		// Only update Koban if a GUID already exists and the address is billing.
		if ( $koban_third_guid && 'billing' === $address_type ) {
			$third_payload = ( new UpsertThird() )->user_to_koban_third( get_user_by( 'id', $customer_id ) );

			if ( $this->api->upsert_user( $third_payload, $koban_third_guid ) ) {
				return $state->success( __( 'Updated Koban Third with new billing details.', 'woocommerce-koban-sync' ) );
			}
			return $state->failed( __( 'Could not update Koban Third.', 'woocommerce-koban-sync' ) );
		}

		// No update is needed if there's no Koban GUID or it's not a billing address.
		return $state->success(
			__( 'Update not necessary, user either not synced yet or address was not billing.', 'woocommerce-koban-sync' )
		);
	}
}
