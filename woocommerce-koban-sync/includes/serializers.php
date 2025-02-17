<?php

/**
 * Contains helper classes for serializing data into Koban-compatible structures.
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
		$defaults = [
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
		];

		$billing = wp_parse_args( $billing_data, $defaults );

		WCKoban_Logger::info( 'Preparing Third payload from billing', [ 'billing' => $billing ] );

		$label = trim( $billing['first_name'] . ' ' . $billing['last_name'] );
		if ( $label == '' ) {
			$label = $billing['email'] ?: 'Guest';
		}

		$status_code = 'PTC'; // code for "Particuliers"
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

		return [
			'Label'      => $label,
			'FirstName'  => $billing['first_name'],
			'Status'     => [
				'Code' => $status_code,
			],
			'Type'       => [
				'Code' => $type_code,
			],
			'Address'    => [
				'Name'      => $billing['last_name'],
				'FirstName' => $billing['first_name'],
				'Phone'     => $billing['phone'],
				'Street'    => trim( $billing['address_1'] . ' ' . $billing['address_2'] ),
				'ZipCode'   => $billing['postcode'],
				'City'      => $billing['city'],
				'Country'   => $billing['country'] ? mb_strtoupper( $billing['country'], 'utf-8' ) : 'FR',
			],
			'Cell'       => $billing['phone'],
			'EMail'      => $billing['email'],
			'AssignedTo' => [
				'FullName' => 'Florian Piedimonte',
			],
			'Optin'      => true,
		];
	}

	/**
	 * Transforms a WooCommerce Order into a Koban Invoice payload.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param string $kobanThirdGuid The Koban Third GUID.
	 *
	 * @return array    Koban-compatible invoice payload.
	 * TODO: Tax handling
	 */
	public static function order_to_koban_invoice( \WC_Order $order, ?string $kobanThirdGuid = '' ): array {
		$invoiceNumber = $order->get_order_number();
		$invoiceDate   = gmdate( 'Y-m-d\TH:i:s\Z' ); // or from $order->get_date_created()

		$paymentMethod = $order->get_payment_method();
		$lines         = [];

		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product             = $item->get_product();
			$quantity            = $item->get_quantity();
			$lineLabel           = $product ? $product->get_name() : $item->get_name();
			$lineSubtotalExclTax = $item->get_subtotal();
			$lineTotalExclTax    = $item->get_total();
			$vatRate             = 0;

			if ( (float) $item->get_subtotal_tax() > 0 ) {
				$vatRate = 20; // or get from $item->get_taxes() if multiple rates
			}

			$ht  = (float) $lineTotalExclTax;
			$ttc = $ht * ( 1 + ( $vatRate / 100 ) );

			$lines[] = [
				'Label'     => $lineLabel,
				'Quantity'  => $quantity,
				'Ht'        => $ht,
				'Ttc'       => round( $ttc, 2 ),
				'Vat'       => $vatRate,
				'UnitPrice' => ( $quantity > 0 ) ? round( $ht / $quantity, 2 ) : $ht,
			];
		}

		$third = [];
		if ( $kobanThirdGuid ) {
			$third = [ 'Guid' => $kobanThirdGuid ];
		}

		return [
			'Number'      => $invoiceNumber,
			'InvoiceDate' => $invoiceDate,
			'DueDate'     => '',
			'Status'      => 'SENT',
			'Third'       => $third,
			'Lines'       => $lines,
			'PaymentMode' => 'CB',
		];
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
		$reference = $product->get_sku();
		if ( empty( $reference ) ) {
			$reference = 'PROD-' . $product->get_id();
		}

		$priceExclTax = $product->get_price();
		$vatRate      = 20; // or parse from product’s tax_class if you have more advanced logic
		$ttc          = (float) $priceExclTax * ( 1 + ( $vatRate / 100 ) );

		return [
			'Reference' => $reference,
			'Label'     => $product->get_name(),
			'Comments'  => $product->get_description(),
			'Ht'        => (float) $priceExclTax,
			'Vat'       => $vatRate,
			'Ttc'       => round( $ttc, 2 ),
			'IsSelling' => true,
		];
	}

}
