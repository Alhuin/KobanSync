<?php
/**
 * PaymentComplete class file.
 *
 * Handles synchronization of customer data with Koban after a WooCommerce payment is completed.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WC_Order;
use WCKoban\API;
use WCKoban\Logger;
use WCKoban\Serializers\Order;
use WCKoban\Serializers\UpsertThird;
use WCKoban\Utils\MetaUtils;

/**
 * Class PaymentComplete
 *
 * Registers a WooCommerce hook that triggers upon payment completion, then proceeds through
 * a series of steps to ensure the order and customer data are synced to Koban CRM.
 */
class PaymentComplete {

	/**
	 * An instance of the Koban API client.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * Adds a WooCommerce hook to detect when a user completes a payment.
	 */
	public function register(): void {
		// Execute flow in the background with WooCommerce ActionScheduler.
		add_action( 'woocommerce_payment_complete', array( $this, 'schedule_payment_complete' ) );
		add_action( 'wckoban_handle_payment_complete', array( $this, 'handle_payment_complete' ) );
	}

	/**
	 * Schedules a background action to process payment completion.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function schedule_payment_complete( int $order_id ): void {
		$workflow_id = uniqid( 'wkf_', true );

		Logger::debug( $workflow_id, "Scheduling background sync for order: {$order_id}" );

		as_enqueue_async_action(
			'wckoban_handle_payment_complete',
			array(
				'order_id'    => $order_id,
				'workflow_id' => $workflow_id,
			),
			'koban-sync'
		);
	}

	/**
	 * Called whenever a WooCommerce payment completes. Orchestrates the Koban sync steps:
	 *  - Checking order validity
	 *  - Finding or creating the associated Koban "Third"
	 *  - Generating invoice and payment records
	 *  - Retrieving Invoice PDF document
	 *
	 * @param int    $order_id The WC_Order ID.
	 * @param string $workflow_id The Workflow ID.
	 */
	public function handle_payment_complete( int $order_id, string $workflow_id ): void {
		Logger::debug(
			$workflow_id,
			'Detected payment complete',
			array( 'order_id' => $order_id )
		);

		$steps = array(
			array( $this, 'check_data_integrity' ),
			array( $this, 'find_koban_third_guid' ),
			array( $this, 'create_koban_invoice' ),
			array( $this, 'create_koban_payment' ),
			array( $this, 'get_koban_invoice_pdf' ),
		);

		$order = wc_get_order( $order_id );
		$data  = array(
			'order' => $order,
		);

		// TODO: Get last failed step.
		$last_failed_step = null;
		// Run the workflow through our state machine.
		$state     = new StateMachine( $steps, $workflow_id, $data, $last_failed_step );
		$this->api = new API( $workflow_id );
		$state->process_steps();
	}

	/**
	 * Ensures the order is valid and not already processed (i.e., no existing invoice).
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True if valid or processed; false on error.
	 */
	public function check_data_integrity( StateMachine $state ): bool {
		$order = $state->get_data( 'order' );

		if ( ! $order instanceof WC_Order ) {
			return $state->failed( 'Invalid order ID.' );
		}

		// Early termination if an invoice pdf already exists.
		if ( MetaUtils::get_koban_invoice_pdf_path( $order ) ) {
			return $state->stop( 'Order already processed.' );
		}

		// user_id 0 is Guest.
		$user_id = $order->get_user_id();
		if ( 0 !== $user_id && ! get_user_by( 'id', $user_id ) ) {
			return $state->failed( 'Invalid user_id.' );
		}
		return $state->success();
	}

