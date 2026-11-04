<?php
/**
 * Base test case with the WordPress HTTP API mocked for EN calls.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\En;

use Brain\Monkey\Functions;
use PocztaPolska\WooCommerce\En\Client;
use PocztaPolska\WooCommerce\En\ClientFactory;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Mocks wp_remote_post() with responses recorded on INT.
 */
abstract class EnHttpTestCase extends TestCase {

	protected const ENDPOINT = 'https://int-e-nadawca.api.poczta-polska.pl/websrv/';

	/**
	 * Requests sent through wp_remote_post().
	 *
	 * @var list<array{url: string, args: array<string, mixed>}>
	 */
	protected array $requests = array();

	/**
	 * Responses by operation name: [HTTP code, body] or a WP_Error.
	 *
	 * @var array<string, array{0: int, 1: string}|\WP_Error>
	 */
	protected array $responses = array();

	/**
	 * Mocks the WordPress HTTP API.
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_remote_post' )->alias(
			function ( string $url, array $args ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);

				$operation = basename( trim( (string) $args['headers']['SOAPAction'], '"' ) );
				$response  = $this->responses[ $operation ] ?? array( 404, '' );

				if ( $response instanceof \WP_Error ) {
					return $response;
				}

				return array(
					'response' => array( 'code' => $response[0] ),
					'body'     => $response[1],
				);
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ) => $thing instanceof \WP_Error );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $response ) => $response['response']['code'] );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $response ) => $response['body'] );
	}

	/**
	 * Registers a recorded response for an operation.
	 *
	 * @param string $operation Operation name.
	 * @param string $fixture   Fixture file in tests/fixtures/en.
	 * @param int    $code      HTTP status.
	 */
	protected function respond( string $operation, string $fixture, int $code = 200 ): void {
		$this->responses[ $operation ] = array( $code, self::fixture( 'en/' . $fixture ) );
	}

	/**
	 * Returns a client with test credentials.
	 *
	 * @param string $login    Login.
	 * @param string $password Password.
	 */
	protected function client( string $login = 'user', string $password = 'secret' ): Client {
		return new Client( self::ENDPOINT, $login, $password, ClientFactory::wsdl_path() );
	}
}
