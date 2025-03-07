<?php
/**
 * CustomerSaveAddressHook class file.
 *
 * Handles synchronization of customer address changes to Koban CRM when a customer billing address is updated.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WCKoban\API;
use WCKoban\Logger;
use WCKoban\Serializers\ThirdSerializer;
use WCKoban\Utils\MetaUtils;

/**
 * Class CustomerSaveAddressHook
 *
 * Registers a WooCommerce hook to capture and sync a user's address changes to Koban CRM.
 */
class CustomerSaveAddressHook {

	/**
	 * The number of allowed retries before definitive failure.
	 *
	 * @var int
	 */
	private static int $max_retries = 2;

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
		add_action( 'wckoban_handle_customer_save_address', array( $this, 'handle_customer_save_address' ), 10, 4 );
	}

	/**
	 * Registers the handler as a background task.
	 *
	 * @param int    $customer_id      The WP_User id.
	 * @param string $address_type  The address type ('shipping' | 'billing').
	 */
	public function schedule_customer_save_address( int $customer_id, string $address_type ): void {
		$workflow_id = uniqid( 'wkf_', true );

		if ( 'billing' === $address_type ) {
			Logger::record_workflow_schedule( $workflow_id, 'Customer Address', "Scheduling background sync for user: {$customer_id}" );

			as_enqueue_async_action(
				'wckoban_handle_customer_save_address',
				array(
					'customer_id'  => $customer_id,
					'address_type' => $address_type,
					'workflow_id'  => $workflow_id,
					'attempt'      => 0,
				),
				'koban-sync'
			);
		} else {
			Logger::debug( $workflow_id, "Skipping customer save address for address_type == {$address_type}" );
		}
	}

	/**
	 * Called whenever a user saves an address in WooCommerce.
	 * Initiates a small workflow to update Koban if it's a billing address.
	 *
	 * @param int    $customer_id WordPress User ID.
	 * @param string $address_type Address type being saved (e.g., 'billing' or 'shipping').
	 * @param string $workflow_id The Workflow ID.
	 * @param int    $attempt The current retry number, 0 if initial attempt.
	 */
	public function handle_customer_save_address( int $customer_id, string $address_type, string $workflow_id, int $attempt ): void {
		Logger::debug(
			$workflow_id,
			'Detected customer save address',
			array(
				'customer_id'  => $customer_id,
				'address_type' => $address_type,
				'workflow_id'  => $workflow_id,
			)
		);

		$steps = array(
			array( $this, 'check_data_integrity' ),
			array( $this, 'update_koban_third' ),
		);
		$data  = array(
			'customer_id'  => $customer_id,
			'address_type' => $address_type,
			'workflow_id'  => $workflow_id,
		);

		$failed_step = MetaUtils::get_koban_workflow_failed_step_for_user_id( $customer_id );
		$state       = new StateMachine( $steps, $data, $failed_step );
		$this->api   = new API( $workflow_id );
		$state->process_steps();
		$this->handle_exit( $state, $attempt );
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
			return $state->failed(
				/* translators: %s: the WooCommerce Customer ID */
				sprintf( __( 'Invalid customer ID: %s', 'woocommerce-koban-sync' ), $customer_id ),
				false
			);
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
			$third_payload = ( new ThirdSerializer() )->from_user( get_user_by( 'id', $customer_id ) );

			if ( $this->api->upsert_user( $third_payload, $koban_third_guid ) ) {
				return $state->success( __( 'Updated Koban Third with new billing details.', 'woocommerce-koban-sync' ) );
			}
			return $state->failed( __( 'Could not update Koban Third.', 'woocommerce-koban-sync' ) );
		}

		// No update is needed if there's no Koban GUID or it's not a billing address.
		return $state->stop(
			__( 'Update not necessary, user either not synced yet or address was not billing.', 'woocommerce-koban-sync' )
		);
	}

	/**
	 * Handles the final state of the workflow, scheduling retries if necessary.
	 *
	 * @param StateMachine $state   The workflow state manager.
	 * @param int          $attempt The current attempt count.
	 */
	private function handle_exit( StateMachine $state, int $attempt ): void {
		$status      = $state->get_status();
		$data        = $state->get_data();
		$customer_id = $data['customer_id'];

		MetaUtils::set_koban_workflow_status_for_user_id( $customer_id, $status );

		if ( StateMachine::STATUS_FAILED === $status && $state->retry && $attempt < self::$max_retries ) {
			MetaUtils::set_koban_workflow_failed_step_for_user_id( $data['customer_id'], $state->failed_step );

			as_schedule_single_action(
				time() + 60,
				'wckoban_handle_customer_save_address',
				array(
					'customer_id'  => $customer_id,
					'address_type' => $data['address_type'],
					'workflow_id'  => $data['workflow_id'],
					'attempt'      => $attempt + 1,
				),
				'koban-sync'
			);
		} else {
			MetaUtils::set_koban_workflow_failed_step_for_user_id( $customer_id, null );
		}
	}
}
