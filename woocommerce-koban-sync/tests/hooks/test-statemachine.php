<?php
/**
 * Tests for the StateMachine workflow logic. Verifies transitions to success,
 * stop, and fail statuses, as well as partial step retries and data merging.
 *
 * @package WooCommerceKobanSync
 */

namespace hooks;

use WCKoban\Hooks\StateMachine;
use WP_UnitTestCase;

/**
 * Class StateMachineTest
 *
 * Ensures the StateMachine class properly processes a series of steps, handles
 * failures, supports skipping previously completed steps, and manages state and data.
 */
class TestStateMachine extends WP_UnitTestCase {

	/**
	 * The StateMachine Worfklow ID.
	 *
	 * @var string
	 */
	private string $workflow_id = 'workflow_id';

	/**
	 * Step that adds keyA to the workflow data and succeeds.
	 *
	 * @param  StateMachine $state Workflow state machine.
	 */
	public function step_one_success( StateMachine $state ): bool {
		return $state->success( 'Step one completed.', array( 'keyA' => 'valueA' ) );
	}

	/**
	 * Step that adds keyB to the workflow data and succeeds.
	 *
	 * @param  StateMachine $state Workflow state machine.
	 */
	public function step_two_success( StateMachine $state ): bool {
		return $state->success( 'Step two completed.', array( 'keyB' => 'valueB' ) );
	}

	/**
	 * Step that stops the workflow prematurely without failing.
	 *
	 * @param  StateMachine $state Workflow state machine.
	 */
	public function step_stop_early( StateMachine $state ): bool {
		return $state->stop( 'Stopping early for demonstration.' );
	}

	/**
	 * Step that fails immediately, ending the workflow.
	 *
	 * @param  StateMachine $state Workflow state machine.
	 */
	public function step_fail_now( StateMachine $state ): bool {
		return $state->failed( 'Step intentionally failed.' );
	}

	/**
	 * Step that fails immediately and indicates no retries should occur.
	 *
	 * @param  StateMachine $state Workflow state machine.
	 */
	public function step_fail_no_retry( StateMachine $state ): bool {
		return $state->failed( 'Failure with no retry.', false );
	}

	/**
	 * Step that fails on its first run but succeeds on subsequent runs.
	 *
	 * @param  StateMachine $state Workflow state machine.
	 */
	public function step_might_fail( StateMachine $state ): bool {
		static $first_run = true;
		if ( $first_run ) {
			$first_run = false;
			return $state->failed( 'Failing on first invocation.' );
		}
		return $state->success( 'Succeeded on retry.' );
	}

	/**
	 * Successful steps conclude merge data into the workflow state.
	 */
	public function test_all_steps_succeed() {
		$steps   = array(
			array( $this, 'step_one_success' ),
			array( $this, 'step_two_success' ),
		);
		$machine = new StateMachine(
			$steps,
			array(
				'workflow_id' => $this->workflow_id,
				'initial'     => 'initVal',
			)
		);

		$machine->process_steps();

		$this->assertSame( StateMachine::STATUS_SUCCESS, $machine->get_status() );
		$this->assertSame( 'initVal', $machine->get_data( 'initial' ) );
		$this->assertSame( 'valueA', $machine->get_data( 'keyA' ) );
		$this->assertSame( 'valueB', $machine->get_data( 'keyB' ) );

		$full_state = $machine->get_state();
		$this->assertArrayHasKey( 'step_one_success', $full_state );
		$this->assertArrayHasKey( 'step_two_success', $full_state );
		$this->assertSame( StateMachine::STATUS_SUCCESS, $full_state['step_one_success']['status'] );
		$this->assertSame( StateMachine::STATUS_SUCCESS, $full_state['step_two_success']['status'] );
	}

	/**
	 * If a step stops, sets STATUS_STOP, halts further steps and does not mark a failed step.
	 */
	public function test_stop_flow() {
		$steps   = array(
			array( $this, 'step_one_success' ),
			array( $this, 'step_stop_early' ),
			array( $this, 'step_two_success' ),
		);
		$machine = new StateMachine( $steps, array( 'workflow_id' => $this->workflow_id ) );

		$machine->process_steps();

		$this->assertSame( StateMachine::STATUS_STOP, $machine->get_status() );
		$this->assertNull( $machine->failed_step, 'Stop should not set a failed step.' );

		$full_state = $machine->get_state();
		$this->assertArrayHasKey( 'step_one_success', $full_state );
		$this->assertArrayHasKey( 'step_stop_early', $full_state );
		$this->assertArrayNotHasKey( 'step_two_success', $full_state );
	}

	/**
	 * If the first step fails, subsequent steps do not run and the final status is STATUS_FAILED.
	 */
	public function test_immediate_failure() {
		$steps   = array(
			array( $this, 'step_fail_now' ),
			array( $this, 'step_two_success' ),
		);
		$machine = new StateMachine( $steps, array( 'workflow_id' => $this->workflow_id ) );

		$machine->process_steps();

		$this->assertSame( StateMachine::STATUS_FAILED, $machine->get_status() );

		$full_state = $machine->get_state();
		$this->assertArrayHasKey( 'step_fail_now', $full_state );
		$this->assertArrayNotHasKey( 'step_two_success', $full_state );
	}

	/**
	 * If a step fails with no retry, set retry=false and end the workflow with STATUS_FAILED.
	 */
	public function test_fail_no_retry() {
		$steps   = array(
			array( $this, 'step_one_success' ),
			array( $this, 'step_fail_no_retry' ),
			array( $this, 'step_two_success' ),
		);
		$machine = new StateMachine( $steps, array( 'workflow_id' => $this->workflow_id ) );

		$machine->process_steps();

		$this->assertSame( StateMachine::STATUS_FAILED, $machine->get_status() );
		$this->assertFalse( $machine->retry );

		$full_state = $machine->get_state();
		$this->assertArrayHasKey( 'step_one_success', $full_state );
		$this->assertArrayHasKey( 'step_fail_no_retry', $full_state );
		$this->assertArrayNotHasKey( 'step_two_success', $full_state );
	}

	/**
	 * Partial re-run,  The second step fails on the first run and succeeds on the second run.
	 */
	public function test_resume_after_failed_step() {
		$steps = array(
			array( $this, 'step_one_success' ),
			array( $this, 'step_might_fail' ),
			array( $this, 'step_two_success' ),
		);

		// First attempt: fails on step_might_fail.
		$machine1 = new StateMachine( $steps, array( 'workflow_id' => $this->workflow_id ) );
		$machine1->process_steps();

		$this->assertSame( StateMachine::STATUS_FAILED, $machine1->get_status() );
		$this->assertSame( 'step_might_fail', $machine1->failed_step );

		// Second attempt: skip step_one_success, start from step_might_fail.
		$machine2 = new StateMachine(
			$steps,
			array(
				'resumed'     => 'data',
				'workflow_id' => $this->workflow_id,
			),
			'step_might_fail'
		);
		$machine2->process_steps();

		$this->assertSame( StateMachine::STATUS_SUCCESS, $machine2->get_status() );

		$full_state2 = $machine2->get_state();
		$this->assertArrayNotHasKey( 'step_one_success', $full_state2 );
		$this->assertSame( StateMachine::STATUS_SUCCESS, $full_state2['step_might_fail']['status'] );
		$this->assertSame( StateMachine::STATUS_SUCCESS, $full_state2['step_two_success']['status'] );
	}
}
