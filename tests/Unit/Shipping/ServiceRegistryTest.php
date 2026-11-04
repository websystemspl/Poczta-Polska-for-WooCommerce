<?php
/**
 * Tests of the service registry.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Shipping;

use PocztaPolska\WooCommerce\Shipping\Service;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Service registry.
 */
final class ServiceRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		ServiceRegistry::reset();
	}

	protected function tearDown(): void {
		ServiceRegistry::reset();
		parent::tearDown();
	}

	public function test_built_in_services(): void {
		$this->assertSame(
			array( ServiceRegistry::POCZTEX_COURIER, ServiceRegistry::POCZTEX_POINT, ServiceRegistry::POCZTEX_LOCKER, ServiceRegistry::ECOMMERCE ),
			array_keys( ServiceRegistry::all() )
		);
	}

	public function test_ecommerce_is_a_letter_without_cod(): void {
		$service = ServiceRegistry::get( ServiceRegistry::ECOMMERCE );

		$this->assertNotNull( $service );
		$this->assertSame( 'przesylkaFirmowaPoleconaType', $service->en_type );
		$this->assertSame( '845', $service->product_symbol );
		$this->assertFalse( $service->supports_cod );
		$this->assertSame( 2000, $service->max_weight );
		$this->assertTrue( $service->requires_weight );
		$this->assertFalse( $service->needs_pickup_point() );
	}

	public function test_pocztex_point_services_need_a_point_and_limit_formats(): void {
		$point  = ServiceRegistry::get( ServiceRegistry::POCZTEX_POINT );
		$locker = ServiceRegistry::get( ServiceRegistry::POCZTEX_LOCKER );

		$this->assertNotNull( $point );
		$this->assertNotNull( $locker );
		$this->assertSame( 'PUNKT', $point->point_type );
		$this->assertSame( 'AUTOMAT', $locker->point_type );
		$this->assertTrue( $point->allows_format( 'XL' ) );
		$this->assertFalse( $locker->allows_format( 'XL' ) );
		$this->assertFalse( $point->allows_format( '2XL' ) );
		$this->assertTrue( ServiceRegistry::get( ServiceRegistry::POCZTEX_COURIER )?->allows_format( '2XL' ) );
	}

	public function test_services_can_be_added_by_filter(): void {
		\Brain\Monkey\Filters\expectApplied( 'ppwc_services' )->once()->andReturnUsing(
			static function ( array $services ): array {
				$services['ppwc_test'] = new Service( 'ppwc_test', 'paletaType', '000', null, false, 1000000, array(), false );
				$services['broken']    = 'not a service';

				return $services;
			}
		);

		$this->assertNotNull( ServiceRegistry::get( 'ppwc_test' ) );
		$this->assertNull( ServiceRegistry::get( 'broken' ) );
	}

	public function test_unknown_service(): void {
		$this->assertNull( ServiceRegistry::get( 'flat_rate' ) );
	}
}
