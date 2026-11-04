<?php
/**
 * Pickup point.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Post office, partner point or parcel locker (placowkaPocztowaType).
 */
final class PickupPoint {

	public const TYPE_POINT = 'PUNKT';

	public const TYPE_LOCKER = 'AUTOMAT';

	/**
	 * Network of Pocztex parcel lockers (siecPlacowek).
	 */
	public const NETWORK_POCZTEX_LOCKER = 'AUTOMAT POCZTEX';

	/**
	 * Constructor.
	 *
	 * @param int    $pni             Point ID (PNI).
	 * @param string $name            Full name (nazwa).
	 * @param string $print_name      Name printed on labels (nazwaWydruk).
	 * @param string $network         Network (siecPlacowek), e.g. POCZTAPOLSKA, ZABKA, AUTOMAT POCZTEX.
	 * @param string $type            PUNKT or AUTOMAT.
	 * @param string $street          Street (ulica), empty in villages without streets.
	 * @param string $house_number    House number (numerDomu).
	 * @param string $flat_number     Flat number (numerLokalu).
	 * @param string $postcode        Postcode, five digits without a dash.
	 * @param string $city            City (miejscowosc).
	 * @param string $voivodeship     Voivodeship (wojewodztwo).
	 * @param string $description     Extra description (opis).
	 * @param float  $latitude        Latitude, 0 when unknown.
	 * @param float  $longitude       Longitude, 0 when unknown.
	 * @param float  $cod_limit       Maximum cash on delivery amount in PLN, 0 when the point does not handle COD.
	 * @param bool   $business_pickup Whether the point hands out business parcels (punktWydaniaPrzesylkiBiznesowej).
	 */
	public function __construct(
		public readonly int $pni,
		public readonly string $name,
		public readonly string $print_name,
		public readonly string $network,
		public readonly string $type,
		public readonly string $street,
		public readonly string $house_number,
		public readonly string $flat_number,
		public readonly string $postcode,
		public readonly string $city,
		public readonly string $voivodeship,
		public readonly string $description,
		public readonly float $latitude,
		public readonly float $longitude,
		public readonly float $cod_limit,
		public readonly bool $business_pickup,
	) {
	}

	/**
	 * Whether the point accepts cash on delivery.
	 */
	public function supports_cod(): bool {
		return $this->cod_limit > 0;
	}

	/**
	 * Whether the point accepts cash on delivery of the given amount in PLN.
	 *
	 * @param float $amount Amount in PLN.
	 */
	public function accepts_cod_amount( float $amount ): bool {
		return $this->supports_cod() && $amount > 0 && $amount <= $this->cod_limit;
	}

	/**
	 * Whether customers can collect Pocztex parcels here (EN FAQ 13).
	 */
	public function is_pickup_point(): bool {
		return $this->business_pickup;
	}

	/**
	 * Address in one line, e.g. "Teodora Toeplitza 2, 01-560 Warszawa".
	 */
	public function address_line(): string {
		$street = trim( $this->street . ' ' . $this->house_number . ( '' !== $this->flat_number ? '/' . $this->flat_number : '' ) );

		if ( '' === trim( $this->street ) ) {
			// Villages without streets: the house number follows the city name.
			$street = trim( $this->city . ' ' . $this->house_number );
		}

		$postcode = 5 === strlen( $this->postcode ) ? substr( $this->postcode, 0, 2 ) . '-' . substr( $this->postcode, 2 ) : $this->postcode;

		return $street . ', ' . $postcode . ' ' . $this->city;
	}
}
