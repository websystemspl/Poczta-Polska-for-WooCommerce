<?php
/**
 * Weight of a shipping package.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Weight of the products in a package, in grams.
 */
final class PackageWeight {

	/**
	 * Grams per store weight unit.
	 *
	 * @var array<string, float>
	 */
	private const GRAMS_PER_UNIT = array(
		'kg'  => 1000.0,
		'g'   => 1.0,
		'lbs' => 453.59237,
		'oz'  => 28.349523125,
	);

	/**
	 * Constructor.
	 *
	 * @param int  $grams          Total weight of the products that have a weight, rounded up.
	 * @param bool $missing_weight Whether at least one product has no weight.
	 */
	public function __construct(
		public readonly int $grams,
		public readonly bool $missing_weight,
	) {
	}

	/**
	 * Calculates the weight of package items.
	 *
	 * @param list<array{weight: string, quantity: int|float}> $items Product weight in the store unit (empty when not set) and quantity.
	 * @param string                                           $unit  Store weight unit: kg, g, lbs or oz.
	 */
	public static function from_items( array $items, string $unit ): self {
		$per_unit = self::GRAMS_PER_UNIT[ $unit ] ?? self::GRAMS_PER_UNIT['kg'];
		$grams    = 0.0;
		$missing  = false;

		foreach ( $items as $item ) {
			$weight = str_replace( ',', '.', trim( $item['weight'] ) );

			if ( '' === $weight || ! is_numeric( $weight ) || (float) $weight <= 0 ) {
				$missing = true;
				continue;
			}

			$grams += (float) $weight * $per_unit * (float) $item['quantity'];
		}

		// Round half a gram down first, so 1.9 kg stays 1900 g despite float noise.
		return new self( (int) ceil( round( $grams, 3 ) ), $missing );
	}
}
