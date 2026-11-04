<?php
/**
 * Elektroniczny Nadawca WebAPI client.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En;

use PocztaPolska\WooCommerce\En\Dto\Buffer;
use PocztaPolska\WooCommerce\En\Dto\Card;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\En\Dto\PostingOffice;
use PocztaPolska\WooCommerce\En\Dto\SenderProfile;
use PocztaPolska\WooCommerce\En\Dto\ShipmentResult;
use PocztaPolska\WooCommerce\En\Exception\AuthenticationException;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the Elektroniczny Nadawca WebAPI (SOAP) and maps responses to DTOs.
 */
final class Client implements ClientInterface {

	/**
	 * Voivodeship codes accepted by getPlacowkiPocztowe (GUS codes).
	 *
	 * @var list<string>
	 */
	public const VOIVODESHIP_CODES = array( '02', '04', '06', '08', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '32' );

	/**
	 * Timeout for point lists, the Masovian voivodeship alone is about 3.5 MB of XML.
	 */
	private const POINTS_TIMEOUT = 90;

	/**
	 * Timeout for calls with many shipments (up to 500 per call).
	 */
	private const BULK_TIMEOUT = 120;

	/**
	 * Namespace of the EN types.
	 */
	private const NS = 'http://e-nadawca.poczta-polska.pl';

	/**
	 * WebAPI endpoint.
	 *
	 * @var string
	 */
	private string $location;

	/**
	 * EN login.
	 *
	 * @var string
	 */
	private string $login;

	/**
	 * EN password.
	 *
	 * @var string
	 */
	private string $password;

	/**
	 * Path to the WSDL file.
	 *
	 * @var string
	 */
	private string $wsdl;

	/**
	 * SOAP transport, created on the first call.
	 *
	 * @var SoapTransport|null
	 */
	private ?SoapTransport $soap = null;

