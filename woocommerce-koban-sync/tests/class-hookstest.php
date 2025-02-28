<?php
/**
 * Tests for the WooCommerce Koban Sync plugin hooks.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Tests;

use WCKoban\Hooks\CustomerSaveAddressHook;
use WCKoban\Hooks\ProductUpdateHook;
use WCKoban\Hooks\PaymentCompleteHook;
use WCKoban\Utils\MetaUtils;
use WCKoban\Tests\Mocks\CreateInvoiceSuccess;
use WCKoban\Tests\Mocks\CreatePaymentSuccess;
use WCKoban\Tests\Mocks\CreateProductSuccess;
use WCKoban\Tests\Mocks\CreateThirdSuccess;
use WCKoban\Tests\Mocks\FindUserByEmailNotFound;
use WCKoban\Tests\Mocks\FindUserByEmailSuccess;
use WCKoban\Tests\Mocks\GetInvoicePdfSuccess;
use WCKoban\Tests\Mocks\UpdateProductSuccess;
use WCKoban\Tests\Mocks\UpdateThirdSuccess;
use function WCKoban\Tests\Mocks\set_next_responses;

/**
 * Class HooksTest
 *
 * Contains unit tests for the key WooCommerce hooks
 * that trigger Koban sync logic (payment complete, address save,
 * product create/update).
 */
class HooksTest extends WCKoban_UnitTestCase {

	/**
	 * Sets up each test by initializing mocks and WP test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		global $debug;
		$debug = true;
		$this->reset_mocks();
	}

	/**
	 * Payment complete: Background handler action should be scheduled on woocommerce_payment_complete.
	 */
	public function test_payment_complete_action_scheduled() {
		$order_id = $this->create_wc_order();

		do_action( 'woocommerce_payment_complete', $order_id );

		$this->assertTrue( as_has_scheduled_action( 'wckoban_handle_payment_complete' ) );
	}

