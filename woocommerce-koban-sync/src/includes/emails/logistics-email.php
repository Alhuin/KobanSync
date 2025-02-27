<?php
/**
 * Logistics Email (HTML template)
 *
 * @package WooCommerceKobanSync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

	<p><?php esc_html_e( 'Hello Logistics Team,', 'woocommerce' ); ?></p>

	<p>
		<?php esc_html_e( 'A new order has just completed payment. The following documents are attached:', 'woocommerce' ); ?>
	</p>
	<ul>
		<li><?php esc_html_e( 'The invoice PDF from Koban', 'woocommerce' ); ?></li>
		<li><?php esc_html_e( 'The Chronopost shipping label', 'woocommerce' ); ?></li>
	</ul>

	<p>
		<strong><?php esc_html_e( 'Order ID:', 'woocommerce' ); ?></strong>
		<?php echo esc_html( $order->get_id() ); ?><br>

		<strong><?php esc_html_e( 'Customer:', 'woocommerce' ); ?></strong>
		<?php
		echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		?>
	</p>

<?php
do_action( 'woocommerce_email_footer', $email );
