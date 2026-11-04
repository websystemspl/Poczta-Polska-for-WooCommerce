<?php
/**
 * Elektroniczny Nadawca shipment structure.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Support\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the addShipment structure from a draft and checks it against the EN data
 * specification (addressee and sender fields) before anything is sent.
 */
final class ShipmentBuilder {

	/**
	 * Recipient field lengths for domestic shipments (EN data specification).
	 *
	 * @var array<string, int>
	 */
	private const MAX_LENGTH = array(
		'name'         => 60,
		'name2'        => 60,
		'street'       => 75,
		'house_number' => 11,
		'flat_number'  => 11,
		'city'         => 35,
		'email'        => 50,
		'phone'        => 20,
	);

	/**
	 * Characters of the description EN accepts (opisType); the label prints the first 40.
	 */
	private const DESCRIPTION_LENGTH = 250;

	/**
	 * Characters of the COD transfer title.
	 */
	private const COD_TITLE_LENGTH = 55;

	/**
	 * Returns the problems that make the draft invalid, empty when it can be sent.
	 *
	 * @param ShipmentDraft $draft Draft.
	 * @return list<string>
	 */
	public static function problems( ShipmentDraft $draft ): array {
		$problems  = array();
		$recipient = $draft->recipient;
		$service   = $draft->service;
		$labels    = array(
			'name'         => __( 'recipient name', 'poczta-polska-for-woocommerce' ),
			'name2'        => __( 'recipient name (continued)', 'poczta-polska-for-woocommerce' ),
			'street'       => __( 'street', 'poczta-polska-for-woocommerce' ),
			'house_number' => __( 'house number', 'poczta-polska-for-woocommerce' ),
			'flat_number'  => __( 'flat number', 'poczta-polska-for-woocommerce' ),
			'city'         => __( 'city', 'poczta-polska-for-woocommerce' ),
			'email'        => __( 'email', 'poczta-polska-for-woocommerce' ),
			'phone'        => __( 'phone', 'poczta-polska-for-woocommerce' ),
		);

		if ( 'PL' !== ( $recipient['country'] ?? '' ) ) {
			$problems[] = __( 'Domestic services deliver to Poland only.', 'poczta-polska-for-woocommerce' );
		}

		foreach ( array( 'name', 'street', 'city' ) as $required ) {
			if ( '' === ( $recipient[ $required ] ?? '' ) ) {
				/* translators: %s: field name. */
				$problems[] = sprintf( __( 'The %s is missing.', 'poczta-polska-for-woocommerce' ), $labels[ $required ] );
			}
		}

		foreach ( self::MAX_LENGTH as $field => $max ) {
			if ( ! Validator::fits( (string) ( $recipient[ $field ] ?? '' ), $max ) ) {
				/* translators: 1: field name, 2: maximum number of characters. */
				$problems[] = sprintf( __( 'The %1$s is longer than %2$d characters.', 'poczta-polska-for-woocommerce' ), $labels[ $field ], $max );
			}
		}

		if ( null === Validator::postcode( (string) ( $recipient['postcode'] ?? '' ) ) ) {
			$problems[] = __( 'The recipient postcode is not a Polish postcode.', 'poczta-polska-for-woocommerce' );
		}

		// EN requires a mobile number when the parcel goes to a point (error 30707).
		if ( $service->needs_pickup_point() && '' === ( $recipient['mobile'] ?? '' ) ) {
			$problems[] = __( 'A Polish mobile number of the recipient is required for delivery to a pickup point.', 'poczta-polska-for-woocommerce' );
		}

		if ( $service->needs_pickup_point() && $draft->pickup_pni <= 0 ) {
			$problems[] = __( 'The pickup point is missing.', 'poczta-polska-for-woocommerce' );
		}

		foreach ( $draft->weights as $weight ) {
			if ( $weight <= 0 || $weight > $service->max_weight ) {
				/* translators: %d: maximum weight in grams. */
				$problems[] = sprintf( __( 'Every parcel must weigh from 1 to %d g.', 'poczta-polska-for-woocommerce' ), $service->max_weight );
				break;
			}
		}

		if ( count( $draft->weights ) > 1 && $service->requires_weight ) {
			$problems[] = __( 'Letters cannot have several parcels.', 'poczta-polska-for-woocommerce' );
		}

		if ( ! $service->allows_format( $draft->format ) ) {
			/* translators: %s: parcel format. */
			$problems[] = sprintf( __( 'The format %s is not available for this service.', 'poczta-polska-for-woocommerce' ), $draft->format );
		}

		if ( $draft->cod_amount > 0 && ! $service->supports_cod ) {
			$problems[] = __( 'Cash on delivery is not available for this service.', 'poczta-polska-for-woocommerce' );
		}

		if ( $draft->cod_amount > 0 && 'RACHUNEK_BANKOWY' === ( $draft->options['cod_method'] ?? '' ) && null === Validator::nrb( (string) ( $draft->options['cod_account'] ?? '' ) ) ) {
			$problems[] = __( 'Enter the bank account for cash on delivery in the plugin settings.', 'poczta-polska-for-woocommerce' );
		}

		return $problems;
	}

