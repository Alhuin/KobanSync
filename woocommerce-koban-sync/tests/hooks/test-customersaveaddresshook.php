<?php
/**
 * Tests for KobanSync CustomerSaveAddressHook
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests\Hooks;

use WCKoban\Hooks\CustomerSaveAddressHook;
use WCKoban\Hooks\StateMachine;
use WCKoban\Tests\Mocks\CreateThirdFailure;
use WCKoban\Tests\Mocks\UpdateThirdFailure;
use WCKoban\Tests\WCKoban_UnitTestCase;
use WCKoban\Utils\MetaUtils;
use WCKoban\Tests\Mocks\UpdateThirdSuccess;
use function WCKoban\Tests\Mocks\set_next_responses;

/**
 * Class TestCustomerSaveAddressHook
 *
 * Contains unit tests for the Logic triggered by 'woocommerce_customer_save_address' hook.
 */
class TestCustomerSaveAddressHook extends WCKoban_UnitTestCase {

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
		parent::setup();

		$this->customer_without_guid_id = $this->create_wc_customer( 'no_guid@mail.com' );
		$this->customer_with_guid_id    = $this->create_wc_customer( 'guid@mail.com' );
		MetaUtils::set_koban_third_guid( $this->customer_with_guid_id, 'test_koban_guid' );
	}

	/**
	 * Delete test db entries after each test
	 */
	public function tearDown(): void {
		parent::tearDown();

		wp_delete_user( $this->customer_with_guid_id );
		wp_delete_user( $this->customer_without_guid_id );
		as_unschedule_all_actions( 'wckoban_handle_customer_save_address' );
	}

	/**
	 * Trigger woocommerce_customer_save_address hook
	 * Should not schedule background handler action if the address is not billing
	 */
	public function test_customer_save_address_action_not_scheduled_if_not_billing_address() {
		do_action( 'woocommerce_customer_save_address', $this->customer_with_guid_id, 'address_type' );

		$this->assertFalse(
			as_has_scheduled_action( 'wckoban_handle_customer_save_address' ),
			'Expected wckoban_handle_customer_save_address to be scheduled.'
		);
	}

	/**
	 * Trigger woocommerce_customer_save_address hook
	 * Should schedule background handler action if the address is billing
	 */
	public function test_customer_save_address_action_scheduled_if_billing_address() {
		do_action( 'woocommerce_customer_save_address', $this->customer_with_guid_id, 'billing' );

		$this->assertNotFalse(
			as_has_scheduled_action( 'wckoban_handle_customer_save_address' ),
			'Expected wckoban_handle_customer_save_address to be scheduled.'
		);
	}

	/**
	 * Customer with Koban GUID billing address modification
	 */
	public function test_customer_save_address_billing_with_guid() {
		$customer_id = $this->customer_with_guid_id;

		$expected_requests = array(
			new UpdateThirdSuccess(),
		);
		set_next_responses( $expected_requests );

		( new CustomerSaveAddressHook() )->handle_customer_save_address(
			$customer_id,
			'billing',
			'workflow_id',
			0
		);

		$this->assertRequestsCount( 1 );
		$this->assertRequests( $expected_requests );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_customer_save_address',
				array(
					'customer_id'  => $this->customer_with_guid_id,
					'address_type' => 'billing',
					'workflow_id'  => 'workflow_id',
					'attempt'      => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Customer without Koban GUID billing address modification
	 */
	public function test_customer_save_address_billing_no_guid() {
		( new CustomerSaveAddressHook() )->handle_customer_save_address(
			$this->customer_without_guid_id,
			'billing',
			'workflow_id',
			0
		);

		$this->assertRequestsCount( 0 );

		$this->assertSame(
			StateMachine::STATUS_STOP,
			MetaUtils::get_koban_workflow_status_for_user_id( $this->customer_without_guid_id ),
			'Expected the workflow to stop without guid.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_user_id( $this->customer_without_guid_id ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_customer_save_address',
				array(
					'customer_id'  => $this->customer_with_guid_id,
					'address_type' => 'billing',
					'workflow_id'  => 'workflow_id',
					'attempt'      => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after stop.'
		);
	}

	/**
	 * Customer with Koban GUID shipping address modification
	 */
	public function test_customer_save_address_shipping_with_guid() {
		( new CustomerSaveAddressHook() )->handle_customer_save_address(
			$this->customer_with_guid_id,
			'shipping',
			'workflow_id',
			0
		);

		$this->assertRequestsCount( 0 );

		$this->assertSame(
			StateMachine::STATUS_STOP,
			MetaUtils::get_koban_workflow_status_for_user_id( $this->customer_with_guid_id ),
			'Expected the workflow to stop with shipping address.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_user_id( $this->customer_with_guid_id ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_customer_save_address',
				array(
					'customer_id'  => $this->customer_with_guid_id,
					'address_type' => 'shipping',
					'workflow_id'  => 'workflow_id',
					'attempt'      => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after stop.'
		);
	}

	/**
	 * Check data integrity failure should not retry
	 */
	public function test_customer_save_address_check_data_integrity_fails_no_retries() {
		( new CustomerSaveAddressHook() )->handle_customer_save_address(
			123,
			'billing',
			'workflow_id',
			0
		);
		$this->assertRequestsCount( 0 );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_customer_save_address',
				array(
					'customer_id'  => 123,
					'address_type' => 'billing',
					'workflow_id'  => 'workflow_id',
					'attempt'      => 1,
				),
				'koban-sync'
			),
			'Expected no retry scheduled with invalid customer_id.'
		);
	}

	/**
	 * Third update failure should retry
	 */
	public function test_customer_save_address_fails_on_third_then_retries() {
		// First run: upsert_koban_third fails.
		set_next_responses(
			array(
				new UpdateThirdFailure(),
			)
		);

		( new CustomerSaveAddressHook() )->handle_customer_save_address(
			$this->customer_with_guid_id,
			'billing',
			'workflow_id',
			0
		);

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_user_id( $this->customer_with_guid_id ),
			'Expected the workflow to fail due to third update error.'
		);

		$this->assertSame(
			'update_koban_third',
			MetaUtils::get_koban_workflow_failed_step_for_user_id( $this->customer_with_guid_id ),
			'Expected update_koban_third to be stored as failed_step'
		);

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_customer_save_address',
				array(
					'customer_id'  => $this->customer_with_guid_id,
					'address_type' => 'billing',
					'workflow_id'  => 'workflow_id',
					'attempt'      => 1,
				),
				'koban-sync'
			),
			'Expected a new scheduled action with attempt=1'
		);

		// Second run: upsert_koban_third succeeds.
		set_next_responses(
			array(
				new UpdateThirdSuccess(),
			)
		);

		( new CustomerSaveAddressHook() )->handle_customer_save_address(
			$this->customer_with_guid_id,
			'billing',
			'workflow_id',
			1
		);

		$this->assertSame(
			StateMachine::STATUS_SUCCESS,
			MetaUtils::get_koban_workflow_status_for_user_id( $this->customer_with_guid_id ),
			'Expected successful completion on retry.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_user_id( $this->customer_with_guid_id ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_customer_save_address',
				array(
					'customer_id'  => $this->customer_with_guid_id,
					'address_type' => 'billing',
					'workflow_id'  => 'workflow_id',
					'attempt'      => 2,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}
}
