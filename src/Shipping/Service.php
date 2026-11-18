<?php
/**
 * Poczta Polska service.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * A delivery service offered at checkout and its Elektroniczny Nadawca mapping.
 */
final class Service {

	public const EN_TYPE_COURIER = 'pocztex2021KurierType';

	public const EN_TYPE_TODAY = 'pocztex2021NaDzisType';

	public const AREA_CITY = 'MIASTO';

	public const AREA_COUNTRY = 'KRAJ';

	/**
	 * Longest distance (km) EN accepts for "Na dziś" within the city.
	 */
	public const TODAY_CITY_MAX_DISTANCE = 40;

	/**
	 * Constructor.
	 *
	 * @param string      $id              Shipping method ID in WooCommerce (stored in orders, never change it).
	 * @param string      $en_type         Shipment type in Elektroniczny Nadawca, e.g. pocztex2021KurierType.
	 * @param string      $product_symbol  Product symbol on the contract card (symbolIWD), e.g. 855.
	 * @param string|null $point_type      Pickup point type (PUNKT or AUTOMAT), null for home delivery.
	 * @param bool        $supports_cod    Whether cash on delivery is allowed.
	 * @param int         $max_weight      Maximum weight in grams.
	 * @param string[]    $formats         Allowed parcel formats (Pocztex) or sizes (letters).
	 * @param bool        $requires_weight Whether every product must have a weight (letters are priced by weight limits).
	 * @param string|null $area            Delivery area of Pocztex "Na dziś" (MIASTO or KRAJ), null for other services.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $en_type,
		public readonly string $product_symbol,
		public readonly ?string $point_type,
		public readonly bool $supports_cod,
		public readonly int $max_weight,
		public readonly array $formats,
		public readonly bool $requires_weight,
		public readonly ?string $area = null,
	) {
	}

	/**
	 * Whether this is a Pocztex 2.0 parcel service (courier type or "Na dziś"), as opposed to a letter.
	 */
	public function is_pocztex(): bool {
		return ! $this->requires_weight;
	}

	/**
	 * Whether the EN type is pocztex2021KurierType, which alone carries the pickup and posting
	 * points, the Pocztex envelope and the delivery hour.
	 */
	public function is_courier_type(): bool {
		return self::EN_TYPE_COURIER === $this->en_type;
	}

	/**
	 * Whether the customer picks a point on the map.
	 */
	public function needs_pickup_point(): bool {
		return null !== $this->point_type;
	}

	/**
	 * Whether a parcel format is allowed.
	 *
	 * @param string $format Format, e.g. M.
	 */
	public function allows_format( string $format ): bool {
		return in_array( $format, $this->formats, true );
	}
}
