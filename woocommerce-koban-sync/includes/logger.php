<?php

if ( ! class_exists( 'WCKoban_Logger' ) ) {
	/**
	 * Logger class for Koban Sync, storing logs in a custom table or skipping in certain environments.
	 */
	class WCKoban_Logger {

		/**
		 * Generic log method that inserts a record into the custom logs table, if available.
		 *
		 * @param string $level The severity level (e.g., info, error).
		 * @param string $message The log message.
		 * @param array $context Additional data for context.
		 */
		public static function log( string $level, string $message, array $context = [] ): void {
			global $wpdb;

			$table_name = $wpdb->prefix . 'koban_sync_logs';
			$time       = current_time( 'mysql' ); // HH:MM:SS local time

			$json_context = wp_json_encode( $context );

			$wpdb->insert(
				$table_name,
				[
					'time'    => $time,
					'level'   => $level,
					'message' => $message,
					'context' => $json_context,
				],
				[ '%s', '%s', '%s', '%s' ]
			);
		}

		/**
		 * Shortcut for info-level logs.
		 *
		 * @param string $message The log message.
		 * @param array $context Additional context data.
		 */
		public static function info( string $message, array $context = [] ): void {
			self::log( 'info', $message, $context );
		}

		/**
		 * Shortcut for error-level logs.
		 *
		 * @param string $message The log message.
		 * @param array $context Additional context data.
		 */
		public static function error( string $message, array $context = [] ): void {
			self::log( 'error', $message, $context );
		}
	}
}
