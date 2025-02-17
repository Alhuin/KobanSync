<?php
/**
 * Tests for the WooCommerce Koban Sync plugin hooks.
 */

use mocks\api\CreateThirdSuccess;
use mocks\api\FindUserByEmailSuccess;
use mocks\api\CreateInvoiceSuccess;
use mocks\api\GetInvoicePdfSuccess;
use mocks\api\FindUserByEmailNotFound;
use mocks\api\UpdateThirdSuccess;

class HooksTest extends WP_UnitTestCase {

	/**
	 * Set up test environment before each test.
	 */
	public function setUp(): void {
		parent::setUp();
//		global $debug;
//		$debug = true;

		$this->reset_mocks();
	}

	/**
	 * Reset global mock variables for fresh test runs.
	 */
	public function reset_mocks(): void {
		global $wp_remote_requests, $mock_response_queue, $request_index;

		$request_index       = 0;
		$mock_response_queue = [];
		$wp_remote_requests  = [];
	}

	/**
	 * Asserts that the request at a given index matches the expected endpoint.
	 *
	 * @param \mocks\api\MockResponse $mockResponse The expected mock response containing the endpoint.
	 */
	public function assertRequests( array $mockResponses ): void {
		global $wp_remote_requests, $request_index;

		foreach ( $mockResponses as $reponse ) {
			$this->assertStringContainsString(
				$reponse->endpoint,
				$wp_remote_requests[ $request_index ]['url'],
				"Request {$request_index} should be sent to endpoint: " . $reponse->endpoint
			);
			$request_index ++;
		}
	}

	/**
	 * Asserts that the total number of HTTP requests matches the expected count.
	 *
	 * @param int $expected The number of requests expected.
	 */
	public function assertRequestsCount( int $expected ): void {
		global $wp_remote_requests;

		$this->assertCount( $expected, $wp_remote_requests, "Expected {$expected} HTTP requests to be made." );
	}


	/**
	 * Test: payment completed for a registered user with no Koban GUID, and the email exists in Koban.
	 */
	public function test_payment_complete_registered_user_no_guid_email_exists() {
		$expected_requests = [
			new FindUserByEmailSuccess,
			new CreateInvoiceSuccess,
			new GetInvoicePdfSuccess
		];

		$user_id = $this->factory->user->create( [
			'user_login' => 'alice',
			'user_email' => 'alice@example.com',
			'role'       => 'customer',
		] );

		$order    = wc_create_order( [
			'customer_id'        => $user_id,
			'billing_first_name' => 'Alice',
			'billing_last_name'  => 'Wonderland',
			'billing_email'      => 'alice@example.com',
			'billing_phone'      => '0606060606',
			'billing_address_1'  => '4 place marc sangnier',
			'billing_city'       => 'Lyon',
			'billing_country'    => 'FR'
		] );
		$order_id = $order->get_id();

		set_next_responses( $expected_requests );

		wckoban_on_payment_complete( $order_id );

		$this->assertRequestsCount( 3 );

		$this->assertRequests( $expected_requests );

		$this->assertSame(
			get_user_meta( $user_id, 'koban_guid', true ),
			( new FindUserByEmailSuccess )->guid,
			"Expected 'koban_guid' user_meta to match the GUID returned by Koban when searching by email."
		);

		$this->assertSame(
			get_post_meta( $order_id, 'koban_invoice_guid', true ),
			( new CreateInvoiceSuccess )->guid,
			"Expected 'koban_invoice_guid' in order_meta to match the GUID returned by Koban for the invoice."
		);
	}


	/**
	 * Test: payment completed for a registered user with no Koban GUID, and the email does not exist in Koban.
	 */
	public function test_payment_complete_registered_user_no_guid_email_does_not_exist() {
		$expected_requests = [
			new FindUserByEmailNotFound,
			new CreateThirdSuccess,
			new CreateInvoiceSuccess,
			new GetInvoicePdfSuccess
		];

		$user_id = $this->factory->user->create( [
			'user_login' => 'alice',
			'user_email' => 'alice@example.com',
			'role'       => 'customer',
		] );

		$order    = wc_create_order( [
			'customer_id'        => $user_id,
			'billing_first_name' => 'Alice',
			'billing_last_name'  => 'Wonderland',
			'billing_email'      => 'alice@example.com',
			'billing_phone'      => '0606060606',
			'billing_address_1'  => '4 place marc sangnier',
			'billing_city'       => 'Lyon',
			'billing_country'    => 'FR'
		] );
		$order_id = $order->get_id();

		set_next_responses( $expected_requests );

		wckoban_on_payment_complete( $order_id );

		$this->assertRequestsCount( 4 );

		$this->assertRequests( $expected_requests );

		$this->assertSame(
			get_user_meta( $user_id, 'koban_guid', true ),
			( new FindUserByEmailSuccess )->guid,
			"Expected 'koban_guid' user_meta to match the GUID for the newly created user in Koban."
		);

		$this->assertSame(
			get_post_meta( $order_id, 'koban_invoice_guid', true ),
			( new CreateInvoiceSuccess )->guid,
			"Expected 'koban_invoice_guid' in order_meta to match the GUID returned by Koban for the invoice."
		);
	}

