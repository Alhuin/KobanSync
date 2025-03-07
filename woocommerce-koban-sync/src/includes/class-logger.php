<?php
/**
 * Logger class file.
 *
 * Handles logging into a custom Koban sync logs table.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban;

/**
 * Logger class for Koban Sync, storing logs in a custom table or skipping in certain environments.
 */
class Logger {


	/**
	 * Record the scheduling of a new workflow in the custom logs table.
	 *
	 * @param string $workflow_id  The unique identifier for this workflow.
	 * @param string $action_type  Label of the action type, eg 'Payment Complete'.
	 * @param string $message      Information about the scheduling.
	 *
	 * @return void
	 */
	public static function record_workflow_schedule( string $workflow_id, string $action_type, string $message ): void {
		global $wpdb;

		// Check if we already have a row for this workflow_id.
		$table = $wpdb->prefix . 'koban_sync_logs';

		// Insert a new row with minimal data.
		$wpdb->insert(
			$table,
			array(
				'workflow_id'        => $workflow_id,
				'action_type'        => $action_type,
				'status'             => 'scheduled',
				'time'               => current_time( 'mysql' ),
				'scheduling_message' => $message,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Finalize workflow: store the final JSON payload, set final status, update time.
	 *
	 * @param string $workflow_id The workflow ID.
	 * @param string $final_status e.g. success of failed.
	 * @param array  $steps_array The big array of steps & statuses.
	 */
	public static function record_workflow_completion( string $workflow_id, string $final_status, array $steps_array ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'koban_sync_logs';

		$json_payload = wp_json_encode( $steps_array );

		$wpdb->update(
			$table,
			array(
				'status'  => $final_status,
				'payload' => $json_payload,
			),
			array( 'workflow_id' => $workflow_id ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Generic log method that inserts a record into the custom logs table, if available.
	 *
	 * @param string $level   The severity level (e.g., info, error).
	 * @param string $message The log message.
	 * @param array  $context Additional data for context.
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'koban_sync_logs';
		$time       = current_time( 'mysql' ); // HH:MM:SS local time.

		$json_context = wp_json_encode( $context );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table_name,
			array(
				'time'    => $time,
				'level'   => $level,
				'message' => $message,
				'context' => $json_context,
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Shortcut for info-level logs.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Shortcut for error-level logs.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 *
	 * Write debug logs to a dedicated log file.
	 *
	 * @param string $workflow_id The Workflow identifier.
	 * @param string $message       The message.
	 * @param array  $context        Additional data.
	 *
	 * @return void
	 */
	public static function debug( string $workflow_id, string $message, array $context = array() ) {
		$upload_dir = wp_upload_dir( null, false );
		$log_file   = trailingslashit( $upload_dir['basedir'] ) . 'koban-debug.log';

		$date_str     = gmdate( 'Y-m-d H:i:s' );
		$context_json = wp_json_encode( $context );
		$line         = sprintf( "[%s] %s: %s | context=%s\n", $date_str, $workflow_id, $message, $context_json );

		file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
	}
}
