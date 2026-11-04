<?php
/**
 * Elektroniczny Nadawca client factory.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En;

use PocztaPolska\WooCommerce\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the EN client for the environment and credentials from the settings.
 */
final class ClientFactory {

	/**
	 * Returns a client for an environment (the active one by default).
	 *
	 * @param string|null $environment Environment, see Settings::ENV_* constants.
	 */
	public static function create( ?string $environment = null ): ClientInterface {
		$environment = $environment ?? Settings::environment();

		if ( ! isset( Settings::EN_ENDPOINTS[ $environment ] ) ) {
			$environment = Settings::ENV_TEST;
		}

		$client = new Client(
			Settings::EN_ENDPOINTS[ $environment ],
			Settings::get( 'en_' . $environment . '_login' ),
			Settings::en_password( $environment ),
			self::wsdl_path()
		);

		/**
		 * Filters the Elektroniczny Nadawca client.
		 *
		 * Lets tests and development setups use a client that does not call EN.
		 *
		 * @param ClientInterface $client      Client.
		 * @param string          $environment Environment: production or test.
		 */
		return apply_filters( 'ppwc_en_client', $client, $environment );
	}

	/**
	 * Path to the bundled WSDL (EN FAQ 7 recommends a local copy).
	 */
	public static function wsdl_path(): string {
		return PPWC_PLUGIN_DIR . 'resources/wsdl/labs.wsdl';
	}
}
