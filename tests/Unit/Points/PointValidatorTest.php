<?php
/**
 * Tests of the pickup point validation.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Points;

use Brain\Monkey\Functions;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Points\PointValidator;
use PocztaPolska\WooCommerce\Shipping\Service;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Pickup point validation.
 */
final class PointValidatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		ServiceRegistry::reset();
		Functions\when( 'wc_price' )->alias( static fn( $amount ) => number_format( (float) $amount, 2, ',', ' ' ) . ' zł' );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
	}

	private static function service( string $id ): Service {
		$service = ServiceRegistry::get( $id );
		self::assertNotNull( $service );

		return $service;
	}

	private static function point( string $type = 'PUNKT', float $cod_limit = 4000.0, bool $pickup = true, string $network = 'ZABKA' ): PickupPoint {
		return new PickupPoint( 536973, 'Sklep Żabka', 'Sklep Żabka', $network, $type, 'Teodora Toeplitza', '2', '', '01560', 'Warszawa', 'mazowieckie', '', 52.27, 20.98, $cod_limit, $pickup );
	}

	public function test_home_delivery_needs_no_point(): void {
		$this->assertNull( PointValidator::error( self::service( ServiceRegistry::POCZTEX_COURIER ), null, false, 100.0 ) );
		$this->assertNull( PointValidator::error( self::service( ServiceRegistry::ECOMMERCE ), null, false, null ) );
	}

	public function test_letters_never_accept_cod(): void {
		$this->assertStringContainsString( 'not available for the chosen shipping method', (string) PointValidator::error( self::service( ServiceRegistry::ECOMMERCE ), null, false, 20.0 ) );
		$this->assertNull( PointValidator::error( self::service( ServiceRegistry::POCZTEX_COURIER ), null, false, 20.0 ), 'The courier takes cash on delivery.' );
	}

	public function test_point_is_required(): void {
		$this->assertStringContainsString( 'choose a pickup point', (string) PointValidator::error( self::service( ServiceRegistry::POCZTEX_POINT ), null, false, null ) );
		$this->assertStringContainsString( 'choose a parcel locker', (string) PointValidator::error( self::service( ServiceRegistry::POCZTEX_LOCKER ), null, false, null ) );
	}

	public function test_point_that_disappeared(): void {
		$this->assertStringContainsString( 'not available any more', (string) PointValidator::error( self::service( ServiceRegistry::POCZTEX_POINT ), null, true, null ) );
	}

	public function test_type_must_match_the_method(): void {
		$this->assertNotNull( PointValidator::error( self::service( ServiceRegistry::POCZTEX_LOCKER ), self::point( 'PUNKT' ), true, null ) );
		$this->assertStringContainsString( 'is a parcel locker', (string) PointValidator::error( self::service( ServiceRegistry::POCZTEX_POINT ), self::point( 'AUTOMAT' ), true, null ) );
		$this->assertNull( PointValidator::error( self::service( ServiceRegistry::POCZTEX_POINT ), self::point( 'PUNKT' ), true, null ) );
	}

	public function test_lockers_of_other_networks_are_accepted(): void {
		// EN accepted Pocztex AUTOMAT shipments to Biedronka lockers on INT (2026-11-02).
		$this->assertNull( PointValidator::error( self::service( ServiceRegistry::POCZTEX_LOCKER ), self::point( 'AUTOMAT', 4000.0, true, 'AUTOMAT BIEDRONKA' ), true, 50.0 ) );
	}

	public function test_point_must_hand_out_parcels(): void {
		$this->assertStringContainsString( 'does not hand out parcels', (string) PointValidator::error( self::service( ServiceRegistry::POCZTEX_POINT ), self::point( 'PUNKT', 4000.0, false ), true, null ) );
	}

	public function test_cod_requires_a_point_with_cod(): void {
		$service = self::service( ServiceRegistry::POCZTEX_POINT );

		$this->assertStringContainsString( 'does not accept cash on delivery', (string) PointValidator::error( $service, self::point( 'PUNKT', 0.0 ), true, 50.0 ) );
		$this->assertNull( PointValidator::error( $service, self::point( 'PUNKT', 0.0 ), true, null ), 'Without COD any point will do.' );
	}

	public function test_cod_amount_must_fit_the_point_limit(): void {
		$service = self::service( ServiceRegistry::POCZTEX_POINT );

		$this->assertNull( PointValidator::error( $service, self::point( 'PUNKT', 4000.0 ), true, 4000.0 ) );
		$error = (string) PointValidator::error( $service, self::point( 'PUNKT', 4000.0 ), true, 5014.99 );
		$this->assertStringContainsString( '4 000,00 zł', $error );
	}
}
