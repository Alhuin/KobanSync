<?php
/**
 * Class: StateMachine.php
 *
 * Orchestrates a step-based workflow with controlled success, failure, or stop outcomes.
 * Enables partial re-try from a previously failed step, maintains shared data across steps,
 * and logs final workflow state.
 *
 * @package WCKoban\Hooks
 */

namespace WCKoban\Hooks;

use WCKoban\Logger;

/**
 * Class StateMachine
 *
 * Manages a sequence of callable steps in a workflow, tracking results and
 * maintaining shared state. Steps can succeed, fail, or signal an early stop.
 */
class StateMachine {

	/**
	 * Steps to process, each step is a callable.
	 *
	 * @var array
	 */
	private array $steps;

	/**
	 * Name of the step that failed in a previous run, if any.
	 *
	 * @var string|null
	 */
	private ?string $failed_step;

	/**
	 * Overall workflow state, including per-step info and final status.
	 *
	 * @var array
	 */
	private array $state;

	/**
	 * Shared data available to steps for reading/writing.
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * Currently executing step name.
	 *
	 * @var string
	 */
	private string $current_step;

	/**
	 * Constructor.
	 *
	 * @param array   $steps       Callables representing each workflow step.
	 * @param string  $workflow_id Unique ID for the workflow instance.
	 * @param array   $data        Initial shared data.
	 * @param ?string $failed_step Step name where a previous run failed, if any.
	 */
	public function __construct( array $steps, string $workflow_id, array $data = array(), ?string $failed_step = null ) {
		$this->steps       = $steps;
		$this->data        = $data;
		$this->failed_step = $failed_step;
		$this->state       = array(
			'status'      => 'processing',
			'workflow_id' => $workflow_id,
		);
	}

	/**
	 * Get the current workflow status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->state['status'];
	}

	/**
	 * Get the entire workflow state.
	 *
	 * @return array
	 */
	public function get_state(): array {
		return $this->state;
	}

	/**
	 * Prune steps preceding the previously failed step.
	 *
	 * @return array
	 */
	private function get_steps_to_process(): array {
		foreach ( $this->steps as $index => $step ) {
			if ( $this->failed_step === $step[1] ) {
				return array_slice( $this->steps, $index );
			}
		}
		return $this->steps;
	}

	/**
	 * Run each step in order, stopping on failure or explicit stop.
	 * Finalizes workflow status and logs the outcome.
	 */
	public function process_steps(): void {
		$steps = $this->get_steps_to_process();

		foreach ( $steps as $step ) {
			$this->current_step = $step[1];

			if ( ! $step( $this ) || 'stop' === $this->state[ $this->current_step ]['status'] ) {
				break;
			}
		}
		$this->state['status'] = $this->state[ $this->current_step ]['status'] ?? 'processing';
		$this->log();
	}

	/**
	 * Merge new data into the current step’s state.
	 *
	 * @param array $new_state Data to merge.
	 */
	private function update_state( array $new_state ): void {
		$this->state[ $this->current_step ] = array_replace_recursive(
			$this->state[ $this->current_step ] ?? array(),
			$new_state
		);
	}

	/**
	 * Mark the current step as successful, optionally merge more data.
	 *
	 * @param string|null $message Optional success message.
	 * @param mixed       $data    Data to merge into shared state.
	 * @return bool                True always (allows step chain to continue).
	 */
	public function success( ?string $message = null, $data = null ): bool {
		$this->update_state(
			array(
				'status'  => 'success',
				'message' => $message,
			)
		);

		if ( $data ) {
			$this->data = array_replace( $this->data, $data );
		}
		return true;
	}

	/**
	 * Mark the current step as stopped (not a failure), halting further steps.
	 *
	 * @param string|null $message Optional stop message.
	 * @return bool                True always (workflow stops here).
	 */
	public function stop( ?string $message = null ): bool {
		$this->update_state(
			array(
				'status'  => 'stop',
				'message' => $message,
			)
		);
		return true;
	}

	/**
	 * Mark the current step as failed, halting the workflow.
	 *
	 * @param string|null $message Optional failure message.
	 * @return bool                False always (workflow stops here).
	 */
	public function failed( ?string $message = null ): bool {
		$this->update_state(
			array(
				'status'  => 'failed',
				'message' => $message,
			)
		);
		return false;
	}

	/**
	 * Retrieve data from shared state. Returns all data if no key is provided.
	 *
	 * @param string|null $key Specific key to retrieve or null for all.
	 * @return mixed|null      Value or null if key not found.
	 */
	public function get_data( ?string $key ) {
		if ( $key ) {
			return $this->data[ $key ] ?? null;
		}
		return $this->data;
	}

	/**
	 * Log final workflow state upon completion.
	 */
	private function log(): void {
		Logger::info( 'Workflow execution finished.', $this->state );
	}
}
