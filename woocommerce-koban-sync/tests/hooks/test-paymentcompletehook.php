<?php
/**
 * Tests for KobanSync PaymentCompleteHook.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests;

use WCKoban\Hooks\PaymentCompleteHook;
use WCKoban\Hooks\StateMachine;
use WCKoban\Tests\Mocks\CreateInvoiceFailure;
use WCKoban\Tests\Mocks\CreateInvoiceSuccess;
use WCKoban\Tests\Mocks\CreatePaymentFailure;
use WCKoban\Tests\Mocks\CreatePaymentSuccess;
use WCKoban\Tests\Mocks\CreateThirdFailure;
use WCKoban\Tests\Mocks\CreateThirdSuccess;
use WCKoban\Tests\Mocks\FindUserByEmailNotFound;
use WCKoban\Tests\Mocks\FindUserByEmailSuccess;
use WCKoban\Tests\Mocks\GetInvoicePdfFailure;
use WCKoban\Tests\Mocks\GetInvoicePdfSuccess;
use WCKoban\Utils\MetaUtils;
use function WCKoban\Tests\Mocks\set_next_responses;

/**
 * Class TestPaymentCompleteHook
 *
 * Contains unit tests for the Logic triggered by 'woocommerce_payment_complete' hook.
 */
class TestPaymentCompleteHook extends WCKoban_UnitTestCase {

	/**
	 * The test workflow ID, set up here to avoid typos and repetition
	 *
	 * @var string
	 */
	private string $workflow_id = 'workflow_id';

	/**
	 * Generate test db entries before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->customer_with_guid_id = $this->create_wc_customer( 'no_guid@mail.com' );
		MetaUtils::set_koban_third_guid( $this->customer_with_guid_id, 'test_koban_guid' );
		$this->order_for_customer_with_guid_id = $this->create_wc_order( array( 'customer_id' => $this->customer_with_guid_id ) );
		$this->setup_shipping_label( $this->order_for_customer_with_guid_id );

		$this->customer_without_guid_id           = $this->create_wc_customer( 'guid@mail.com' );
		$this->order_for_customer_without_guid_id = $this->create_wc_order( array( 'customer_id' => $this->customer_without_guid_id ) );
		$this->setup_shipping_label( $this->order_for_customer_without_guid_id );

		$this->order_guest_id = $this->create_wc_order( array( 'customer_id' => 0 ) );
		$this->setup_shipping_label( $this->order_guest_id );
	}

	/**
	 * Delete test db entries after each test
	 */
	public function tearDown(): void {
		parent::tearDown();

		wp_delete_user( $this->customer_without_guid_id );
		wp_delete_user( $this->customer_with_guid_id );
		wp_delete_post( $this->order_for_customer_with_guid_id );
		wp_delete_post( $this->order_for_customer_without_guid_id );
		wp_delete_post( $this->order_guest_id );
		as_unschedule_all_actions( 'wckoban_handle_payment_complete' );
	}

	/**
	 * Trigger woocommerce_payment_complete hook
	 * Should schedule background handler action
	 */
	public function test_payment_complete_action_scheduled() {
		do_action( 'woocommerce_payment_complete', $this->order_guest_id );

		$this->assertNotFalse(
			as_next_scheduled_action( 'wckoban_handle_payment_complete' ),
			'Expected wckoban_handler_payment_complete to be scheduled.'
		);
	}

