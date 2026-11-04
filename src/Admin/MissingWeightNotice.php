<?php
/**
 * Warning about products without a weight.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Warns that products without a weight hide the eCommerce letter method.
 *
 * Letters are limited to 2000 g, so the plugin cannot offer them when a product
 * weight is unknown. Pocztex uses the default weight from the settings instead.
 */
final class MissingWeightNotice {

	private const TRANSIENT = 'ppwc_products_without_weight';

	/**
	 * Custom product query variable handled by the products data store filter.
	 */
	private const QUERY_VAR = 'ppwc_without_weight';

	/**
	 * How many products the notice names.
	 */
	private const SHOWN = 5;

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'query_without_weight' ), 10, 2 );
		add_action( 'woocommerce_update_product', array( $this, 'flush' ) );
		add_action( 'woocommerce_new_product', array( $this, 'flush' ) );
		add_action( 'woocommerce_update_product_variation', array( $this, 'flush' ) );
	}

	/**
	 * Clears the cached product list.
	 */
	public function flush(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Adds the "no weight" condition to wc_get_products() when the custom variable is set.
	 *
	 * @param array<string, mixed> $query      WP_Query arguments.
	 * @param array<string, mixed> $query_vars wc_get_products() arguments.
	 * @return array<string, mixed>
	 */
	public function query_without_weight( array $query, array $query_vars ): array {
		if ( empty( $query_vars[ self::QUERY_VAR ] ) ) {
			return $query;
		}

		$meta_query   = isset( $query['meta_query'] ) && is_array( $query['meta_query'] ) ? $query['meta_query'] : array();
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_weight',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'   => '_weight',
				'value' => '',
			),
		);

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin notice, cached for an hour.
		$query['meta_query'] = $meta_query;

		return $query;
	}

	/**
	 * Shows the warning on the shipping settings and the plugin settings screens.
	 */
	public function notice(): void {
		if ( ! current_user_can( SettingsPage::CAPABILITY ) || ! $this->on_relevant_screen() || ! $this->letter_method_enabled() ) {
			return;
		}

		$products = $this->products_without_weight();

		if ( array() === $products['ids'] ) {
			return;
		}

		$links = array();

		foreach ( array_slice( $products['ids'], 0, self::SHOWN ) as $id ) {
			$product = wc_get_product( $id );

			if ( $product instanceof \WC_Product ) {
				$links[] = array(
					'url'  => (string) get_edit_post_link( $product->get_parent_id() > 0 ? $product->get_parent_id() : $id, 'raw' ),
					'text' => $product->get_name(),
				);
			}
		}

		Template::view(
			'admin/notice.php',
			array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %d: number of products. */
					_n(
						'%d product has no weight. The eCommerce parcel is not offered for carts with this product, and Pocztex uses the default weight from the Poczta Polska settings.',
						'%d products have no weight. The eCommerce parcel is not offered for carts with these products, and Pocztex uses the default weight from the Poczta Polska settings.',
						$products['total'],
						'poczta-polska-for-woocommerce'
					),
					$products['total']
				),
				'links'   => $links,
			)
		);
	}

	/**
	 * Returns the IDs of up to SHOWN shippable products without a weight and their total count.
	 *
	 * @return array{ids: list<int>, total: int}
	 */
	private function products_without_weight(): array {
		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['ids'], $cached['total'] ) && is_array( $cached['ids'] ) ) {
			return array(
				'ids'   => array_values( array_map( 'intval', $cached['ids'] ) ),
				'total' => (int) $cached['total'],
			);
		}

		$result = wc_get_products(
			array(
				'status'        => 'publish',
				'type'          => array( 'simple', 'variation' ),
				'virtual'       => false,
				'downloadable'  => false,
				'limit'         => self::SHOWN,
				'return'        => 'ids',
				'paginate'      => true,
				self::QUERY_VAR => true,
			)
		);

		$data = array(
			'ids'   => is_object( $result ) && isset( $result->products ) && is_array( $result->products ) ? array_values( array_map( 'intval', $result->products ) ) : array(),
			'total' => is_object( $result ) && isset( $result->total ) ? (int) $result->total : 0,
		);

		set_transient( self::TRANSIENT, $data, HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Whether any shipping zone has an enabled eCommerce letter method.
	 */
	private function letter_method_enabled(): bool {
		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = array( 'shipping_methods' => ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods( true ) );

		foreach ( $zones as $zone ) {
			foreach ( (array) ( $zone['shipping_methods'] ?? array() ) as $method ) {
				if ( $method instanceof \WC_Shipping_Method && ServiceRegistry::ECOMMERCE === $method->id && $method->is_enabled() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the current screen is the WooCommerce shipping settings or the plugin settings.
	 */
	private function on_relevant_screen(): bool {
		$screen = get_current_screen();

		if ( null === $screen ) {
			return false;
		}

		if ( 'toplevel_page_' . SettingsPage::MENU_SLUG === $screen->id ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides where to show a notice.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return 'woocommerce_page_wc-settings' === $screen->id && 'shipping' === $tab;
	}
}
