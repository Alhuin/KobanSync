<?php
/**
 * WCKoban_API class file.
 *
 * Handles interactions with the Koban CRM API.
 *
 * @package WooCommerceKobanSync
 */

if ( ! class_exists( 'WCKoban_API' ) ) {
	/**
	 * A client class for interacting with the Koban CRM API.
	 */
	class WCKoban_API {


		/**
		 * The base URL for Koban API.
		 *
		 * @var string
		 */
		private $api_url;

		/**
		 * The API key credential for Koban.
		 *
		 * @var string
		 */
		private $api_key;

		/**
		 * The user key credential for Koban.
		 *
		 * @var string
		 */
		private $user_key;

		/**
		 * Constructor. Loads Koban credentials from stored plugin options.
		 */
		public function __construct() {
			$options        = get_option( 'wckoban_sync_options' );
			$this->api_url  = $options['koban_url'] ?? '';
			$this->api_key  = $options['koban_api_key'] ?? '';
			$this->user_key = $options['koban_user_key'] ?? '';
		}

		/**
		 * Executes a generic API request (POST, GET, etc.) to Koban.
		 *
		 * @param string     $url    The Koban API endpoint URL.
		 * @param string     $method HTTP method (e.g. 'GET', 'POST').
		 * @param array|null $body   Request body, which will be JSON-encoded if provided.
		 *
		 * @return array|false        The decoded JSON response or false on error.
		 * TODO: Handle retries
		 */
		private function make_request( string $url, string $method = 'GET', ?array $body = null ): ?array {
			$headers = array(
				'X-ncApi'      => $this->api_key,
				'X-ncUser'     => $this->user_key,
				'Content-Type' => 'application/json',
			);

			$args = array(
				'method'    => $method,
				'headers'   => $headers,
				'timeout'   => 15,
				'sslverify' => true,
			);

			WCKoban_Logger::info(
				'Sending request to Koban',
				array(
					'url'    => $url,
					'method' => $method,
					'body'   => $body,
				)
			);

			if ( $body ) {
				$args['body'] = wp_json_encode( $body );
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				WCKoban_Logger::error(
					'HTTP error while calling Koban API',
					array(
						'url'    => $url,
						'method' => $method,
						'body'   => $body,
						'error'  => $error_message,
					)
				);

				return false;
			}

			$response_body = wp_remote_retrieve_body( $response );

			if ( empty( $response_body ) ) {
				WCKoban_Logger::error(
					'Empty response from Koban API',
					array(
						'url'    => $url,
						'method' => $method,
						'body'   => $body,
					)
				);

				return false;
			}

			$response_data = json_decode( $response_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				WCKoban_Logger::error(
					'JSON decoding error from Koban API',
					array(
						'url'      => $url,
						'method'   => $method,
						'body'     => $body,
						'response' => $response_body,
					)
				);

				return false;
			}

			return $response_data;
		}

		/**
		 * Creates or updates a Third (user) record in Koban.
		 *
		 * @param array       $user_payload Data to upsert in Koban.
		 * @param string|null $koban_guid   Optional existing GUID to update, or null to create a new record.
		 *
		 * @return string|false             Returns the Third GUID on success, false on failure.
		 */
		public function upsert_user( array $user_payload, ?string $koban_guid = null ): ?string {
			$url = $this->api_url . '/ncThird/PostOne?uniqueproperty=Extcode';

			$response_data = $this->make_request( $url, 'POST', $user_payload );

			WCKoban_Logger::info(
				'upsert_user response',
				array(
					'user_payload' => $user_payload,
					'koban_guid'   => $koban_guid,
					'response'     => $response_data,
				)
			);

			if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] ) {
				WCKoban_Logger::error(
					'Invalid API response for user upsert',
					array(
						'user_payload'  => $user_payload,
						'koban_guid'    => $koban_guid,
						'response_data' => $response_data,
					)
				);

				return false;
			}

			return $response_data['Result'];
		}

		/**
		 * Searches for a Third (user) in Koban by email address.
		 *
		 * @param string $email The email address to search.
		 *
		 * @return string|false The found Third GUID, or false if not found.
		 */
		public function find_user_by_email( string $email ): ?string {
			$url = $this->api_url . '/ncThird/GetOneByKey?uniqueproperty=Email&value=' . rawurlencode( $email );

			$response_data = $this->make_request( $url, 'GET' );

			if ( ! $response_data || ! isset( $response_data['Guid'] ) ) {
				WCKoban_Logger::info(
					'User not found in Koban by email',
					array(
						'email' => $email,
						'data'  => $response_data,
					)
				);

				return false;
			}

			return $response_data['Guid'];
		}

		/**
		 * Creates a new invoice in Koban.
		 *
		 * @param array $invoice_payload Data describing the invoice.
		 *
		 * @return string|false The newly created invoice GUID, or false on failure.
		 */
		public function create_invoice( array $invoice_payload ): ?string {
			$url = $this->api_url . '/ncInvoice';

			$response_data = $this->make_request( $url, 'POST', $invoice_payload );

			if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] ) {
				WCKoban_Logger::error(
					'Failed to create invoice in Koban',
					array(
						'invoice_payload' => $invoice_payload,
						'response_data'   => $response_data,
					)
				);

				return false;
			}

			return $response_data['Result'];
		}


		/**
		 * Retrieves the PDF link for an existing invoice in Koban by GUID.
		 *
		 * @param string $invoice_guid The Koban invoice GUID.
		 *
		 * @return string|false Link to the invoice PDF, or false if unavailable.
		 */
		public function get_invoice_pdf( string $invoice_guid ): ?string {
			$url = $this->api_url . '/ncInvoice/GetPDF?id=' . $invoice_guid;

			$response_data = $this->make_request( $url, 'GET' );

			if ( ! $response_data || empty( $response_data['link'] ) ) {
				WCKoban_Logger::error(
					'Failed to retrieve invoice pdf url in Koban',
					array(
						'invoice_guid'  => $invoice_guid,
						'response_data' => $response_data,
					)
				);

				return false;
			}

			return $response_data['link'];
		}

		/**
		 * Creates or updates a Product in Koban by reference.
		 *
		 * @param array       $product_payload The product data to upsert.
		 * @param string|null $product_guid    Optional existing GUID, if relevant.
		 *
		 * @return bool                        True on success, false on failure.
		 */
		public function upsert_product( array $product_payload, ?string $product_guid = null ): bool {
			$url = $this->api_url . '/api/v1/ncProduct/PostOne?uniqueproperty=Reference&catproductuniqueproperty=Reference';

			$response_data = $this->make_request( $url, 'POST', $product_payload );

			if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] ) {
				WCKoban_Logger::error(
					'Invalid API response for product upsert',
					array(
						'productPayload' => $product_payload,
						'product_guid'   => $product_guid,
						'response_data'  => $response_data,
					)
				);

				return false;
			}

			return true;
		}
	}
}
