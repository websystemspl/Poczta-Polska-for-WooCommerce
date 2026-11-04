<?php
/**
 * Tests of keeping shipments and buffers in step with Elektroniczny Nadawca.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Shipments;

use Mockery;
use PocztaPolska\WooCommerce\En\ClientInterface;
use PocztaPolska\WooCommerce\En\Dto\Buffer;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;
use PocztaPolska\WooCommerce\Shipments\ShipmentService;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;
use PocztaPolska\WooCommerce\Tests\Unit\WordPressStore;

/**
 * Buffer date changes, sub-parcels, sent envelopes and buffers gone from EN.
 */
final class ShipmentServiceSyncTest extends TestCase {

	use WordPressStore;

	private const BUFFER = 392359110;

	private const TODAY = '2026-11-04';

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
		$this->install_store( self::TODAY );

		$this->client     = Mockery::mock( ClientInterface::class );
		$this->repository = new ShipmentRepository();
	}

	protected function tearDown(): void {
		$this->uninstall_store();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Returns the service with a buffer of the plugin dated $date.
	 *
	 * @param string $date Buffer date.
	 */
	private function service_with_buffer( string $date ): ShipmentService {
		$service = new ShipmentService( $this->client, $this->repository, 'test' );
		$service->buffers()->remember( new Buffer( self::BUFFER, 458350, 260578, 860575, $date, 'WooCommerce ' . $date ) );

		return $service;
	}

	/**
	 * Adds a shipment row.
	 *
	 * @param string               $guid  GUID.
	 * @param array<string, mixed> $extra Extra columns and data.
	 */
	private function add_shipment( string $guid, array $extra = array() ): int {
		return $this->repository->insert(
			$extra + array(
				'order_id'         => 37,
				'environment'      => 'test',
				'guid'             => $guid,
				'method'           => 'ppwc_pocztex_point',
				'status'           => Shipment::STATUS_CREATED,
				'tracking_number'  => 'PX0000000001',
				'buffer_id'        => self::BUFFER,
				'card_id'          => 458350,
				'label_printed_at' => '2026-11-03 08:44:04',
				'data'             => array( 'draft' => array( 'customer_number' => 'PPWC-F95719533E292FFB81A4' ) ),
			)
		);
	}

	public function test_second_renumbering_finds_the_shipment_by_the_stored_shop_number(): void {
		// The row already has the GUID of an earlier renumbering, EN keeps the first shop number.
		$id = $this->add_shipment( 'A359132A6F024857951AE14388AA8195' );

		$this->client->shouldReceive( 'buffers' )->andReturn( array( new Buffer( self::BUFFER, 458350, 260578, 860575, '2026-11-03', '' ) ) );
		$this->client->shouldReceive( 'update_buffer' )->once()->withArgs( static fn( Buffer $buffer, bool $dates ): bool => self::TODAY === $buffer->date && $dates );
		$this->client->shouldReceive( 'buffer_shipments' )->with( self::BUFFER )->andReturn(
			array(
				'9DD8A0C6DB4C422AB0E78777B7B52DDB' => array(
					'tracking'        => 'PX6038635173',
					'customer_number' => 'PPWC-F95719533E292FFB81A4',
					'sub_guids'       => array(),
				),
			)
		);

		$renumbered = $this->service_with_buffer( '2026-11-03' )->move_buffer_to_today( self::BUFFER );
		$shipment   = $this->repository->find( $id );

		$this->assertSame( 1, $renumbered );
		$this->assertSame( '9DD8A0C6DB4C422AB0E78777B7B52DDB', $shipment?->guid );
		$this->assertSame( 'PX6038635173', $shipment?->tracking_number );
		$this->assertSame( '', $shipment?->label_printed, 'The old label is no longer valid.' );
		$this->assertTrue( $shipment?->is_editable() );
		$this->assertSame( self::TODAY, $this->options['ppwc_buffers']['test'][ self::BUFFER ]['date'] );
		$this->assertSame( 'buffer_date_changed', $this->wpdb->rows( 'ppwc_audit_log' )[0]['action'] );
	}

	public function test_date_changed_outside_the_plugin_is_read_from_en(): void {
		$this->add_shipment( 'B153F2BF1DF846729CD377030D4F4117' );

		// The plugin thinks the buffer is for today, EN has it for tomorrow.
		$this->client->shouldReceive( 'buffers' )->andReturn( array( new Buffer( self::BUFFER, 458350, 260578, 860575, '2026-11-05', '' ) ) );
		$this->client->shouldReceive( 'update_buffer' )->once();
		$this->client->shouldReceive( 'buffer_shipments' )->andReturn( array() );

		$this->service_with_buffer( self::TODAY )->move_buffer_to_today( self::BUFFER );

		$this->addToAssertionCount( 1 );
	}

	public function test_sub_parcels_get_new_guids_and_unknown_numbers(): void {
		$id = $this->add_shipment(
			'B64F194286A366465BA2007DADCD2516',
			array(
				'data' => array(
					'draft'        => array(
						'customer_number' => 'PPWC-B64F194286A366465BA2',
						'sub_guids'       => array( 'BAF78BB24D3660CA6975CF36D5FF6C1B' ),
					),
					'sub_tracking' => array( 'PX6038635117' ),
				),
			)
		);

		$this->client->shouldReceive( 'buffers' )->andReturn( array( new Buffer( self::BUFFER, 458350, 260578, 860575, self::TODAY, '' ) ) );
		$this->client->shouldReceive( 'update_buffer' )->never();
		$this->client->shouldReceive( 'buffer_shipments' )->andReturn(
			array(
				'B153F2BF1DF846729CD377030D4F4117' => array(
					'tracking'        => 'PX6038635186',
					'customer_number' => 'PPWC-B64F194286A366465BA2',
					'sub_guids'       => array( '87E432C64F0A4099AA78228149D31E80' ),
				),
			)
		);

		$this->service_with_buffer( self::TODAY )->move_buffer_to_today( self::BUFFER );
		$shipment = $this->repository->find( $id );

		$this->assertSame( array( '87E432C64F0A4099AA78228149D31E80' ), $shipment?->data['draft']['sub_guids'] );
		$this->assertTrue( $shipment?->has_unknown_sub_tracking() );
		$this->assertSame( array( 'PX6038635186' ), $shipment?->tracking_numbers(), 'The old sub-parcel number is not shown.' );
	}

	public function test_sent_envelope_fills_in_the_sub_parcel_numbers(): void {
		$id = $this->add_shipment(
			'B153F2BF1DF846729CD377030D4F4117',
			array(
				'tracking_number'  => 'PX6038635186',
				'label_printed_at' => null,
				'data'             => array(
					'draft'              => array(
						'customer_number' => 'PPWC-B64F194286A366465BA2',
						'sub_guids'       => array( '87E432C64F0A4099AA78228149D31E80' ),
					),
					'sub_tracking'       => array(),
					'sub_tracking_stale' => true,
				),
			)
		);

		$this->client->shouldReceive( 'buffers' )->andReturn( array( new Buffer( self::BUFFER, 458350, 260578, 860575, self::TODAY, '' ) ) );
		$this->client->shouldReceive( 'buffer_shipments' )->andReturn(
			array(
				'B153F2BF1DF846729CD377030D4F4117' => array(
					'tracking'        => 'PX6038635186',
					'customer_number' => 'PPWC-B64F194286A366465BA2',
					'sub_guids'       => array( '87E432C64F0A4099AA78228149D31E80' ),
				),
			)
		);
		$this->client->shouldReceive( 'send_buffer' )->with( self::BUFFER, 260578 )->andReturn(
			array(
				'envelope_id' => 7817740,
				'status'      => 'WYSLANY',
			)
		);
		$this->client->shouldReceive( 'envelope_content' )->with( 7817740 )->andReturn(
			array(
				'B153F2BF1DF846729CD377030D4F4117' => array(
					'tracking' => 'PX6038635186',
					'status'   => 'NIEPOTWIERDZONA',
				),
				'87E432C64F0A4099AA78228149D31E80' => array(
					'tracking' => 'PX6038635199',
					'status'   => 'NIEPOTWIERDZONA',
				),
			)
		);

		$sent     = $this->service_with_buffer( self::TODAY )->send_buffer( self::BUFFER );
		$shipment = $this->repository->find( $id );

		$this->assertSame( 7817740, $sent['envelope_id'] );
		$this->assertSame( Shipment::STATUS_SENT, $shipment?->status );
		$this->assertSame( array( 'PX6038635186', 'PX6038635199' ), $shipment?->tracking_numbers() );
		$this->assertFalse( $shipment?->has_unknown_sub_tracking() );
		$this->assertSame( 'WYSLANY', $this->options['ppwc_envelope_status']['test'][7817740]['status'] );
		$this->assertArrayNotHasKey( self::BUFFER, $this->options['ppwc_buffers']['test'], 'EN deletes the buffer after sending.' );
	}

	public function test_buffer_gone_from_en_is_forgotten_and_its_shipments_marked(): void {
		$id = $this->add_shipment( '2AD409CDA8834A579BEA83FAB9E6D172' );

		$this->client->shouldReceive( 'buffers' )->andReturn( array() );
		$this->client->shouldReceive( 'buffer_shipments' )->with( self::BUFFER )->andThrow( new EnException( 'Podany identyfikator zbioru nie istnieje', 96001 ) );

		$forgotten = $this->service_with_buffer( self::TODAY )->sync_buffers( true );
		$shipment  = $this->repository->find( $id );

		$this->assertSame( array( self::BUFFER ), $forgotten );
		$this->assertSame( Shipment::STATUS_FAILED, $shipment?->status );
		$this->assertStringContainsString( 'no longer exists', (string) $shipment?->last_error );
		$this->assertArrayNotHasKey( self::BUFFER, $this->options['ppwc_buffers']['test'] );
		$this->assertSame( 'buffer_missing', $this->wpdb->rows( 'ppwc_audit_log' )[0]['action'] );
	}

	public function test_buffer_missing_from_the_list_but_readable_is_kept(): void {
		$id = $this->add_shipment( '2AD409CDA8834A579BEA83FAB9E6D172' );

		$this->client->shouldReceive( 'buffers' )->andReturn( array() );
		$this->client->shouldReceive( 'buffer_shipments' )->andReturn( array() );

		$this->assertSame( array(), $this->service_with_buffer( self::TODAY )->sync_buffers( true ) );
		$this->assertSame( Shipment::STATUS_CREATED, $this->repository->find( $id )?->status );
	}

	public function test_failing_buffer_list_changes_nothing(): void {
		$id = $this->add_shipment( '2AD409CDA8834A579BEA83FAB9E6D172' );

		$this->client->shouldReceive( 'buffers' )->andThrow( new EnException( 'Brak połączenia' ) );
		$this->client->shouldReceive( 'buffer_shipments' )->never();

		$this->assertSame( array(), $this->service_with_buffer( self::TODAY )->sync_buffers( true ) );
		$this->assertSame( Shipment::STATUS_CREATED, $this->repository->find( $id )?->status );
		$this->assertArrayHasKey( self::BUFFER, $this->options['ppwc_buffers']['test'] );
	}
}