	/**
	 * Constructor.
	 *
	 * @param string $location WebAPI endpoint.
	 * @param string $login    EN login.
	 * @param string $password EN password.
	 * @param string $wsdl     Path to the local WSDL file.
	 */
	public function __construct( string $location, string $login, string $password, string $wsdl ) {
		$this->location = $location;
		$this->login    = $login;
		$this->password = $password;
		$this->wsdl     = $wsdl;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $text Text to echo.
	 */
	public function hello( string $text = 'ping' ): string {
		$response = $this->call( 'hello', array( 'in' => $text ) );

		return self::string( $response, 'out' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws EnException When the call fails or the date is invalid.
	 */
	public function password_expiry_date(): \DateTimeImmutable {
		$response = $this->call( 'getPasswordExpiredDate', array() );
		$date     = \DateTimeImmutable::createFromFormat( '!Y-m-d', self::string( $response, 'dataWygasniecia' ), wp_timezone() );

		if ( false === $date ) {
			throw new EnException( esc_html__( 'Elektroniczny Nadawca returned an invalid password expiry date.', 'poczta-polska-for-woocommerce' ) );
		}

		return $date;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cards(): array {
		$response = $this->call( 'getKarty', array() );
		$cards    = array();

		foreach ( self::objects( $response, 'karta' ) as $card ) {
			$products = array();
			$list     = self::object( $card, 'produktyInKarta' );

			foreach ( null !== $list ? self::objects( $list, 'produktInKarta' ) : array() as $product ) {
				$products[] = array(
					'symbol' => self::string( $product, 'symbolIWD' ),
					'name'   => self::string( $product, 'nazwaWyswietlana' ),
				);
			}

			$cards[] = new Card(
				self::int( $card, 'idKarta' ),
				self::string( $card, 'opis' ),
				self::bool( $card, 'aktywna' ),
				self::int( $card, 'typ' ),
				$products
			);
		}

		return $cards;
	}

	/**
	 * {@inheritDoc}
	 */
	public function posting_offices(): array {
		$response = $this->call( 'getUrzedyNadania', array() );
		$offices  = array();

		foreach ( self::objects( $response, 'urzedyNadania' ) as $office ) {
			$offices[] = new PostingOffice(
				self::int( $office, 'urzadNadania' ),
				self::string( $office, 'opis' ),
				self::string( $office, 'nazwaWydruk' )
			);
		}

		return $offices;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $voivodeship_code GUS code: 02, 04 ... 32.
	 * @throws EnException When the code is invalid or the call fails.
	 */
	public function pickup_points( string $voivodeship_code ): array {
		if ( ! in_array( $voivodeship_code, self::VOIVODESHIP_CODES, true ) ) {
			throw new EnException(
				sprintf(
					/* translators: %s: voivodeship code. */
					esc_html__( 'Invalid voivodeship code: %s.', 'poczta-polska-for-woocommerce' ),
					esc_html( $voivodeship_code )
				)
			);
		}

		$response = $this->call( 'getPlacowkiPocztowe', array( 'idWojewodztwo' => $voivodeship_code ), self::POINTS_TIMEOUT );

		return array_map( array( self::class, 'map_point' ), self::objects( $response, 'placowka' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int[] $pnis Point IDs.
	 */
	public function pickup_points_by_pni( array $pnis ): array {
		$pnis = array_values( array_unique( array_filter( array_map( 'intval', $pnis ) ) ) );

		if ( array() === $pnis ) {
			return array();
		}

		$response = $this->call( 'getPlacowkaPocztowa', array( 'pni' => $pnis ) );

		return array_map( array( self::class, 'map_point' ), self::objects( $response, 'placowka' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $count Number of GUIDs, 1-100.
	 */
	public function guids( int $count ): array {
		$response = $this->call( 'getGuid', array( 'ilosc' => min( 100, max( 1, $count ) ) ) );
		$guids    = $response->guid ?? array();

		return array_values( array_map( static fn( $guid ): string => strtoupper( (string) $guid ), is_array( $guids ) ? $guids : array( $guids ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function profiles(): array {
		$response = $this->call( 'getProfilList', array() );
		$profiles = array();

		foreach ( self::objects( $response, 'profil' ) as $profile ) {
			$profiles[] = new SenderProfile(
				self::int( $profile, 'idProfil' ),
				self::string( $profile, 'nazwaSkrocona' ),
				trim( self::string( $profile, 'nazwa' ) . ' ' . self::string( $profile, 'nazwa2' ) ),
				self::address_line( $profile )
			);
		}

		return $profiles;
	}

	/**
	 * {@inheritDoc}
	 */
	public function buffers(): array {
		$response = $this->call( 'getEnvelopeBuforList', array() );
		$buffers  = array();

		foreach ( self::objects( $response, 'bufor' ) as $buffer ) {
			$profile   = self::object( $buffer, 'profil' );
			$buffers[] = new Buffer(
				self::int( $buffer, 'idBufor' ),
				self::int( $buffer, 'idKarta' ),
				self::int( $buffer, 'urzadNadania' ),
				null !== $profile ? self::int( $profile, 'idProfil' ) : 0,
				self::string( $buffer, 'dataNadania' ),
				self::string( $buffer, 'opis' ),
				self::bool( $buffer, 'active' )
			);
		}

		return $buffers;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Buffer $buffer Buffer data.
	 * @throws EnException When EN does not return the new buffer.
	 */
	public function create_buffer( Buffer $buffer ): int {
		$response = $this->call( 'createEnvelopeBufor', array( 'bufor' => array( self::buffer_params( $buffer ) ) ) );
		$created  = self::objects( $response, 'createdBufor' );
		$id       = isset( $created[0] ) ? self::int( $created[0], 'idBufor' ) : 0;

		if ( $id <= 0 ) {
			throw new EnException( esc_html__( 'Elektroniczny Nadawca did not create the buffer.', 'poczta-polska-for-woocommerce' ) );
		}

		return $id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Buffer $buffer                Buffer data.
	 * @param bool   $update_shipment_dates Whether to move the shipment dates too.
	 */
	public function update_buffer( Buffer $buffer, bool $update_shipment_dates ): void {
		$params = self::buffer_params( $buffer ) + array(
			'idBufor'                                 => $buffer->id,
			'aktualizujPlanowanaDateNadaniaPrzesylek' => $update_shipment_dates,
		);

		$this->call( 'updateEnvelopeBufor', array( 'bufor' => array( $params ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $buffer_id Buffer ID.
	 */
	public function delete_buffer( int $buffer_id ): void {
		$this->call( 'clearEnvelope', array( 'idBufor' => $buffer_id ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $buffer_id Buffer ID.
	 */
	public function buffer_shipments( int $buffer_id ): array {
		$response  = $this->call( 'getEnvelopeBufor', array( 'idBufor' => $buffer_id ), self::BULK_TIMEOUT );
		$shipments = array();

		foreach ( self::objects( $response, 'przesylka' ) as $shipment ) {
			$shipments[ strtoupper( self::string( $shipment, 'guid' ) ) ] = array(
				'tracking'        => self::string( $shipment, 'numerNadania' ),
				'customer_number' => self::string( $shipment, 'numerPrzesylkiKlienta' ),
				'sub_guids'       => array_values(
					array_map(
						static fn( object $sub ): string => strtoupper( self::string( $sub, 'guid' ) ),
						self::objects( $shipment, 'subPrzesylka' )
					)
				),
			);
		}

		return $shipments;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int                                                   $buffer_id Buffer ID.
	 * @param list<array{type: string, data: array<string, mixed>}> $shipments Shipments.
	 */
	public function add_shipments( int $buffer_id, array $shipments ): array {
		$response = $this->call(
			'addShipment',
			array(
				'przesylki' => array_map( array( self::class, 'soap_shipment' ), $shipments ),
				'idBufor'   => $buffer_id,
			),
			self::BULK_TIMEOUT
		);
		$results  = array();

		foreach ( self::objects( $response, 'retval' ) as $item ) {
			$guid             = strtoupper( self::string( $item, 'guid' ) );
			$results[ $guid ] = new ShipmentResult( $guid, self::string( $item, 'numerNadania' ), self::error_messages( $item ) );
		}

		return $results;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int      $buffer_id Buffer ID.
	 * @param string[] $guids     GUIDs.
	 */
	public function delete_shipments( int $buffer_id, array $guids ): array {
		$response = $this->call(
			'clearEnvelopeByGuids',
			array(
				'guid'    => array_values( $guids ),
				'idBufor' => $buffer_id,
			),
			self::BULK_TIMEOUT,
			false
		);

		return self::errors_by_guid( $response );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int      $from  Source buffer ID.
	 * @param int      $to    Target buffer ID.
	 * @param string[] $guids GUIDs.
	 */
	public function move_shipments( int $from, int $to, array $guids ): array {
		$response = $this->call(
			'moveShipments',
			array(
				'idBuforFrom' => $from,
				'idBuforTo'   => $to,
				'guid'        => array_values( $guids ),
			),
			self::BULK_TIMEOUT,
			false
		);

		// notMovedGuid is not reliable (EN returns the text "Array"), the errors name the GUIDs.
		return self::errors_by_guid( $response );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $guids    GUIDs.
	 * @param bool     $one_file One PDF for all shipments.
	 * @throws EnException When EN returns no PDF.
	 */
	public function labels( array $guids, bool $one_file ): array {
		$response = $this->call(
			'getPrintForParcel',
			array(
				'guid' => array_values( $guids ),
				'type' => array(
					'kind'   => 'ADDRESS_LABEL',
					'method' => $one_file ? 'ALL_PARCELS_IN_ONE_FILE' : 'EACH_PARCEL_SEPARATELY',
				),
			),
			self::BULK_TIMEOUT
		);
		$files    = array();

		foreach ( self::objects( $response, 'printResult' ) as $result ) {
			$pdf = $result->print ?? '';

			if ( is_string( $pdf ) && str_starts_with( $pdf, '%PDF' ) ) {
				$files[] = $pdf;
			}
		}

		if ( array() === $files ) {
			throw new EnException( esc_html__( 'Elektroniczny Nadawca did not return the labels.', 'poczta-polska-for-woocommerce' ) );
		}

		return $files;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $buffer_id Buffer ID.
	 * @param int $office_id Posting office ID.
	 * @throws EnException When EN does not confirm the envelope.
	 */
	public function send_buffer( int $buffer_id, int $office_id ): array {
		$response = $this->call(
			'sendEnvelope',
			array(
				'urzadNadania' => $office_id,
				'idBufor'      => $buffer_id,
			),
			self::BULK_TIMEOUT
		);
		$envelope = self::int( $response, 'idEnvelope' );

		if ( $envelope <= 0 ) {
			throw new EnException( esc_html__( 'Elektroniczny Nadawca did not confirm sending the buffer.', 'poczta-polska-for-woocommerce' ) );
		}

		return array(
			'envelope_id' => $envelope,
			'status'      => self::string( $response, 'envelopeStatus' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $envelope_id Envelope ID.
	 */
	public function envelope_status( int $envelope_id ): string {
		return self::string( $this->call( 'getEnvelopeStatus', array( 'idEnvelope' => $envelope_id ) ), 'envelopeStatus' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $envelope_id Envelope ID.
	 */
	public function envelope_content( int $envelope_id ): array {
		$response = $this->call( 'getEnvelopeContentShort', array( 'idEnvelope' => $envelope_id ), self::BULK_TIMEOUT );
		$parcels  = array();

		foreach ( self::objects( $response, 'przesylka' ) as $parcel ) {
			$parcels[ strtoupper( self::string( $parcel, 'guid' ) ) ] = array(
				'tracking' => self::string( $parcel, 'numerNadania' ),
				'status'   => self::string( $parcel, 'status' ),
			);
		}

		return $parcels;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array{address: array<string, string>, date: string, hours: string, count: int, weight: int, email: string} $order Courier order.
	 */
	public function order_courier( array $order ): void {
		$this->call(
			'zamowKuriera',
			array_filter(
				array(
					'miejsceOdbioru'               => $order['address'],
					'nadawca'                      => $order['address'],
					'oczekiwanaDataOdbioru'        => $order['date'],
					'oczekiwanaGodzinaOdbioru'     => $order['hours'],
					'szacowanaIloscPrzeslek'       => (string) $order['count'],
					'szacowanaLacznaMasaPrzesylek' => (string) $order['weight'],
					'potwierdzenieZamowieniaEmail' => $order['email'],
				),
				static fn( $value ): bool => '' !== $value && array() !== $value
			)
		);
	}

	/**
	 * Calls a WebAPI operation.
	 *
	 * @param string               $operation Operation name.
	 * @param array<string, mixed> $params    Parameters.
	 * @param int                  $timeout   Timeout in seconds.
	 * @param bool                 $throw_on_error Whether an error in the response throws. Operations with
	 *                                             per-shipment errors read them from the response instead.
	 * @throws AuthenticationException When the credentials are missing or rejected.
	 * @throws EnException             When the call fails or EN returns an error.
	 */
	private function call( string $operation, array $params, int $timeout = SoapTransport::DEFAULT_TIMEOUT, bool $throw_on_error = true ): object {
		if ( '' === $this->login || '' === $this->password ) {
			throw new AuthenticationException( esc_html__( 'Enter the Elektroniczny Nadawca login and password in the plugin settings.', 'poczta-polska-for-woocommerce' ) );
		}

		$started = microtime( true );

		try {
			$soap = $this->soap();
			$soap->set_timeout( $timeout );
			$response = $soap->__soapCall( $operation, array( $params ) );
		} catch ( EnException $exception ) {
			Logger::error( 'EN call failed.', self::log_context( $operation, $started, $exception ) );
			throw $exception;
		} catch ( \SoapFault $fault ) {
			Logger::error( 'EN call failed.', self::log_context( $operation, $started, $fault ) );
			throw new EnException(
				sprintf(
					/* translators: %s: error message from Elektroniczny Nadawca. */
					esc_html__( 'Elektroniczny Nadawca returned an error: %s', 'poczta-polska-for-woocommerce' ),
					esc_html( $fault->getMessage() )
				),
				0,
				$fault // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous exception, not output.
			);
		}

		if ( ! is_object( $response ) ) {
			throw new EnException( esc_html__( 'Elektroniczny Nadawca returned an invalid response.', 'poczta-polska-for-woocommerce' ) );
		}

		Logger::debug( 'EN call.', self::log_context( $operation, $started ) );

		if ( $throw_on_error ) {
			self::throw_on_error( $response );
		}

		return $response;
	}

	/**
	 * Returns the SOAP transport.
	 *
	 * @throws \SoapFault When the WSDL cannot be parsed.
	 */
	private function soap(): SoapTransport {
		if ( null === $this->soap ) {
			$this->soap = new SoapTransport( $this->wsdl, $this->location, $this->login, $this->password );
		}

		return $this->soap;
	}

	/**
	 * Throws when the response carries an EN error (errorNumber >= 1).
	 *
	 * Error number 0 is informational only.
	 *
	 * @param object $response Response.
	 * @throws EnException With the first error.
	 */
	private static function throw_on_error( object $response ): void {
		foreach ( self::objects( $response, 'error' ) as $error ) {
			$number = self::int( $error, 'errorNumber' );

			if ( $number >= 1 ) {
				throw new EnException(
					sprintf(
						/* translators: 1: error description from Elektroniczny Nadawca, 2: error number. */
						esc_html__( '%1$s (EN error %2$d)', 'poczta-polska-for-woocommerce' ),
						esc_html( self::string( $error, 'errorDesc' ) ),
						(int) $number
					),
					(int) $number
				);
			}
		}
	}

	/**
	 * Builds buforType parameters.
	 *
	 * @param Buffer $buffer Buffer.
	 * @return array<string, mixed>
	 */
	private static function buffer_params( Buffer $buffer ): array {
		$params = array(
			'dataNadania'  => $buffer->date,
			'urzadNadania' => $buffer->office_id,
			'active'       => $buffer->active,
			'opis'         => $buffer->description,
			'idKarta'      => $buffer->card_id,
		);

		if ( $buffer->profile_id > 0 ) {
			$params['profil'] = array( 'idProfil' => $buffer->profile_id );
		}

		return $params;
	}

	/**
	 * Wraps a shipment in a SoapVar with its EN type (przesylkaType is abstract), also for sub-parcels.
	 *
	 * @param array{type: string, data: array<string, mixed>} $shipment Shipment.
	 */
	private static function soap_shipment( array $shipment ): \SoapVar {
		$data = $shipment['data'];

		if ( isset( $data['subPrzesylka'] ) && is_array( $data['subPrzesylka'] ) ) {
			$data['subPrzesylka'] = array_map( array( self::class, 'soap_shipment' ), $data['subPrzesylka'] );
		}

		return new \SoapVar( (object) $data, SOAP_ENC_OBJECT, $shipment['type'], self::NS );
	}

	/**
	 * Returns the error messages of a response item (errorNumber >= 1).
	 *
	 * @param object $item Response item.
	 * @return list<string>
	 */
	private static function error_messages( object $item ): array {
		$messages = array();

		foreach ( self::objects( $item, 'error' ) as $error ) {
			$number = self::int( $error, 'errorNumber' );

			if ( $number >= 1 ) {
				/* translators: 1: error description from Elektroniczny Nadawca, 2: error number. */
				$messages[] = sprintf( __( '%1$s (EN error %2$d)', 'poczta-polska-for-woocommerce' ), self::string( $error, 'errorDesc' ), $number );
			}
		}

		return $messages;
	}

	/**
	 * Groups response errors by GUID: the guid attribute, or a GUID named in the description.
	 *
	 * @param object $response Response.
	 * @return array<string, string>
	 */
	private static function errors_by_guid( object $response ): array {
		$errors = array();

		foreach ( self::objects( $response, 'error' ) as $error ) {
			$number = self::int( $error, 'errorNumber' );

			if ( $number < 1 ) {
				continue;
			}

			$guid = strtoupper( self::string( $error, 'guid' ) );

			if ( '' === $guid && 1 === preg_match( '/guid:\s*([0-9A-F]{32})/i', self::string( $error, 'errorDesc' ), $matches ) ) {
				$guid = strtoupper( $matches[1] );
			}

			/* translators: 1: error description from Elektroniczny Nadawca, 2: error number. */
			$message         = sprintf( __( '%1$s (EN error %2$d)', 'poczta-polska-for-woocommerce' ), self::string( $error, 'errorDesc' ), $number );
			$errors[ $guid ] = isset( $errors[ $guid ] ) ? $errors[ $guid ] . ' ' . $message : $message;
		}

		return $errors;
	}

	/**
	 * Address of an adresType object in one line.
	 *
	 * @param object $address Address.
	 */
	private static function address_line( object $address ): string {
		$street   = trim( self::string( $address, 'ulica' ) . ' ' . self::string( $address, 'numerDomu' ) . ( '' !== self::string( $address, 'numerLokalu' ) ? '/' . self::string( $address, 'numerLokalu' ) : '' ) );
		$postcode = self::string( $address, 'kodPocztowy' );
		$postcode = 5 === strlen( $postcode ) ? substr( $postcode, 0, 2 ) . '-' . substr( $postcode, 2 ) : $postcode;

		return trim( $street . ', ' . $postcode . ' ' . self::string( $address, 'miejscowosc' ), ' ,' );
	}

	/**
	 * Maps placowkaPocztowaType to a DTO.
	 *
	 * @param object $point Point from the response.
	 */
	private static function map_point( object $point ): PickupPoint {
		$location  = self::object( $point, 'lokalizacjaGeograficzna' );
		$latitude  = null !== $location ? self::object( $location, 'szerokosc' ) : null;
		$longitude = null !== $location ? self::object( $location, 'dlugosc' ) : null;

		return new PickupPoint(
			self::int( $point, 'id' ),
			self::string( $point, 'nazwa' ),
			self::string( $point, 'nazwaWydruk' ),
			self::string( $point, 'siecPlacowek' ),
			self::string( $point, 'typ' ),
			self::string( $point, 'ulica' ),
			self::string( $point, 'numerDomu' ),
			self::string( $point, 'numerLokalu' ),
			self::string( $point, 'kodPocztowy' ),
			self::string( $point, 'miejscowosc' ),
			self::string( $point, 'wojewodztwo' ),
			self::string( $point, 'opis' ),
			null !== $latitude ? self::float( $latitude, 'dec' ) : 0.0,
			null !== $longitude ? self::float( $longitude, 'dec' ) : 0.0,
			// PLN, despite "w groszach" in the WebAPI description (EN FAQ 12, PP 27.10.2026).
			self::float( $point, 'maksymalnaKwotaPobrania' ),
			self::bool( $point, 'punktWydaniaPrzesylkiBiznesowej' )
		);
	}

	/**
	 * Builds the log context of a call.
	 *
	 * @param string          $operation Operation name.
	 * @param float           $started   Start time from microtime(true).
	 * @param \Throwable|null $error     Error, if any.
	 * @return array<string, mixed>
	 */
	private static function log_context( string $operation, float $started, ?\Throwable $error = null ): array {
		$context = array(
			'operation'   => $operation,
			'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
		);

		if ( null !== $error ) {
			$context['error'] = $error->getMessage();
		}

		return $context;
	}

	/**
	 * Returns a list of objects from a property that SOAP_SINGLE_ELEMENT_ARRAYS keeps as an array.
	 *
	 * @param object $data     Parent object.
	 * @param string $property Property name.
	 * @return list<object>
	 */
	private static function objects( object $data, string $property ): array {
		$value = $data->{$property} ?? array();
		$value = is_array( $value ) ? $value : array( $value );

		return array_values( array_filter( $value, 'is_object' ) );
	}

	/**
	 * Returns a child object, or null when the property is missing.
	 *
	 * @param object $data     Parent object.
	 * @param string $property Property name.
	 */
	private static function object( object $data, string $property ): ?object {
		$value = $data->{$property} ?? null;

		return is_object( $value ) ? $value : null;
	}

	/**
	 * Reads a scalar property as a trimmed string.
	 *
	 * @param object $data     Object.
	 * @param string $property Property name.
	 */
	private static function string( object $data, string $property ): string {
		$value = $data->{$property} ?? '';

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Reads a property as an integer.
	 *
	 * @param object $data     Object.
	 * @param string $property Property name.
	 */
	private static function int( object $data, string $property ): int {
		return (int) self::string( $data, $property );
	}

	/**
	 * Reads a property as a float.
	 *
	 * @param object $data     Object.
	 * @param string $property Property name.
	 */
	private static function float( object $data, string $property ): float {
		return (float) self::string( $data, $property );
	}

	/**
	 * Reads a boolean property (xsd:boolean or "true"/"false").
	 *
	 * @param object $data     Object.
	 * @param string $property Property name.
	 */
	private static function bool( object $data, string $property ): bool {
		$value = $data->{$property} ?? false;

		return is_bool( $value ) ? $value : in_array( strtolower( (string) $value ), array( '1', 'true' ), true );
	}
}
