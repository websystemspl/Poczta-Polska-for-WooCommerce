<?php
/**
 * Availability rules of a service.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a service can deliver a package.
 */
final class Availability {

	/**
	 * Countries served by the domestic services.
	 */
	private const DOMESTIC_COUNTRY = 'PL';

	/**
	 * Whether the service can deliver the package.
	 *
	 * @param Service       $service    Service.
	 * @param string        $country    Destination country code.
	 * @param PackageWeight $weight     Package weight.
	 * @param int           $max_weight Weight limit of the method instance in grams, 0 for the service limit only.
	 */
	public static function is_available( Service $service, string $country, PackageWeight $weight, int $max_weight = 0 ): bool {
		// Domestic services never go abroad, whatever the shipping zone says (OPZ II.1.3.e).
		if ( self::DOMESTIC_COUNTRY !== strtoupper( $country ) ) {
			return false;
		}

		// Letters are limited by weight, so the weight must be known for every product.
		if ( $service->requires_weight && $weight->missing_weight ) {
			return false;
		}

		return $weight->grams <= self::weight_limit( $service, $max_weight );
	}

	/**
	 * Returns the effective weight limit in grams.
	 *
	 * @param Service $service    Service.
	 * @param int     $max_weight Weight limit of the method instance in grams, 0 for none.
	 */
	public static function weight_limit( Service $service, int $max_weight ): int {
		return $max_weight > 0 ? min( $max_weight, $service->max_weight ) : $service->max_weight;
	}
}
