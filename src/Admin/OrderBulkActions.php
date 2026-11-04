<?php
/**
 * Bulk actions in the orders list.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentException;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;
use PocztaPolska\WooCommerce\Shipments\ShipmentService;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * "Create shipments", "Print labels" and "Delete shipments" for many orders at once
 * (PP answer of 24.09.2026, no. 10), in the HPOS and the legacy orders list.
 */
final class OrderBulkActions {

	private const CREATE = 'ppwc_create_shipments';

	private const LABELS = 'ppwc_print_labels';

	private const DELETE = 'ppwc_delete_shipments';

	/**
	 * Transient with the result of the last bulk action of a user.
	 */
	private const RESULT = 'ppwc_bulk_result_';

	/**
	 * Maximum orders in one bulk action, so the request ends within the PHP time limit.
	 */
	private const LIMIT = 100;

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		foreach ( array( 'woocommerce_page_wc-orders', 'edit-shop_order' ) as $screen ) {
			add_filter( 'bulk_actions-' . $screen, array( $this, 'add_actions' ) );
			add_filter( 'handle_bulk_actions-' . $screen, array( $this, 'handle' ), 10, 3 );
		}

		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Adds the actions to the bulk actions menu.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public function add_actions( $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();

		$actions[ self::CREATE ] = __( 'Poczta Polska: create shipments', 'poczta-polska-for-woocommerce' );
		$actions[ self::LABELS ] = __( 'Poczta Polska: print labels (one PDF)', 'poczta-polska-for-woocommerce' );
		$actions[ self::DELETE ] = __( 'Poczta Polska: delete shipments', 'poczta-polska-for-woocommerce' );

		return $actions;
	}

	/**
	 * Runs an action. WordPress and WooCommerce check the bulk action nonce before this filter.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param int[]  $ids      Order IDs.
	 */
	public function handle( $redirect, $action, $ids ): string {
		$redirect = (string) $redirect;

		if ( ! in_array( $action, array( self::CREATE, self::LABELS, self::DELETE ), true ) ) {
			return $redirect;
		}

		$ids    = array_slice( array_values( array_filter( array_map( 'absint', (array) $ids ), static fn( int $id ): bool => current_user_can( 'edit_shop_order', $id ) ) ), 0, self::LIMIT );
		$result = array(
			'done'   => 0,
			'errors' => array(),
		);

		if ( self::LABELS === $action ) {
			$shipments = array();

			foreach ( $ids as $id ) {
				foreach ( ( new ShipmentRepository() )->for_order( $id ) as $shipment ) {
					if ( $shipment->is_in_buffer() && Settings::environment() === $shipment->environment ) {
						$shipments[] = $shipment->id;
					}
				}
			}

			if ( array() !== $shipments ) {
				// Redirect here: the legacy orders screen (edit.php) strips "action" from the returned URL.
				wp_safe_redirect( OrderShipmentsBox::labels_url( $shipments ) );
				exit;
			}

			$result['errors'][] = __( 'None of the selected orders has a shipment in a buffer.', 'poczta-polska-for-woocommerce' );
		} elseif ( self::CREATE === $action ) {
			$orders = array_values( array_filter( array_map( 'wc_get_order', $ids ), static fn( $order ): bool => $order instanceof \WC_Order ) );

			foreach ( ( new ShipmentService() )->create( $orders ) as $order_id => $created ) {
				if ( $created instanceof Shipment ) {
					++$result['done'];
				} else {
					/* translators: 1: order number, 2: error. */
					$result['errors'][] = sprintf( __( 'Order %1$d: %2$s', 'poczta-polska-for-woocommerce' ), $order_id, $created );
				}
			}
		} else {
			$service = new ShipmentService();

			foreach ( $ids as $id ) {
				foreach ( ( new ShipmentRepository() )->for_order( $id ) as $shipment ) {
					if ( ! $shipment->is_deletable() || Settings::environment() !== $shipment->environment ) {
						continue;
					}

					try {
						$service->delete( $shipment );
						++$result['done'];
					} catch ( ShipmentException $exception ) {
						/* translators: 1: order number, 2: error. */
						$result['errors'][] = sprintf( __( 'Order %1$d: %2$s', 'poczta-polska-for-woocommerce' ), $id, html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) );
					}
				}
			}
		}

		$result['action'] = $action;
		set_transient( self::RESULT . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

		return $redirect;
	}

	/**
	 * Shows the result of the last bulk action once.
	 */
	public function notice(): void {
		$key    = self::RESULT . get_current_user_id();
		$result = get_transient( $key );

		if ( ! is_array( $result ) ) {
			return;
		}

		delete_transient( $key );

		$done     = (int) ( $result['done'] ?? 0 );
		$errors   = array_map( 'strval', (array) ( $result['errors'] ?? array() ) );
		$messages = array(
			/* translators: %d: number of shipments. */
			self::CREATE => _n( 'Created %d shipment in Elektroniczny Nadawca.', 'Created %d shipments in Elektroniczny Nadawca.', $done, 'poczta-polska-for-woocommerce' ),
			/* translators: %d: number of shipments. */
			self::DELETE => _n( 'Deleted %d shipment.', 'Deleted %d shipments.', $done, 'poczta-polska-for-woocommerce' ),
			self::LABELS => '',
		);
		$message  = $messages[ (string) ( $result['action'] ?? '' ) ] ?? '';

		Template::view(
			'admin/bulk-result.php',
			array(
				'type'    => array() === $errors ? 'success' : ( $done > 0 ? 'warning' : 'error' ),
				'message' => '' !== $message ? sprintf( $message, $done ) : '',
				'errors'  => $errors,
			)
		);
	}
}
