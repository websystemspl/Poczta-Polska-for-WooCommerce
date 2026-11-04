<?php
/**
 * Transport failure.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Elektroniczny Nadawca could not be reached or returned an unexpected HTTP response.
 *
 * The request may or may not have been processed, so callers that change data
 * must check the state in Elektroniczny Nadawca before retrying.
 */
final class ConnectionException extends EnException {
}
