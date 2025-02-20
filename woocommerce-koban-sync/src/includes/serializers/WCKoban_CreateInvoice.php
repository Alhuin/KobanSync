<?php
/**
 * WCKoban_CreateInvoice class file.
 *
 * Builds Koban payloads to create Invoices in Koban CRM.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Serializers;

use WC_Order;

/**
 * Class WCKoban_CreateInvoice
 *
 * Handles serialization for Koban createInvoice payloads
 */
class WCKoban_CreateInvoice {
	/**
	 * Serializes a WooCommerce Order into a Koban createInvoice payload.
	 *
	 * @param WC_Order $order          The WooCommerce order.
	 * @param string   $koban_third_guid The Koban Third GUID.
	 *
	 * @return array    The Koban createInvoice payload
	 * TODO: Tax handling
	 */
	public function order_to_koban_invoice( WC_Order $order, ?string $koban_third_guid = '' ): array {
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
}
