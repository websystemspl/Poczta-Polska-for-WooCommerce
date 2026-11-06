<?php
/**
 * Tests of resolving a PNI to point details.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Points;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PocztaPolska\WooCommerce\En\ClientInterface;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Points\PointResolver;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;
use PocztaPolska\WooCommerce\Tests\Unit\WordPressStore;

/**
 * Local copy first, EN only within the visitors' budget.
 */
final class PointResolverTest extends TestCase {

	use WordPressStore;

	private const PNI = 536973;

	/**
	 * EN client returned by the ppwc_en_client filter.
	 *
	 * @var ClientInterface&\Mockery\MockInterface
	 */
	private $client;

	protected function setUp(): void {
		parent::setUp();
		$this->install_store();

		$this->client = Mockery::mock( ClientInterface::class );
		Filters\expectApplied( 'ppwc_en_client' )->zeroOrMoreTimes()->andReturn( $this->client );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
	}

	/**
	 * Transient of the lookup budget of an address.
	 *
	 * @param string $address IP address.
	 */
	private static function budget( string $address ): string {
		return 'ppwc_point_lookups_' . substr( md5( $address ), 0, 16 );
	}

	protected function tearDown(): void {
		$this->uninstall_store();
		Mockery::close();
		parent::tearDown();
	}

	private static function point( int $pni = self::PNI ): PickupPoint {
		return new PickupPoint( $pni, 'Żabka', 'ŻABKA Z1234', 'ŻABKA', 'PUNKT', 'Pługowa', '39', '', '94238', 'Łódź', 'łódzkie', '', 51.75, 19.45, 4000.0, true );
	}

	public function test_pni_out_of_range_is_not_looked_up(): void {
		$this->client->shouldReceive( 'pickup_points_by_pni' )->never();

		$resolver = new PointResolver();

		$this->assertNull( $resolver->resolve( 0 ) );
		$this->assertNull( $resolver->resolve( -5 ) );
		$this->assertNull( $resolver->resolve( 100000000 ) );
	}

	public function test_point_from_the_local_copy_does_not_ask_en(): void {
		$this->wpdb->insert( 'wp_ppwc_points', get_object_vars( self::point() ) );
		$this->client->shouldReceive( 'pickup_points_by_pni' )->never();

		$this->assertSame( self::PNI, ( new PointResolver() )->resolve( self::PNI )?->pni );
	}

	public function test_point_from_en_is_cached_as_a_plain_array(): void {
		$this->client->shouldReceive( 'pickup_points_by_pni' )->once()->with( array( self::PNI ) )->andReturn( array( self::point() ) );

		$resolver = new PointResolver();
		$first    = $resolver->resolve( self::PNI );
		$second   = $resolver->resolve( self::PNI );

		$this->assertSame( 4000.0, $first?->cod_limit );
		$this->assertEquals( $first, $second );
		$this->assertIsArray( $this->transients[ 'ppwc_point_' . self::PNI ] );
	}

	public function test_missing_point_is_remembered(): void {
		$this->client->shouldReceive( 'pickup_points_by_pni' )->once()->andReturn( array() );

		$resolver = new PointResolver();

		$this->assertNull( $resolver->resolve( self::PNI ) );
		$this->assertNull( $resolver->resolve( self::PNI ) );
	}

	public function test_en_error_is_not_cached(): void {
		$this->client->shouldReceive( 'pickup_points_by_pni' )->twice()->andThrow( new EnException( 'Timeout' ) );

		$resolver = new PointResolver();
		$resolver->resolve( self::PNI );
		$resolver->resolve( self::PNI );

		$this->assertArrayNotHasKey( 'ppwc_point_' . self::PNI, $this->transients );
	}

	public function test_a_visitor_has_a_limited_number_of_en_lookups(): void {
		$this->client->shouldReceive( 'pickup_points_by_pni' )->times( 30 )->andReturn( array() );

		$resolver = new PointResolver();

		for ( $pni = 1; $pni <= 31; $pni++ ) {
			$resolver->resolve( $pni );
		}

		$this->assertSame( 30, $this->transients[ self::budget( '203.0.113.5' ) ] );
	}

	public function test_another_visitor_has_an_own_budget(): void {
		$this->transients[ self::budget( '203.0.113.5' ) ] = 30;
		$_SERVER['REMOTE_ADDR']                            = '198.51.100.7';
		$this->client->shouldReceive( 'pickup_points_by_pni' )->once()->andReturn( array( self::point() ) );

		$this->assertNotNull( ( new PointResolver() )->resolve( self::PNI ) );
		$this->assertSame( 1, $this->transients[ self::budget( '198.51.100.7' ) ] );
	}

	public function test_shop_managers_are_not_limited(): void {
		Functions\when( 'current_user_can' )->alias( static fn( string $capability ): bool => 'edit_shop_orders' === $capability );
		$this->transients[ self::budget( '203.0.113.5' ) ] = 30;
		$this->client->shouldReceive( 'pickup_points_by_pni' )->once()->andReturn( array( self::point() ) );

		$this->assertNotNull( ( new PointResolver() )->resolve( self::PNI ) );
	}
}