	/**
	 * Registered user, no Koban GUID, email found in Koban
	 */
	public function test_payment_complete_registered_user_no_guid_email_exists() {
		$order_id    = $this->order_for_customer_without_guid_id;
		$customer_id = $this->customer_without_guid_id;

		$expected_requests = array(
			new FindUserByEmailSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$this->assertRequestsCount( 4 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			MetaUtils::get_koban_third_guid( $customer_id ),
			( new FindUserByEmailSuccess() )->guid,
			'Expected user_meta to match the found Koban GUID.'
		);
		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Expected order_meta to match the newly created Koban invoice GUID.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Registered user, no Koban GUID, email not found in Koban.
	 */
	public function test_payment_complete_registered_user_no_guid_email_does_not_exist() {
		$order_id    = $this->order_for_customer_without_guid_id;
		$customer_id = $this->customer_without_guid_id;

		$expected_requests = array(
			new FindUserByEmailNotFound(),
			new CreateThirdSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$this->assertRequestsCount( 5 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			MetaUtils::get_koban_third_guid( $customer_id ),
			( new CreateThirdSuccess() )->guid,
			'Expected user_meta to store the newly created Koban Third GUID.'
		);
		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Expected order_meta to store the new Koban invoice GUID.'
		);
		$this->assertSame(
			MetaUtils::get_koban_payment_guid( $order ),
			( new CreatePaymentSuccess() )->guid,
			'Expected order_meta to store the new Koban payment GUID.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Registered user with existing Koban GUID
	 */
	public function test_payment_complete_registered_user_with_meta_guid() {
		$order_id    = $this->order_for_customer_with_guid_id;
		$customer_id = $this->customer_with_guid_id;

		$expected_requests = array(
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		$this->setup_shipping_label( $order_id );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$this->assertRequestsCount( 3 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			'test_koban_guid',
			MetaUtils::get_koban_third_guid( $customer_id ),
			'Existing Koban GUID should remain unchanged.'
		);
		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Order invoice GUID should match newly created Koban invoice.'
		);
		$this->assertSame(
			MetaUtils::get_koban_payment_guid( $order ),
			( new CreatePaymentSuccess() )->guid,
			'Order payment GUID should match newly created Koban payment.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Guest user, email found in Koban.
	 */
	public function test_payment_complete_guest_user_email_exists() {
		$order_id = $this->order_guest_id;

		$expected_requests = array(
			new FindUserByEmailSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$this->assertRequestsCount( 4 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Order invoice GUID should match newly created Koban invoice.'
		);
		$this->assertSame(
			MetaUtils::get_koban_payment_guid( $order ),
			( new CreatePaymentSuccess() )->guid,
			'Order payment GUID should match newly created Koban payment.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Guest user, email not found in Koban.
	 */
	public function test_payment_complete_guest_user_email_does_not_exist() {
		$order_id = $this->order_guest_id;

		$expected_requests = array(
			new FindUserByEmailNotFound(),
			new CreateThirdSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$this->assertRequestsCount( 5 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			( new CreateInvoiceSuccess() )->guid,
			MetaUtils::get_koban_invoice_guid( $order ),
			'Invoice GUID in order_meta should match newly created Koban invoice.'
		);
		$this->assertSame(
			( new CreatePaymentSuccess() )->guid,
			MetaUtils::get_koban_payment_guid( $order ),
			'Payment GUID in order_meta should match newly created Koban payment.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Check data integrity failure should not retry
	 */
	public function test_payment_complete_check_data_integrity_fails_no_retries() {
		$order_id = $this->create_wc_order( array( 'customer_id' => 123 ) );
		$this->setup_shipping_label( $order_id );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$this->assertRequestsCount( 0 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected the workflow to fail due to invalid customer_id.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'worfklow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling with invalid customer_id.'
		);
	}

	/**
	 * Already processed order should terminate early
	 */
	public function test_payment_complete_already_processed_order() {
		$order_id = $this->order_for_customer_without_guid_id;
		MetaUtils::set_koban_workflow_status_for_order( wc_get_order( $order_id ), StateMachine::STATUS_SUCCESS );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$this->assertRequestsCount( 0 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_STOP,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected the workflow to stop immediately.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after stop.'
		);
	}

	/**
	 * Third creation failure should retry
	 */
	public function test_payment_complete_fails_on_third_then_retries() {
		$order_id = $this->order_for_customer_without_guid_id;

		// First run: create_koban_third fails.
		set_next_responses(
			array(
				new FindUserByEmailNotFound(),
				new CreateThirdFailure(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected the workflow to fail due to third creation error.'
		);

		$this->assertSame(
			'find_koban_third_guid',
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected find_koban_third to be stored as failed_step'
		);

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected a new scheduled action with attempt=1'
		);

		// Second run: create_koban_third succeeds
		set_next_responses(
			array(
				new FindUserByEmailNotFound(),
				new CreateThirdSuccess(),
				new CreateInvoiceSuccess(),
				new CreatePaymentSuccess(),
				new GetInvoicePdfSuccess(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 1 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_SUCCESS,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected successful completion on retry.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 2,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Invoice creation failure should retry
	 */
	public function test_payment_complete_fails_on_invoice_then_retries() {
		$order_id = $this->order_for_customer_without_guid_id;

		// First run: create_koban_invoice fails.
		set_next_responses(
			array(
				new FindUserByEmailSuccess(),
				new CreateInvoiceFailure(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected the workflow to fail due to invoice creation error.'
		);

		$this->assertSame(
			'create_koban_invoice',
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected create_koban_invoice to be stored as failed_step'
		);

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected a new scheduled action with attempt=1'
		);

		// Second run: create_koban_invoice succeeds
		set_next_responses(
			array(
				new CreateInvoiceSuccess(),
				new CreatePaymentSuccess(),
				new GetInvoicePdfSuccess(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 1 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_SUCCESS,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected successful completion on retry.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 2,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Payment creation failure should retry
	 */
	public function test_payment_complete_fails_on_payment_then_retries() {
		$order_id = $this->order_for_customer_without_guid_id;

		// First run: create_koban_payment fails.
		set_next_responses(
			array(
				new FindUserByEmailSuccess(),
				new CreateInvoiceSuccess(),
				new CreatePaymentFailure(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected the workflow to fail due to payment creation error.'
		);

		$this->assertSame(
			'create_koban_payment',
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected create_koban_payment to be stored as failed_step'
		);

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				array(),
				'koban-sync'
			),
			'Expected a new scheduled action with attempt=1'
		);

		// Second run: create_koban_payment succeeds
		set_next_responses(
			array(
				new CreatePaymentSuccess(),
				new GetInvoicePdfSuccess(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 1 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_SUCCESS,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected successful completion on retry.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 2,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Get Invoice PDF failure should retry
	 */
	public function test_payment_complete_fails_on_pdf_then_retries() {
		$order_id = $this->order_for_customer_without_guid_id;

		// First run: get_koban_invoice_pdf fails.
		set_next_responses(
			array(
				new FindUserByEmailSuccess(),
				new CreateInvoiceSuccess(),
				new CreatePaymentSuccess(),
				new GetInvoicePdfFailure(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected the workflow to fail due to get invoice pdf error.'
		);

		$this->assertSame(
			'get_koban_invoice_pdf',
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected get_koban_invoice_pdf to be stored as failed_step'
		);

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected a new scheduled action with attempt=1'
		);

		// Second run: get_koban_invoice_pdf succeeds
		set_next_responses(
			array(
				new GetInvoicePdfSuccess(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 1 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_SUCCESS,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected successful completion on retry.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 2,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Send logistics email failure because of shipping data missing should not retry
	 */
	public function test_payment_complete_no_shipping_data_fails_no_retries() {
		$order_id = $this->create_wc_order();
		set_next_responses(
			array(
				new FindUserByEmailSuccess(),
				new CreateInvoiceSuccess(),
				new CreatePaymentSuccess(),
				new GetInvoicePdfSuccess(),
			)
		);

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, $this->workflow_id, 0 );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_order( $order ),
			'Expected fail on "No shipping data found" in the email step.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_order( $order ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_payment_complete',
				array(
					'order_id'    => $order_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling with invalid shipping data.'
		);
	}
}
