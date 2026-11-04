<?php
/**
 * Pickup point chosen in the current checkout.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Points;

use PocztaPolska\WooCommerce\Shipping\Service;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the chosen PNI in the WooCommerce session, separately for points and lockers,
 * so switching the shipping method never carries a locker over to the point method.
 *
 * The classic checkout and the Checkout block share this storage.
 */
final class PointSelection {

	private const SESSION_KEY = 'ppwc_pickup_points';

	/**
	 * Returns the chosen PNI for a point type (PUNKT or AUTOMAT), 0 when none.
	 *
	 * @param string $point_type Point type.
	 */
	public static function get( string $point_type ): int {
		$session = self::session();

		if ( null === $session ) {
			return 0;
		}

		$stored = $session->get( self::SESSION_KEY, array() );

		return is_array( $stored ) ? (int) ( $stored[ $point_type ] ?? 0 ) : 0;
	}

	/**
	 * Stores the chosen PNI for a point type. 0 clears it.
	 *
	 * @param string $point_type Point type.
	 * @param int    $pni        Point ID.
	 */
	public static function set( string $point_type, int $pni ): void {
		$session = self::session();

		if ( null === $session || ! in_array( $point_type, self::point_types(), true ) ) {
			return;
		}

		$stored                = $session->get( self::SESSION_KEY, array() );
		$stored                = is_array( $stored ) ? $stored : array();
		$stored[ $point_type ] = max( 0, $pni );

		$session->set( self::SESSION_KEY, $stored );
	}

	/**
	 * Returns the payment method chosen in the checkout, empty when unknown.
	 */
	public static function chosen_payment_method(): string {
		$session = self::session();

		return null !== $session ? (string) $session->get( 'chosen_payment_method', '' ) : '';
	}

	/**
	 * Returns the shipping rate ID chosen for the first package, empty when none.
	 */
	public static function chosen_rate_id(): string {
		$session = self::session();
		$chosen  = null !== $session ? (array) $session->get( 'chosen_shipping_methods', array() ) : array();

		return (string) current( $chosen );
	}

	/**
	 * Clears all choices (after the order is placed).
	 */
	public static function clear(): void {
		$session = self::session();

		if ( null !== $session ) {
			$session->set( self::SESSION_KEY, null );
		}
	}

	/**
	 * Returns the service of the chosen shipping method of the first package, or null
	 * when it is not a Poczta Polska method.
	 *
	 * @param array<int|string, mixed>|null $chosen_methods Chosen method rate IDs, from the session when null.
	 */
	public static function chosen_service( ?array $chosen_methods = null ): ?Service {
		if ( null === $chosen_methods ) {
			$session        = self::session();
			$chosen_methods = null !== $session ? (array) $session->get( 'chosen_shipping_methods', array() ) : array();
		}

		$rate_id = (string) current( $chosen_methods );
		$parts   = explode( ':', $rate_id );

		return ServiceRegistry::get( $parts[0] );
	}

	/**
	 * Point types that can be chosen.
	 *
	 * @return list<string>
	 */
	private static function point_types(): array {
		$types = array();

		foreach ( ServiceRegistry::all() as $service ) {
			if ( null !== $service->point_type ) {
				$types[] = $service->point_type;
			}
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Returns the WooCommerce session, null outside the storefront (REST admin, cron, CLI).
	 */
	private static function session(): ?\WC_Session {
		return function_exists( 'WC' ) && WC()->session instanceof \WC_Session ? WC()->session : null;
	}
}
