<?php
/**
 * ProductSerializer class file.
 *
 * Builds Koban payloads to create or update Products in Koban CRM.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Serializers;

use WC_Product;
use WCKoban\Utils\MetaUtils;

/**
 * Class ProductSerializer
 *
 * Handles serialization for Koban upsertProduct payloads
 */
class ProductSerializer {

	/**
	 * Serializes a WooCommerce product into a Koban upsertProduct payload.
	 *
	 * @param WC_Product $product The WooCommerce product object.
	 *
	 * @return array The Koban upsertProduct payload
	 * TODO: Tax and prices Handling
	 */
	public function from_product( WC_Product $product ): array {
		$product_id          = $product->get_id();
		$koban_product_guid  = MetaUtils::get_koban_product_guid( $product );
		$categories          = get_the_terms( $product_id, 'product_cat' );
		$koban_category_code = null;
		$price_excl_tax      = $product->get_price();
		$vat_rate            = 20; // or parse from product’s tax_class if you have more advanced logic.
		$ttc                 = (float) $price_excl_tax * ( 1 + ( $vat_rate / 100 ) );
		$image_id            = $product->get_image_id();

		if ( ! empty( $categories ) ) {
			$category            = array_pop( $categories );
			$koban_category_code = MetaUtils::get_koban_category_code( $category->term_id );
		}

		$data = array(
			'Label'          => $product->get_name(),
			'Catproduct'     => array( 'Reference' => $koban_category_code ),
			'Ht'             => (float) $price_excl_tax,
			'Vat'            => $vat_rate,
			'Ttc'            => round( $ttc, 2 ),
			'IsSelling'      => true,
			'eShopURL'       => get_permalink( $product_id ),   // Does not display ?
			'VatUpdatable'   => true,
			'IsManufactured' => true,
			'Obsolete'       => false,
			'DCreated'       => $product->get_date_created()->getTimestamp(),
			'DUpdated'       => $product->get_date_modified()->getTimestamp(),
		);

		if ( $image_id ) {
			$data['ImageUrl'] = wp_get_attachment_image_url( $image_id, 'medium' );
		}
		if ( $koban_product_guid ) {  // Update.
			$data['Guid'] = $koban_product_guid;
		} else {    // Create.
			$data['Reference'] = PRODUCT_PREFIX . $product_id;
		}
		return $data;
	}
}
