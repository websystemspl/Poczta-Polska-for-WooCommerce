<?php
/**
 * Tests of the buffer and shipment operations of the EN client (responses recorded on INT).
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\En;

use PocztaPolska\WooCommerce\En\Dto\Buffer;
use PocztaPolska\WooCommerce\En\Exception\EnException;

/**
 * Buffers and shipments.
 */
final class ClientShipmentsTest extends EnHttpTestCase {

	private const POINT_GUID  = '2C4ADC5EC9B24EF6A34E52CDCB613A8C';
	private const COD_GUID    = 'C0CFBBA51E594468B532C3CC5C713390';
	private const LETTER_GUID = '931A2EFBD90A486888A3494F14BC881F';

	public function test_profiles(): void {
		$this->respond( 'getProfilList', 'getProfilList.xml' );

		$profiles = $this->client()->profiles();

		$this->assertCount( 1, $profiles );
		$this->assertSame( 860575, $profiles[0]->id );
		$this->assertSame( 'profil-testowy', $profiles[0]->short_name );
		$this->assertSame( 'KLIENT0TES', $profiles[0]->name );
		$this->assertSame( 'Testowa 1, 00-900 Warszawa', $profiles[0]->address );
	}

	public function test_create_buffer_sends_card_office_and_profile(): void {
		$this->respond( 'createEnvelopeBufor', 'createEnvelopeBufor.xml' );

		$id = $this->client()->create_buffer( new Buffer( 0, 458350, 260578, 860575, '2026-11-02', 'ppwc' ) );

		$this->assertSame( 392359067, $id );
		$body = (string) $this->requests[0]['args']['body'];
		$this->assertStringContainsString( 'dataNadania="2026-11-02"', $body );
		$this->assertStringContainsString( 'urzadNadania="260578"', $body );
		$this->assertStringContainsString( '<idKarta>458350</idKarta>', $body );
		$this->assertStringContainsString( 'idProfil="860575"', $body );
	}

	public function test_buffers(): void {
		$this->respond( 'getEnvelopeBuforList', 'getEnvelopeBuforList.xml' );

		$buffers = $this->client()->buffers();

		$this->assertCount( 2, $buffers );
		$this->assertSame( 392359067, $buffers[0]->id );
		$this->assertSame( 458350, $buffers[0]->card_id );
		$this->assertSame( 260578, $buffers[0]->office_id );
		$this->assertSame( 860575, $buffers[0]->profile_id );
		$this->assertSame( 'ppwc fixture A', $buffers[0]->description );
	}

	public function test_add_shipments_builds_typed_shipments_and_returns_results_by_guid(): void {
		$this->respond( 'addShipment', 'addShipment.xml' );

		$results = $this->client()->add_shipments(
			392359067,
			array(
				array(
					'type' => 'pocztex2021KurierType',
					'data' => array(
						'guid'         => self::POINT_GUID,
						'adres'        => array(
							'nazwa'       => 'Jan Testowy',
							'kodPocztowy' => '94238',
						),
						'masa'         => 400,
						'punktOdbioru' => array( 'id' => 536973 ),
						'subPrzesylka' => array(
							array(
								'type' => 'subPocztex2021KurierType',
								'data' => array(
									'guid' => 'AAAABBBBCCCCDDDDEEEEFFFF00001111',
									'masa' => 300,
								),
							),
						),
					),
				),
			)
		);

		$body = (string) $this->requests[0]['args']['body'];
		$this->assertStringContainsString( 'xsi:type="ns1:pocztex2021KurierType"', $body );
		$this->assertStringContainsString( '<adres nazwa="Jan Testowy" kodPocztowy="94238"/>', $body, 'adres fields are attributes.' );
		$this->assertStringContainsString( '<subPrzesylka guid="AAAABBBBCCCCDDDDEEEEFFFF00001111"', $body );
		$this->assertStringContainsString( '<idBufor>392359067</idBufor>', $body );
		$this->assertSame( 120, $this->requests[0]['args']['timeout'] );

		$this->assertTrue( $results[ self::POINT_GUID ]->is_success() );
		$this->assertSame( 'PX6038626496', $results[ self::POINT_GUID ]->tracking_number );
		$this->assertTrue( $results[ self::LETTER_GUID ]->is_success() );
		$this->assertFalse( $results[ self::COD_GUID ]->is_success(), 'COD at a point without COD.' );
		$this->assertStringContainsString( 'EN error 84049', $results[ self::COD_GUID ]->errors[0] );
	}

	public function test_buffer_shipments_lists_guids_with_tracking_numbers(): void {
		$this->respond( 'getEnvelopeBufor', 'getEnvelopeBufor.xml' );

		$shipments = $this->client()->buffer_shipments( 392359067 );

		$this->assertSame( 'PX6038626496', $shipments[ self::POINT_GUID ]['tracking'] );
		$this->assertSame( '00559007730038627325', $shipments[ self::LETTER_GUID ]['tracking'] );
	}

