<?php
/**
 * In-memory options, transients and database for unit tests.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit;

use Brain\Monkey\Functions;
use PocztaPolska\WooCommerce\Tests\Stubs\FakeWpdb;

/**
 * Replaces the WordPress options and transients API with arrays and $wpdb with FakeWpdb.
 */
trait WordPressStore {

	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	protected array $options = array();

	/**
	 * Transients.
	 *
	 * @var array<string, mixed>
	 */
	protected array $transients = array();

	/**
	 * Database.
	 *
	 * @var FakeWpdb
	 */
	protected FakeWpdb $wpdb;

	/**
	 * Installs the in-memory store. Call from setUp() after parent::setUp().
	 *
	 * @param string $today Today's date for wp_date( 'Y-m-d' ).
	 */
	protected function install_store( string $today = '2026-11-04' ): void {
		$this->wpdb       = new FakeWpdb();
		$GLOBALS['wpdb']  = $this->wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Fake database of the unit tests.
		$this->options    = array();
		$this->transients = array();

		Functions\when( 'get_option' )->alias( fn( string $name, $fallback = false ) => array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $fallback );
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'add_option' )->alias(
			function ( string $name, $value = '' ): bool {
				if ( array_key_exists( $name, $this->options ) ) {
					return false;
				}
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				unset( $this->options[ $name ] );
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias( fn( string $name ) => $this->transients[ $name ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( string $name, $value ): bool {
				$this->transients[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( string $name ): bool {
				unset( $this->transients[ $name ] );
				return true;
			}
		);
		Functions\when( 'wp_json_encode' )->alias( static fn( $value ) => json_encode( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- This is the wp_json_encode() stub.
		Functions\when( 'wp_date' )->alias(
			static function ( string $format, ?int $timestamp = null ) use ( $today ): string {
				if ( 'Y-m-d' === $format && null === $timestamp ) {
					return $today;
				}

				return gmdate( $format, $timestamp ?? time() );
			}
		);
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ) => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ) );
	}

	/**
	 * Removes the fake database.
	 */
	protected function uninstall_store(): void {
		unset( $GLOBALS['wpdb'] );
	}
}