	/**
	 * Finds or creates a Koban "Third" record associated with the order customer.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True on success, false on failure.
	 */
	public function find_koban_third_guid( StateMachine $state ): bool {
		$order   = $state->get_data( 'order' );
		$user_id = $order->get_user_id();

		// Check local user meta for an existing Koban GUID.
		if ( $user_id ) {
			$koban_third_guid = MetaUtils::get_koban_third_guid( $user_id );
			if ( $koban_third_guid ) {
				return $state->success(
					'Found Koban GUID in user metadata.',
					array( 'koban_third_guid' => $koban_third_guid )
				);
			}
		}

		// If not found in meta, query Koban by email.
		$koban_third_guid = $this->api->find_user_by_email( $order->get_billing_email() );
		if ( $koban_third_guid ) {
			if ( $user_id ) {
				MetaUtils::set_koban_third_guid( $user_id, $koban_third_guid );
			}
			return $state->success(
				'Found Koban Third with matching email.',
				array( 'koban_third_guid' => $koban_third_guid )
			);
		}

		// If no remote record, create one in Koban.
		$koban_third_guid = $this->create_koban_third( $order, $user_id );
		if ( $koban_third_guid ) {
			if ( $user_id ) {
				MetaUtils::set_koban_third_guid( $user_id, $koban_third_guid );
			}
			return $state->success(
				'Created Koban Third.',
				array( 'koban_third_guid' => $koban_third_guid )
			);
		}

		return $state->failed( 'Could not create Koban Third.' );
	}

	/**
	 * Helper to create a new Koban Third from WooCommerce order data.
	 *
	 * @param  WC_Order $order   The order object to extract user/billing info from.
	 * @param  int      $user_id The associated WordPress user ID, if any.
	 * @return string|null       The new Koban third GUID, or null on failure.
	 */
	public function create_koban_third( WC_Order $order, int $user_id ): ?string {
		$third_payload    = ( new UpsertThird() )->order_to_koban_third( $order );
		$koban_third_guid = $this->api->upsert_user( $third_payload );

		return $koban_third_guid;
	}

	/**
	 * Creates a new invoice in Koban and stores its GUID in the order meta.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True on success, false on failure.
	 */
	public function create_koban_invoice( StateMachine $state ): bool {
		$koban_third_guid = $state->get_data( 'koban_third_guid' );
		$order            = $state->get_data( 'order' );

		$invoice_payload    = ( new Order() )->to_koban_invoice( $order, $koban_third_guid );
		$koban_invoice_guid = $this->api->create_invoice( $invoice_payload );

		if ( $koban_invoice_guid ) {
			MetaUtils::set_koban_invoice_guid_for_order( $order, $koban_invoice_guid );

			return $state->success(
				'Created a Koban Invoice',
				array( 'koban_invoice_guid' => $koban_invoice_guid )
			);
		}
		return $state->failed( 'Could not create Koban Invoice' );
	}

	/**
	 * Creates a payment in Koban for the order's invoice, storing the result in the order meta.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True on success, false on failure.
	 */
	public function create_koban_payment( StateMachine $state ): bool {
		$koban_invoice_guid = $state->get_data( 'koban_invoice_guid' );
		$order              = $state->get_data( 'order' );

		$payment_payload    = ( new Order() )->to_koban_payment( $order, $koban_invoice_guid );
		$koban_payment_guid = $this->api->create_payment( $payment_payload );

		if ( $koban_payment_guid ) {
			MetaUtils::set_koban_payment_guid_for_order( $order, $koban_payment_guid );

			return $state->success( 'Created Koban Payment' );
		}
		return $state->failed( 'Could not create Koban Payment' );
	}

	/**
	 * Retrieves the invoice PDF from Koban by its GUID, downloading it and storing the file path in order meta.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True on success, false on failure.
	 */
	public function get_koban_invoice_pdf( StateMachine $state ): bool {
		$koban_invoice_guid = $state->get_data( 'koban_invoice_guid' );
		$order              = $state->get_data( 'order' );

		$koban_invoice_pdf_path = $this->api->get_invoice_pdf( $koban_invoice_guid );
		if ( $koban_invoice_pdf_path ) {
			MetaUtils::set_koban_invoice_pdf_path_for_order( $order, $koban_invoice_pdf_path );

			return $state->success( 'Retrieved Koban Invoice PDF' );
		}
		return $state->failed( 'Could not retrieve Koban Invoice PDF' );
	}
}
