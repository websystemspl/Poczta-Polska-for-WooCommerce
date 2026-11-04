<?php
/**
 * Base test case.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Sets up Brain Monkey for every test.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Sets up WordPress function mocks.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_timezone' )->alias( static fn() => new \DateTimeZone( 'Europe/Warsaw' ) );
	}

	/**
	 * Tears down mocks.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Returns the contents of a fixture file.
	 *
	 * @param string $path Path relative to tests/fixtures.
	 */
	protected static function fixture( string $path ): string {
		$contents = file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.

		if ( false === $contents ) {
			throw new \RuntimeException( 'Missing fixture: ' . $path ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test code.
		}

		return $contents;
	}
}
