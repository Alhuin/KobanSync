<?php
/**
 * ThirdSerializer class file.
 *
 * Builds Koban payloads to create or update WooCommerce "Thirds" (accounts) in Koban CRM.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Serializers;

use WC_Customer;
use WC_Order;

/**
 * Class ThirdSerializer
 *
 * Handles serialization for Koban upsertThird payloads
 */
class ThirdSerializer {

	/**
	 *  WC_Order to Koban Third payload
	 *
	 * @param WC_Order $order The WooCommerce Order.
	 *
	 * @return array The Koban Third payload.
	 */
	public function from_order( WC_Order $order ): array {
		return $this->billing_to_koban_third(
			$this->billing_data_from_order( $order )
		);
	}

	/**
	 *  WC_Customer to Koban Third payload
	 *
	 * @param WC_Customer $user The WooCommerce Customer.
	 *
	 * @return array The Koban Third payload.
	 */
	public function from_user( WC_Customer $user ): array {
		return $this->billing_to_koban_third(
			$this->billing_data_from_user( $user )
		);
	}

	/**
	 * Builds a Koban Third payload from billing data.
	 *
	 * @param array $billing_data Associative array of billing fields.
	 *
	 * @return array The Koban upsertThird payload
	 */
	protected static function billing_to_koban_third( array $billing_data ): array {
		$defaults = array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'phone'      => '',
			'address_1'  => '',
			'address_2'  => '',
			'city'       => '',
			'postcode'   => '',
			'country'    => '',
		);

		$billing = wp_parse_args( $billing_data, $defaults );

		$label = trim( $billing['first_name'] . ' ' . $billing['last_name'] );
		if ( '' === $label ) {
			$label = $billing['email'] ?? 'Guest';
		}

		$status_code = CUSTOMER_THIRD_STATUS_CODE;
		$type_code   = CUSTOMER_COUNTRY_THIRD_TYPE_CODE_MAPPER[ $billing['country'] ] ?? 'Particuliers (Autre)';

		$billing_address = array(
			'Name'      => $billing['last_name'],
			'FirstName' => $billing['first_name'],
			'Phone'     => $billing['phone'],
			'Street'    => trim( $billing['address_1'] . ' ' . $billing['address_2'] ),
			'ZipCode'   => $billing['postcode'],
			'City'      => $billing['city'],
			'Country'   => $billing['country'] ? mb_strtoupper( $billing['country'], 'utf-8' ) : 'FR',
		);

		return array(
			'Label'          => $label,
			'FirstName'      => $billing['first_name'],
			'Status'         => array(
				'Code' => $status_code,
			),
			'Type'           => array(
				'Code' => $type_code,
			),
			'Address'        => $billing_address,
			'InvoiceAddress' => $billing_address,
			'EMail'          => $billing['email'],
			'AssignedTo'     => array(
				'FullName' => DEFAULT_ASSIGNEDTO_FULLNAME,
			),
			'Optin'          => true,
		);
	}

	/**
	 * Extracts relevant billing fields from a WordPress User.
	 *
	 * @param WC_Customer $user The WooCommerce Customer.
	 *
	 * @return array An associative array of billing data.
	 */
	protected static function billing_data_from_user( WC_Customer $user ): array {
		return array(
			'first_name' => $user->get_billing_first_name(),
			'last_name'  => $user->get_billing_last_name(),
			'email'      => $user->get_billing_email(),
			'phone'      => $user->get_billing_phone(),
			'address_1'  => $user->get_billing_address_1(),
			'address_2'  => $user->get_billing_address_2(),
			'city'       => $user->get_billing_city(),
			'postcode'   => $user->get_billing_postcode(),
			'country'    => $user->get_billing_country(),
		);
	}

	/**
	 * Extracts relevant billing fields from a WooCommerce Order.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 *
	 * @return array An associative array of billing data.
	 */
	protected static function billing_data_from_order( WC_Order $order ): array {
		return array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
		);
	}
}
