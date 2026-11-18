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
 * Pocztex "Na dziś" within the city: same-day delivery up to 40 km.
 */
final class PocztexTodayCityMethod extends AbstractMethod {

	protected const SERVICE = ServiceRegistry::POCZTEX_TODAY_CITY;

	/**
	 * {@inheritDoc}
	 */
	protected function default_title(): string {
		return __( 'Pocztex Today (city)', 'poczta-polska-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function form_fields(): array {
		$fields = parent::form_fields();

		$fields['distance'] = array(
			'title'             => __( 'Default distance (km)', 'poczta-polska-for-woocommerce' ),
			'type'              => 'number',
			'description'       => __( 'Distance from the posting place to the recipient proposed for each shipment, 1-40 km. Elektroniczny Nadawca requires it for same-day delivery within the city.', 'poczta-polska-for-woocommerce' ),
			'default'           => '',
			'desc_tip'          => true,
			'custom_attributes' => array(
				'min'  => 1,
				'max'  => 40,
				'step' => 1,
			),
		);

		return $fields;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Pocztex "Na dziś": same-day courier delivery within the city, up to 40 km from the posting place. The distance is entered for each shipment.', 'poczta-polska-for-woocommerce' );
	}
}
