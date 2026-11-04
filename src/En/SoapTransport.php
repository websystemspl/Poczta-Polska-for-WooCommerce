<?php
/**
 * SOAP transport over the WordPress HTTP API.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En;

use PocztaPolska\WooCommerce\En\Exception\AuthenticationException;
use PocztaPolska\WooCommerce\En\Exception\ConnectionException;

defined( 'ABSPATH' ) || exit;

/**
 * SoapClient that builds and parses messages from the bundled WSDL,
 * but sends them with wp_remote_post().
 *
 * The WordPress HTTP API verifies TLS certificates, respects proxy settings
 * and lets tests replace responses through the pre_http_request filter.
 */
final class SoapTransport extends \SoapClient {

	/**
	 * Default timeout in seconds.
	 */
	public const DEFAULT_TIMEOUT = 30;

	/**
	 * EN login.
	 *
	 * @var string
	 */
	private string $login;

	/**
	 * EN password.
	 *
	 * @var string
	 */
	private string $password;

	/**
	 * Timeout of the next request in seconds.
	 *
	 * @var int
	 */
	private int $timeout = self::DEFAULT_TIMEOUT;

	/**
	 * Constructor.
	 *
	 * @param string $wsdl     Path to the local WSDL file.
	 * @param string $location WebAPI endpoint. The WSDL points at production, so the endpoint is always set here (EN FAQ 7).
	 * @param string $login    EN login.
	 * @param string $password EN password.
	 * @throws \SoapFault When the WSDL cannot be parsed.
	 */
	public function __construct( string $wsdl, string $location, string $login, string $password ) {
		$this->login    = $login;
		$this->password = $password;

		parent::__construct(
			$wsdl,
			array(
				'location'     => $location,
				'soap_version' => SOAP_1_1,
				'encoding'     => 'UTF-8',
				'exceptions'   => true,
				'trace'        => false,
				'cache_wsdl'   => WSDL_CACHE_BOTH,
				'features'     => SOAP_SINGLE_ELEMENT_ARRAYS,
			)
		);
	}

	/**
	 * Sets the timeout of the following requests.
	 *
	 * @param int $seconds Timeout in seconds.
	 */
	public function set_timeout( int $seconds ): void {
		$this->timeout = max( 1, $seconds );
	}

	/**
	 * Sends a SOAP request.
	 *
	 * The variadic parameter keeps the signature compatible with PHP 8.5,
	 * which added an optional parameter to SoapClient::__doRequest().
	 *
	 * @param string $request  SOAP envelope.
	 * @param string $location Endpoint.
	 * @param string $action   SOAPAction.
	 * @param int    $version  SOAP version.
	 * @param bool   $one_way  Whether no response is expected.
	 * @param mixed  ...$extra Parameters added in newer PHP versions.
	 * @return string Response body.
	 * @throws AuthenticationException When EN rejects the credentials.
	 * @throws ConnectionException     When EN cannot be reached or returns an unexpected response.
	 */
	public function __doRequest( $request, $location, $action, $version, $one_way = false, ...$extra ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Overrides SoapClient.
		$require_tls12 = static function ( $handle, array $args, string $url ) use ( $location ): void {
			if ( str_starts_with( $url, $location ) && defined( 'CURL_SSLVERSION_TLSv1_2' ) ) {
				// TLS 1.2 or newer (contract requirement), the HTTP API has no option for it.
				curl_setopt( $handle, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Only reachable through the http_api_curl hook.
			}
		};

		add_action( 'http_api_curl', $require_tls12, 10, 3 );

		try {
			$response = wp_remote_post(
				$location,
				array(
					'timeout'     => $this->timeout,
					'redirection' => 0,
					'sslverify'   => true,
					'headers'     => array(
						'Content-Type'  => 'text/xml; charset=utf-8',
						'SOAPAction'    => '"' . $action . '"',
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication required by EN.
						'Authorization' => 'Basic ' . base64_encode( $this->login . ':' . $this->password ),
					),
					'body'        => $request,
				)
			);
		} finally {
			remove_action( 'http_api_curl', $require_tls12, 10 );
		}

		if ( is_wp_error( $response ) ) {
			throw new ConnectionException(
				sprintf(
					/* translators: %s: error message from the HTTP layer. */
					esc_html__( 'Could not connect to Elektroniczny Nadawca: %s', 'poczta-polska-for-woocommerce' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 401 === $code ) {
			throw new AuthenticationException( esc_html__( 'Elektroniczny Nadawca rejected the login or password.', 'poczta-polska-for-woocommerce' ) );
		}

		// SOAP faults come with HTTP 500, SoapClient turns them into a SoapFault.
		if ( ( 200 === $code || 500 === $code ) && '' !== $body ) {
			return $body;
		}

		throw new ConnectionException(
			sprintf(
				/* translators: %d: HTTP status code. */
				esc_html__( 'Elektroniczny Nadawca returned an unexpected response (HTTP %d).', 'poczta-polska-for-woocommerce' ),
				(int) $code
			)
		);
	}
}