	/**
	 * Builds the shipment for ClientInterface::add_shipments().
	 *
	 * @param ShipmentDraft $draft Draft (check problems() first).
	 * @return array{type: string, data: array<string, mixed>}
	 */
	public static function build( ShipmentDraft $draft ): array {
		$service = $draft->service;
		$data    = array(
			'guid'  => $draft->guid,
			'opis'  => mb_substr( $draft->description, 0, self::DESCRIPTION_LENGTH ),
			'adres' => self::address( $draft->recipient ),
		);

		if ( $service->requires_weight ) {
			// przesylkaFirmowaPoleconaType: weight, size and category are attributes.
			return array(
				'type' => $service->en_type,
				'data' => $data + array(
					'masa'                  => $draft->weights[0] ?? 0,
					'gabaryt'               => $draft->format,
					'kategoria'             => (string) ( $draft->options['category'] ?? 'EKONOMICZNA' ),
					'numerPrzesylkiKlienta' => $draft->customer_number,
				),
			);
		}

		if ( $draft->cod_amount > 0 ) {
			$data['pobranie'] = array_filter(
				array(
					'sposobPobrania' => (string) ( $draft->options['cod_method'] ?? 'RACHUNEK_BANKOWY' ),
					'kwotaPobrania'  => $draft->cod_amount,
					'nrb'            => 'RACHUNEK_BANKOWY' === ( $draft->options['cod_method'] ?? 'RACHUNEK_BANKOWY' ) ? (string) Validator::nrb( (string) ( $draft->options['cod_account'] ?? '' ) ) : '',
					'tytulem'        => mb_substr( (string) ( $draft->options['cod_title'] ?? '' ), 0, self::COD_TITLE_LENGTH ),
				),
				static fn( $value ): bool => '' !== $value
			);
		}

		$data['masa'] = $draft->weights[0] ?? 0;

		// Parcel lockers reject the "fragile" service (EN FAQ 12).
		if ( ! empty( $draft->options['fragile'] ) && PickupPoint::TYPE_LOCKER !== $service->point_type ) {
			$data['ostroznie'] = true;
		}

		$data['format']                = $draft->format;
		$data['numerPrzesylkiKlienta'] = $draft->customer_number;

		$insurance = (int) ( $draft->options['insurance'] ?? 0 );

		if ( $insurance > 0 ) {
			$data['ubezpieczenie'] = array(
				'rodzaj' => 'STANDARD',
				'kwota'  => $insurance,
			);
		}

		$data['zawartosc'] = array( 'zawartoscInna' => (string) ( $draft->options['content'] ?? '' ) );

		$subs = array();

		foreach ( $draft->sub_guids as $index => $guid ) {
			$subs[] = array(
				'type' => 'sub' . ucfirst( $service->en_type ),
				'data' => array(
					'guid'   => $guid,
					'masa'   => $draft->weights[ $index + 1 ] ?? 0,
					'format' => $draft->format,
				),
			);
		}

		if ( array() !== $subs ) {
			$data['subPrzesylka'] = $subs;
		}

		if ( $draft->pickup_pni > 0 ) {
			$data['punktOdbioru'] = array( 'id' => $draft->pickup_pni );
		}

		$channel = (string) ( $draft->options['posting_channel'] ?? 'COURIER' );
		$posting = (int) ( $draft->options['posting_pni'] ?? 0 );

		// Courier pickup is the EN default: no punktNadania (WebAPI description of pocztex2021KurierType).
		if ( 'COURIER' !== $channel && $posting > 0 ) {
			$data['punktNadania']    = array( 'id' => $posting );
			$data['shipmentChannel'] = $channel;
		}

		return array(
			'type' => $service->en_type,
			'data' => $data,
		);
	}

	/**
	 * Builds adresType (all fields are attributes).
	 *
	 * @param array<string, string> $recipient Recipient.
	 * @return array<string, string>
	 */
	private static function address( array $recipient ): array {
		return array_filter(
			array(
				'nazwa'       => $recipient['name'] ?? '',
				'nazwa2'      => $recipient['name2'] ?? '',
				'ulica'       => $recipient['street'] ?? '',
				'numerDomu'   => $recipient['house_number'] ?? '',
				'numerLokalu' => $recipient['flat_number'] ?? '',
				'miejscowosc' => $recipient['city'] ?? '',
				'kodPocztowy' => (string) Validator::postcode( (string) ( $recipient['postcode'] ?? '' ) ),
				'kraj'        => 'Polska',
				'mobile'      => $recipient['mobile'] ?? '',
				'telefon'     => $recipient['phone'] ?? '',
				'email'       => $recipient['email'] ?? '',
			),
			static fn( string $value ): bool => '' !== $value
		);
	}
}
