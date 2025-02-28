<?php
/**
 * PaymentSerializer class file.
 *
 * Builds Koban payloads to create Payments in Koban CRM.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Serializers;

use WC_Order;

/**
 * Class PaymentSerializer
 *
 * Handles serialization for Koban createPayment payloads
 */
class PaymentSerializer {

	/**
	 * Serializes a WooCommerce Order into a Koban createPayment payload.
	 *
	 * @param WC_Order $order               The WooCommerce order.
	 * @param string   $koban_invoice_guid  The Koban Invoice GUID.
	 *
	 * @return array    The Koban createPayment payload
	 */
	public function from_order( WC_Order $order, string $koban_invoice_guid ): array {
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
