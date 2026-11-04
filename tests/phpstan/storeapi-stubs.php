<?php
/**
 * WooCommerce Store API classes missing from php-stubs/woocommerce-stubs (signatures from WooCommerce 11.2).
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

// phpcs:disable

namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \Exception {
		/**
		 * @param string               $error_code       Machine-readable error code.
		 * @param string               $message          User-friendly translated message.
		 * @param int                  $http_status_code HTTP status code.
		 * @param array<string, mixed> $additional_data  Extra data.
		 */
		public function __construct( $error_code, $message, $http_status_code = 400, $additional_data = array() ) {}
	}
}

namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
	class CartSchema {
		const IDENTIFIER = 'cart';
	}
}
