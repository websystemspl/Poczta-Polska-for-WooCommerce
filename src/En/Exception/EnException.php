<?php
/**
 * Base exception of the Elektroniczny Nadawca client.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * An Elektroniczny Nadawca call failed.
 *
 * The message is safe to show to a shop manager: it never contains credentials.
 */
class EnException extends \RuntimeException {

	/**
	 * Error number returned by Elektroniczny Nadawca (errorNumber), 0 when unknown.
	 *
	 * @var int
	 */
	private int $en_error_number;

	/**
	 * Constructor.
	 *
	 * @param string          $message         Error description.
	 * @param int             $en_error_number Elektroniczny Nadawca error number, 0 when unknown.
	 * @param \Throwable|null $previous        Previous exception.
	 */
	public function __construct( string $message, int $en_error_number = 0, ?\Throwable $previous = null ) {
		parent::__construct( $message, $en_error_number, $previous );

		$this->en_error_number = $en_error_number;
	}

	/**
	 * Returns the Elektroniczny Nadawca error number, 0 when unknown.
	 */
	public function get_en_error_number(): int {
		return $this->en_error_number;
	}
}
