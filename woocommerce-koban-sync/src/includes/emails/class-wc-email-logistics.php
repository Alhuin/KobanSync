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
		$this->title          = 'Logistics Email';
		$this->description    = 'Sends invoice PDF + shipping label to logistics.';
		$this->template_html  = 'logistics-email.php';
		$this->template_plain = 'logistics-email-plain.php';
		$this->email_type     = 'html';
		$this->recipient      = $this->get_option( 'recipient', 'logistics@example.com' );

		$this->template_base = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/';

		parent::__construct();

		if ( 'yes' === $this->get_option( 'enabled', 'yes' ) ) {
			$this->enabled = 'yes';
		}
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

		$this->subject                 = "Invoice & Shipping label for order #$order_number";
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
