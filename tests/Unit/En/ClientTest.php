<?php
/**
 * Tests of the EN client against responses recorded on the INT environment.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\En;

use PocztaPolska\WooCommerce\En\Client;
use PocztaPolska\WooCommerce\En\ClientFactory;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\En\Exception\AuthenticationException;
use PocztaPolska\WooCommerce\En\Exception\ConnectionException;
use PocztaPolska\WooCommerce\En\Exception\EnException;

/**
 * EN client.
 */
final class ClientTest extends EnHttpTestCase {

	public function test_hello_sends_request_to_configured_endpoint_with_basic_auth(): void {
		$this->respond( 'hello', 'hello.xml' );

		$this->assertSame( 'Hello ping', $this->client()->hello() );

		$this->assertCount( 1, $this->requests );
		$request = $this->requests[0];
		$this->assertSame( self::ENDPOINT, $request['url'], 'The WSDL points at production, the endpoint must come from the settings.' );
		$this->assertSame( 'Basic ' . base64_encode( 'user:secret' ), $request['args']['headers']['Authorization'] );
		$this->assertTrue( $request['args']['sslverify'] );
		$this->assertSame( 0, $request['args']['redirection'] );
		$this->assertStringContainsString( '<ns1:hello><in>ping</in></ns1:hello>', (string) $request['args']['body'] );
	}

	public function test_tls_hook_is_removed_after_the_request(): void {
		$this->respond( 'hello', 'hello.xml' );

		$this->client()->hello();

		$this->assertFalse( has_action( 'http_api_curl' ) );
	}

	public function test_password_expiry_date(): void {
		$this->respond( 'getPasswordExpiredDate', 'getPasswordExpiredDate.xml' );

		$this->assertSame( '2027-11-02', $this->client()->password_expiry_date()->format( 'Y-m-d' ) );
	}

	public function test_cards(): void {
		$this->respond( 'getKarty', 'getKarty.xml' );

		$cards = $this->client()->cards();

		$this->assertCount( 3, $cards );
		$this->assertSame( 458350, $cards[0]->id );
		$this->assertSame( 'Karta Testowa', $cards[0]->description );
		$this->assertTrue( $cards[0]->active );
		$this->assertTrue( $cards[0]->is_shipping_card() );
		$this->assertTrue( $cards[0]->has_product( '855' ) );
		$this->assertTrue( $cards[0]->has_product( '845' ) );
		$this->assertFalse( $cards[0]->has_product( '999' ) );
		$this->assertFalse( $cards[1]->active );
		$this->assertFalse( $cards[2]->is_shipping_card(), 'Type 2 cards hold OPNA addresses.' );
	}

	public function test_posting_offices(): void {
		$this->respond( 'getUrzedyNadania', 'getUrzedyNadania.xml' );

		$offices = $this->client()->posting_offices();

		$this->assertCount( 16, $offices );
		$this->assertSame( 433423, $offices[0]->id );
		$this->assertSame( 'Komorniki', $offices[0]->description );
		$this->assertSame( 'WER Komorniki', $offices[0]->print_name );
	}

	public function test_pickup_points_by_pni_maps_points(): void {
		$this->respond( 'getPlacowkaPocztowa', 'getPlacowkaPocztowa.xml' );

		$points = $this->client()->pickup_points_by_pni( array( 536973, 531604, 213458, 536973 ) );

		$this->assertCount( 3, $points );

		$zabka = $points[0];
		$this->assertSame( 536973, $zabka->pni );
		$this->assertSame( 'ZABKA', $zabka->network );
		$this->assertSame( PickupPoint::TYPE_POINT, $zabka->type );
		$this->assertSame( 'Teodora Toeplitza 2, 01-560 Warszawa', $zabka->address_line() );
		$this->assertSame( 4000.0, $zabka->cod_limit, 'maksymalnaKwotaPobrania is in PLN.' );
		$this->assertTrue( $zabka->is_pickup_point() );
		$this->assertEqualsWithDelta( 52.26882, $zabka->latitude, 0.000001 );
		$this->assertEqualsWithDelta( 20.984837, $zabka->longitude, 0.000001 );

		$this->assertFalse( $points[1]->supports_cod() );
		$this->assertSame( 'Sulgostów 61, 26-415 Sulgostów', $points[1]->address_line(), 'Villages without streets.' );

		$this->assertSame( PickupPoint::TYPE_LOCKER, $points[2]->type );
		$this->assertSame( PickupPoint::NETWORK_POCZTEX_LOCKER, $points[2]->network );

		$body = (string) $this->requests[0]['args']['body'];
		$this->assertSame( 3, substr_count( $body, '<pni>' ), 'Duplicate PNIs are sent once.' );
	}

