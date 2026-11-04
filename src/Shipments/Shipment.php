<?php
/**
 * Shipment stored in the shop.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

defined( 'ABSPATH' ) || exit;

/**
 * A shipment as the shop knows it (row of the ppwc_shipments table).
 */
final class Shipment {

	/**
	 * GUID reserved, the result of addShipment is not known yet (e.g. after a timeout).
	 */
	public const STATUS_PENDING = 'pending';

	/**
	 * In a buffer in Elektroniczny Nadawca, with a tracking number.
	 */
	public const STATUS_CREATED = 'created';

	/**
	 * Rejected by Elektroniczny Nadawca, see last_error.
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * The buffer was sent, the shipment cannot change any more.
	 */
	public const STATUS_SENT = 'sent';

	/**
	 * Constructor.
	 *
	 * @param int                  $id              Row ID.
	 * @param int                  $order_id        Order ID.
	 * @param string               $environment     test or production.
	 * @param string               $guid            GUID of the main parcel.
	 * @param string               $service         Service ID.
	 * @param string               $status          STATUS_* constant.
	 * @param string               $tracking_number Tracking number of the main parcel.
	 * @param int                  $buffer_id       Buffer ID.
	 * @param int                  $card_id         Contract card.
	 * @param int                  $office_id       Posting office.
	 * @param int                  $envelope_id     Envelope ID after sending.
	 * @param string               $label_printed   Date of the first label download (GMT), empty when never.
	 * @param array<string, mixed> $data            Draft and sub-parcel tracking numbers.
	 * @param string               $last_error      Last error.
	 * @param string               $created_at      Creation date (GMT).
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $order_id,
		public readonly string $environment,
		public readonly string $guid,
		public readonly string $service,
		public readonly string $status,
		public readonly string $tracking_number,
		public readonly int $buffer_id,
		public readonly int $card_id,
		public readonly int $office_id,
		public readonly int $envelope_id,
		public readonly string $label_printed,
		public readonly array $data,
		public readonly string $last_error,
		public readonly string $created_at,
	) {
	}

	/**
	 * Whether the shipment can still be edited: the label was not printed and the buffer was not sent
	 * (OPZ II.2.1.e).
	 */
	public function is_editable(): bool {
		return self::STATUS_CREATED === $this->status && '' === $this->label_printed;
	}

	/**
	 * Whether the shipment can be deleted (only before the buffer is sent).
	 */
	public function is_deletable(): bool {
		return self::STATUS_SENT !== $this->status;
	}

	/**
	 * Whether the shipment is in a buffer in EN.
	 */
	public function is_in_buffer(): bool {
		return self::STATUS_CREATED === $this->status;
	}

	/**
	 * Shop shipment number sent as numerPrzesylkiKlienta. EN keeps it when a buffer date change gives the
	 * shipment a new GUID, so it is read from the stored draft, not derived from the current GUID.
	 */
	public function customer_number(): string {
		$draft = isset( $this->data['draft'] ) && is_array( $this->data['draft'] ) ? $this->data['draft'] : array();

		return isset( $draft['customer_number'] ) && is_string( $draft['customer_number'] ) && '' !== $draft['customer_number']
			? $draft['customer_number']
			: DraftFactory::customer_number( $this->guid );
	}

	/**
	 * Whether EN gave the sub-parcels new tracking numbers that are known only from the new label
	 * until the buffer is sent.
	 */
	public function has_unknown_sub_tracking(): bool {
		return ! empty( $this->data['sub_tracking_stale'] );
	}

	/**
	 * Tracking numbers of all parcels, main parcel first.
	 *
	 * @return list<string>
	 */
	public function tracking_numbers(): array {
		$subs = isset( $this->data['sub_tracking'] ) && is_array( $this->data['sub_tracking'] ) ? array_values( array_map( 'strval', $this->data['sub_tracking'] ) ) : array();

		return array_values( array_filter( array_merge( array( $this->tracking_number ), $subs ) ) );
	}
}
