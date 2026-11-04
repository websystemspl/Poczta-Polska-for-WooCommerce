<?php
/**
 * Posting office.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Posting office (urząd nadania) from getUrzedyNadania.
 */
final class PostingOffice {

	/**
	 * Constructor.
	 *
	 * @param int    $id          Posting office ID (urzadNadania).
	 * @param string $description Description (opis).
	 * @param string $print_name  Name printed on labels (nazwaWydruk).
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $description,
		public readonly string $print_name,
	) {
	}
}
