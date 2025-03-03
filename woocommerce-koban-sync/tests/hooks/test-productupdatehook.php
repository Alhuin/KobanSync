<?php
/**
 * Tests for KobanSync CustomerSaveAddressHook
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests;

use WCKoban\Hooks\ProductUpdateHook;
use WCKoban\Hooks\StateMachine;
use WCKoban\Tests\Mocks\CreateProductFailure;
use WCKoban\Tests\Mocks\CreateProductSuccess;
use WCKoban\Tests\Mocks\UpdateProductFailure;
use WCKoban\Tests\Mocks\UpdateProductSuccess;
use WCKoban\Utils\MetaUtils;
use function WCKoban\Tests\Mocks\set_next_responses;

/**
 * Class TestProductUpdateHook
 *
 * Contains unit tests for the Logic triggered by 'woocommerce_customer_save_address' hook.
 */
class TestProductUpdateHook extends WCKoban_UnitTestCase {

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

		$this->product_without_guid_id = $this->create_wp_post_product();
		$this->product_with_guid_id    = $this->create_wp_post_product();
		MetaUtils::set_koban_product_guid_for_product_id( $this->product_with_guid_id, 'test_koban_guid' );
	}

	/**
	 * Delete test db entries after each test
	 */
	public function tearDown(): void {
		parent::tearDown();

		wp_delete_post( $this->product_without_guid_id );
		wp_delete_post( $this->product_with_guid_id );
		as_unschedule_all_actions( 'wckoban_handle_product_update' );
	}

	/**
	 * Trigger woocommerce_product_update hook
	 * Should schedule background handler action
	 */
	public function test_product_update_schedules_action() {
		do_action( 'woocommerce_update_product', $this->product_with_guid_id );

		// $this->assertTrue( as_has_scheduled_action( 'handle_product_update' ) );

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
			),
			'Expected wckoban_handle_woocommerce_product_update to be scheduled.'
		);
	}

	/**
	 * Trigger woocommerce_new_product hook
	 * Should schedule background handler action
	 */
	public function test_new_product_schedules_action() {
		do_action( 'woocommerce_new_product', $this->product_without_guid_id );

		// $this->assertTrue( as_has_scheduled_action( 'wckoban_handle_product_update' ) );

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
			),
			'Expected wckoban_handle_woocommerce_product_update to be scheduled.'
		);
	}

	/**
	 * Product without GUID (create)
	 */
	public function test_create_product() {
		$expected_requests = array(
			new CreateProductSuccess(),
		);
		set_next_responses( $expected_requests );

		( new ProductUpdateHook() )->handle_product_update(
			$this->product_without_guid_id,
			$this->workflow_id,
			0
		);

		$this->assertRequestsCount( 1 );
		$this->assertRequests( $expected_requests );

		$product = wc_get_product( $this->product_without_guid_id );

		$this->assertSame(
			MetaUtils::get_koban_product_guid( $product ),
			( new CreateProductSuccess() )->guid,
			'Expected newly created product to store the Koban product GUID.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
				array(
					'product_id'  => $this->product_without_guid_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Product with GUID (update)
	 */
	public function test_update_product() {
		$expected_requests = array(
			new UpdateProductSuccess(),
		);
		set_next_responses( $expected_requests );

		( new ProductUpdateHook() )->handle_product_update(
			$this->product_with_guid_id,
			$this->workflow_id,
			0
		);

		$this->assertRequestsCount( 1 );
		$this->assertRequests( $expected_requests );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
				array(
					'product_id'  => $this->product_with_guid_id,
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
	public function test_product_update_check_data_integrity_fails_no_retries() {
		( new ProductUpdateHook() )->handle_product_update(
			123,
			$this->workflow_id,
			0
		);
		$this->assertRequestsCount( 0 );

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
				array(
					'product_id'  => 123,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'Expected no scheduling with invalid product ID.'
			)
		);
	}

	/**
	 * Product update failure should retry
	 */
	public function test_update_product_fails_then_retries() {
		// First run: upsert_koban_product fails.
		set_next_responses(
			array(
				new UpdateProductFailure(),
			)
		);

		( new ProductUpdateHook() )->handle_product_update(
			$this->product_with_guid_id,
			$this->workflow_id,
			0
		);

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_product_id( $this->product_with_guid_id ),
			'Expected the workflow to fail due to product update error.'
		);

		$this->assertSame(
			'upsert_koban_product',
			MetaUtils::get_koban_workflow_failed_step_for_product_id( $this->product_with_guid_id ),
			'Expected upsert_koban_product to be stored as failed_step'
		);

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
				array(
					'product_id'  => $this->product_with_guid_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected a new scheduled action with attempt=1'
		);

		// Second run: upsert_koban_product succeeds.
		set_next_responses(
			array(
				new UpdateProductSuccess(),
			)
		);

		( new ProductUpdateHook() )->handle_product_update(
			$this->product_with_guid_id,
			$this->workflow_id,
			1
		);

		$this->assertSame(
			StateMachine::STATUS_SUCCESS,
			MetaUtils::get_koban_workflow_status_for_product_id( $this->product_with_guid_id ),
			'Expected successful completion on retry.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_product_id( $this->product_with_guid_id ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
				array(
					'product_id'  => $this->product_with_guid_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 2,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}

	/**
	 * Product update failure should retry
	 */
	public function test_create_product_fails_then_retries() {
		// First run: upsert_koban_product fails.
		set_next_responses(
			array(
				new CreateProductFailure(),
			)
		);

		( new ProductUpdateHook() )->handle_product_update(
			$this->product_without_guid_id,
			$this->workflow_id,
			0
		);

		$this->assertSame(
			StateMachine::STATUS_FAILED,
			MetaUtils::get_koban_workflow_status_for_product_id( $this->product_without_guid_id ),
			'Expected the workflow to fail due to product creation error.'
		);

		$this->assertSame(
			'upsert_koban_product',
			MetaUtils::get_koban_workflow_failed_step_for_product_id( $this->product_without_guid_id ),
			'Expected upsert_koban_product to be stored as failed_step'
		);

		$this->assertNotFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
				array(
					'product_id'  => $this->product_without_guid_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 1,
				),
				'koban-sync'
			),
			'Expected a new scheduled action with attempt=1'
		);

		// Second run: upsert_koban_product succeeds.
		set_next_responses(
			array(
				new CreateProductSuccess(),
			)
		);

		( new ProductUpdateHook() )->handle_product_update(
			$this->product_without_guid_id,
			$this->workflow_id,
			1
		);

		$this->assertSame(
			( new CreateProductSuccess() )->guid,
			MetaUtils::get_koban_product_guid( wc_get_product( $this->product_without_guid_id ) ),
			'Expected newly created product GUID to be stored in product meta.'
		);

		$this->assertSame(
			StateMachine::STATUS_SUCCESS,
			MetaUtils::get_koban_workflow_status_for_product_id( $this->product_without_guid_id ),
			'Expected successful completion on retry.'
		);

		$this->assertEmpty(
			MetaUtils::get_koban_workflow_failed_step_for_product_id( $this->product_without_guid_id ),
			'Expected failed_step to be cleared if not retrying.'
		);

		$this->assertFalse(
			as_next_scheduled_action(
				'wckoban_handle_product_update',
				array(
					'product_id'  => $this->product_without_guid_id,
					'workflow_id' => $this->workflow_id,
					'attempt'     => 2,
				),
				'koban-sync'
			),
			'Expected no scheduling after success.'
		);
	}
}
