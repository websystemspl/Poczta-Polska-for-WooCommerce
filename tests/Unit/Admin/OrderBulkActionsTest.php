<?php
/**
 * Tests of the bulk actions in the orders list.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Admin;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PocztaPolska\WooCommerce\Admin\OrderBulkActions;
use PocztaPolska\WooCommerce\En\ClientInterface;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;
use PocztaPolska\WooCommerce\Tests\Unit\WordPressStore;

/**
 * Other actions untouched, orders the user may not edit skipped, results kept for the notice.
 */
final class OrderBulkActionsTest extends TestCase {

	use WordPressStore;

	private const REDIRECT = 'https://example.com/wp-admin/admin.php?page=wc-orders';

	/**
	 * EN client.
	 *
	 * @var ClientInterface&\Mockery\MockInterface
	 */
	private $client;

	/**
	 * Shipments.
	 *
	 * @var ShipmentRepository
	 */
	private ShipmentRepository $repository;

	protected function setUp(): void {
		parent::setUp();
		$this->install_store();

		$this->client     = Mockery::mock( ClientInterface::class );
		$this->repository = new ShipmentRepository();
		Filters\expectApplied( 'ppwc_en_client' )->zeroOrMoreTimes()->andReturn( $this->client );
		Functions\when( 'current_user_can' )->justReturn( true );
	}

	protected function tearDown(): void {
		$this->uninstall_store();
		Mockery::close();
		parent::tearDown();
	}

	private function add_shipment( int $order_id, string $status ): int {
		return $this->repository->insert(
			array(
				'order_id'        => $order_id,
				'environment'     => 'test',
				'guid'            => strtoupper( md5( $order_id . $status ) ),
				'method'          => 'ppwc_pocztex_courier',
				'status'          => $status,
				'tracking_number' => 'PX00000000' . $order_id,
				'buffer_id'       => 392359110,
				'data'            => array(),
			)
		);
	}

	/**
	 * Result stored for the notice.
	 *
	 * @return array<string, mixed>
	 */
	private function bulk_result(): array {
		return $this->transients['ppwc_bulk_result_1'];
	}

	public function test_actions_of_other_plugins_are_passed_through(): void {
		$this->assertSame( self::REDIRECT, ( new OrderBulkActions() )->handle( self::REDIRECT, 'mark_completed', array( 37 ) ) );
		$this->assertSame( array(), $this->transients );
	}

	public function test_three_actions_are_added(): void {
		$actions = ( new OrderBulkActions() )->add_actions( array( 'trash' => 'Trash' ) );

		$this->assertSame( array( 'trash', 'ppwc_create_shipments', 'ppwc_print_labels', 'ppwc_delete_shipments' ), array_keys( $actions ) );
	}

	public function test_labels_without_shipments_in_a_buffer_report_an_error(): void {
		$this->add_shipment( 37, Shipment::STATUS_SENT );

		( new OrderBulkActions() )->handle( self::REDIRECT, 'ppwc_print_labels', array( 37 ) );

		$this->assertSame( array( 'None of the selected orders has a shipment in a buffer.' ), $this->bulk_result()['errors'] );
	}

	public function test_delete_skips_sent_shipments_and_orders_the_user_may_not_edit(): void {
		$deletable = $this->add_shipment( 37, Shipment::STATUS_CREATED );
		$sent      = $this->add_shipment( 38, Shipment::STATUS_SENT );
		$foreign   = $this->add_shipment( 39, Shipment::STATUS_CREATED );

		Functions\when( 'current_user_can' )->alias( static fn( string $capability, int $id = 0 ): bool => 39 !== $id );
		$this->client->shouldReceive( 'delete_shipments' )->once()->andReturn( array() );

		( new OrderBulkActions() )->handle( self::REDIRECT, 'ppwc_delete_shipments', array( '37', '38', '39' ) );

		$this->assertNull( $this->repository->find( $deletable ) );
		$this->assertNotNull( $this->repository->find( $sent ) );
		$this->assertNotNull( $this->repository->find( $foreign ) );
		$this->assertSame( 1, $this->bulk_result()['done'] );
		$this->assertSame( array(), $this->bulk_result()['errors'] );
	}

	public function test_en_error_is_reported_per_order(): void {
		$this->add_shipment( 37, Shipment::STATUS_CREATED );
		$this->client->shouldReceive( 'delete_shipments' )->andReturn( array( '' => 'Błąd 12345: zbiór jest zablokowany "X"' ) );

		( new OrderBulkActions() )->handle( self::REDIRECT, 'ppwc_delete_shipments', array( 37 ) );

		$this->assertSame( array( 'Order 37: Błąd 12345: zbiór jest zablokowany "X"' ), $this->bulk_result()['errors'] );
	}
}
