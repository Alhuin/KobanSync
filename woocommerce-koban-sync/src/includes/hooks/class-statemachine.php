<?php
/**
 * Class: StateMachine.php
 *
 * Orchestrates a step-based workflow with controlled success, failure, or stop outcomes.
 * Enables partial re-try from a previously failed step, maintains shared data across steps,
 * and logs final workflow state.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WCKoban\Logger;
use WCKoban\Utils\MetaUtils;

/**
 * Class StateMachine
 *
 * Manages a sequence of callable steps in a workflow, tracking results and
 * maintaining shared state. Steps can succeed, fail, or signal an early stop.
 */
class StateMachine {

	const STATUS_PROCESSING = 'processing';
	const STATUS_STOP       = 'stop';
	const STATUS_SUCCESS    = 'success';
	const STATUS_FAILED     = 'failed';

	/**
	 * Status labels for translation.
	 *
	 * @var array
	 */
	private static array $labels = array();

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
	public ?string $failed_step;

	/**
	 * True if a retry is required, false otherwise.
	 *
	 * @var bool
	 */
	public bool $retry = true;

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
	 * @param array   $data        Initial shared data.
	 * @param ?string $failed_step Step name where a previous run failed, if any.
	 */
	public function __construct( array $steps, array $data = array(), ?string $failed_step = null ) {
		$this->steps       = $steps;
		$this->data        = $data;
		$this->failed_step = $failed_step;
		$this->state       = array(
			'status' => self::STATUS_PROCESSING,
		);

		self::$labels[ self::STATUS_STOP ]       = __( 'Stop', 'woocommerce-koban-sync' );
		self::$labels[ self::STATUS_FAILED ]     = __( 'Failed', 'woocommerce-koban-sync' );
		self::$labels[ self::STATUS_PROCESSING ] = __( 'Processing', 'woocommerce-koban-sync' );
		self::$labels[ self::STATUS_SUCCESS ]    = __( 'Success', 'woocommerce-koban-sync' );
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

			if ( ! $step( $this ) || self::STATUS_STOP === $this->state[ $this->current_step ]['status'] ) {
				break;
			}
		}
		$this->state['status'] = $this->state[ $this->current_step ]['status'] ?? self::STATUS_PROCESSING;
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
				'status'  => self::STATUS_SUCCESS,
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
				'status'  => self::STATUS_STOP,
				'message' => $message,
			)
		);
		return true;
	}

	/**
	 * Mark the current step as failed, halting the workflow.
	 *
	 * @param string|null $message Optional failure message.
	 * @param bool        $retry If a retry is required.
	 *
	 * @return bool                False always (workflow stops here).
	 */
	public function failed( ?string $message = null, $retry = true ): bool {
		$this->failed_step = $this->current_step;

		$this->update_state(
			array(
				'status'  => self::STATUS_FAILED,
				'message' => $message,
			)
		);

		if ( ! $retry ) {
			$this->retry = false;
		}
		return false;
	}

	/**
	 * Retrieve data from shared state. Returns all data if no key is provided.
	 *
	 * @param string|null $key Specific key to retrieve or null for all.
	 * @return mixed|null      Value or null if key not found.
	 */
	public function get_data( ?string $key = null ) {
		if ( $key ) {
			return $this->data[ $key ] ?? null;
		}
		return $this->data;
	}

	/**
	 * Log final workflow state upon completion.
	 */
	private function log(): void {
		Logger::info( __( 'Workflow execution finished.', 'woocommerce-koban-sync' ), $this->state );
	}
}
