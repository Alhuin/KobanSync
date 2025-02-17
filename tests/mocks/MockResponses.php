<?php

namespace mocks\api;

/**
 * Base class for mock HTTP responses used in testing the Koban CRM plugin.
 */
abstract class MockResponse {

	/** @var string The expected HTTP method for this mock response. */
	public string $method;

	/** @var string The API endpoint that this mock response targets. */
	public string $endpoint;

	/** @var array The mock response data that will be returned. */
	public array $response;

	/** @var string The path to the JSON file containing the mock response. */
	public string $jsonPath;

	/** @var string|null Extracted GUID from the mock response, if present. */
	public ?string $guid;

	/**
	 * Constructor that automatically loads the JSON response from disk.
	 */
	public function __construct() {
		$this->loadJsonResponse();
	}

	/**
	 * Loads the mock response from a JSON file in the "api" directory.
	 * It decodes the content, populates the response property, and extracts the GUID if available.
	 *
	 * @throws \Exception If the JSON file cannot be found or decoded.
	 */
	protected function loadJsonResponse(): void {
		$path = __DIR__ . "/api/" . $this->jsonPath;

		if ( ! file_exists( $path ) ) {
			throw new \Exception( "Fichier JSON introuvable : " . $path );
		}

		$jsonContent = file_get_contents( $path );
		$data        = json_decode( $jsonContent, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( "Erreur de décodage JSON : " . json_last_error_msg() );
		}

		if ( isset( $data["body"]["Guid"] ) ) {
			$this->guid = $data["body"]["Guid"];
		} else if ( isset( $data["body"]["Result"] ) ) {
			$this->guid = $data["body"]["Result"];
		} else {
			$this->guid = null;
		}

		$this->response = [
			"code" => $data["code"],
			"body" => json_encode($data["body"])
		];
	}
}

/**
 * Successful "find user by email" response from Koban.
 */
class FindUserByEmailSuccess extends MockResponse {
	public string $method = 'GET';
	public string $endpoint = 'ncThird/GetOneByKey?uniqueproperty=Email&value=';
	public string $jsonPath = 'find_user_by_email_success.json';
}

/**
 * NotFound "find user by email" response from Koban.
 */
class FindUserByEmailNotFound extends MockResponse {
	public string $method = 'GET';
	public string $endpoint = 'ncThird/GetOneByKey?uniqueproperty=Email&value=';
	public string $jsonPath = 'find_user_by_email_error.json';
}

/**
 * Successful "createInvoice" response from Koban.
 */
class CreateInvoiceSuccess extends MockResponse {
	public string $method = 'POST';
	public string $endpoint = '/ncInvoice';
	public string $jsonPath = 'create_invoice_success.json';
}

/**
 * Successful "getInvoicePDF" response from Koban.
 */
class GetInvoicePdfSuccess extends MockResponse {
	public string $method = 'GET';
	public string $endpoint = '/ncInvoice/GetPDF';
	public string $jsonPath = 'get_invoice_pdf_success.json';
}

/**
 * Successful "createThird" response from Koban.
 */
class CreateThirdSuccess extends MockResponse {
	public string $method = 'POST';
	public string $endpoint = 'ncThird/PostOne?uniqueproperty=Extcode';
	public string $jsonPath = 'create_third_success.json';
}

/**
 * Successful "updateThird" response from Koban.
 */
class UpdateThirdSuccess extends MockResponse {
	public string $method = 'POST';
	public string $endpoint = 'ncThird/PostOne?uniqueproperty=Extcode';
	public string $jsonPath = 'update_third_success.json';
}