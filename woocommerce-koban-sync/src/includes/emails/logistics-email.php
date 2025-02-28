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

	<p><?php echo esc_html__( 'Hello Logistics Team,', 'woocommerce-koban-sync' ); ?></p>

	<p>
		<?php echo esc_html__( 'A new order has just completed payment. The following documents are attached:', 'woocommerce-koban-sync' ); ?>
	</p>
	<ul>
		<li><?php echo esc_html__( 'The invoice PDF', 'woocommerce-koban-sync' ); ?></li>
		<li><?php echo esc_html__( 'The Chronopost shipping label', 'woocommerce-koban-sync' ); ?></li>
	</ul>

	<p>
		<strong><?php echo esc_html__( 'Order ID:', 'woocommerce-koban-sync' ); ?></strong>
		<?php echo esc_html( $order->get_id() ); ?><br>

		<strong><?php echo esc_html__( 'Customer:', 'woocommerce-koban-sync' ); ?></strong>
		<?php
		echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		?>
	</p>

<?php
do_action( 'woocommerce_email_footer', $email );
