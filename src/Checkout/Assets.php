<?php
/**
 * Checkout scripts and styles.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Checkout;

use PocztaPolska\WooCommerce\En\Dto\PickupPoint;
use PocztaPolska\WooCommerce\Rest\PointsController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the pickup point map assets and loads them on the checkout page only.
 */
final class Assets {

	/**
	 * Poczta Polska map widget. It must be loaded from Poczta Polska, not copied
	 * (integration manual of the widget). Declared in readme.txt, "External services".
	 */
	public const WIDGET_URL = 'https://mapa.ecommerce.poczta-polska.pl/widget/scripts/ppwidget.js';

	public const HANDLE_WIDGET = 'ppwc-ppwidget';

	public const HANDLE_MAP = 'ppwc-pickup-map';

	public const HANDLE_STYLE = 'ppwc-checkout';

	private const HANDLE_CLASSIC = 'ppwc-checkout-classic';

	/**
	 * Widget point types for each service point type.
	 *
	 * The current widget (map-widget-v016) groups points as PUNKTY and AUTOMATY, the same
	 * filters it shows to customers. With these types it also applies payOnPickup and
	 * payOnPickupMax. The per-network types from the 2024 manual are sent as "oldTypes",
	 * and "EURO SKLEP" or "DUZY BEN" make the widget API fail (checked 2026-11-02).
	 *
	 * @var array<string, list<string>>
	 */
	private const WIDGET_TYPES = array(
		PickupPoint::TYPE_POINT  => array( 'PUNKTY' ),
		PickupPoint::TYPE_LOCKER => array( 'AUTOMATY' ),
	);

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_classic' ) );
	}

	/**
	 * Registers the shared map scripts and the style.
	 */
	public static function register_assets(): void {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External script versioned by Poczta Polska.
		wp_register_script( self::HANDLE_WIDGET, self::WIDGET_URL, array(), null, array( 'in_footer' => true ) );
		wp_register_script( self::HANDLE_MAP, PPWC_PLUGIN_URL . 'assets/js/pickup-map.js', array( self::HANDLE_WIDGET ), PPWC_VERSION, array( 'in_footer' => true ) );
		wp_add_inline_script( self::HANDLE_MAP, 'window.ppwcPickupMapConfig = ' . wp_json_encode( self::map_config() ) . ';', 'before' );
		wp_register_style( self::HANDLE_STYLE, PPWC_PLUGIN_URL . 'assets/css/checkout.css', array(), PPWC_VERSION );
	}

	/**
	 * Loads the classic checkout script on a checkout page without the Checkout block.
	 */
	public function enqueue_classic(): void {
		if ( ! is_checkout() || is_wc_endpoint_url() || has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		wp_enqueue_script( self::HANDLE_CLASSIC, PPWC_PLUGIN_URL . 'assets/js/checkout-classic.js', array( 'jquery', 'wc-checkout', self::HANDLE_MAP ), PPWC_VERSION, array( 'in_footer' => true ) );
		wp_enqueue_style( self::HANDLE_STYLE );
	}

	/**
	 * Configuration of the map script.
	 *
	 * @return array<string, mixed>
	 */
	private static function map_config(): array {
		return array(
			'widgetTypes' => self::WIDGET_TYPES,
			'restUrl'     => rest_url( PointsController::NAMESPACE . '/points' ),
			'i18n'        => array(
				'fallbackTitle' => __( 'Find a pickup point', 'poczta-polska-for-woocommerce' ),
				'fallbackIntro' => __( 'The Poczta Polska map is not available. Search for a point by postcode, city or street.', 'poczta-polska-for-woocommerce' ),
				'fallbackLabel' => __( 'Postcode, city or street', 'poczta-polska-for-woocommerce' ),
				'search'        => __( 'Search', 'poczta-polska-for-woocommerce' ),
				'searching'     => __( 'Searching…', 'poczta-polska-for-woocommerce' ),
				'noResults'     => __( 'No points found. Try another postcode or city.', 'poczta-polska-for-woocommerce' ),
				'error'         => __( 'The search failed. Please try again.', 'poczta-polska-for-woocommerce' ),
				'close'         => __( 'Close', 'poczta-polska-for-woocommerce' ),
			),
		);
	}
}
