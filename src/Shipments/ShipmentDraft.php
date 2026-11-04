<?php
/**
 * Shipment data before it is sent to Elektroniczny Nadawca.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

use PocztaPolska\WooCommerce\Shipping\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Everything needed to register one shipment. Created from an order by DraftFactory,
 * the shop manager may change the phone, parcels and pickup point before sending.
 */
final class ShipmentDraft {

	/**
	 * Constructor.
	 *
	 * @param Service              $service         Service.
	 * @param string               $guid            GUID of the shipment (main parcel).
	 * @param string[]             $sub_guids       GUIDs of further parcels (multi-parcel Pocztex).
	 * @param array<string,string> $recipient       Recipient: name, name2, street, house_number, flat_number, postcode, city, country, mobile, phone, email.
	 * @param int[]                $weights         Weight in grams of each parcel, main parcel first.
	 * @param string               $format          Pocztex format (S-2XL) or letter size (GABARYT_A, GABARYT_B).
	 * @param int                  $cod_amount      Cash on delivery in grosze, 0 for none.
	 * @param int                  $pickup_pni      Pickup point, 0 for home delivery.
	 * @param string               $description     Description printed on the label.
	 * @param string               $customer_number Number of the shipment in the shop (numerPrzesylkiKlienta).
	 * @param array<string,mixed>  $options         Settings: fragile, content, insurance, category, cod_account, cod_method, cod_title, posting_channel, posting_pni.
	 * @param array<string,string> $contact         Mobile and phone changed by the shop manager for this shipment (stored with it).
	 */
	public function __construct(
		public readonly Service $service,
		public readonly string $guid,
		public readonly array $sub_guids,
		public readonly array $recipient,
		public readonly array $weights,
		public readonly string $format,
		public readonly int $cod_amount,
		public readonly int $pickup_pni,
		public readonly string $description,
		public readonly string $customer_number,
		public readonly array $options,
		public readonly array $contact = array(),
	) {
	}

	/**
	 * Total weight in grams.
	 */
	public function total_weight(): int {
		return (int) array_sum( $this->weights );
	}

	/**
	 * Returns the draft as an array, to store it with the shipment.
	 *
	 * Without the recipient: the address, phone and email stay in the order and in EN only
	 * (data minimisation), editing builds the shipment from the order again. Only phone numbers
	 * that the shop manager changed for this shipment are kept, so the next edit does not lose them;
	 * the privacy eraser removes them.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'service'         => $this->service->id,
			'guid'            => $this->guid,
			'sub_guids'       => $this->sub_guids,
			'weights'         => $this->weights,
			'format'          => $this->format,
			'cod_amount'      => $this->cod_amount,
			'pickup_pni'      => $this->pickup_pni,
			'description'     => $this->description,
			'customer_number' => $this->customer_number,
			'options'         => $this->options,
		) + ( array() !== $this->contact ? array( 'contact' => $this->contact ) : array() );
	}
}
