<?php
/**
 * Daily refresh of the delivery points copy.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Points;

use PocztaPolska\WooCommerce\En\Client;
use PocztaPolska\WooCommerce\En\ClientFactory;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Lifecycle;
use PocztaPolska\WooCommerce\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Refreshes the copy of pickup points from getPlacowkiPocztowe once a day at about 05:00
 * (OPZ II.1.2, EN FAQ 13).
 *
 * The daily job queues one job per voivodeship, so a slow or failing voivodeship
 * does not block the others and no single job handles the whole country.
 */
final class PointsSync {

	public const HOOK_DAILY = 'ppwc_sync_points';

	public const HOOK_VOIVODESHIP = 'ppwc_sync_points_voivodeship';

	public const STATUS_OPTION = 'ppwc_points_sync';

	/**
	 * Local hour of the daily refresh.
	 */
	private const HOUR = 5;

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
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( self::HOOK_DAILY, array( $this, 'run_daily' ) );
		add_action( self::HOOK_VOIVODESHIP, array( $this, 'sync_voivodeship' ) );
		add_action( 'admin_init', array( $this, 'ensure_scheduled' ) );
	}

	/**
	 * Schedules the next daily refresh, and an immediate one when the copy is empty.
	 */
	public function ensure_scheduled(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( self::HOOK_DAILY, array(), Lifecycle::ACTION_GROUP ) ) {
			return;
		}

		if ( 0 === $this->repository->count() ) {
			$this->queue_all();
		}

		self::schedule_next();
	}

	/**
	 * Runs the daily refresh and schedules the next one.
	 */
	public function run_daily(): void {
		$this->queue_all();
		self::schedule_next();
	}

	/**
	 * Queues the refresh of every voivodeship (also used by the "Refresh now" button).
	 */
	public function queue_all(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		foreach ( Client::VOIVODESHIP_CODES as $code ) {
			if ( ! as_has_scheduled_action( self::HOOK_VOIVODESHIP, array( $code ), Lifecycle::ACTION_GROUP ) ) {
				as_enqueue_async_action( self::HOOK_VOIVODESHIP, array( $code ), Lifecycle::ACTION_GROUP );
			}
		}
	}

	/**
	 * Refreshes the points of one voivodeship.
	 *
	 * An empty or failed response keeps the previous copy: removing all points of a
	 * voivodeship because of a temporary EN problem would block checkout there.
	 *
	 * @param string $code GUS voivodeship code.
	 * @throws EnException When EN fails, so Action Scheduler marks the job as failed.
	 */
	public function sync_voivodeship( string $code ): void {
		try {
			$points = ClientFactory::create()->pickup_points( $code );
		} catch ( EnException $exception ) {
			self::store_status( $code, 0, $exception->getMessage() );
			Logger::error(
				'Pickup points refresh failed.',
				array(
					'voivodeship' => $code,
					'error'       => $exception->getMessage(),
				)
			);
			throw $exception;
		}

		if ( array() === $points ) {
			self::store_status( $code, 0, 'Elektroniczny Nadawca returned no points, the previous copy was kept.' );
			return;
		}

		$saved = $this->repository->replace_voivodeship( $code, $points );

		self::store_status( $code, $saved, '' );
		Logger::log(
			'info',
			'Pickup points refreshed.',
			array(
				'voivodeship' => $code,
				'points'      => $saved,
			)
		);
	}

	/**
	 * Returns the refresh status per voivodeship.
	 *
	 * @return array<string, array{time: int, points: int, error: string}>
	 */
	public static function status(): array {
		$stored = get_option( self::STATUS_OPTION, array() );
		$status = array();

		foreach ( is_array( $stored ) ? $stored : array() as $code => $entry ) {
			if ( is_array( $entry ) ) {
				$status[ (string) $code ] = array(
					'time'   => (int) ( $entry['time'] ?? 0 ),
					'points' => (int) ( $entry['points'] ?? 0 ),
					'error'  => (string) ( $entry['error'] ?? '' ),
				);
			}
		}

		return $status;
	}

	/**
	 * Returns the timestamp of the next local 05:00.
	 *
	 * @param \DateTimeImmutable|null $now Current time (tests).
	 */
	public static function next_run( ?\DateTimeImmutable $now = null ): int {
		$now  = $now ?? new \DateTimeImmutable( 'now', wp_timezone() );
		$next = $now->setTime( self::HOUR, 0 );

		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' )->setTime( self::HOUR, 0 );
		}

		return $next->getTimestamp();
	}

	/**
	 * Schedules the next daily refresh. Single actions instead of a recurring one keep
	 * the local hour across daylight saving time changes.
	 */
	private static function schedule_next(): void {
		if ( function_exists( 'as_schedule_single_action' ) && ! as_has_scheduled_action( self::HOOK_DAILY, array(), Lifecycle::ACTION_GROUP ) ) {
			as_schedule_single_action( self::next_run(), self::HOOK_DAILY, array(), Lifecycle::ACTION_GROUP );
		}
	}

	/**
	 * Stores the result of a voivodeship refresh.
	 *
	 * @param string $code   Voivodeship code.
	 * @param int    $points Number of saved points.
	 * @param string $error  Error message, empty on success.
	 */
	private static function store_status( string $code, int $points, string $error ): void {
		$status = self::status();
		$entry  = $status[ $code ] ?? array(
			'time'   => 0,
			'points' => 0,
			'error'  => '',
		);

		$entry['error'] = $error;

		// A failure keeps the time and count of the last successful refresh.
		if ( '' === $error ) {
			$entry['time']   = time();
			$entry['points'] = $points;
		}

		$status[ $code ] = $entry;

		update_option( self::STATUS_OPTION, $status, false );
	}
}
