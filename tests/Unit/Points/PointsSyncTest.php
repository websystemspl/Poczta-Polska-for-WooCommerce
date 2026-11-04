<?php
/**
 * Tests of the points refresh schedule.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Points;

use PocztaPolska\WooCommerce\Points\PointsSync;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Points refresh schedule.
 */
final class PointsSyncTest extends TestCase {

	private static function at( string $time ): \DateTimeImmutable {
		return new \DateTimeImmutable( $time, new \DateTimeZone( 'Europe/Warsaw' ) );
	}

	public function test_before_five_runs_today(): void {
		$this->assertSame( self::at( '2026-11-02 05:00' )->getTimestamp(), PointsSync::next_run( self::at( '2026-11-02 03:10' ) ) );
	}

	public function test_after_five_runs_tomorrow(): void {
		$this->assertSame( self::at( '2026-11-03 05:00' )->getTimestamp(), PointsSync::next_run( self::at( '2026-11-02 09:30' ) ) );
	}

	public function test_exactly_five_runs_tomorrow(): void {
		$this->assertSame( self::at( '2026-11-03 05:00' )->getTimestamp(), PointsSync::next_run( self::at( '2026-11-02 05:00' ) ) );
	}

	public function test_local_hour_survives_daylight_saving_change(): void {
		// Clocks go back on 2026-10-25 at 03:00 in Poland.
		$next = PointsSync::next_run( self::at( '2026-10-24 12:00' ) );

		$this->assertSame( '2026-10-25 05:00', ( new \DateTimeImmutable( '@' . $next ) )->setTimezone( new \DateTimeZone( 'Europe/Warsaw' ) )->format( 'Y-m-d H:i' ) );
	}
}
