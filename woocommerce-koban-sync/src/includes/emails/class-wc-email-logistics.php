<?php
/**
 * Class: WC_Email_Logistics
 *
 * Defines a custom WooCommerce email class that sends an invoice PDF and shipping label to the logistics team.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Emails;

use WC_Order;
use WC_Email;

/**
 * Class WC_Email_Logistics
 *
 * Sends an invoice PDF and Chronopost shipping label to the logistics department.
 */
class WC_Email_Logistics extends WC_Email {

	/**
	 * Constructor.
	 *
	 * Sets email properties and loads default options.
	 */
	public function __construct() {
		$this->id             = 'wc_email_logistics';
		$this->description    = __( 'Sends invoice PDF + shipping label to logistics.', 'woocommerce-koban-sync' );
		$this->template_html  = 'logistics-email.php';
		$this->template_plain = 'logistics-email-plain.php';
		$this->email_type     = 'html';

		$this->init_form_fields();
		$this->init_settings();

		$this->template_base = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/';

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', 'logistics@example.com' );
		$this->title     = $this->get_option( 'title', __( 'Logistics Email', 'woocommerce-koban-sync' ) );

		if ( 'yes' === $this->get_option( 'enabled', 'yes' ) ) {
			$this->enabled = 'yes';
		}
	}

	/**
	 * Add WooCommerce setting to define email recipients
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields = array_merge(
			$this->form_fields,
			array(
				'recipient' => array(
					'title'       => __( 'Recipient(s)', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Enter an email address or multiple addresses (comma separated).', 'woocommerce' ),
					'default'     => 'logistics@example.com',
					'desc_tip'    => true,
				),
			)
		);
	}

	/**
	 * Trigger the email.
	 *
	 * @param int    $order_id    WooCommerce order ID.
	 * @param string $invoice_path Absolute path to the invoice PDF.
	 * @param string $label_path   Absolute path to the shipping label PDF.
	 *
	 * @return bool True if email sent, false otherwise.
	 */
	public function trigger( $order_id, $invoice_path, $label_path ): bool {
		$this->setup_locale();

		$this->object = wc_get_order( $order_id );
		if ( ! $this->object instanceof WC_Order ) {
			return false;
		}

		$order_number = $this->object->get_order_number();

		/* translators: %s: the WooCommerce order number */
		$this->subject                 = sprintf( __( 'Invoice & Shipping label for order #%s', 'woocommerce-koban-sync' ), $order_number );
		$this->find['order_number']    = '{order_number}';
		$this->replace['order_number'] = $order_number;

		$this->attachments = array(
			$invoice_path,
			$label_path,
		);

		$result = false;
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$result = $this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->attachments
			);
		}

		$this->restore_locale();

		return $result;
	}

	/**
	 * Generate the HTML content.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Generate the plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
