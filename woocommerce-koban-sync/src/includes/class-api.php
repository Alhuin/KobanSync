<?php
/**
 * API class file.
 *
 * Handles interactions with the Koban CRM API.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban;

/**
 * A client class for interacting with the Koban CRM API.
 */
class API {

	/**
	 * Base URL for Koban API.
	 *
	 * @var string
	 */
	private string $api_url;

	/**
	 * Koban API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Koban user key.
	 *
	 * @var string
	 */
	private string $user_key;

	/**
	 * The Workflow_id.
	 *
	 * @var string
	 */
	private string $workflow_id;

	/**
	 * Retrieves Koban credentials and builds the API base URL.
	 *
	 * @param string $workflow_id The Workflow_id.
	 */
	public function __construct( string $workflow_id ) {
		$options           = get_option( 'wckoban_sync_options' );
		$this->api_url     = rtrim( $options['koban_api_url'] ?? '', '/' );
		$this->api_key     = $options['koban_api_key'] ?? '';
		$this->user_key    = $options['koban_user_key'] ?? '';
		$this->workflow_id = $workflow_id;
	}

	/**
	 * Sends an HTTP request to Koban, handling JSON/PDF responses.
	 *
	 * @param string     $url    Endpoint URL.
	 * @param string     $method HTTP verb.
	 * @param array|null $body   Request body (JSON-encoded if present).
	 * @return array|null        Decoded response data or error structure.
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
			'timeout'   => 30,
			'sslverify' => true,
		);

		Logger::debug(
			$this->workflow_id,
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
			Logger::debug(
				$this->workflow_id,
				'HTTP error while calling Koban API',
				array(
					'url'    => $url,
					'method' => $method,
					'body'   => $body,
					'error'  => $response->get_error_message(),
				)
			);

			return array(
				'error'   => $response->get_error_code(),
				'message' => $response->get_error_message(),
			);
		}

		$status_code   = $response['response']['code'];
		$content_type  = wp_remote_retrieve_header( $response, 'content-type' );
		$response_body = wp_remote_retrieve_body( $response );

		if ( false !== strpos( $content_type, 'application/pdf' ) ) {
			Logger::debug( $this->workflow_id, 'Received PDF from Koban', array( 'status_code' => $status_code ) );
			return array(
				'type' => 'pdf',
				'data' => $response_body,
			);
		}

		Logger::debug(
			$this->workflow_id,
			'Received response from Koban',
			array(
				'status_code' => $status_code,
				'body'        => json_decode( $response_body, true ),
			)
		);

		if ( false !== strpos( $content_type, 'application/html' ) ) {
			return array(
				'error'   => 'received_html_response',
				'message' => '',
			);
		}

		if ( 404 === $status_code ) {
			return array(
				'error'   => 404,
				'message' => 'NotFound',
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			Logger::debug(
				$this->workflow_id,
				'Unexpected HTTP response',
				array(
					'url'    => $url,
					'method' => $method,
					'status' => $status_code,
					'body'   => $response_body,
				)
			);
			return array(
				'error'   => $status_code,
				'message' => $response_body,
			);
		}

		$decoded = json_decode( $response_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::debug(
				$this->workflow_id,
				'JSON decoding error from Koban API',
				array(
					'url'      => $url,
					'method'   => $method,
					'body'     => $body,
					'response' => $response_body,
				)
			);
			return null;
		}

		return $decoded;
	}

	/**
	 * Wraps make_request() with retries and exponential backoff.
	 *
	 * @param string     $url      Endpoint URL.
	 * @param string     $method   HTTP verb.
	 * @param array|null $body     Request body.
	 * @param int        $retries  Number of attempts.
	 * @param int        $delay    Base delay for exponential backoff.
	 * @return array|null          Response data or null after max attempts.
	 */
	private function make_request_with_retries( string $url, string $method = 'GET', ?array $body = null, int $retries = 3, int $delay = 2 ): ?array {
		$attempts = 0;

		while ( $attempts < $retries ) {
			$response = $this->make_request( $url, $method, $body );

			// Return if successful or 404 is encountered.
			if ( $response && ( ! isset( $response['error'] ) || 404 === $response['error'] ) ) {
				return $response;
			}

			++$attempts;
			Logger::debug( $this->workflow_id, "Retrying request ($attempts/$retries)" );
			sleep( pow( $delay, $attempts ) );
		}

		Logger::debug(
			$this->workflow_id,
			'Failed after retry attempts',
			array(
				'url'    => $url,
				'method' => $method,
				'body'   => $body,
			)
		);
		return null;
	}

