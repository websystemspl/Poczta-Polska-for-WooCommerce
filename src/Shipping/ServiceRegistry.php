<?php
/**
 * Registry of Poczta Polska services.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping;

use PocztaPolska\WooCommerce\En\Dto\PickupPoint;

defined( 'ABSPATH' ) || exit;

/**
 * Services available in the plugin.
 *
 * Domestic services only. Services from the option scope (pallets, international)
 * can be added through the ppwc_services filter without changing the rest of the code.
 */
final class ServiceRegistry {

	public const POCZTEX_COURIER = 'ppwc_pocztex_courier';

	public const POCZTEX_POINT = 'ppwc_pocztex_point';

	public const POCZTEX_LOCKER = 'ppwc_pocztex_locker';

	public const ECOMMERCE = 'ppwc_ecommerce';

	public const POCZTEX_TODAY_CITY = 'ppwc_pocztex_today_city';

	public const POCZTEX_TODAY_COUNTRY = 'ppwc_pocztex_today_country';

	/**
	 * Maximum weight of a letter service (OPZ II.1.3.e).
	 */
	public const LETTER_MAX_WEIGHT = 2000;

	/**
	 * Services, built on first use.
	 *
	 * @var array<string, Service>|null
	 */
	private static ?array $services = null;

	/**
	 * Returns all services keyed by ID.
	 *
	 * @return array<string, Service>
	 */
	public static function all(): array {
		if ( null === self::$services ) {
			$services = array();

			foreach ( self::defaults() as $service ) {
				$services[ $service->id ] = $service;
			}

			/**
			 * Filters the Poczta Polska services.
			 *
			 * @param array<string, Service> $services Services keyed by shipping method ID.
			 */
			$filtered = apply_filters( 'ppwc_services', $services );

			self::$services = array_filter( $filtered, static fn( $service ): bool => $service instanceof Service );
		}

		return self::$services;
	}

	/**
	 * Returns a service by ID.
	 *
	 * @param string $id Shipping method ID.
	 */
	public static function get( string $id ): ?Service {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Clears the cached services (tests, filters added late).
	 */
	public static function reset(): void {
		self::$services = null;
	}

	/**
	 * Built-in services.
	 *
	 * Format limits checked on the INT environment (2026-11-02): parcel lockers accept
	 * S-L, pickup points S-XL, 2XL goes by courier only.
	 *
	 * @return list<Service>
	 */
	private static function defaults(): array {
		return array(
			new Service( self::POCZTEX_COURIER, Service::EN_TYPE_COURIER, '855', null, true, 50000, array( 'S', 'M', 'L', 'XL', '2XL' ), false ),
			new Service( self::POCZTEX_POINT, Service::EN_TYPE_COURIER, '855', PickupPoint::TYPE_POINT, true, 20000, array( 'S', 'M', 'L', 'XL' ), false ),
			new Service( self::POCZTEX_LOCKER, Service::EN_TYPE_COURIER, '855', PickupPoint::TYPE_LOCKER, true, 20000, array( 'S', 'M', 'L' ), false ),
			new Service( self::ECOMMERCE, 'przesylkaFirmowaPoleconaType', '845', null, false, self::LETTER_MAX_WEIGHT, array( 'GABARYT_A', 'GABARYT_B' ), true ),
			// Pocztex "Na dziś": same-day delivery, within the city (distance up to 40 km) or in the country (INT 2026-11-18).
			new Service( self::POCZTEX_TODAY_CITY, Service::EN_TYPE_TODAY, '855', null, true, 50000, array( 'S', 'M', 'L', 'XL', '2XL' ), false, Service::AREA_CITY ),
			new Service( self::POCZTEX_TODAY_COUNTRY, Service::EN_TYPE_TODAY, '855', null, true, 50000, array( 'S', 'M', 'L', 'XL', '2XL' ), false, Service::AREA_COUNTRY ),
		);
	}
}
