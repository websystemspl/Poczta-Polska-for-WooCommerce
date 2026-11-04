<?php
/**
 * Result of adding a shipment.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Result of one shipment in addShipment (addShipmentResponseItemType).
 */
final class ShipmentResult {

	/**
	 * Constructor.
	 *
	 * @param string   $guid            Shipment GUID.
	 * @param string   $tracking_number Tracking number (numerNadania), empty on failure.
	 * @param string[] $errors          Error messages from EN, with the error number.
	 */
	public function __construct(
		public readonly string $guid,
		public readonly string $tracking_number,
		public readonly array $errors,
	) {
	}

	/**
	 * Whether EN accepted the shipment.
	 */
	public function is_success(): bool {
		return array() === $this->errors && '' !== $this->tracking_number;
	}
}
