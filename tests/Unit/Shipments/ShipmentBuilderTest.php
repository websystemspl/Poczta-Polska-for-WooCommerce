<?php
/**
 * Tests of the shipment builder.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Shipments;

use PocztaPolska\WooCommerce\Shipments\DraftFactory;
use PocztaPolska\WooCommerce\Shipments\ShipmentBuilder;
use PocztaPolska\WooCommerce\Shipments\ShipmentDraft;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Shipment builder.
 */
final class ShipmentBuilderTest extends TestCase {

	private const GUID = '2C4ADC5EC9B24EF6A34E52CDCB613A8C';

	protected function setUp(): void {
		parent::setUp();
		ServiceRegistry::reset();
	}

	/**
	 * Returns a draft with sensible defaults.
	 *
	 * @param string               $service   Service ID.
	 * @param array<string, mixed> $overrides Constructor arguments to change.
	 */
	private static function draft( string $service, array $overrides = array() ): ShipmentDraft {
		$args = array_merge(
			array(
				'service'         => ServiceRegistry::get( $service ),
				'guid'            => self::GUID,
				'sub_guids'       => array(),
				'recipient'       => array(
					'name'         => 'Jan Testowy',
					'name2'        => '',
					'street'       => 'Pługowa',
					'house_number' => '39',
					'flat_number'  => '',
					'postcode'     => '94-238',
					'city'         => 'Łódź',
					'country'      => 'PL',
					'mobile'       => '601302890',
					'phone'        => '',
					'email'        => 'test@example.com',
				),
				'weights'         => array( 400 ),
				'format'          => 'S',
				'cod_amount'      => 0,
				'pickup_pni'      => 0,
				'description'     => 'Zamówienie 39',
				'customer_number' => 'PPWC-12',
				'options'         => array(
					'fragile'         => false,
					'content'         => 'Towar',
					'insurance'       => 0,
					'category'        => 'EKONOMICZNA',
					'cod_account'     => '61109010140000071219812874',
					'cod_method'      => 'RACHUNEK_BANKOWY',
					'cod_title'       => 'Zamówienie 39',
					'posting_channel' => 'COURIER',
					'posting_pni'     => 0,
				),
			),
			$overrides
		);

		return new ShipmentDraft( ...$args );
	}

