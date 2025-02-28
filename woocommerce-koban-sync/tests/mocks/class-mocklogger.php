<?php
/**
 * MockLogger class file.
 *
 * Contains a simple mock logger for test scenarios.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests\Mocks;

/**
 * Simple mock logger for tests, printing messages to stdout or handling them in any needed way.
 */
class MockLogger {

	/**
	 * The debug flag.
	 *
	 * @var bool
	 */
	private static bool $debug_mode = false;

	/**
	 * Allows external code to enable/disable debug mode.
	 *
	 * @param bool $debug The debug flag.
	 */
	public static function set_debug_mode( bool $debug ): void {
		self::$debug_mode = $debug;
	}

	/**
	 * Generic logger function for mock purposes.
	 *
	 * @param string $level   The severity level (info, error, etc.).
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		if ( ! self::$debug_mode ) {
			return;
		}
		$json_context = is_array( $context ) ? json_encode( $context ) : $context;

		if ( strtolower( $level ) === 'error' ) {
			printf(
				"\e[31m[MockLogger] %s: %s | context=%s\e[0m\n",
				strtoupper( $level ),
				$message,
				$json_context
			);
		} else {
			printf(
				"[MockLogger] %s: %s | context=%s\n",
				strtoupper( $level ),
				$message,
				$json_context
			);
		}
	}

	/**
	 * Logs an informational message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Context data.
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Logs an error message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Context data.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Writes debug logs to a log file if debug mode is enabled.
	 *
	 * @param string $workflow_id The Workflow ID.
	 * @param string $message The message.
	 * @param array  $context Additional data.
	 */
	public static function debug( string $workflow_id, string $message, array $context = array() ) {
		if ( ! self::$debug_mode ) {
			return;
		}

		$upload_dir = wp_upload_dir( null, false );
		$log_file   = trailingslashit( $upload_dir['basedir'] ) . 'koban-debug.log';

		$date_str     = gmdate( 'Y-m-d H:i:s' );
		$context_json = wp_json_encode( $context );
		$line         = sprintf( "[%s] %s: %s | context=%s\n", $date_str, $workflow_id, $message, $context_json );

		file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
	}
}