	/**
	 * Payment complete: Registered user, no Koban GUID, email found in Koban.
	 */
	public function test_payment_complete_registered_user_no_guid_email_exists() {
		$expected_requests = array(
			new FindUserByEmailSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		$customer_id = $this->create_wc_customer();
		$order_id    = $this->create_wc_order( array( 'customer_id' => $customer_id ) );
		$this->setup_shipping_label( $order_id );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, 'workflow_id' );

		$this->assertRequestsCount( 4 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			MetaUtils::get_koban_third_guid( $customer_id ),
			( new FindUserByEmailSuccess() )->guid,
			'Expected user_meta to match the found Koban GUID.'
		);
		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Expected order_meta to match the newly created Koban invoice GUID.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order );
	}

	/**
	 * Payment complete: Registered user, no Koban GUID, email not found in Koban.
	 */
	public function test_payment_complete_registered_user_no_guid_email_does_not_exist() {
		$expected_requests = array(
			new FindUserByEmailNotFound(),
			new CreateThirdSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		$customer_id = $this->create_wc_customer();
		$order_id    = $this->create_wc_order( array( 'customer_id' => $customer_id ) );
		$this->setup_shipping_label( $order_id );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, 'workflow_id' );

		$this->assertRequestsCount( 5 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			MetaUtils::get_koban_third_guid( $customer_id ),
			( new CreateThirdSuccess() )->guid,
			'Expected user_meta to store the newly created Koban Third GUID.'
		);
		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Expected order_meta to store the new Koban invoice GUID.'
		);
		$this->assertSame(
			MetaUtils::get_koban_payment_guid( $order ),
			( new CreatePaymentSuccess() )->guid,
			'Expected order_meta to store the new Koban payment GUID.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order ); }

	/**
	 * Payment complete: Registered user with existing Koban GUID, no Third creation/update needed.
	 */
	public function test_payment_complete_registered_user_with_meta_guid() {
		$expected_requests = array(
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		$customer_id = $this->create_wc_customer();
		MetaUtils::set_koban_third_guid( $customer_id, 'test_koban_guid' );
		$order_id = $this->create_wc_order( array( 'customer_id' => $customer_id ) );
		$this->setup_shipping_label( $order_id );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, 'workflow_id' );

		$this->assertRequestsCount( 3 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			'test_koban_guid',
			MetaUtils::get_koban_third_guid( $customer_id ),
			'Existing Koban GUID should remain unchanged.'
		);
		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Order invoice GUID should match newly created Koban invoice.'
		);
		$this->assertSame(
			MetaUtils::get_koban_payment_guid( $order ),
			( new CreatePaymentSuccess() )->guid,
			'Order payment GUID should match newly created Koban payment.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order ); }

	/**
	 * Payment complete: Guest user, email found in Koban.
	 */
	public function test_payment_complete_guest_user_email_exists() {
		$expected_requests = array(
			new FindUserByEmailSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		$order_id = $this->create_wc_order();
		$this->setup_shipping_label( $order_id );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, 'workflow_id' );

		$this->assertRequestsCount( 4 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			MetaUtils::get_koban_invoice_guid( $order ),
			( new CreateInvoiceSuccess() )->guid,
			'Order invoice GUID should match newly created Koban invoice.'
		);
		$this->assertSame(
			MetaUtils::get_koban_payment_guid( $order ),
			( new CreatePaymentSuccess() )->guid,
			'Order payment GUID should match newly created Koban payment.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order ); }

	/**
	 * Payment complete: Guest user, email not found in Koban.
	 */
	public function test_payment_complete_guest_user_email_does_not_exist() {
		$expected_requests = array(
			new FindUserByEmailNotFound(),
			new CreateThirdSuccess(),
			new CreateInvoiceSuccess(),
			new CreatePaymentSuccess(),
			new GetInvoicePdfSuccess(),
		);
		set_next_responses( $expected_requests );

		$order_id = $this->create_wc_order();
		$this->setup_shipping_label( $order_id );

		( new PaymentCompleteHook() )->handle_payment_complete( $order_id, 'workflow_id' );

		$this->assertRequestsCount( 5 );
		$this->assertRequests( $expected_requests );

		$order = wc_get_order( $order_id );

		$this->assertSame(
			( new CreateInvoiceSuccess() )->guid,
			MetaUtils::get_koban_invoice_guid( $order ),
			'Invoice GUID in order_meta should match newly created Koban invoice.'
		);
		$this->assertSame(
			( new CreatePaymentSuccess() )->guid,
			MetaUtils::get_koban_payment_guid( $order ),
			'Payment GUID in order_meta should match newly created Koban payment.'
		);

		$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );

		$this->assertNotEmpty( $koban_invoice_pdf_path );
		$this->assertFileExists( $koban_invoice_pdf_path );
		$this->assertLogisticsEmailSentWithAttachments( $order );
	}

	/**
	 * Payment complete: Background handler action should be scheduled on woocommerce_customer_save_address.
	 */
	public function test_customer_save_address_action_scheduled() {
		$customer_id = $this->create_wc_customer();

		do_action( 'woocommerce_customer_save_address', $customer_id, 'address_type' );

		$this->assertTrue( as_has_scheduled_action( 'wckoban_handle_customer_save_address' ) );
	}

	/**
	 * Customer billing address update with no existing Koban GUID: no remote call expected.
	 */
	public function test_customer_save_address_no_guid() {
		$customer_id = $this->create_wc_customer();

		( new CustomerSaveAddressHook() )->handle_customer_save_address( $customer_id, 'billing', 'workflow_id' );

		$this->assertRequestsCount( 0 );
	}

	/**
	 * Customer shipping address update with an existing Koban GUID should not sync to Koban (billing only).
	 */
	public function test_customer_save_address_shipping_with_guid() {
		$customer_id = $this->create_wc_customer();
		MetaUtils::set_koban_third_guid( $customer_id, 'testKobanGuid' );

		( new CustomerSaveAddressHook() )->handle_customer_save_address( $customer_id, 'shipping', 'workflow_id' );
		$this->assertRequestsCount( 0 );
	}

	/**
	 * Customer billing address update with an existing Koban GUID should trigger an update call to Koban.
	 */
	public function test_customer_save_address_billing_with_guid() {
		$expected_requests = array(
			new UpdateThirdSuccess(),
		);
		set_next_responses( $expected_requests );

		$customer_id = $this->create_wc_customer();
		MetaUtils::set_koban_third_guid( $customer_id, 'testKobanGuid' );

		( new CustomerSaveAddressHook() )->handle_customer_save_address( $customer_id, 'billing', 'workflow_id' );

		$this->assertRequestsCount( 1 );
		$this->assertRequests( $expected_requests );
	}

	/**
	 * Payment complete: Background handler action should be scheduled on woocommerce_update_product.
	 */
	public function test_product_update_action_scheduled() {
		$product_id = $this->create_wp_post_product();

		do_action( 'woocommerce_update_product', $product_id );
		$this->assertTrue( as_has_scheduled_action( 'wckoban_handle_product_update' ) );
	}

	/**
	 * Payment complete: Background handler action should be scheduled on woocommerce_new_product.
	 */
	public function test_product_create_action_scheduled() {
		$product_id = $this->create_wp_post_product();

		do_action( 'woocommerce_new_product', $product_id );
		$this->assertTrue( as_has_scheduled_action( 'wckoban_handle_product_update' ) );
	}

	/**
	 * Create a WooCommerce product with no existing Koban GUID; triggers woocommerce_new_product.
	 */
	public function test_create_product() {
		$expected_requests = array(
			new CreateProductSuccess(),
		);
		set_next_responses( $expected_requests );

		$product_id = $this->create_wp_post_product();

		( new ProductUpdateHook() )->handle_product_update( $product_id, 'workflow_id' );

		$this->assertRequestsCount( 1 );
		$this->assertRequests( $expected_requests );

		$product = wc_get_product( $product_id );
		$this->assertSame(
			MetaUtils::get_koban_product_guid( $product ),
			( new CreateProductSuccess() )->guid,
			'Expected newly created product to store the Koban product GUID.'
		);
	}

	/**
	 * Update a product by post ID, which triggers woocommerce_update_product with an existing GUID.
	 */
	public function test_update_product() {
		$expected_requests = array(
			new UpdateProductSuccess(),
		);
		set_next_responses( $expected_requests );

		$product_id = $this->create_wp_post_product();
		MetaUtils::set_koban_product_guid_for_product_id( $product_id, 'test_koban_guid' );

		( new ProductUpdateHook() )->handle_product_update( $product_id, 'workflow_id' );

		$this->assertRequestsCount( 1 );
		$this->assertRequests( $expected_requests );
	}
}