	public function test_courier_shipment(): void {
		$shipment = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_COURIER ) );

		$this->assertSame( 'pocztex2021KurierType', $shipment['type'] );
		$this->assertSame( self::GUID, $shipment['data']['guid'] );
		$this->assertSame( '94238', $shipment['data']['adres']['kodPocztowy'], 'Postcode without a dash.' );
		$this->assertSame( 'Polska', $shipment['data']['adres']['kraj'] );
		$this->assertArrayNotHasKey( 'nazwa2', $shipment['data']['adres'], 'Empty fields are not sent.' );
		$this->assertSame( array( 'zawartoscInna' => 'Towar' ), $shipment['data']['zawartosc'], 'Pocztex requires the content (EN error 84024).' );
		$this->assertArrayNotHasKey( 'punktNadania', $shipment['data'], 'Courier pickup is the default, without punktNadania.' );
		$this->assertArrayNotHasKey( 'pobranie', $shipment['data'] );
		$this->assertSame( 'PPWC-12', $shipment['data']['numerPrzesylkiKlienta'] );
		$this->assertSame( array(), ShipmentBuilder::problems( self::draft( ServiceRegistry::POCZTEX_COURIER ) ) );
	}

	public function test_landline_is_sent_next_to_the_mobile(): void {
		$recipient          = self::draft( ServiceRegistry::POCZTEX_POINT )->recipient;
		$recipient['phone'] = '426543210';
		$address            = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_POINT, array( 'recipient' => $recipient ) ) )['data']['adres'];

		$this->assertSame( '601302890', $address['mobile'] );
		$this->assertSame( '426543210', $address['telefon'] );
	}

	public function test_only_changed_phone_numbers_are_stored_with_the_shipment(): void {
		$this->assertArrayNotHasKey( 'contact', self::draft( ServiceRegistry::POCZTEX_COURIER )->to_array() );

		$stored = self::draft( ServiceRegistry::POCZTEX_COURIER, array( 'contact' => array( 'phone' => '426543210' ) ) )->to_array();

		$this->assertSame( array( 'phone' => '426543210' ), $stored['contact'] );
		$this->assertArrayNotHasKey( 'recipient', $stored, 'The address stays in the order.' );
	}

	public function test_cash_on_delivery_in_grosze_with_account(): void {
		$shipment = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_COURIER, array( 'cod_amount' => 4298 ) ) );

		$this->assertSame(
			array(
				'sposobPobrania' => 'RACHUNEK_BANKOWY',
				'kwotaPobrania'  => 4298,
				'nrb'            => '61109010140000071219812874',
				'tytulem'        => 'Zamówienie 39',
			),
			$shipment['data']['pobranie']
		);
	}

	public function test_point_shipment_with_multiple_parcels(): void {
		$draft    = self::draft(
			ServiceRegistry::POCZTEX_POINT,
			array(
				'pickup_pni' => 536973,
				'sub_guids'  => array( 'AAAABBBBCCCCDDDDEEEEFFFF00001111' ),
				'weights'    => array( 400, 300 ),
			)
		);
		$shipment = ShipmentBuilder::build( $draft );

		$this->assertSame( array( 'id' => 536973 ), $shipment['data']['punktOdbioru'] );
		$this->assertSame( 'subPocztex2021KurierType', $shipment['data']['subPrzesylka'][0]['type'] );
		$this->assertSame( 300, $shipment['data']['subPrzesylka'][0]['data']['masa'] );
		$this->assertSame( $shipment['data']['opis'], $shipment['data']['subPrzesylka'][0]['data']['opis'], 'Every parcel label shows the description.' );
		$this->assertSame( 700, $draft->total_weight() );
		$this->assertSame( array(), ShipmentBuilder::problems( $draft ) );
	}

	public function test_point_needs_mobile_and_point(): void {
		$recipient           = self::draft( ServiceRegistry::POCZTEX_POINT )->recipient;
		$recipient['mobile'] = '';
		$problems            = ShipmentBuilder::problems( self::draft( ServiceRegistry::POCZTEX_POINT, array( 'recipient' => $recipient ) ) );

		$this->assertCount( 2, $problems );
		$this->assertStringContainsString( 'mobile', $problems[0] );
		$this->assertStringContainsString( 'pickup point', $problems[1] );
	}

	public function test_locker_drops_fragile_and_rejects_xl(): void {
		$options            = self::draft( ServiceRegistry::POCZTEX_LOCKER )->options;
		$options['fragile'] = true;
		$draft              = self::draft(
			ServiceRegistry::POCZTEX_LOCKER,
			array(
				'pickup_pni' => 213458,
				'options'    => $options,
				'format'     => 'XL',
			)
		);

		$this->assertArrayNotHasKey( 'ostroznie', ShipmentBuilder::build( $draft )['data'] );
		$this->assertStringContainsString( 'format XL', implode( ' ', ShipmentBuilder::problems( $draft ) ) );
	}

	public function test_posting_at_office_or_locker(): void {
		$options                    = self::draft( ServiceRegistry::POCZTEX_COURIER )->options;
		$options['posting_channel'] = 'APM';
		$options['posting_pni']     = 213458;
		$data                       = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_COURIER, array( 'options' => $options ) ) )['data'];

		$this->assertSame( array( 'id' => 213458 ), $data['punktNadania'] );
		$this->assertSame( 'APM', $data['shipmentChannel'] );

		// A parcel locker does not have to be named (checked on INT 17.11): only the channel is sent.
		$options['posting_pni'] = 0;
		$data                   = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_COURIER, array( 'options' => $options ) ) )['data'];

		$this->assertArrayNotHasKey( 'punktNadania', $data );
		$this->assertSame( 'APM', $data['shipmentChannel'] );
	}

	public function test_ecommerce_letter_uses_attributes_and_size(): void {
		$shipment = ShipmentBuilder::build( self::draft( ServiceRegistry::ECOMMERCE, array( 'format' => 'GABARYT_A' ) ) );

		$this->assertSame( 'przesylkaFirmowaPoleconaType', $shipment['type'] );
		$this->assertSame( 'GABARYT_A', $shipment['data']['gabaryt'] );
		$this->assertSame( 'EKONOMICZNA', $shipment['data']['kategoria'] );
		$this->assertSame( 400, $shipment['data']['masa'] );
		$this->assertArrayNotHasKey( 'zawartosc', $shipment['data'] );
		$this->assertArrayNotHasKey( 'format', $shipment['data'] );
	}

	public function test_ecommerce_rejects_cod_and_heavy_parcels(): void {
		$problems = ShipmentBuilder::problems(
			self::draft(
				ServiceRegistry::ECOMMERCE,
				array(
					'format'     => 'GABARYT_A',
					'cod_amount' => 1000,
					'weights'    => array( 2100 ),
				)
			)
		);

		$this->assertContains( 'Cash on delivery is not available for this service.', $problems );
		$this->assertContains( 'Every parcel must weigh from 1 to 2000 g.', $problems );
	}

	public function test_recipient_validation(): void {
		$recipient             = self::draft( ServiceRegistry::POCZTEX_COURIER )->recipient;
		$recipient['postcode'] = '10115';
		$recipient['country']  = 'DE';
		$recipient['name']     = str_repeat( 'a', 61 );
		$recipient['street']   = '';
		$problems              = ShipmentBuilder::problems( self::draft( ServiceRegistry::POCZTEX_COURIER, array( 'recipient' => $recipient ) ) );

		$this->assertContains( 'Domestic services deliver to Poland only.', $problems );
		$this->assertContains( 'The street is missing.', $problems );
		$this->assertContains( 'The recipient name is longer than 60 characters.', $problems );
	}

	public function test_cod_needs_valid_account(): void {
		$options                = self::draft( ServiceRegistry::POCZTEX_COURIER )->options;
		$options['cod_account'] = '';
		$problems               = ShipmentBuilder::problems(
			self::draft(
				ServiceRegistry::POCZTEX_COURIER,
				array(
					'cod_amount' => 1000,
					'options'    => $options,
				)
			)
		);

		$this->assertSame( array( 'Enter the bank account for cash on delivery in the plugin settings.' ), $problems );
	}

	public function test_long_description_and_title_are_cut(): void {
		$options              = self::draft( ServiceRegistry::POCZTEX_COURIER )->options;
		$options['cod_title'] = str_repeat( 'ż', 70 );
		$data                 = ShipmentBuilder::build(
			self::draft(
				ServiceRegistry::POCZTEX_COURIER,
				array(
					'description' => str_repeat( 'x', 300 ),
					'cod_amount'  => 100,
					'options'     => $options,
				)
			)
		)['data'];

		$this->assertSame( 250, mb_strlen( $data['opis'] ) );
		$this->assertSame( 55, mb_strlen( $data['pobranie']['tytulem'] ) );
	}

	public function test_fragile_and_declared_value_go_to_every_parcel(): void {
		$options            = self::draft( ServiceRegistry::POCZTEX_COURIER )->options;
		$options['fragile'] = true;
		$options['value']   = 25000;
		$data               = ShipmentBuilder::build(
			self::draft(
				ServiceRegistry::POCZTEX_COURIER,
				array(
					'options'   => $options,
					'sub_guids' => array( 'AAAABBBBCCCCDDDDEEEEFFFF00001111' ),
					'weights'   => array( 400, 300 ),
				)
			)
		)['data'];

		$this->assertTrue( $data['ostroznie'] );
		$this->assertTrue( $data['subPrzesylka'][0]['data']['ostroznie'] );
		$this->assertSame( 25000, $data['wartosc'] );
	}

	public function test_insurance_is_sent_in_grosze_with_the_extra_courier_services(): void {
		$options                 = self::draft( ServiceRegistry::POCZTEX_COURIER )->options;
		$options['insurance']    = 5000;
		$options['envelope']     = true;
		$options['oversize']     = true;
		$options['hour']         = 'DO_GODZ_12';
		$options['confirmation'] = 'SMS';
		$draft                   = self::draft(
			ServiceRegistry::POCZTEX_COURIER,
			array(
				'options' => $options,
				'format'  => 'S',
				'weights' => array( 800 ),
			)
		);
		$data                    = ShipmentBuilder::build( $draft )['data'];

		$this->assertSame(
			array(
				'rodzaj' => 'STANDARD',
				'kwota'  => 500000,
			),
			$data['ubezpieczenie']
		);
		$this->assertTrue( $data['kopertaPocztex'] );
		$this->assertTrue( $data['ponadgabaryt'] );
		$this->assertSame( 'DO_GODZ_12', $data['godzinaDoreczenia'] );
		$this->assertSame( 'SMS', $data['potwierdzenieDoreczenia']['sposob'] );
		$this->assertSame( $draft->recipient['mobile'], $data['potwierdzenieDoreczenia']['kontakt'] );
		$this->assertSame( array(), ShipmentBuilder::problems( $draft ) );
	}

	public function test_phased_out_delivery_hours_are_not_sent(): void {
		foreach ( array( 'DO_GODZ_8', 'DO_GODZ_9', 'PO_GODZ_17' ) as $hour ) {
			$options         = self::draft( ServiceRegistry::POCZTEX_COURIER )->options;
			$options['hour'] = $hour;
			$data            = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_COURIER, array( 'options' => $options ) ) )['data'];

			$this->assertArrayNotHasKey( 'godzinaDoreczenia', $data, $hour . ' is kept by Poczta Polska for old contracts only.' );
		}
	}

	public function test_envelope_default_fits_one_small_light_parcel_only(): void {
		$this->assertTrue( DraftFactory::fits_envelope( 'S', array( 1000 ) ) );
		$this->assertFalse( DraftFactory::fits_envelope( 'M', array( 400 ) ) );
		$this->assertFalse( DraftFactory::fits_envelope( 'S', array( 1001 ) ) );
		$this->assertFalse( DraftFactory::fits_envelope( 'S', array( 300, 300 ) ) );
	}

	public function test_envelope_needs_one_small_light_parcel(): void {
		$options             = self::draft( ServiceRegistry::POCZTEX_COURIER )->options;
		$options['envelope'] = true;
		$problems            = ShipmentBuilder::problems(
			self::draft(
				ServiceRegistry::POCZTEX_COURIER,
				array(
					'options' => $options,
					'format'  => 'M',
				)
			)
		);

		$this->assertStringContainsString( 'envelope', implode( ' ', $problems ) );
	}

	public function test_today_services_send_the_area_and_the_city_distance(): void {
		$options             = self::draft( ServiceRegistry::POCZTEX_TODAY_CITY )->options;
		$options['distance'] = 15;
		$options['envelope'] = true;
		$options['hour']     = 'DO_GODZ_12';
		$city                = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_TODAY_CITY, array( 'options' => $options ) ) );
		$country             = ShipmentBuilder::build( self::draft( ServiceRegistry::POCZTEX_TODAY_COUNTRY ) )['data'];

		$this->assertSame( 'pocztex2021NaDzisType', $city['type'] );
		$this->assertSame( 'MIASTO', $city['data']['obszar'] );
		$this->assertSame( 15, $city['data']['odleglosc'] );
		$this->assertArrayNotHasKey( 'kopertaPocztex', $city['data'], 'Courier-only elements are not sent for Na dziś.' );
		$this->assertArrayNotHasKey( 'godzinaDoreczenia', $city['data'] );
		$this->assertArrayNotHasKey( 'shipmentChannel', $city['data'] );
		$this->assertSame( 'KRAJ', $country['obszar'] );
		$this->assertArrayNotHasKey( 'odleglosc', $country, 'EN rejects a distance in the country (84207).' );
	}

	public function test_today_city_needs_a_distance_up_to_40_km(): void {
		$options             = self::draft( ServiceRegistry::POCZTEX_TODAY_CITY )->options;
		$options['distance'] = 45;

		$this->assertStringContainsString( '40 km', implode( ' ', ShipmentBuilder::problems( self::draft( ServiceRegistry::POCZTEX_TODAY_CITY, array( 'options' => $options ) ) ) ) );
		$this->assertSame( array(), ShipmentBuilder::problems( self::draft( ServiceRegistry::POCZTEX_TODAY_COUNTRY ) ) );
	}
}