	/**
	 * Creates/updates a Koban "Third" record.
	 *
	 * @param array $user_payload Data for the Koban third.
	 * @return string|null        The Third GUID or null on failure.
	 */
	public function upsert_user( array $user_payload ): ?string {
		$url  = $this->api_url . '/ncThird/PostOne?uniqueproperty=Extcode';
		$data = $this->make_request_with_retries( $url, 'POST', $user_payload );

		if ( ! isset( $data['Success'] ) || ! $data['Success'] || ! isset( $data['Result'] ) ) {
			return null;
		}
		return $data['Result'];
	}

	/**
	 * Retrieves a Third record by email.
	 *
	 * @param string $email Email to match.
	 * @return string|null  The Third GUID or null if not found.
	 */
	public function find_user_by_email( string $email ): ?string {
		$url  = $this->api_url . '/ncThird/GetOneByKey?uniqueproperty=Email&value=' . rawurlencode( $email );
		$data = $this->make_request_with_retries( $url, 'GET' );

		if ( isset( $data['error'] ) || ! isset( $data['Guid'] ) ) {
			return null;
		}
		return $data['Guid'];
	}

	/**
	 * Creates a new invoice.
	 *
	 * @param array $invoice_payload Invoice details for Koban.
	 * @return string|null           Invoice GUID or null on failure.
	 */
	public function create_invoice( array $invoice_payload ): ?string {
		$url  = $this->api_url . '/ncInvoice/PostMany?uniqueproperty=Number&orderuniqueproperty=Number&thirduniqueproperty=Guid';
		$data = $this->make_request_with_retries( $url, 'POST', $invoice_payload );

		if ( ! isset( $data['Success'] ) || ! $data['Success'] || ! isset( $data['Result'][0] ) ) {
			return null;
		}
		return $data['Result'][0];
	}

	/**
	 * Retrieves and saves a Koban invoice PDF locally.
	 *
	 * @param string $invoice_guid The invoice GUID.
	 * @return string|null         Path to the saved PDF or null on failure.
	 */
	public function get_invoice_pdf( string $invoice_guid ): ?string {
		$url  = $this->api_url . '/ncInvoice/GetPDF?id=' . $invoice_guid;
		$data = $this->make_request_with_retries( $url, 'GET' );

		if ( ! $data || ! isset( $data['type'] ) || 'pdf' !== $data['type'] ) {
			return null;
		}
		$pdf_binary    = $data['data'];
		$upload_dir    = wp_upload_dir();
		$protected_dir = trailingslashit( $upload_dir['basedir'] ) . 'protected-pdfs';

		if ( ! file_exists( $protected_dir ) ) {
			wp_mkdir_p( $protected_dir );
			file_put_contents( $protected_dir . '/.htaccess', "Order allow,deny\nDeny from all\n" );
		}

		$filename = 'koban-invoice-' . $invoice_guid . '.pdf';
		$filepath = trailingslashit( $protected_dir ) . $filename;
		file_put_contents( $filepath, $pdf_binary );

		return $filepath;
	}

	/**
	 * Creates a Product record by reference.
	 *
	 * @param array $product_payload Product data for Koban.
	 * @return string|null           The Product GUID or null on failure.
	 */
	public function create_product( array $product_payload ): ?string {
		$url  = $this->api_url . '/ncProduct/PostOne?uniqueproperty=Reference&catproductuniqueproperty=Reference';
		$data = $this->make_request_with_retries( $url, 'POST', $product_payload );

		if ( ! isset( $data['Success'] ) || ! $data['Success'] || ! isset( $data['Result'] ) ) {
			return null;
		}
		return $data['Result'];
	}

	/**
	 * Updates a Product record by GUID.
	 *
	 * @param array $product_payload Product data for Koban.
	 * @return bool                  True on success, false otherwise.
	 */
	public function update_product( array $product_payload ): bool {
		$url  = $this->api_url . '/ncProduct/PostOne?uniqueproperty=Guid&catproductuniqueproperty=Reference';
		$data = $this->make_request_with_retries( $url, 'POST', $product_payload );

		return ( isset( $data['Success'] ) && $data['Success'] );
	}

	/**
	 * Creates a Payment for a given invoice in Koban.
	 *
	 * @param array $payment_payload Payment details for Koban.
	 * @return string|null           The Payment GUID or null on failure.
	 */
	public function create_payment( array $payment_payload ): ?string {
		$url  = $this->api_url . '/ncPayment/PostMany?uniqueproperty=Number&invoiceuniqueproperty=Guid';
		$data = $this->make_request_with_retries( $url, 'POST', $payment_payload );

		if ( ! isset( $data['Success'] ) || ! $data['Success'] || ! isset( $data['Result'][0] ) ) {
			return null;
		}
		return $data['Result'][0];
	}
}
