<?php
/**
 * Encryption of stored secrets.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts secrets (Elektroniczny Nadawca passwords) before they are stored in the database.
 *
 * Uses libsodium secretbox. The key comes from the PPWC_ENCRYPTION_KEY constant when it
 * is defined in wp-config.php, otherwise it is derived from the WordPress salts.
 * Changing the key or the salts makes stored secrets unreadable, so they must be
 * entered again.
 */
final class Crypto {

	private const PREFIX = 'ppwc1:';

	/**
	 * Encrypts a plain text value.
	 *
	 * @param string $plain Value to encrypt.
	 * @return string Encrypted value, or an empty string for empty input.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );

		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext stored as text, not obfuscation.
	}

	/**
	 * Decrypts a value produced by encrypt().
	 *
	 * @param string $stored Stored value.
	 * @return string Plain text, or an empty string when the value cannot be decrypted.
	 */
	public static function decrypt( string $stored ): string {
		if ( ! self::is_encrypted( $stored ) ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary ciphertext stored as text, not obfuscation.

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Whether a value is already encrypted by this class.
	 *
	 * @param string $value Value to check.
	 */
	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::PREFIX );
	}

	/**
	 * Returns the 32-byte encryption key.
	 */
	private static function key(): string {
		$material = defined( 'PPWC_ENCRYPTION_KEY' ) && is_string( PPWC_ENCRYPTION_KEY ) && '' !== PPWC_ENCRYPTION_KEY
			? PPWC_ENCRYPTION_KEY
			: wp_salt( 'auth' ) . wp_salt( 'secure_auth' );

		return sodium_crypto_generichash( 'ppwc-encryption|' . $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
