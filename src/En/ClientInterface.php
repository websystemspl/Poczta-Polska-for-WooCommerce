<?php
/**
 * Elektroniczny Nadawca client contract.
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
use PocztaPolska\WooCommerce\En\Exception\EnException;

defined( 'ABSPATH' ) || exit;

/**
 * Operations of the Elektroniczny Nadawca WebAPI used by the plugin.
 *
 * Every method throws EnException (or a subclass) when the call fails.
 */
interface ClientInterface {

	/**
	 * Test call. Returns the echoed text.
	 *
	 * @param string $text Text to echo.
	 * @throws EnException When the call fails.
	 */
	public function hello( string $text = 'ping' ): string;

	/**
	 * Returns the expiry date of the EN password.
	 *
	 * @throws EnException When the call fails.
	 */
	public function password_expiry_date(): \DateTimeImmutable;

	/**
	 * Returns the contract cards available to the EN user.
	 *
	 * @return list<Card>
	 * @throws EnException When the call fails.
	 */
	public function cards(): array;

	/**
	 * Returns the posting offices of the contract.
	 *
	 * @return list<PostingOffice>
	 * @throws EnException When the call fails.
	 */
	public function posting_offices(): array;

	/**
	 * Returns all delivery points in a voivodeship.
	 *
	 * @param string $voivodeship_code GUS code: 02, 04 ... 32.
	 * @return list<PickupPoint>
	 * @throws EnException When the call fails.
	 */
	public function pickup_points( string $voivodeship_code ): array;

	/**
	 * Returns the points with the given PNIs.
	 *
	 * @param int[] $pnis Point IDs.
	 * @return list<PickupPoint>
	 * @throws EnException When the call fails.
	 */
	public function pickup_points_by_pni( array $pnis ): array;

	/**
	 * Returns new shipment GUIDs (32 uppercase hex characters).
	 *
	 * @param int $count Number of GUIDs, 1-100.
	 * @return list<string>
	 * @throws EnException When the call fails.
	 */
	public function guids( int $count ): array;

	/**
	 * Returns the sender profiles of the EN user.
	 *
	 * @return list<SenderProfile>
	 * @throws EnException When the call fails.
	 */
	public function profiles(): array;

	/**
	 * Returns the buffers of the EN user.
	 *
	 * @return list<Buffer>
	 * @throws EnException When the call fails.
	 */
	public function buffers(): array;

	/**
	 * Creates a buffer and returns its ID.
	 *
	 * @param Buffer $buffer Buffer data (ID is ignored).
	 * @throws EnException When the call fails.
	 */
	public function create_buffer( Buffer $buffer ): int;

	/**
	 * Updates a buffer (date, office, description).
	 *
	 * @param Buffer $buffer                Buffer data.
	 * @param bool   $update_shipment_dates Whether to move the planned date of its shipments too.
	 *                                      Shipments with a printed label then get a new tracking number and GUID.
	 * @throws EnException When the call fails.
	 */
	public function update_buffer( Buffer $buffer, bool $update_shipment_dates ): void;

	/**
	 * Deletes a buffer with all its shipments (clearEnvelope).
	 *
	 * @param int $buffer_id Buffer ID.
	 * @throws EnException When the call fails.
	 */
	public function delete_buffer( int $buffer_id ): void;

	/**
	 * Returns the shipments in a buffer by GUID: tracking number, the shop's shipment number
	 * (numerPrzesylkiKlienta, which survives the GUID change after updating the buffer date) and the
	 * GUIDs of sub-parcels. EN does not return sub-parcel tracking numbers here.
	 *
	 * @param int $buffer_id Buffer ID.
	 * @return array<string, array{tracking: string, customer_number: string, sub_guids: list<string>}>
	 * @throws EnException When the call fails.
	 */
	public function buffer_shipments( int $buffer_id ): array;

	/**
	 * Adds shipments to a buffer (up to 500).
	 *
	 * Each shipment is {type: EN type, data: fields}. Sub-parcels go in data.subPrzesylka
	 * with the same structure.
	 *
	 * @param int                                                   $buffer_id Buffer ID.
	 * @param list<array{type: string, data: array<string, mixed>}> $shipments Shipments.
	 * @return array<string, ShipmentResult> Results by GUID.
	 * @throws EnException When the call fails as a whole.
	 */
	public function add_shipments( int $buffer_id, array $shipments ): array;

	/**
	 * Deletes shipments from a buffer.
	 *
	 * @param int      $buffer_id Buffer ID.
	 * @param string[] $guids     GUIDs (main GUID for multi-parcel shipments).
	 * @return array<string, string> Errors by GUID, '' for errors EN did not link to a GUID.
	 * @throws EnException When the call fails as a whole.
	 */
	public function delete_shipments( int $buffer_id, array $guids ): array;

	/**
	 * Moves shipments between buffers. Tracking numbers stay the same.
	 *
	 * @param int      $from  Source buffer ID.
	 * @param int      $to    Target buffer ID.
	 * @param string[] $guids GUIDs.
	 * @return array<string, string> Errors by GUID, '' for errors EN did not link to a GUID.
	 * @throws EnException When the call fails as a whole.
	 */
	public function move_shipments( int $from, int $to, array $guids ): array;

	/**
	 * Returns address labels as PDF.
	 *
	 * @param string[] $guids    GUIDs.
	 * @param bool     $one_file Whether to return one PDF for all shipments.
	 * @return list<string> PDF documents.
	 * @throws EnException When the call fails.
	 */
	public function labels( array $guids, bool $one_file ): array;

	/**
	 * Sends a buffer to the posting office. EN deletes the buffer afterwards.
	 *
	 * @param int $buffer_id Buffer ID.
	 * @param int $office_id Posting office ID.
	 * @return array{envelope_id: int, status: string}
	 * @throws EnException When the call fails.
	 */
	public function send_buffer( int $buffer_id, int $office_id ): array;

	/**
	 * Returns the status of a sent envelope: WYSLANY, DOSTARCZONY, PRZYJETY, WALIDOWANY or BLEDNY.
	 *
	 * @param int $envelope_id Envelope ID.
	 * @throws EnException When the call fails.
	 */
	public function envelope_status( int $envelope_id ): string;

	/**
	 * Returns the parcels of a sent envelope by GUID, sub-parcels included: tracking number and status.
	 *
	 * @param int $envelope_id Envelope ID.
	 * @return array<string, array{tracking: string, status: string}>
	 * @throws EnException When the call fails.
	 */
	public function envelope_content( int $envelope_id ): array;

	/**
	 * Orders a courier to collect the parcels.
	 *
	 * @param array{address: array<string, string>, date: string, hours: string, count: int, weight: int, email: string} $order Pickup address (adresType fields), date (Y-m-d), hour range (e.g. 08:00-20:00), number of parcels, total weight in grams, confirmation email.
	 * @throws EnException When EN rejects the order.
	 */
	public function order_courier( array $order ): void;
}
