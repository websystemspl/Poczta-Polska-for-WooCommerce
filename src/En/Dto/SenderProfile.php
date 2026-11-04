<?php
/**
 * Sender profile.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Sender profile (profilType). Elektroniczny Nadawca prints its address as the sender on labels.
 */
final class SenderProfile {

	/**
	 * Constructor.
	 *
	 * @param int    $id         Profile ID (idProfil).
	 * @param string $short_name Short name (nazwaSkrocona).
	 * @param string $name       Sender name (nazwa).
	 * @param string $address    Address in one line.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $short_name,
		public readonly string $name,
		public readonly string $address,
	) {
	}
}
