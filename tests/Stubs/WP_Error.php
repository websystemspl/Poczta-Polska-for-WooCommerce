<?php
/**
 * Minimal WP_Error for unit tests.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stand-in for WordPress' WP_Error.
	 */
	class WP_Error {

		/**
		 * Message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			unset( $code );
			$this->message = $message;
		}

		/**
		 * Returns the message.
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}
