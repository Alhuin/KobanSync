<?php
/**
 * ProductUpdate class file.
 *
 * Handles synchronization of WooCommerce product data with Koban CRM whenever
 * a product is created or updated.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WC_Product;
use WCKoban\API;
use WCKoban\Logger;
use WCKoban\Serializers\UpsertProduct;
use WCKoban\Utils\MetaUtils;

/**
 * Class ProductUpdate
 *
 * Attaches to WooCommerce product CRUD actions (creation/updating) to ensure
 * the product information is reflected in Koban CRM.
 */
class ProductUpdate {

	/**
	 * An instance of the Koban API client.
	 *
	 * @var API
	 */
	private API $api;

	/**
	 * Adds a WooCommerce hook to detect when a product is created or updated.
	 */
	public function register(): void {
		add_action( 'woocommerce_new_product', array( $this, 'schedule_product_update' ) );
		add_action( 'woocommerce_update_product', array( $this, 'schedule_product_update' ) );
		add_action( 'wckoban_handle_product_update', array( $this, 'handle_product_update' ), 10, 2 );
	}

	/**
	 * Registers the handler as a background task if an update was not triggered in the last 2 seconds.
	 *
	 * @param int $product_id The WC_Product ID.
	 */
	public function schedule_product_update( int $product_id ): void {
		$workflow_id = uniqid( 'wkf_', true );

		$transient_key = 'wckoban_product_processing_' . $product_id;

		// If we've processed this product update in the last 2 seconds, skip.
		if ( get_transient( $transient_key ) ) {
			Logger::debug(
				$workflow_id,
				"Skipping repeated product update trigger due to transient lock for product: $product_id"
			);
		}

		set_transient( $transient_key, true, 2 );
		Logger::debug( $workflow_id, "Scheduling background sync for product: {$product_id}" );

		as_enqueue_async_action(
			'wckoban_handle_product_update',
			array(
				'product_id'  => $product_id,
				'workflow_id' => $workflow_id,
			),
			'koban-sync'
		);
	}

	/**
	 * Called whenever a product is created or updated in WooCommerce.
	 *  Initiates a small workflow to update Koban.
	 *
	 * @param int    $product_id The WooCommerce Product ID.
	 * @param string $workflow_id The Workflow ID.
	 */
	public function handle_product_update( int $product_id, string $workflow_id ): void {
		Logger::debug(
			$workflow_id,
			'Detected product create/update',
			array( 'product_id' => $product_id )
		);

		$steps = array(
			array( $this, 'check_data_integrity' ),
			array( $this, 'upsert_koban_product' ),
		);

		$data = array( 'product_id' => $product_id );

		// TODO: Get last failed step.
		$last_failed_step = null;
		$state            = new StateMachine( $steps, $workflow_id, $data, $last_failed_step );
		$this->api        = new API( $workflow_id );
		$state->process_steps();
	}

	/**
	 * Ensures the product data is valid and prevents duplicate triggers via a transient lock.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True if the product is valid or early-termination; false otherwise.
	 */
	public function check_data_integrity( StateMachine $state ): bool {
		$product_id = $state->get_data( 'product_id' );

		if ( ! wc_get_product( $product_id ) instanceof WC_Product ) {
			/* translators: %s: the WooCommerce Product ID */
			return $state->failed( sprintf( __( 'Invalid Product ID: %s.', 'woocommerce-koban-sync' ), $product_id ) );
		}

		return $state->success();
	}

	/**
	 * Creates or updates a product in Koban.
	 *
	 * @param  StateMachine $state The workflow state manager.
	 * @return bool                True on success; false otherwise.
	 */
	public function upsert_koban_product( StateMachine $state ): bool {
		$product         = wc_get_product( $state->get_data( 'product_id' ) );
		$product_payload = ( new UpsertProduct() )->product_to_koban( $product );

		if ( isset( $product_payload['Guid'] ) ) {
			if ( $this->api->update_product( $product_payload ) ) {
				return $state->success( __( 'Updated Koban Product.', 'woocommerce-koban-sync' ) );
			}
			return $state->failed( __( 'Could not update Koban Product.', 'woocommerce-koban-sync' ) );
		}

		$koban_product_guid = $this->api->create_product( $product_payload );
		if ( $koban_product_guid ) {
			MetaUtils::set_koban_product_guid_for_product( $product, $koban_product_guid );
			return $state->success( __( 'Created Koban Product.', 'woocommerce-koban-sync' ) );
		}
		return $state->failed( __( 'Could not create Koban Product.', 'woocommerce-koban-sync' ) );
	}
}
