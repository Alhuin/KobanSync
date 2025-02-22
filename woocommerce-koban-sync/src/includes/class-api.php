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
		$this->api_url  = $this->api_url . '/api/v1';
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

		Logger::info(
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
			Logger::error(
				'HTTP error while calling Koban API',
				array(
					'url'    => $url,
					'method' => $method,
					'body'   => $body,
					'error'  => $error_message,
				)
			);

			return null;
		}

		$content_type  = wp_remote_retrieve_header( $response, 'content-type' );
		$response_body = wp_remote_retrieve_body( $response );

		if ( false !== strpos( $content_type, 'application/pdf' ) ) {
			return array(
				'type' => 'pdf',
				'data' => $response_body,
			);
		}

		Logger::info(
			'Received response from Koban',
			array(
				'code' => $response['response']['code'],
				'body' => json_decode( $response_body, true ),
			)
		);

		if ( 404 == $response['response']['code'] ) {
			return null;
		}

		$response_body = wp_remote_retrieve_body( $response );

		if ( empty( $response_body ) ) {
			Logger::error(
				'Empty response from Koban API',
				array(
					'url'    => $url,
					'method' => $method,
					'body'   => $body,
				)
			);

			return null;
		}

		$response_data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::error(
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
		$url           = $this->api_url . '/ncThird/PostOne?uniqueproperty=Extcode';
		$response_data = $this->make_request( $url, 'POST', $user_payload );

		if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] ) {
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
		$url           = $this->api_url . '/ncThird/GetOneByKey?uniqueproperty=Email&value=' . rawurlencode( $email );
		$response_data = $this->make_request( $url, 'GET' );

		if ( ! $response_data || ! isset( $response_data['Guid'] ) ) {
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
		$url           = $this->api_url . '/ncInvoice/PostMany?uniqueproperty=Number&orderuniqueproperty=Number&thirduniqueproperty=Guid';
		$response_data = $this->make_request( $url, 'POST', $invoice_payload );

		if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] ) {
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
		$url           = $this->api_url . '/ncInvoice/GetPDF?id=' . $invoice_guid;
		$response_data = $this->make_request( $url, 'GET' );

		if ( ! $response_data ) {
			return false;
		}

		if ( isset( $response_data['type'] ) && 'pdf' === $response_data['type'] ) {
			$pdf_binary = $response_data['data'];
			$upload_dir = wp_upload_dir();

			// Create a subfolder with .htaccess blocking direct access.
			$protected_dir = trailingslashit( $upload_dir['basedir'] ) . 'protected-pdfs';
			if ( ! file_exists( $protected_dir ) ) {
				wp_mkdir_p( $protected_dir );

				file_put_contents(
					$protected_dir . '/.htaccess',
					"Order allow,deny\nDeny from all\n"
				);
			}

			// Save the file.
			$filename = 'koban-invoice-' . $invoice_guid . '.pdf';
			$filepath = trailingslashit( $protected_dir ) . $filename;
			file_put_contents( $filepath, $pdf_binary );

			return $filepath;
		}

		return null;
	}

	/**
	 * Creates a Product in Koban by reference.
	 *
	 * @param array $product_payload The product data to upsert.
	 *
	 * @return string  The created Product GUID
	 */
	public function create_product( array $product_payload ): string {
		$url           = $this->api_url . '/ncProduct/PostOne?uniqueproperty=Reference&catproductuniqueproperty=Reference';
		$response_data = $this->make_request( $url, 'POST', $product_payload );

		if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] ) {
			return false;
		}
		return $response_data['Result'];
	}

	/**
	 * Updates a Product in Koban by Guid.
	 *
	 * @param array $product_payload The product data to upsert.
	 *
	 * @return bool  True on success, false on failure.
	 */
	public function update_product( array $product_payload ): bool {
		$url           = $this->api_url . '/ncProduct/PostOne?uniqueproperty=Guid&catproductuniqueproperty=Reference';
		$response_data = $this->make_request( $url, 'POST', $product_payload );

		if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Creates a Payment in Koban For an Invoice identified by Guid.
	 *
	 * @param array $payment_payload The payment data to create.
	 *
	 * @return string|false  The Payment GUID on success, False on error.
	 */
	public function create_payment( array $payment_payload ): string {
		$url           = $this->api_url . '/ncPayment/PostMany?uniqueproperty=Number&invoiceuniqueproperty=Guid';
		$response_data = $this->make_request( $url, 'POST', $payment_payload );

		if ( ! $response_data || empty( $response_data['Success'] ) || true !== $response_data['Success'] || ! isset( $response_data['Result'][0] ) ) {
			return false;
		}
		return $response_data['Result'][0];
	}
}
