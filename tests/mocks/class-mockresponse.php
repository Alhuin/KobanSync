<?php
/**
 *
 * Contains the MockResponse base class for test mocking.
 *
 * @package WooCommerceKobanSync\Tests
 */

namespace mocks\api;

/**
 * Class MockResponse
 *
 * Serves as a base class for mock HTTP responses used in testing.
 */
abstract class MockResponse {


	/**
	 * The expected HTTP method for this mock response.
	 *
	 * @var string
	 */
	public string $method;

	/**
	 * The API endpoint that this mock response targets.
	 *
	 * @var string
	 */
	public string $endpoint;

	/**
	 * The mock response data that will be returned.
	 *
	 * @var array
	 */
	public array $response;

	/**
	 * The path to the JSON file containing the mock response.
	 *
	 * @var string
	 */
	public string $json_path;

	/**
	 * Extracted GUID from the mock response, if present.
	 *
	 * @var string|null
	 */
	public ?string $guid;

	/**
	 * Constructor that automatically loads the JSON response from disk.
	 */
	public function __construct() {
		$this->load_json_response();
	}

	/**
	 * Loads the mock response from a JSON file in the "api" directory.
	 * It decodes the content, populates the response property, and extracts the GUID if available.
	 *
	 * @throws \Exception If the JSON file cannot be found or decoded.
	 */
	protected function load_json_response(): void {
		$path = __DIR__ . '/api/' . $this->json_path;

		if ( ! file_exists( $path ) ) {
			throw new \Exception( 'Fichier JSON introuvable : ' . $path );
		}

		$json_content = file_get_contents( $path );
		$data         = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Erreur de décodage JSON : ' . json_last_error_msg() );
		}

		if ( isset( $data['body']['Guid'] ) ) {
			$this->guid = $data['body']['Guid'];
		} elseif ( isset( $data['body']['Result'] ) ) {
			$this->guid = $data['body']['Result'];
		} else {
			$this->guid = null;
		}

		$this->response = array(
			'code' => $data['code'],
			'body' => wp_json_encode( $data['body'] ),
		);
	}
}

// phpcs:disable Squiz.Commenting.VariableComment.Missing

/**
 * Successful "find user by email" response from Koban.
 */
class FindUserByEmailSuccess extends MockResponse {
	public string $method    = 'GET';
	public string $endpoint  = 'ncThird/GetOneByKey?uniqueproperty=Email&value=';
	public string $json_path = 'find_user_by_email_success.json';
}

/**
 * NotFound "find user by email" response from Koban.
 */
class FindUserByEmailNotFound extends MockResponse {

	public string $method    = 'GET';
	public string $endpoint  = 'ncThird/GetOneByKey?uniqueproperty=Email&value=';
	public string $json_path = 'find_user_by_email_error.json';
}

/**
 * Successful "createInvoice" response from Koban.
 */
class CreateInvoiceSuccess extends MockResponse {

	public string $method    = 'POST';
	public string $endpoint  = '/ncInvoice';
	public string $json_path = 'create_invoice_success.json';
}

/**
 * Successful "getInvoicePDF" response from Koban.
 */
class GetInvoicePdfSuccess extends MockResponse {

	public string $method    = 'GET';
	public string $endpoint  = '/ncInvoice/GetPDF';
	public string $json_path = 'get_invoice_pdf_success.json';
}

/**
 * Successful "createThird" response from Koban.
 */
class CreateThirdSuccess extends MockResponse {

	public string $method    = 'POST';
	public string $endpoint  = 'ncThird/PostOne?uniqueproperty=Extcode';
	public string $json_path = 'create_third_success.json';
}

/**
 * Successful "updateThird" response from Koban.
 */
class UpdateThirdSuccess extends MockResponse {

	public string $method    = 'POST';
	public string $endpoint  = 'ncThird/PostOne?uniqueproperty=Extcode';
	public string $json_path = 'update_third_success.json';
}

/**
 * Successful "createProduct" response from Koban.
 */
class CreateProductSuccess extends MockResponse {

	public string $method    = 'POST';
	public string $endpoint  = 'ncProduct/PostOne?uniqueproperty=Reference';
	public string $json_path = 'create_product_success.json';
}

/**
 * Successful "updateProduct" response from Koban.
 */
class UpdateProductSuccess extends MockResponse {

	public string $method    = 'POST';
	public string $endpoint  = 'ncProduct/PostOne?uniqueproperty=Guid';
	public string $json_path = 'update_product_success.json';
}
