<?php
/**
 * WCKoban_Serializer class file.
 *
 * Contains helper methods for serializing data into Koban-compatible structures.
 *
 * @package WooCommerceKobanSync
 */

if ( ! class_exists( 'WCKoban_Serializer' ) ) {
	/**
	 * A client class for serializing data into Koban-compatible structures.
	 */
	class WCKoban_Serializer {


		/**
		 * Builds a Koban Third payload from WooCommerce billing data.
		 *
		 * @param array $billing_data Associative array of billing fields, e.g. from an order or user meta.
		 *
		 * @return array A fully formed payload ready to send to Koban for creating or updating a Third.
		 */
		public static function billing_to_koban_third( array $billing_data ): array {
			$defaults = array(
				'first_name' => '',
				'last_name'  => '',
				'email'      => '',
				'phone'      => '',
				'address_1'  => '',
				'address_2'  => '',
				'city'       => '',
				'state'      => '',
				'postcode'   => '',
				'country'    => '',
			);

			$billing = wp_parse_args( $billing_data, $defaults );

			WCKoban_Logger::info( 'Preparing Third payload from billing', array( 'billing' => $billing ) );

			$label = trim( $billing['first_name'] . ' ' . $billing['last_name'] );
			if ( '' === $label ) {
				$label = $billing['email'] ? $billing['email'] : 'Guest';
			}

			$status_code = 'PTC'; // code for "Particuliers".
			switch ( $billing['country'] ) {
				case 'FR':
					$type_code = 'P';
					break;
				case 'BE':
					$type_code = 'PB';
					break;
				case 'CH':
					$type_code = 'PS';
					break;
				case 'LX':
					$type_code = 'PLX';
					break;
				default:
					$type_code = 'Particuliers (Autre)';
			}

			return array(
				'Label'      => $label,
				'FirstName'  => $billing['first_name'],
				'Status'     => array(
					'Code' => $status_code,
				),
				'Type'       => array(
					'Code' => $type_code,
				),
				'Address'    => array(
					'Name'      => $billing['last_name'],
					'FirstName' => $billing['first_name'],
					'Phone'     => $billing['phone'],
					'Street'    => trim( $billing['address_1'] . ' ' . $billing['address_2'] ),
					'ZipCode'   => $billing['postcode'],
					'City'      => $billing['city'],
					'Country'   => $billing['country'] ? mb_strtoupper( $billing['country'], 'utf-8' ) : 'FR',
				),
				'Cell'       => $billing['phone'],
				'EMail'      => $billing['email'],
				'AssignedTo' => array(
					'FullName' => 'Florian Piedimonte',
				),
				'Optin'      => true,
			);
		}

		/**
		 * Transforms a WooCommerce Order into a Koban Invoice payload.
		 *
		 * @param \WC_Order $order          The WooCommerce order.
		 * @param string    $koban_third_guid The Koban Third GUID.
		 *
		 * @return array    Koban-compatible invoice payload.
		 * TODO: Tax handling
		 */
		public static function order_to_koban_invoice( \WC_Order $order, ?string $koban_third_guid = '' ): array {
			$invoice_number = $order->get_order_number();
			$invoice_date   = gmdate( 'Y-m-d\TH:i:s\Z' ); // or from $order->get_date_created().

			$payment_method = $order->get_payment_method();
			$lines          = array();

			foreach ( $order->get_items() as $item ) {
				$product                = $item->get_product();
				$quantity               = $item->get_quantity();
				$line_label             = $product ? $product->get_name() : $item->get_name();
				$line_subtotal_excl_tax = $item->get_subtotal();
				$line_total_excl_tax    = $item->get_total();
				$vat_rate               = 0;

				if ( (float) $item->get_subtotal_tax() > 0 ) {
					$vat_rate = 20; // or get from $item->get_taxes() if multiple rates.
				}

				$ht  = (float) $line_total_excl_tax;
				$ttc = $ht * ( 1 + ( $vat_rate / 100 ) );

				$lines[] = array(
					'Label'     => $line_label,
					'Quantity'  => $quantity,
					'Ht'        => $ht,
					'Ttc'       => round( $ttc, 2 ),
					'Vat'       => $vat_rate,
					'UnitPrice' => ( $quantity > 0 ) ? round( $ht / $quantity, 2 ) : $ht,
				);
			}

			$third = array();
			if ( $koban_third_guid ) {
				$third = array( 'Guid' => $koban_third_guid );
			}

			return array(
				'Number'      => $invoice_number,
				'InvoiceDate' => $invoice_date,
				'DueDate'     => '',
				'Status'      => 'SENT',
				'Third'       => $third,
				'Lines'       => $lines,
				'PaymentMode' => 'CB',
			);
		}

		/**
		 * Builds a Koban product payload from a WooCommerce product.
		 *
		 * @param \WC_Product $product The WooCommerce product object.
		 *
		 * @return array        A data array suitable for Koban's product API.
		 * TODO: Tax and prices Handling
		 */
		public static function product_to_koban( \WC_Product $product ): array {
			$product_id               = $product->get_id();
			$koban_product_guid       = get_post_meta( $product_id, 'koban_guid', true );
			$categories               = get_the_terms( $product_id, 'product_cat' );
			$koban_category_reference = null;
			$price_excl_tax           = $product->get_price();
			$vat_rate                 = 20; // or parse from product’s tax_class if you have more advanced logic.
			$ttc                      = (float) $price_excl_tax * ( 1 + ( $vat_rate / 100 ) );
			$image_id                 = $product->get_image_id();

			if ( ! empty( $categories ) ) {
				$category                 = array_pop( $categories );
				$koban_category_reference = get_field( 'koban_category_reference', $category );
			}

			$data = array(
				'Label'          => $product->get_name(),
				'Comments'       => $product->get_description(),
				'Catproduct'     => array( 'Reference' => $koban_category_reference ),
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
				// --- Other Fields Available ----
			// 'Model'          => 'Model',
			// 'Brand'          => 'Brand',
			// 'Packing'        => 'Pack',
			// 'StockMin'       => 0,
			// 'PCB'            => 10,
			// 'Regroup'        => 'Regroupment Code',
			// 'Classification' => 'Classification Code',
			// 'Unit'           => 'Unit Code',
			// 'Comments'       => '',
			// 'Margin'         => 0,
			// 'PrHt'           => 0,
			);

			if ( $image_id ) {
				$data['ImageUrl'] = wp_get_attachment_image_url( $image_id, 'medium' );
			}
			if ( $koban_product_guid ) {  // Update.
				$data['Guid'] = $koban_product_guid;
			} else {    // Create.
				$data['Reference'] = 'WKS-' . $product_id;
			}
			WCKoban_Logger::info( 'Serialize', array( 'data' => $data ) );
			return $data;
		}
	}

}
