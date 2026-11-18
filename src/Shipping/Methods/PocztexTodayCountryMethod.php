<?php
/**
 * Pocztex Courier shipping method.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping\Methods;

use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Pocztex "Na dziś" in the country: same-day delivery.
 */
final class PocztexTodayCountryMethod extends AbstractMethod {

	protected const SERVICE = ServiceRegistry::POCZTEX_TODAY_COUNTRY;

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'Pocztex Today (country)', 'poczta-polska-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Pocztex "Na dziś": same-day courier delivery in the country.', 'poczta-polska-for-woocommerce' );
	}
}
