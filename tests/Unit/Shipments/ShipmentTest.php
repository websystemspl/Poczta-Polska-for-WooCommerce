<?php
/**
 * Tests of the shipment row.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Shipments;

use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Shipment row.
 */
final class ShipmentTest extends TestCase {

	/**
	 * Returns a shipment with the given GUID, label date and data.
	 *
	 * @param string               $guid  GUID.
	 * @param string               $label Label print date.
	 * @param array<string, mixed> $data  Data column.
	 */
	private static function shipment( string $guid, string $label = '', array $data = array() ): Shipment {
		return new Shipment( 1, 46, 'test', $guid, 'ppwc_pocztex_point', Shipment::STATUS_CREATED, 'PX6038635186', 392359110, 458350, 260578, 0, $label, $data, '', '2026-11-03 08:00:00' );
	}

	public function test_customer_number_comes_from_the_stored_draft_after_a_new_guid(): void {
		$shipment = self::shipment( 'B153F2BF1DF846729CD377030D4F4117', '', array( 'draft' => array( 'customer_number' => 'PPWC-B64F194286A366465BA2' ) ) );

		$this->assertSame( 'PPWC-B64F194286A366465BA2', $shipment->customer_number() );
	}

	public function test_customer_number_falls_back_to_the_guid(): void {
		$this->assertSame( 'PPWC-B64F194286A366465BA2', self::shipment( 'B64F194286A366465BA2007DADCD2516' )->customer_number() );
	}

	public function test_editable_until_the_label_is_printed(): void {
		$this->assertTrue( self::shipment( 'B64F194286A366465BA2007DADCD2516' )->is_editable() );
		$this->assertFalse( self::shipment( 'B64F194286A366465BA2007DADCD2516', '2026-11-03 08:44:04' )->is_editable() );
	}

	public function test_unknown_sub_parcel_numbers_after_a_date_change(): void {
		$shipment = self::shipment(
			'B153F2BF1DF846729CD377030D4F4117',
			'',
			array(
				'sub_tracking'       => array(),
				'sub_tracking_stale' => true,
			)
		);

		$this->assertTrue( $shipment->has_unknown_sub_tracking() );
		$this->assertSame( array( 'PX6038635186' ), $shipment->tracking_numbers() );
	}
}
