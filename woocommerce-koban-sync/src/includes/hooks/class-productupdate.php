<?php
/**
 * ProductUpdate class file.
 *
 * Handles synchronization of product changes to Koban CRM when products are created or updated.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Hooks;

use WCKoban\API;
use WCKoban\Logger;
use WCKoban\Serializers\UpsertProduct;

/**
 * Class ProductUpdate
 *
 * Registers a WooCommerce hook on product update and send Product data to Koban
 */
class ProductUpdate {

	/**
	 * Register the product update handler with WooCommerce.
	 */
	public function register(): void {
		add_action( 'woocommerce_new_product', array( $this, 'handle_product_update' ), 10, 1 );
		add_action( 'woocommerce_update_product', array( $this, 'handle_product_update' ), 10, 1 );
	}

	/**
	 * Syncs or updates a product in Koban when a WooCommerce product is created or updated.
	 *
	 * @param int $product_id   The WooCommerce Product ID.
	 */
	public function handle_product_update( int $product_id ): void {
		Logger::info(
			'Detected product create/update',
			array(
				'product_id' => $product_id,
			)
		);

		$transient_key = 'wckoban_product_processing_' . $product_id;
		if ( get_transient( $transient_key ) ) {
			Logger::info(
				'Skipping repeated product update trigger due to transient lock',
				array( 'product_id' => $product_id )
			);

			return;
		}

		set_transient( $transient_key, true, 2 );
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			Logger::error(
				'Could not retrieve WooCommerce product',
				array( 'product_id' => $product_id )
			);

			return;
		}

		$product_paylaod = ( new UpsertProduct() )->product_to_koban( $product );
		Logger::info(
			'Serializing product data for Koban upsert',
			array( 'serialized' => $product_paylaod )
		);

		if ( isset( $product_paylaod['Guid'] ) ) {
			( new API() )->update_product( $product_paylaod );
		} else {
			$koban_product_guid = ( new API() )->create_product( $product_paylaod );
			$product->update_meta_data( 'koban_guid', $koban_product_guid );
			$product->save();
		}
	}
}
