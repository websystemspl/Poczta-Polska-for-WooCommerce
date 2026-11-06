<?php
/**
 * Resolves a PNI to point details.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Points;

use PocztaPolska\WooCommerce\En\ClientFactory;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\En\Exception\EnException;

defined( 'ABSPATH' ) || exit;

/**
 * Finds a point in the local copy, or asks Elektroniczny Nadawca when the copy
 * does not have it yet (new points appear in EN during the day).
 *
 * Point details shown in orders always come from here, never from the browser.
 */
final class PointResolver {

	/**
	 * How long a point fetched from EN is cached.
	 */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * How long "no such point" is cached, so checkout refreshes do not ask EN again and again.
	 */
	private const MISSING_TTL = HOUR_IN_SECONDS;

	/**
	 * Transient name prefix. uninstall.php removes these transients.
	 */
	public const TRANSIENT_PREFIX = 'ppwc_point_';

	/**
	 * Prefix of the per-visitor counters of EN lookups (uninstall.php removes these transients too).
	 */
	private const LOOKUPS_TRANSIENT = 'ppwc_point_lookups_';

	/**
	 * EN lookups allowed per window for one visitor (IP address): the public REST route and the
	 * checkout accept any PNI, so without a limit anyone could make the shop call EN with its
	 * credentials. The budget is per address, so one visitor cannot use it up for everyone.
	 */
	private const LOOKUPS_LIMIT = 30;

	private const LOOKUPS_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Highest PNI accepted (EN uses up to 8 digits).
	 */
	private const MAX_PNI = 99999999;

	/**
	 * Repository.
	 *
	 * @var PointsRepository
	 */
	private PointsRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param PointsRepository|null $repository Repository.
	 */
	public function __construct( ?PointsRepository $repository = null ) {
		$this->repository = $repository ?? new PointsRepository();
	}

	/**
	 * Returns the point, or null when it does not exist.
	 *
	 * @param int $pni Point ID.
	 */
	public function resolve( int $pni ): ?PickupPoint {
		if ( $pni <= 0 || $pni > self::MAX_PNI ) {
			return null;
		}

		$point = $this->repository->find( $pni );

		if ( null !== $point ) {
			return $point;
		}

		$transient = self::TRANSIENT_PREFIX . $pni;
		$cached    = get_transient( $transient );

		if ( is_array( $cached ) ) {
			return array() === $cached ? null : self::from_array( $cached );
		}

		if ( ! self::may_ask_en() ) {
			return null;
		}

		try {
			$points = ClientFactory::create()->pickup_points_by_pni( array( $pni ) );
		} catch ( EnException $exception ) {
			// Not cached: EN may be back on the next request.
			return null;
		}

		$point = $points[0] ?? null;

		// Plain arrays, not objects: a transient must stay readable without the plugin classes.
		set_transient( $transient, null !== $point ? get_object_vars( $point ) : array(), null !== $point ? self::CACHE_TTL : self::MISSING_TTL );

		return $point;
	}

	/**
	 * Whether a point missing from the local copy may be looked up in EN now. Shop managers are not
	 * limited; each visitor has a small budget per time window.
	 */
	private static function may_ask_en(): bool {
		if ( current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}

		$transient = self::LOOKUPS_TRANSIENT . substr( md5( self::visitor_address() ), 0, 16 );
		$count     = (int) get_transient( $transient );

		if ( $count >= self::LOOKUPS_LIMIT ) {
			return false;
		}

		set_transient( $transient, $count + 1, self::LOOKUPS_WINDOW );

		return true;
	}

	/**
	 * IP address of the visitor as the web server reports it (a proxy in front of the shop must set
	 * REMOTE_ADDR itself, as WordPress expects).
	 */
	private static function visitor_address(): string {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

		return '' !== $address ? $address : 'unknown';
	}

	/**
	 * Rebuilds a point from its cached properties.
	 *
	 * @param array<mixed> $data Properties of PickupPoint.
	 */
	private static function from_array( array $data ): ?PickupPoint {
		try {
			return new PickupPoint(
				(int) ( $data['pni'] ?? 0 ),
				(string) ( $data['name'] ?? '' ),
				(string) ( $data['print_name'] ?? '' ),
				(string) ( $data['network'] ?? '' ),
				(string) ( $data['type'] ?? '' ),
				(string) ( $data['street'] ?? '' ),
				(string) ( $data['house_number'] ?? '' ),
				(string) ( $data['flat_number'] ?? '' ),
				(string) ( $data['postcode'] ?? '' ),
				(string) ( $data['city'] ?? '' ),
				(string) ( $data['voivodeship'] ?? '' ),
				(string) ( $data['description'] ?? '' ),
				(float) ( $data['latitude'] ?? 0 ),
				(float) ( $data['longitude'] ?? 0 ),
				(float) ( $data['cod_limit'] ?? 0 ),
				! empty( $data['business_pickup'] )
			);
		} catch ( \TypeError $error ) {
			return null;
		}
	}
}
