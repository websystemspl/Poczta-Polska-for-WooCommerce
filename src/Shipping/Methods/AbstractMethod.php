<?php
/**
 * Base class of the Poczta Polska shipping methods.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipping\Methods;

use PocztaPolska\WooCommerce\En\Dictionary;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\Shipping\Availability;
use PocztaPolska\WooCommerce\Shipping\PackageWeight;
use PocztaPolska\WooCommerce\Shipping\Service;
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * A Poczta Polska service configured in a WooCommerce shipping zone.
 *
 * Each instance has its own title and price, so a store can offer any number
 * of variants of the same service (OPZ II.1.3.b, II.1.3.e).
 */
abstract class AbstractMethod extends \WC_Shipping_Method {

	/**
	 * Service ID from ServiceRegistry, set by subclasses.
	 */
	protected const SERVICE = '';

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = static::SERVICE;
		$this->method_title       = $this->default_title();
		$this->method_description = $this->description();
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );

		$this->init();
	}

	/**
	 * Default title shown at checkout.
	 */
	abstract protected function default_title(): string;

	/**
	 * Description in the shipping zone screen.
	 */
	abstract protected function description(): string;

	/**
	 * Loads the instance settings.
	 */
	private function init(): void {
		$this->instance_form_fields = $this->form_fields();
		$this->title                = (string) $this->get_option( 'title', $this->default_title() );
		$this->tax_status           = (string) $this->get_option( 'tax_status', 'taxable' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'save_instance_settings' ) );
	}

	/**
	 * Saves the instance settings (process_admin_options() returns a value, actions must not).
	 */
	public function save_instance_settings(): void {
		$this->process_admin_options();
	}

	/**
	 * Returns the service of this method.
	 *
	 * @throws \LogicException When the service is not registered.
	 */
	public function service(): Service {
		$service = ServiceRegistry::get( static::SERVICE );

		if ( null === $service ) {
			throw new \LogicException( 'Unknown Poczta Polska service: ' . esc_html( static::SERVICE ) );
		}

		return $service;
	}

	/**
	 * Instance settings fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function form_fields(): array {
		$unit    = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		$service = $this->service();
		$fields  = array(
			'title'      => array(
				'title'       => __( 'Title', 'poczta-polska-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers at checkout.', 'poczta-polska-for-woocommerce' ),
				'default'     => $this->default_title(),
				'desc_tip'    => true,
			),
			'cost'       => array(
				'title'       => __( 'Price', 'poczta-polska-for-woocommerce' ),
				'type'        => 'price',
				'placeholder' => wc_format_localized_price( '0' ),
				'default'     => '',
			),
			'tax_status' => array(
				'title'   => __( 'Tax status', 'poczta-polska-for-woocommerce' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'poczta-polska-for-woocommerce' ),
					'none'    => _x( 'None', 'Tax status', 'poczta-polska-for-woocommerce' ),
				),
			),
			'free_from'  => array(
				'title'       => __( 'Free shipping from', 'poczta-polska-for-woocommerce' ),
				'type'        => 'price',
				'description' => __( 'Order value after discounts from which this method is free. Leave empty to always charge.', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'max_weight' => array(
				'title'       => sprintf(
					/* translators: %s: store weight unit, e.g. kg. */
					__( 'Maximum weight (%s)', 'poczta-polska-for-woocommerce' ),
					$unit
				),
				'type'        => 'decimal',
				'description' => sprintf(
					/* translators: %s: weight limit of the service, e.g. 2 kg. */
					__( 'The method is hidden for heavier orders. Leave empty to use the service limit (%s).', 'poczta-polska-for-woocommerce' ),
					wc_format_weight( (float) wc_get_weight( $service->max_weight, $unit, 'g' ) )
				),
				'default'     => '',
				'desc_tip'    => true,
			),
		);

		$cards = array( '' => __( 'Default card from the plugin settings', 'poczta-polska-for-woocommerce' ) );

		foreach ( array_keys( Settings::EN_ENDPOINTS ) as $environment ) {
			$options = $cards;

			// WooCommerce builds these fields whenever it reads a setting, also while calculating
			// shipping at checkout. Cards come from EN, so they are loaded on admin screens only,
			// and only for the active environment. Elsewhere the saved value is kept as the choice.
			if ( is_admin() && Settings::environment() === $environment ) {
				foreach ( Dictionary::cards( $environment ) as $id => $card ) {
					/* translators: 1: card number, 2: card description. */
					$options[ (string) $id ] = sprintf( __( '%1$d — %2$s', 'poczta-polska-for-woocommerce' ), $id, $card['description'] );
				}
			}

			$saved = (string) $this->get_option( 'card_id_' . $environment, '' );

			if ( '' !== $saved && ! isset( $options[ $saved ] ) ) {
				$options[ $saved ] = $saved;
			}

			$fields[ 'card_id_' . $environment ] = array(
				'title'   => Settings::ENV_PRODUCTION === $environment
					? __( 'Contract card (production)', 'poczta-polska-for-woocommerce' )
					: __( 'Contract card (test)', 'poczta-polska-for-woocommerce' ),
				'type'    => 'select',
				'default' => '',
				'options' => $options,
			);
		}

		$fields['format'] = array(
			'title'   => $service->requires_weight ? __( 'Size', 'poczta-polska-for-woocommerce' ) : __( 'Parcel format', 'poczta-polska-for-woocommerce' ),
			'type'    => 'select',
			'default' => '',
			'options' => array( '' => __( 'Default from the plugin settings', 'poczta-polska-for-woocommerce' ) ) + array_combine( $service->formats, $service->formats ),
		);

		return $fields;
	}

	/**
	 * Adds the rate when the service can deliver the package.
	 *
	 * @param array<string, mixed> $package Package.
	 */
	public function calculate_shipping( $package = array() ): void {
		$destination = is_array( $package['destination'] ?? null ) ? $package['destination'] : array();
		$contents    = is_array( $package['contents'] ?? null ) ? $package['contents'] : array();
		$weight      = $this->package_weight( $contents );

		if ( ! Availability::is_available( $this->service(), (string) ( $destination['country'] ?? '' ), $weight, $this->max_weight_grams() ) ) {
			return;
		}

		$rate = array(
			'id'        => $this->get_rate_id(),
			'label'     => $this->title,
			'cost'      => $this->is_free( $contents ) ? 0 : (float) wc_format_decimal( (string) $this->get_option( 'cost', '0' ) ),
			'package'   => $package,
			'meta_data' => array(
				'ppwc_weight' => $weight->grams,
			),
		);

		if ( 'none' === $this->tax_status ) {
			$rate['taxes'] = false;
		}

		$this->add_rate( $rate );
	}

	/**
	 * Returns the contract card for an environment: the method card or the default one.
	 *
	 * @param string|null $environment Environment, the active one by default.
	 */
	public function card_id( ?string $environment = null ): string {
		$environment = $environment ?? Settings::environment();
		$card        = (string) $this->get_option( 'card_id_' . $environment, '' );

		return '' !== $card ? $card : Settings::get_for_env( 'card_id', $environment );
	}

	/**
	 * Returns the parcel format (Pocztex) or size (letters) for new shipments.
	 */
	public function format(): string {
		$format = (string) $this->get_option( 'format', '' );

		if ( '' !== $format && $this->service()->allows_format( $format ) ) {
			return $format;
		}

		$default = $this->service()->requires_weight ? Settings::get( 'ecommerce_size' ) : Settings::get( 'pocztex_format' );

		// The global default may not fit this service, e.g. XL for parcel lockers.
		$formats = $this->service()->formats;

		return $this->service()->allows_format( $default ) ? $default : (string) $formats[ array_key_last( $formats ) ];
	}

	/**
	 * Weight limit of this instance in grams, 0 when not set.
	 */
	private function max_weight_grams(): int {
		$max = (float) wc_format_decimal( (string) $this->get_option( 'max_weight', '' ) );

		return $max > 0 ? (int) floor( (float) wc_get_weight( $max, 'g' ) ) : 0;
	}

	/**
	 * Whether the order value reaches the free shipping threshold.
	 *
	 * @param array<mixed> $contents Package contents.
	 */
	private function is_free( array $contents ): bool {
		$threshold = (float) wc_format_decimal( (string) $this->get_option( 'free_from', '' ) );

		if ( $threshold <= 0 ) {
			return false;
		}

		$total = 0.0;

		foreach ( $contents as $item ) {
			if ( is_array( $item ) ) {
				$total += (float) ( $item['line_total'] ?? 0 );
				$total += 'incl' === get_option( 'woocommerce_tax_display_cart' ) ? (float) ( $item['line_tax'] ?? 0 ) : 0.0;
			}
		}

		return round( $total, wc_get_price_decimals() ) >= round( $threshold, wc_get_price_decimals() );
	}

	/**
	 * Calculates the package weight from the cart items.
	 *
	 * @param array<mixed> $contents Package contents.
	 */
	private function package_weight( array $contents ): PackageWeight {
		$items = array();

		foreach ( $contents as $item ) {
			$product = is_array( $item ) ? ( $item['data'] ?? null ) : null;

			if ( ! $product instanceof \WC_Product || ! $product->needs_shipping() ) {
				continue;
			}

			$items[] = array(
				'weight'   => (string) $product->get_weight(),
				'quantity' => (float) ( $item['quantity'] ?? 1 ),
			);
		}

		return PackageWeight::from_items( $items, (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
	}
}
