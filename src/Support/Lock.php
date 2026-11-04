<?php
/**
 * Locks against parallel operations.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Support;

defined( 'ABSPATH' ) || exit;

/**
 * A lock stored as an option: add_option() fails when the row exists, which makes it atomic
 * in the database, also without a persistent object cache.
 */
final class Lock {

	private const PREFIX = 'ppwc_lock_';

	/**
	 * A lock older than this is considered abandoned (e.g. after a fatal error).
	 */
	private const TTL = 180;

	/**
	 * Takes a lock. Returns false when another request holds it.
	 *
	 * @param string $key Lock name, e.g. order_123 or buffer_456.
	 */
	public static function acquire( string $key ): bool {
		$option = self::PREFIX . sanitize_key( $key );

		if ( add_option( $option, time(), '', false ) ) {
			return true;
		}

		$since = (int) get_option( $option, 0 );

		if ( $since > 0 && time() - $since > self::TTL ) {
			delete_option( $option );

			return add_option( $option, time(), '', false );
		}

		return false;
	}

	/**
	 * Releases a lock.
	 *
	 * @param string $key Lock name.
	 */
	public static function release( string $key ): void {
		delete_option( self::PREFIX . sanitize_key( $key ) );
	}
}