	public function test_pickup_points_by_pni_without_pnis_does_not_call_en(): void {
		$this->assertSame( array(), $this->client()->pickup_points_by_pni( array() ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_pickup_points_by_voivodeship(): void {
		$this->respond( 'getPlacowkiPocztowe', 'getPlacowkiPocztowe.xml' );

		$points = $this->client()->pickup_points( '26' );

		$this->assertCount( 12, $points );
		$this->assertStringContainsString( '<idWojewodztwo>26</idWojewodztwo>', (string) $this->requests[0]['args']['body'] );
		$this->assertSame( 90, $this->requests[0]['args']['timeout'] );

		$networks = array_unique( array_map( static fn( PickupPoint $point ) => $point->network, $points ) );
		$this->assertContains( 'AUTOMAT POCZTEX', $networks );
		$this->assertContains( 'AUTOMAT BIEDRONKA', $networks );

		$not_for_pickup = array_filter( $points, static fn( PickupPoint $point ) => ! $point->is_pickup_point() );
		$this->assertNotEmpty( $not_for_pickup, 'The fixture has points that do not hand out parcels (FAQ 13).' );
	}

	public function test_pickup_points_rejects_invalid_voivodeship_code(): void {
		$this->expectException( EnException::class );

		$this->client()->pickup_points( '15' );
	}

	public function test_guids_are_uppercase_and_count_is_clamped(): void {
		$this->respond( 'getGuid', 'getGuid.xml' );

		$guids = $this->client()->guids( 500 );

		$this->assertCount( 3, $guids );
		$this->assertMatchesRegularExpression( '/^[A-F0-9]{32}$/', $guids[0] );
		$this->assertStringContainsString( '<ilosc>100</ilosc>', (string) $this->requests[0]['args']['body'] );
	}

	public function test_missing_credentials_fail_without_calling_en(): void {
		$this->expectException( AuthenticationException::class );

		try {
			$this->client( 'user', '' )->hello();
		} finally {
			$this->assertSame( array(), $this->requests );
		}
	}

	public function test_http_401_raises_authentication_exception(): void {
		$this->responses['hello'] = array( 401, '' );

		$this->expectException( AuthenticationException::class );

		$this->client()->hello();
	}

	public function test_transport_error_raises_connection_exception(): void {
		$this->responses['hello'] = new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );

		$this->expectException( ConnectionException::class );
		$this->expectExceptionMessage( 'cURL error 28' );

		$this->client()->hello();
	}

	public function test_unexpected_http_status_raises_connection_exception(): void {
		$this->responses['hello'] = array( 502, '<html>Bad Gateway</html>' );

		$this->expectException( ConnectionException::class );
		$this->expectExceptionMessage( 'HTTP 502' );

		$this->client()->hello();
	}

	public function test_soap_fault_raises_en_exception(): void {
		$this->respond( 'getPlacowkaPocztowa', 'fault.xml', 500 );

		$this->expectException( EnException::class );
		$this->expectExceptionMessage( 'Violation of encoding rules' );

		$this->client()->pickup_points_by_pni( array( 1 ) );
	}

	public function test_error_in_response_raises_en_exception_with_number(): void {
		$this->responses['getKarty'] = array(
			200,
			'<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://e-nadawca.poczta-polska.pl"><SOAP-ENV:Body><ns1:getKartyResponse><error errorNumber="53601" errorDesc="Brak uprawnień"/></ns1:getKartyResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>',
		);

		try {
			$this->client()->cards();
			$this->fail( 'Expected EnException.' );
		} catch ( EnException $exception ) {
			$this->assertSame( 53601, $exception->get_en_error_number() );
			$this->assertStringContainsString( 'Brak uprawnień', $exception->getMessage() );
		}
	}

	public function test_informational_error_number_zero_is_ignored(): void {
		$this->responses['getKarty'] = array(
			200,
			'<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://e-nadawca.poczta-polska.pl"><SOAP-ENV:Body><ns1:getKartyResponse><karta idKarta="1" opis="A" aktywna="true" typ="1"/><error errorNumber="0" errorDesc="Info"/></ns1:getKartyResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>',
		);

		$this->assertCount( 1, $this->client()->cards() );
	}
}
