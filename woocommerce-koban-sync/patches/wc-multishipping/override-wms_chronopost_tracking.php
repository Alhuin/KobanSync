<?php
/**
 * Should override the content of wc-multishipping/inc/resources/email/templates/wms_chronopost_tracking.php
 *
 * - Adds the default WooCommerce header
 * - Rephrases the $tracking_url sentence (in French)
 * - Adds the default WooCommerce footer
 */
// ------------------------------------------------------------------------------------------------
// WCKoban: Override Chronopost E-mail Template
// ------------------------------------------------------------------------------------------------
do_action( do_action( 'woocommerce_email_header', $email_heading, $email ) );

echo sprintf( __( 'Hi %s,', 'wc-multishipping' ), esc_html( $order->get_billing_first_name() ) ) . '<br/><br/>';
echo sprintf( __( 'The label for order #%s has been generated.', 'wc-multishipping' ), esc_html( $order->get_order_number() ) ) . '<br/><br/>';
echo sprintf( 'Une fois que nous aurons expédié votre commande, vous pourrez la suivre sur ce lien: %s', esc_html( $tracking_url ) ) . '<br/><br/>';
echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );

do_action( 'woocommerce_email_footer', $email );
// ------------------------------------------------------------------------------------------------
// End WCKoban
// ------------------------------------------------------------------------------------------------
