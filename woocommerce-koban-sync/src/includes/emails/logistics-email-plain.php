<?php
/**
 * Logistics Email (Plain text template)
 *
 * @package WooCommerceKobanSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	Hello Logistics Team,

	A new order has just completed payment. The following documents are attached:
	- The invoice PDF from Koban
	- The Chronopost shipping label

	Order ID:  <?php echo esc_html( $order->get_id() ) . "\n"; ?>
	Customer:  <?php echo esc_html( $order->get_billing_first_name() ) . ' ' . esc_html( $order->get_billing_last_name() ) . "\n"; ?>
<?php
