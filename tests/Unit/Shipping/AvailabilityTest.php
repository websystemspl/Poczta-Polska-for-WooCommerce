<?php
/**
 * Tests of package weight and availability rules.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Shipping;

use PocztaPolska\WooCommerce\Shipping\Availability;
use PocztaPolska\WooCommerce\Shipping\PackageWeight;
use PocztaPolska\WooCommerce\Shipping\Service;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Package weight and availability.
 */
final class AvailabilityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		ServiceRegistry::reset();
	}

	private static function service( string $id ): Service {
		$service = ServiceRegistry::get( $id );
		self::assertNotNull( $service );

		return $service;
	}

	public function test_weight_of_hoodie_and_trousers_is_just_below_the_letter_limit(): void {
		// Test products 14 (1.2 kg) and 27 (0.7 kg).
		$weight = PackageWeight::from_items(
			array(
				array(
					'weight'   => '1.2',
					'quantity' => 1,
				),
				array(
					'weight'   => '0.7',
					'quantity' => 1,
				),
			),
			'kg'
		);

		$this->assertSame( 1900, $weight->grams );
		$this->assertFalse( $weight->missing_weight );
		$this->assertTrue( Availability::is_available( self::service( ServiceRegistry::ECOMMERCE ), 'PL', $weight ) );
	}

	public function test_two_hoodies_exceed_the_letter_limit_but_not_pocztex(): void {
		$weight = PackageWeight::from_items(
			array(
				array(
					'weight'   => '1.2',
					'quantity' => 2,
				),
			),
			'kg'
		);

		$this->assertSame( 2400, $weight->grams );
		$this->assertFalse( Availability::is_available( self::service( ServiceRegistry::ECOMMERCE ), 'PL', $weight ) );
		$this->assertTrue( Availability::is_available( self::service( ServiceRegistry::POCZTEX_LOCKER ), 'PL', $weight ) );
	}

	public function test_exactly_2000_grams_is_allowed_for_letters(): void {
		$weight = PackageWeight::from_items(
			array(
				array(
					'weight'   => '2000',
					'quantity' => 1,
				),
			),
			'g'
		);

		$this->assertTrue( Availability::is_available( self::service( ServiceRegistry::ECOMMERCE ), 'PL', $weight ) );
	}

	public function test_missing_weight_hides_letters_only(): void {
		$weight = PackageWeight::from_items(
			array(
				array(
					'weight'   => '',
					'quantity' => 1,
				),
				array(
					'weight'   => '0.4',
					'quantity' => 1,
				),
			),
			'kg'
		);

		$this->assertTrue( $weight->missing_weight );
		$this->assertSame( 400, $weight->grams );
		$this->assertFalse( Availability::is_available( self::service( ServiceRegistry::ECOMMERCE ), 'PL', $weight ) );
		$this->assertTrue( Availability::is_available( self::service( ServiceRegistry::POCZTEX_COURIER ), 'PL', $weight ) );
	}

	public function test_domestic_services_never_go_abroad(): void {
		$weight = new PackageWeight( 500, false );

		foreach ( ServiceRegistry::all() as $service ) {
			$this->assertFalse( Availability::is_available( $service, 'DE', $weight ), $service->id );
			$this->assertTrue( Availability::is_available( $service, 'pl', $weight ), $service->id );
		}
	}

	public function test_instance_limit_is_capped_by_the_service_limit(): void {
		$courier = self::service( ServiceRegistry::POCZTEX_COURIER );
		$letter  = self::service( ServiceRegistry::ECOMMERCE );

		$this->assertSame( 10000, Availability::weight_limit( $courier, 10000 ) );
		$this->assertSame( 50000, Availability::weight_limit( $courier, 0 ) );
		$this->assertSame( 2000, Availability::weight_limit( $letter, 5000 ), 'A method cannot raise the 2000 g letter limit.' );
		$this->assertFalse( Availability::is_available( $courier, 'PL', new PackageWeight( 10001, false ), 10000 ) );
	}

	public function test_units_and_decimal_comma(): void {
		$items = array(
			array(
				'weight'   => '1,5',
				'quantity' => 2,
			),
		);

		$this->assertSame( 3000, PackageWeight::from_items( $items, 'kg' )->grams );
		$this->assertSame( 1361, PackageWeight::from_items( $items, 'lbs' )->grams );
		$this->assertSame( 3, PackageWeight::from_items( $items, 'g' )->grams );
	}
}