	/**
	 * Test: payment completed for a registered user who already has a Koban GUID, skipping user creation/update.
	 */
	public function test_payment_complete_registered_user_with_meta_guid() {
		$expected_requests = [
			new CreateInvoiceSuccess,
			new GetInvoicePdfSuccess
		];

		$user_id = $this->factory->user->create( [
			'user_login' => 'alice',
			'user_email' => 'alice@example.com',
			'role'       => 'customer',
		] );
		update_user_meta( $user_id, "koban_guid", "test_koban_guid" );

		$order    = wc_create_order( [
			'customer_id'        => $user_id,
			'billing_first_name' => 'Alice',
			'billing_last_name'  => 'Wonderland',
			'billing_email'      => 'alice@example.com',
			'billing_phone'      => '0606060606',
			'billing_address_1'  => '4 place marc sangnier',
			'billing_city'       => 'Lyon',
			'billing_country'    => 'FR'
		] );
		$order_id = $order->get_id();

		set_next_responses( $expected_requests );

		wckoban_on_payment_complete( $order_id );

		$this->assertRequestsCount( 2 );

		$this->assertRequests( $expected_requests );

		$this->assertSame(
			"test_koban_guid",
			get_user_meta( $user_id, 'koban_guid', true ),
			"Expected the existing Koban GUID to remain unchanged."
		);
		$this->assertSame(
			get_post_meta( $order_id, 'koban_invoice_guid', true ),
			( new CreateInvoiceSuccess )->guid,
			"Expected 'koban_invoice_guid' in order_meta to match the GUID returned by Koban for the invoice."
		);
	}

	/**
	 * Test: payment completed for a guest user with an email that exists in Koban.
	 */
	public function test_payment_complete_guest_user_email_exists() {
		$expected_requests = [
			new FindUserByEmailSuccess,
			new CreateInvoiceSuccess,
			new GetInvoicePdfSuccess
		];

		$order    = wc_create_order( [
			'customer_id'        => null,
			'billing_first_name' => 'Alice',
			'billing_last_name'  => 'Wonderland',
			'billing_email'      => 'alice@example.com',
			'billing_phone'      => '0606060606',
			'billing_address_1'  => '4 place marc sangnier',
			'billing_city'       => 'Lyon',
			'billing_country'    => 'FR'
		] );
		$order_id = $order->get_id();

		set_next_responses( $expected_requests );

		wckoban_on_payment_complete( $order_id );

		$this->assertRequestsCount( 3 );

		$this->assertRequests( $expected_requests );

		$this->assertSame(
			get_post_meta( $order_id, 'koban_invoice_guid', true ),
			( new CreateInvoiceSuccess )->guid,
			"Expected 'koban_invoice_guid' in order_meta to match the GUID returned by Koban for the invoice."
		);
	}

	/**
	 * Test: payment completed for a guest user with an email that does not exist in Koban.
	 */
	public function test_payment_complete_guest_user_email_does_not_exist() {
		$expected_requests = [
			new FindUserByEmailNotFound,
			new CreateThirdSuccess,
			new CreateInvoiceSuccess,
			new GetInvoicePdfSuccess
		];

		$order    = wc_create_order( [
			'customer_id'        => null,
			'billing_first_name' => 'Alice',
			'billing_last_name'  => 'Wonderland',
			'billing_email'      => 'alice@example.com',
			'billing_phone'      => '0606060606',
			'billing_address_1'  => '4 place marc sangnier',
			'billing_city'       => 'Lyon',
			'billing_country'    => 'FR'
		] );
		$order_id = $order->get_id();

		set_next_responses( $expected_requests );

		wckoban_on_payment_complete( $order_id );

		$this->assertRequestsCount( 4 );

		$this->assertRequests( $expected_requests );

		$this->assertSame(
			get_post_meta( $order_id, 'koban_invoice_guid', true ),
			( new CreateInvoiceSuccess )->guid,
			"Expected 'koban_invoice_guid' in order_meta to match the GUID returned by Koban for the invoice."
		);
	}

