<?php
/**
 * CreateInvoice class file.
 *
 * Builds Koban payloads to create Invoices in Koban CRM.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Serializers;

use WC_Order;
use WCKoban\Logger;

/**
 * Class Order
 *
 * Handles serialization for Koban createInvoice and createPayment payloads
 */
class Order {
	/**
	 * Serializes a WooCommerce Order into a Koban createInvoice payload.
	 *
	 * @param WC_Order $order          The WooCommerce order.
	 * @param string   $koban_third_guid The Koban Third GUID.
	 *
	 * @return array    The Koban createInvoice payload
	 * TODO: Tax handling
	 */
	public function to_koban_invoice( WC_Order $order, string $koban_third_guid ): array {
		$invoice_number = $order->get_order_number();
		$invoice_date   = gmdate( 'Y-m-d\TH:i:s\Z' ); // or from $order->get_date_created().

		$payment_method = $order->get_payment_method();
		$lines          = array();

		$shipping = array();
		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			// Get the data in an unprotected array.
			$shipping[] = $item->get_data();
		}

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
				'Product'   => array(
					'Guid' => get_post_meta( $item->get_id(), KOBAN_THIRD_GUID_META_KEY, true ),
				),
				'Label'     => $line_label,
				'Quantity'  => $quantity,
				'Ht'        => $ht,
				'Ttc'       => round( $ttc, 2 ),
				'Vat'       => $vat_rate,
				'UnitPrice' => ( $quantity > 0 ) ? round( $ht / $quantity, 2 ) : $ht,
			);
		}

		return array(
			array(
				'Number'      => INVOICE_PREFIX . $invoice_number,
				'InvoiceDate' => $invoice_date,
				'DueDate'     => '',
				'Status'      => 'PENDING',
				'Third'       => array(
					'Guid' => $koban_third_guid,
				),
				'Lines'       => $lines,
				'PaymentMode' => array(
					'Code' => DEFAULT_PAYMENTMODE_CODE,
				),
				'Extcode'     => null,
				'OtherThird'  => null,
				'Contact'     => null,
				'Order'       => null,
				'Header'      => null,
				'AssignedTo'  => array(
					'FullName' => DEFAULT_ASSIGNEDTO_FULLNAME,
				),
			),
		);
	}

	/**
	 * Serializes a WooCommerce Order into a Koban createPayment payload.
	 *
	 * @param WC_Order $order               The WooCommerce order.
	 * @param string   $koban_invoice_guid  The Koban Invoice GUID.
	 *
	 * @return array    The Koban createPayment payload
	 */
	public function to_koban_payment( WC_Order $order, string $koban_invoice_guid ): array {
		return array(
			array(
				'Extcode'     => PAYMENT_PREFIX . $order->get_order_number(),
				'Invoice'     => array(
					'Guid' => $koban_invoice_guid,
				),
				'PaymentDate' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'Ttc'         => $order->get_total(),
				'ModeRglt'    => array(
					'Code' => DEFAULT_PAYMENTMODE_CODE,
				),
			),
		);
	}
}
