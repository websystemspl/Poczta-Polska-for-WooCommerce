<?php
/**
 * Tests of the tracking number column in the orders list.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Admin;

use PocztaPolska\WooCommerce\Admin\OrderTrackingColumn;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;
use PocztaPolska\WooCommerce\Tests\Unit\WordPressStore;

/**
 * Column position, numbers of the active environment, one query per page.
 */
final class OrderTrackingColumnTest extends TestCase {

	use WordPressStore;

	protected function setUp(): void {
		parent::setUp();
		$this->install_store();
	}

	protected function tearDown(): void {
		$this->uninstall_store();
		parent::tearDown();
	}

	/**
	 * Adds a shipment row.
	 *
	 * @param int    $order_id    Order ID.
	 * @param string $status      Status.
	 * @param string $tracking    Tracking number.
	 * @param string $environment Environment.
	 */
	private function add_shipment( int $order_id, string $status, string $tracking, string $environment = 'test' ): void {
		( new ShipmentRepository() )->insert(
			array(
				'order_id'        => $order_id,
				'environment'     => $environment,
				'guid'            => strtoupper( md5( $tracking . $environment ) ),
				'method'          => 'ppwc_pocztex_courier',
				'status'          => $status,
				'tracking_number' => $tracking,
				'data'            => array(),
			)
		);
	}

	/**
	 * Renders the column of an order.
	 *
	 * @param OrderTrackingColumn $column   Column.
	 * @param int                 $order_id Order ID.
	 */
	private static function render( OrderTrackingColumn $column, int $order_id ): string {
		ob_start();
		$column->render( 'ppwc_tracking', $order_id );

		return (string) ob_get_clean();
	}

	public function test_column_goes_after_the_order_status(): void {
		$columns = ( new OrderTrackingColumn() )->add_column(
			array(
				'order_number' => 'Order',
				'order_status' => 'Status',
				'order_total'  => 'Total',
			)
		);

		$this->assertSame( array( 'order_number', 'order_status', 'ppwc_tracking', 'order_total' ), array_keys( $columns ) );
	}

	public function test_column_is_added_at_the_end_without_a_status_column(): void {
		$columns = ( new OrderTrackingColumn() )->add_column( array( 'order_number' => 'Order' ) );

		$this->assertSame( array( 'order_number', 'ppwc_tracking' ), array_keys( $columns ) );
	}

	public function test_page_is_loaded_with_one_query(): void {
		$this->add_shipment( 37, Shipment::STATUS_CREATED, 'PX6038626979' );
		$this->add_shipment( 38, Shipment::STATUS_SENT, 'PX6038626982' );

		$column = new OrderTrackingColumn();
		$column->collect_order_ids( (object) array( 'orders' => array( 37, 38, 39 ) ) );

		$first  = self::render( $column, 37 );
		$second = self::render( $column, 38 );
		$none   = self::render( $column, 39 );

		$this->assertStringContainsString( 'PX6038626979', $first );
		$this->assertStringContainsString( 'In buffer', $first );
		$this->assertStringContainsString( 'PX6038626982', $second );
		$this->assertStringContainsString( 'Sent to the post office', $second );
		$this->assertSame( '&ndash;', $none );
		$this->assertSame( 1, $this->wpdb->queries );
	}

	public function test_only_the_active_environment_is_shown(): void {
		$this->add_shipment( 37, Shipment::STATUS_CREATED, 'PX0000000001', 'production' );

		$this->assertSame( '&ndash;', self::render( new OrderTrackingColumn(), 37 ) );
	}

	public function test_rejected_shipment_without_a_valid_one_is_marked(): void {
		$this->add_shipment( 37, Shipment::STATUS_FAILED, '' );

		$this->assertStringContainsString( 'status-failed', self::render( new OrderTrackingColumn(), 37 ) );
	}

	public function test_other_columns_are_left_alone(): void {
		$this->add_shipment( 37, Shipment::STATUS_CREATED, 'PX6038626979' );

		ob_start();
		( new OrderTrackingColumn() )->render( 'order_total', 37 );

		$this->assertSame( '', ob_get_clean() );
		$this->assertSame( 0, $this->wpdb->queries );
	}
}
