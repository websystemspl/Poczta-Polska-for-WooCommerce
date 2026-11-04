<?php
/**
 * Buffer.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Buffer (buforType): shipments prepared for posting on one day, for one card and posting office.
 */
final class Buffer {

	/**
	 * Constructor.
	 *
	 * @param int    $id          Buffer ID (idBufor), 0 for a new buffer.
	 * @param int    $card_id     Contract card (idKarta).
	 * @param int    $office_id   Posting office (urzadNadania).
	 * @param int    $profile_id  Sender profile (idProfil).
	 * @param string $date        Planned posting date, Y-m-d (dataNadania).
	 * @param string $description Description (opis).
	 * @param bool   $active      Whether it is the active buffer of the card.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $card_id,
		public readonly int $office_id,
		public readonly int $profile_id,
		public readonly string $date,
		public readonly string $description,
		public readonly bool $active = false,
	) {
	}

	/**
	 * Returns a copy with another posting date.
	 *
	 * @param string $date Date, Y-m-d.
	 */
	public function with_date( string $date ): self {
		return new self( $this->id, $this->card_id, $this->office_id, $this->profile_id, $date, $this->description, $this->active );
	}
}