	public function test_buffer_shipments_keep_customer_number_and_sub_parcel_guids(): void {
		$this->respond( 'getEnvelopeBufor', 'getEnvelopeBufor-multiparcel.xml' );

		$shipments = $this->client()->buffer_shipments( 392359110 );
		$parcel    = $shipments['B153F2BF1DF846729CD377030D4F4117'];

		$this->assertSame( 'PX6038635186', $parcel['tracking'] );
		$this->assertSame( 'PPWC-B64F194286A366465BA2', $parcel['customer_number'], 'EN keeps the shop number after a new GUID.' );
		$this->assertSame( array( '87E432C64F0A4099AA78228149D31E80' ), $parcel['sub_guids'] );
		$this->assertSame( array(), $shipments['BF8806C1E2970338E0C0E340A5C810F7']['sub_guids'] );
	}

	public function test_add_shipments_to_a_missing_buffer_returns_a_general_error(): void {
		$this->respond( 'addShipment', 'addShipment-missing-buffer.xml' );

		$results = $this->client()->add_shipments( 392365415, array() );

		$this->assertSame( array( '' ), array_keys( $results ), 'EN does not name the shipment.' );
		$this->assertStringContainsString( 'EN error 20603', $results['']->errors[0] );
	}

	public function test_envelope_content_lists_sub_parcels_with_tracking_numbers(): void {
		$this->respond( 'getEnvelopeContentShort', 'getEnvelopeContentShort.xml' );

		$parcels = $this->client()->envelope_content( 7817740 );

		$this->assertCount( 5, $parcels );
		$this->assertSame( 'PX6038635199', $parcels['87E432C64F0A4099AA78228149D31E80']['tracking'], 'Sub-parcel number, missing in getEnvelopeBufor.' );
		$this->assertSame( 'NIEPOTWIERDZONA', $parcels['B153F2BF1DF846729CD377030D4F4117']['status'] );
	}

	public function test_delete_shipments_returns_errors_instead_of_throwing(): void {
		$this->respond( 'clearEnvelopeByGuids', 'clearEnvelopeByGuids.xml' );

		$errors = $this->client()->delete_shipments( 392359067, array( self::COD_GUID, str_repeat( 'F', 32 ) ) );

		$this->assertSame( array( self::COD_GUID, str_repeat( 'F', 32 ) ), array_keys( $errors ) );
		$this->assertStringContainsString( 'EN error 50701', $errors[ self::COD_GUID ] );
	}

	public function test_move_shipments_reads_failed_guids_from_errors(): void {
		$this->respond( 'moveShipments', 'moveShipments.xml' );

		$errors = $this->client()->move_shipments( 392359067, 392359068, array( self::LETTER_GUID, str_repeat( '0', 32 ) ) );

		$this->assertSame( array( str_repeat( '0', 32 ) ), array_keys( $errors ) );
		$this->assertStringContainsString( 'EN error 56203', $errors[ str_repeat( '0', 32 ) ] );
	}

	public function test_labels(): void {
		$this->respond( 'getPrintForParcel', 'getPrintForParcel.xml' );

		$files = $this->client()->labels( array( self::POINT_GUID ), false );

		$this->assertSame( array( '%PDF-1.4 fixture' ), $files );
		$this->assertStringContainsString( '<kind>ADDRESS_LABEL</kind><method>EACH_PARCEL_SEPARATELY</method>', (string) $this->requests[0]['args']['body'] );
	}

	public function test_labels_without_pdf_raise_exception(): void {
		$this->responses['getPrintForParcel'] = array( 200, '<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://e-nadawca.poczta-polska.pl"><SOAP-ENV:Body><ns1:getPrintForParcelResponse/></SOAP-ENV:Body></SOAP-ENV:Envelope>' );

		$this->expectException( EnException::class );

		$this->client()->labels( array( self::POINT_GUID ), true );
	}

	public function test_update_buffer_can_move_shipment_dates(): void {
		$this->respond( 'updateEnvelopeBufor', 'updateEnvelopeBufor.xml' );

		$this->client()->update_buffer( new Buffer( 392359067, 458350, 260578, 860575, '2026-11-03', 'ppwc' ), true );

		$body = (string) $this->requests[0]['args']['body'];
		$this->assertStringContainsString( 'idBufor="392359067"', $body );
		$this->assertStringContainsString( 'dataNadania="2026-11-03"', $body );
		$this->assertStringContainsString( 'aktualizujPlanowanaDateNadaniaPrzesylek="true"', $body );
	}

	public function test_send_buffer_and_status(): void {
		$this->respond( 'sendEnvelope', 'sendEnvelope.xml' );
		$this->respond( 'getEnvelopeStatus', 'getEnvelopeStatus.xml' );

		$sent = $this->client()->send_buffer( 392359067, 260578 );

		$this->assertSame(
			array(
				'envelope_id' => 7817336,
				'status'      => 'WYSLANY',
			),
			$sent
		);
		$this->assertSame( 'WYSLANY', $this->client()->envelope_status( 7817336 ) );
	}
}
