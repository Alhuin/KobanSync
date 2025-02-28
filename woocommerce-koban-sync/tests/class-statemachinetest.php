<?php
/**
 * Tests for the StateMachine workflow logic, verifying step-by-step states
 * and final workflow status (success, stop, fail).
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests;

use WP_UnitTestCase;
use WCKoban\Hooks\StateMachine;

/**
 * Class StateMachineTest
 *
 * Validates the StateMachine’s workflow actions, including data merging,
 * stop logic, failure handling, and request/response storage.
 */
class StateMachineTest extends WP_UnitTestCase {

	/**
	 * Successful step that merges 'keyA'.
	 *
	 * @param StateMachine $state The StateMachine instance.
	 */
	public function step_one_success( StateMachine $state ): bool {
		return $state->success( 'Step one done.', array( 'keyA' => 'valueA' ) );
	}

	/**
	 * Successful step that merges 'keyB'.
	 *
	 * @param StateMachine $state The StateMachine instance.
	 */
	public function step_two_success( StateMachine $state ): bool {
		return $state->success( 'Step two done.', array( 'keyB' => 'valueB' ) );
	}

	/**
	 * Step that issues a stop, halting further processing but not failing.
	 *
	 * @param StateMachine $state The StateMachine instance.
	 */
	public function step_stop_early( StateMachine $state ): bool {
		return $state->stop( 'Stopping early on purpose.' );
	}

	/**
	 * Step that fails immediately, causing the entire workflow to end as "failed."
	 *
	 * @param StateMachine $state The StateMachine instance.
	 */
	public function step_fail_now( StateMachine $state ): bool {
		return $state->failed( 'Step deliberately failed.' );
	}

	/**
	 * Ensures a sequence of successful steps ends with final status=success
	 * and merges data from each step.
	 */
	public function test_all_steps_succeed() {
		$steps   = array(
			array( $this, 'step_one_success' ),
			array( $this, 'step_two_success' ),
		);
		$machine = new StateMachine( $steps, 'workflow_id', array( 'initKey' => 'initVal' ) );
		$machine->process_steps();

		$this->assertSame( 'success', $machine->get_status(), 'Expected final workflow status to be success.' );

		$this->assertSame( 'initVal', $machine->get_data( 'initKey' ) );
		$this->assertSame( 'valueA', $machine->get_data( 'keyA' ) );
		$this->assertSame( 'valueB', $machine->get_data( 'keyB' ) );

		$full_state = $machine->get_state();
		$this->assertArrayHasKey( 'step_one_success', $full_state );
		$this->assertArrayHasKey( 'step_two_success', $full_state );

		$this->assertSame( 'success', $full_state['step_one_success']['status'] );
		$this->assertSame( 'Step one done.', $full_state['step_one_success']['message'] );

		$this->assertSame( 'success', $full_state['step_two_success']['status'] );
		$this->assertSame( 'Step two done.', $full_state['step_two_success']['message'] );
	}

	/**
	 * Verifies a step that calls stop() yields final status=success
	 * but prevents subsequent steps from running.
	 */
	public function test_stop_flow() {
		$steps   = array(
			array( $this, 'step_one_success' ),
			array( $this, 'step_stop_early' ),
			array( $this, 'step_two_success' ),
		);
		$machine = new StateMachine( $steps, 'workflow_id' );
		$machine->process_steps();

		$this->assertSame( 'stop', $machine->get_status(), 'Expected workflow to end in stop.' );

		$full_state = $machine->get_state();
		$this->assertArrayHasKey( 'step_one_success', $full_state );
		$this->assertArrayHasKey( 'step_stop_early', $full_state );
		$this->assertArrayNotHasKey( 'step_two_success', $full_state, 'Last step should not run after stop.' );

		$this->assertSame( 'success', $full_state['step_one_success']['status'] );
		$this->assertSame( 'stop', $full_state['step_stop_early']['status'] );
	}

	/**
	 * Ensures that if the first step fails, no subsequent step runs
	 * and the final workflow status is "failed."
	 */
	public function test_immediate_failure() {
		$steps   = array(
			array( $this, 'step_fail_now' ),
			array( $this, 'step_two_success' ),
		);
		$machine = new StateMachine( $steps, 'workflow_id' );
		$machine->process_steps();

		$this->assertSame( 'failed', $machine->get_status(), 'Expected workflow to fail immediately.' );

		$full_state = $machine->get_state();
		$this->assertArrayHasKey( 'step_fail_now', $full_state );
		$this->assertArrayNotHasKey( 'step_two_success', $full_state, 'Subsequent step must not run.' );

		$this->assertSame( 'failed', $full_state['step_fail_now']['status'] );
		$this->assertSame( 'Step deliberately failed.', $full_state['step_fail_now']['message'] );
	}

	/**
	 * Demonstrates skipping all steps prior to a given failed step.
	 */
	public function test_skip_steps_via_failed_step() {
		$steps = array(
			array( $this, 'step_one_success' ),
			array( $this, 'step_stop_early' ),
			array( $this, 'step_fail_now' ),
		);

		$machine = new StateMachine( $steps, 'workflow_id', array(), 'step_stop_early' );
		$machine->process_steps();

		$this->assertSame( 'stop', $machine->get_status(), 'Expected final status to be stop.' );

		$full_state = $machine->get_state();
		$this->assertArrayNotHasKey( 'step_one_success', $full_state );
		$this->assertArrayHasKey( 'step_stop_early', $full_state );
		$this->assertSame( 'stop', $full_state['step_stop_early']['status'] );
	}
}
