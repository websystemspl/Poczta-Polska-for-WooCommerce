<?php
/**
 * Tests of the personal data export and erasure.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit;

use PocztaPolska\WooCommerce\Points\OrderPoint;
use PocztaPolska\WooCommerce\Privacy;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;

/**
 * Pickup point and tracking numbers in the export; point and corrected phones removed on erasure.
 */
final class PrivacyTest extends TestCase {

	use WordPressStore;

	private const ORDER = 47;

	/**
	 * Shipments.
	 *
	 * @var ShipmentRepository
	 */
	private ShipmentRepository $repository;

	protected function setUp(): void {
		parent::setUp();
		$this->install_store();
		$this->repository = new ShipmentRepository();
	}

	protected function tearDown(): void {
		$this->uninstall_store();
		parent::tearDown();
	}

	private static function order_with_point(): \WC_Order {
		$order = new \WC_Order( self::ORDER );

		$order->meta[ OrderPoint::META_KEY ] = array(
			'pni'     => 213458,
			'name'    => 'Automat Pocztex',
			'type'    => 'AUTOMAT',
			'address' => 'Pługowa 39, 94-238 Łódź',
		);

		return $order;
	}

	/**
	 * Adds a shipment of the order.
	 *
	 * @param string               $tracking Tracking number.
	 * @param array<string, mixed> $data     Data column.
	 */
	private function add_shipment( string $tracking, array $data = array() ): int {
		return $this->repository->insert(
			array(
				'order_id'        => self::ORDER,
				'environment'     => 'test',
				'guid'            => strtoupper( md5( $tracking ) ),
				'method'          => 'ppwc_pocztex_locker',
				'status'          => Shipment::STATUS_CREATED,
				'tracking_number' => $tracking,
				'data'            => $data,
			)
		);
	}

	public function test_export_contains_the_point_and_all_tracking_numbers(): void {
		$this->add_shipment( 'PX6038626979', array( 'sub_tracking' => array( 'PX6038626982' ) ) );

		$exported = ( new Privacy() )->export(
			array(
				array(
					'name'  => 'Other',
					'value' => 'kept',
				),
			),
			self::order_with_point()
		);

		$this->assertCount( 3, $exported );
		$this->assertSame( 'Automat Pocztex, Pługowa 39, 94-238 Łódź (PNI 213458)', $exported[1]['value'] );
		$this->assertSame( 'PX6038626979, PX6038626982', $exported[2]['value'] );
	}

	public function test_export_of_an_order_without_poczta_polska_data_adds_nothing(): void {
		$this->assertSame( array(), ( new Privacy() )->export( array(), new \WC_Order( self::ORDER ) ) );
	}

	public function test_export_ignores_other_objects(): void {
		$this->assertSame( array(), ( new Privacy() )->export( 'not an array', null ) );
	}

	public function test_erase_removes_the_point_and_corrected_phones_but_keeps_tracking(): void {
		$id    = $this->add_shipment(
			'PX6038626979',
			array(
				'draft' => array(
					'customer_number' => 'PPWC-ABC',
					'contact'         => array( 'mobile' => '601302890' ),
					'recipient'       => array( 'name' => 'Dariusz Ławiński' ),
				),
			)
		);
		$order = self::order_with_point();

		( new Privacy() )->erase( $order );
		$shipment = $this->repository->find( $id );

		$this->assertArrayNotHasKey( OrderPoint::META_KEY, $order->meta );
		$this->assertSame( 1, $order->saved );
		$this->assertSame( array( 'customer_number' => 'PPWC-ABC' ), $shipment?->data['draft'] );
		$this->assertSame( 'PX6038626979', $shipment?->tracking_number );
	}
}
