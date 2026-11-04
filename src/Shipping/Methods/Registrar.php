<?php
/**
 * Registration of the shipping methods.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping\Methods;

use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Poczta Polska methods to WooCommerce shipping zones.
 */
final class Registrar {

	/**
	 * Method classes keyed by method ID.
	 *
	 * @var array<string, class-string<AbstractMethod>>
	 */
	private const METHODS = array(
		ServiceRegistry::POCZTEX_COURIER => PocztexCourierMethod::class,
		ServiceRegistry::POCZTEX_POINT   => PocztexPointMethod::class,
		ServiceRegistry::POCZTEX_LOCKER  => PocztexLockerMethod::class,
		ServiceRegistry::ECOMMERCE       => EcommerceMethod::class,
	);

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_methods' ) );
	}

	/**
	 * Adds the methods.
	 *
	 * @param array<string, string|object> $methods Registered methods.
	 * @return array<string, string|object>
	 */
	public function add_methods( array $methods ): array {
		return array_merge( $methods, self::METHODS );
	}
}
