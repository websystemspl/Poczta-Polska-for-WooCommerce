<?php
/**
 * Tracking number column in the orders list.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * "Poczta Polska" column with the tracking numbers in the HPOS and the legacy orders list.
 * The shipments of the whole page are loaded with one query.
 */
final class OrderTrackingColumn {

	private const COLUMN = 'ppwc_tracking';

	private const HPOS_SCREEN = 'woocommerce_page_wc-orders';

	/**
	 * Order IDs of the current list page, collected before the rows are rendered.
	 *
	 * @var list<int>
	 */
	private array $order_ids = array();

	/**
	 * Shipments of the current page by order ID, null until loaded.
	 *
	 * @var array<int, list<Shipment>>|null
	 */
	private ?array $shipments = null;

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_filter( 'manage_' . self::HPOS_SCREEN . '_columns', array( $this, 'add_column' ) );
		add_action( 'manage_' . self::HPOS_SCREEN . '_custom_column', array( $this, 'render' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render' ), 10, 2 );
		add_action( 'current_screen', array( $this, 'watch_order_query' ) );
	}

	/**
	 * On the HPOS orders screen, remembers the IDs returned by the list query.
	 *
	 * @param \WP_Screen $screen Current screen.
	 */
	public function watch_order_query( $screen ): void {
		if ( $screen instanceof \WP_Screen && self::HPOS_SCREEN === $screen->id ) {
			add_filter( 'woocommerce_order_query', array( $this, 'collect_order_ids' ) );
		}
	}

	/**
	 * Collects order IDs from a wc_get_orders() result.
	 *
	 * @param mixed $results Orders, order IDs or a paginated result object.
	 * @return mixed
	 */
	public function collect_order_ids( $results ) {
		$orders = is_object( $results ) && isset( $results->orders ) ? $results->orders : $results;

		foreach ( is_array( $orders ) ? $orders : array() as $order ) {
			$id = $order instanceof \WC_Abstract_Order ? $order->get_id() : absint( $order );

			if ( $id > 0 ) {
				$this->order_ids[] = $id;
			}
		}

		return $results;
	}

	/**
	 * Adds the column after the order status.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_column( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		$result  = array();

		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;

			if ( 'order_status' === $key ) {
				$result[ self::COLUMN ] = __( 'Poczta Polska', 'poczta-polska-for-woocommerce' );
			}
		}

		if ( ! isset( $result[ self::COLUMN ] ) ) {
			$result[ self::COLUMN ] = __( 'Poczta Polska', 'poczta-polska-for-woocommerce' );
		}

		return $result;
	}

	/**
	 * Renders the column.
	 *
	 * @param string              $column        Column key.
	 * @param int|\WC_Order|mixed $order_or_post Order (HPOS) or post ID (legacy).
	 */
	public function render( $column, $order_or_post ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$order_id  = $order_or_post instanceof \WC_Abstract_Order ? $order_or_post->get_id() : absint( $order_or_post );
		$shipments = array_values(
			array_filter(
				$this->shipments( $order_id ),
				static fn( Shipment $shipment ): bool => Settings::environment() === $shipment->environment && in_array( $shipment->status, array( Shipment::STATUS_CREATED, Shipment::STATUS_SENT ), true )
			)
		);
		$failed    = array_filter( $this->shipments( $order_id ), static fn( Shipment $shipment ): bool => Shipment::STATUS_FAILED === $shipment->status && Settings::environment() === $shipment->environment );
		$rows      = array();

		foreach ( $shipments as $shipment ) {
			$rows[] = array(
				'tracking' => $shipment->tracking_numbers(),
				'status'   => OrderShipmentsBox::status_label( $shipment ),
			);
		}

		Template::view(
			'admin/order-tracking-column.php',
			array(
				'rows'   => $rows,
				'failed' => array() !== $failed && array() === $rows,
			)
		);
	}

	/**
	 * Returns the shipments of an order, loading the whole page at the first call.
	 *
	 * @param int $order_id Order ID.
	 * @return list<Shipment>
	 */
	private function shipments( int $order_id ): array {
		if ( null === $this->shipments || ! array_key_exists( $order_id, $this->shipments ) ) {
			$ids = null === $this->shipments ? $this->order_ids : array();

			// Legacy screen: the rows come from the main query.
			if ( null === $this->shipments && array() === $ids && isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof \WP_Query ) {
				foreach ( (array) $GLOBALS['wp_query']->posts as $post ) {
					$ids[] = $post instanceof \WP_Post ? $post->ID : absint( $post );
				}
			}

			$ids[] = $order_id;
			$found = ( new ShipmentRepository() )->for_orders( $ids );

			// Orders without shipments are remembered too, so they are not queried again.
			$this->shipments = ( $this->shipments ?? array() ) + $found + array_fill_keys( $ids, array() );
		}

		return $this->shipments[ $order_id ] ?? array();
	}
}
