<?php
/**
 * Validation of Polish address and payment data.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes values to the format Elektroniczny Nadawca expects.
 *
 * Every method returns the normalized value, or null when the value is invalid.
 */
final class Validator {

	/**
	 * Polish postcode: "00-590" or "00590" becomes "00590".
	 *
	 * @param string $value Postcode.
	 */
	public static function postcode( string $value ): ?string {
		$value = trim( $value );

		if ( 1 !== preg_match( '/^(\d{2})-?(\d{3})$/', $value, $matches ) ) {
			return null;
		}

		return $matches[1] . $matches[2];
	}

	/**
	 * Polish mobile number: nine digits, an optional +48/0048/48 prefix, spaces and dashes allowed.
	 *
	 * Polish mobile numbers start with 4-8 (UKE numbering plan). EN validates the exact ranges.
	 *
	 * @param string $value Phone number.
	 */
	public static function mobile( string $value ): ?string {
		$digits = (string) preg_replace( '/[\s\-().]/', '', trim( $value ) );
		$digits = (string) preg_replace( '/^(\+48|0048)/', '', $digits );

		if ( 11 === strlen( $digits ) && str_starts_with( $digits, '48' ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( 1 !== preg_match( '/^[4-8]\d{8}$/', $digits ) ) {
			return null;
		}

		return $digits;
	}

	/**
	 * Phone number for the "telefon" field: digits and an optional leading plus, up to 20 characters.
	 *
	 * @param string $value Phone number.
	 */
	public static function phone( string $value ): ?string {
		$value = (string) preg_replace( '/[\s\-().]/', '', trim( $value ) );

		if ( 1 !== preg_match( '/^\+?\d{5,19}$/', $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Polish bank account number (NRB): 26 digits with a valid IBAN checksum.
	 * Spaces and the "PL" prefix are allowed.
	 *
	 * @param string $value Account number.
	 */
	public static function nrb( string $value ): ?string {
		$digits = strtoupper( (string) preg_replace( '/\s+/', '', trim( $value ) ) );
		$digits = str_starts_with( $digits, 'PL' ) ? substr( $digits, 2 ) : $digits;

		if ( 1 !== preg_match( '/^\d{26}$/', $digits ) ) {
			return null;
		}

		// IBAN check: move "PL" + check digits to the end, letters to numbers (P=25, L=21), mod 97 = 1.
		$number    = substr( $digits, 2 ) . '2521' . substr( $digits, 0, 2 );
		$remainder = 0;

		foreach ( str_split( $number ) as $digit ) {
			$remainder = ( $remainder * 10 + (int) $digit ) % 97;
		}

		return 1 === $remainder ? $digits : null;
	}

	/**
	 * Whether a text fits in a field of the given length (characters, not bytes).
	 *
	 * @param string $value Text.
	 * @param int    $max   Maximum length.
	 */
	public static function fits( string $value, int $max ): bool {
		return mb_strlen( $value ) <= $max;
	}
}