	/**
	 * Test: customer updates billing address with no existing Koban GUID.
	 * Should make no calls to Koban.
	 */
	public function test_customer_save_address_no_guid() {
		$user_id = $this->factory->user->create( [
			'user_login' => 'bob',
			'user_email' => 'bob@example.com',
			'role'       => 'customer',
		] );
		update_user_meta( $user_id, 'billing_first_name', 'Bobby' );

		wckoban_on_customer_save_address( $user_id, "billing" );

		$this->assertRequestsCount( 0 );
	}

	/**
	 * Test: customer updates shipping address with an existing Koban GUID.
	 * Should not trigger Koban updates because it's shipping address, not billing.
	 */
	public function test_customer_save_address_shipping_with_guid() {
		$user_id = $this->factory->user->create( [
			'user_login' => 'bob',
			'user_email' => 'bob@example.com',
			'role'       => 'customer',
		] );
		update_user_meta( $user_id, 'koban_guid', 'testKobanGuid' );
		update_user_meta( $user_id, 'billing_first_name', 'Bobby' );

		wckoban_on_customer_save_address( $user_id, "shipping" );

		$this->assertRequestsCount( 0 );
	}

	/**
	 * Test: customer updates billing address with an existing Koban GUID.
	 * Should trigger an update to the Koban third record.
	 */
	public function test_customer_save_address_billing_with_guid() {
		$expected_requests = [
			new UpdateThirdSuccess
		];

		$user_id = $this->factory->user->create( [
			'user_login' => 'bob',
			'user_email' => 'bob@example.com',
			'role'       => 'customer',
		] );
		update_user_meta( $user_id, 'koban_guid', 'testKobanGuid' );
		update_user_meta( $user_id, 'billing_first_name', 'Bobby' );

		set_next_responses( $expected_requests );

		wckoban_on_customer_save_address( $user_id, "billing" );

		$this->assertRequestsCount( 1 );
		$this->assertRequests( $expected_requests );

	}

// TODO: Product tests
//	/**
//	 * Teste la création d'un produit (sans guid existant).
//	 */
//	public function test_create_product() {
//		// Créer un produit simple via la factory WooCommerce.
//		$product_id = $this->factory->product->create_simple( [
//			'name'          => 'Fake Product Name',
//			'regular_price' => '10',
//		] );
//
//		// Définir un SKU
//		update_post_meta( $product_id, '_sku', 'FAKE-SKU-222' );
//
//		// Appeler le hook sur le produit
//		wckoban_on_product_update( $product_id );
//
//		// Vérifier que le produit a désormais un meta 'koban_guid'
//		$koban_guid = get_post_meta( $product_id, 'koban_guid', true );
//		$this->assertNotEmpty( $koban_guid, 'Expected a koban_guid to be set for the product' );
//	}
//
//	/**
//	 * Teste la mise à jour d'un produit ayant déjà un guid.
//	 */
//	public function test_update_product() {
//		// Créer un produit simple.
//		$product_id = $this->factory->product->create_simple( [
//			'name'          => 'Fake Product Old Name',
//			'regular_price' => '10',
//		] );
//		update_post_meta( $product_id, '_sku', 'FAKE-SKU-222' );
//
//		// Simuler qu'il possède déjà un guid Koban
//		update_post_meta( $product_id, 'koban_guid', 'OLD_GUID' );
//
//		// Modifier le nom du produit
//		$product = wc_get_product( $product_id );
//		$product->set_name( 'Fake Product Name Changed' );
//		$product->save();
//
//		// Appeler le hook
//		wckoban_on_product_update( $product_id );
//
//		// Vérifier que le produit a été mis à jour (par exemple, le nom est passé à la nouvelle valeur)
//		// Ici, la fonction de sérialisation du produit dans votre plugin devrait refléter le nouveau nom
//		$payload = WCKoban_Serializer::product_to_koban( $product );
//		$this->assertEquals( 'Fake Product Name Changed', $payload['Label'], 'Expected updated product label' );
//	}
}
