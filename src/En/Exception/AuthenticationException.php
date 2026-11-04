<?php
/**
 * Rejected Elektroniczny Nadawca credentials.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Elektroniczny Nadawca rejected the login or password (HTTP 401).
 */
final class AuthenticationException extends EnException {
}
