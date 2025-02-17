<?php

namespace mocks;

/**
 * Simple mock logger for tests, printing messages to stdout or handling them in any needed way.
 */
class MockLogger {
	/**
	 * Generic logger function for mock purposes.
	 *
	 * @param string $level   The severity level (info, error, etc.).
	 * @param string $message The log message.
	 * @param array $context Additional context data.
	 */
	public static function log( string $level, string $message, array $context = [] ): void {
		global $debug;

		if ( ! $debug ) {
			return;
		}
		$jsonContext = is_array( $context ) ? json_encode( $context ) : $context;

		if ( strtolower( $level ) === 'error' ) {
			echo sprintf( "\e[31m[MockLogger] %s: %s | context=%s\e[0m\n",
				strtoupper( $level ),
				$message,
				$jsonContext
			);
		} else {
			echo sprintf( "[MockLogger] %s: %s | context=%s\n",
				strtoupper( $level ),
				$message,
				$jsonContext
			);
		}
	}

	/**
	 * Logs an informational message.
	 *
	 * @param string $message The log message.
	 * @param array $context Context data.
	 */
	public static function info( string $message, array $context = [] ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Logs an error message.
	 *
	 * @param string $message The log message.
	 * @param array $context Context data.
	 */
	public static function error( string $message, array $context = [] ): void {
		self::log( 'error', $message, $context );
	}
}
