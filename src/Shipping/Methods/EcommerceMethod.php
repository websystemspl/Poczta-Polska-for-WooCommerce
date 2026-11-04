<?php
/**
 * Shipping method: eCommerce parcel.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping\Methods;

use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Registered business letter (product 845) up to 2 kg. Cash on delivery is not available.
 */
final class EcommerceMethod extends AbstractMethod {

	protected const SERVICE = ServiceRegistry::ECOMMERCE;

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'eCommerce parcel', 'poczta-polska-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Registered business letter (product 845) up to 2 kg. Cash on delivery is not available.', 'poczta-polska-for-woocommerce' );
	}
}
