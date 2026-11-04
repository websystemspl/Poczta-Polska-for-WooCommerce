<?php
/**
 * PSR-4 autoloader for the plugin classes.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the PocztaPolska\WooCommerce namespace to the src/ directory.
 *
 * The plugin ships without a runtime Composer dependency, so the release ZIP
 * works without a vendor/ directory.
 */
final class Autoloader {

	private const PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Registers the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Loads a plugin class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( ! str_starts_with( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
