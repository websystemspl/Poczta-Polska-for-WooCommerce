<?php
/**
 * Sender details.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use PocztaPolska\WooCommerce\Support\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Sender details: the plugin settings, falling back to the WooCommerce store address.
 */
final class Sender {

	/**
	 * Field lengths from the EN data specification (sender).
	 *
	 * @var array<string, int>
	 */
	private const MAX_LENGTH = array(
		'name'         => 30,
		'name2'        => 30,
		'street'       => 35,
		'house_number' => 11,
		'flat_number'  => 11,
		'city'         => 35,
		'email'        => 50,
	);

	/**
	 * Returns the sender details used for shipments.
	 *
	 * @return array{name: string, name2: string, street: string, house_number: string, flat_number: string, postcode: string, city: string, mobile: string, phone: string, email: string}
	 */
	public static function data(): array {
		$defaults = self::store_defaults();
		$data     = array();

		foreach ( $defaults as $key => $default ) {
			$value        = Settings::get( 'sender_' . $key );
			$data[ $key ] = '' !== $value ? $value : $default;
		}

		$data['postcode'] = Validator::postcode( $data['postcode'] ) ?? $data['postcode'];

		return $data;
	}

	/**
	 * Returns the values taken from WooCommerce when a sender field is empty.
	 *
	 * @return array{name: string, name2: string, street: string, house_number: string, flat_number: string, postcode: string, city: string, mobile: string, phone: string, email: string}
	 */
	public static function store_defaults(): array {
		$address = self::split_address( (string) get_option( 'woocommerce_store_address', '' ) );
		$email   = (string) get_option( 'woocommerce_email_from_address', '' );

		return array(
			'name'         => wp_specialchars_decode( (string) get_option( 'blogname', '' ), ENT_QUOTES ),
			'name2'        => '',
			'street'       => $address['street'],
			'house_number' => $address['house_number'],
			'flat_number'  => $address['flat_number'],
			'postcode'     => (string) get_option( 'woocommerce_store_postcode', '' ),
			'city'         => (string) get_option( 'woocommerce_store_city', '' ),
			'mobile'       => '',
			'phone'        => '',
			'email'        => '' !== $email ? $email : (string) get_option( 'admin_email', '' ),
		);
	}

	/**
	 * Splits a one-line address into street, house number and flat number.
	 *
	 * "ul. Testowa 1" -> "ul. Testowa", "1"; "Długa 12A/5" -> "Długa", "12A", "5";
	 * "Długa 12 m. 5" -> "Długa", "12", "5". Anything else stays in the street.
	 *
	 * @param string $address Address line.
	 * @return array{street: string, house_number: string, flat_number: string}
	 */
	public static function split_address( string $address ): array {
		$address = trim( (string) preg_replace( '/\s+/u', ' ', $address ) );

		if ( 1 === preg_match( '/^(.+?)[\s,]+(\d+[a-z]?)(?:\s*(?:\/|m\.|lok\.)\s*(\d+[a-z]?))?$/iu', $address, $matches ) ) {
			return array(
				'street'       => $matches[1],
				'house_number' => $matches[2],
				'flat_number'  => $matches[3] ?? '',
			);
		}

		return array(
			'street'       => $address,
			'house_number' => '',
			'flat_number'  => '',
		);
	}

	/**
	 * Returns problems that would make Elektroniczny Nadawca reject the sender.
	 *
	 * @return list<string>
	 */
	public static function problems(): array {
		$data     = self::data();
		$problems = array();
		$labels   = array(
			'name'   => __( 'name', 'poczta-polska-for-woocommerce' ),
			'street' => __( 'street', 'poczta-polska-for-woocommerce' ),
			'city'   => __( 'city', 'poczta-polska-for-woocommerce' ),
		);

		foreach ( $labels as $key => $label ) {
			if ( '' === $data[ $key ] ) {
				/* translators: %s: field name. */
				$problems[] = sprintf( __( 'The sender %s is missing.', 'poczta-polska-for-woocommerce' ), $label );
			}
		}

		foreach ( self::MAX_LENGTH as $key => $max ) {
			if ( ! Validator::fits( $data[ $key ], $max ) ) {
				$problems[] = sprintf(
					/* translators: 1: field value, 2: maximum number of characters. */
					__( '"%1$s" is longer than %2$d characters. Enter a shorter version in the sender settings.', 'poczta-polska-for-woocommerce' ),
					$data[ $key ],
					$max
				);
			}
		}

		if ( null === Validator::postcode( $data['postcode'] ) ) {
			$problems[] = __( 'The sender postcode must be a Polish postcode (00-000).', 'poczta-polska-for-woocommerce' );
		}

		return $problems;
	}
}
