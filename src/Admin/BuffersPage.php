<?php
/**
 * Buffers screen.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\En\Dictionary;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\Shipments\EnvelopeStatus;
use PocztaPolska\WooCommerce\Shipments\Shipment;
use PocztaPolska\WooCommerce\Shipments\ShipmentException;
use PocztaPolska\WooCommerce\Shipments\ShipmentRepository;
use PocztaPolska\WooCommerce\Shipments\ShipmentService;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * "Poczta Polska → Buffers": buffers created by the plugin, their shipments and the courier order.
 */
final class BuffersPage {

	public const SLUG = 'ppwc-buffers';

	private const ACTION = 'ppwc_buffers';

	/**
	 * Transient with the message of the last operation of a user.
	 */
	private const NOTICE = 'ppwc_buffers_notice_';

	/**
	 * Courier pickup hour ranges accepted by EN (zamowKuriera).
	 *
	 * @var list<string>
	 */
	private const COURIER_HOURS = array( '08:00-20:00', '08:00-10:00', '10:00-12:00', '12:00-14:00', '14:00-16:00', '16:00-18:00', '18:00-20:00' );

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Loads the script on the buffers screen.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! str_ends_with( $hook_suffix, '_page_' . self::SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'ppwc-admin-buffers', PPWC_PLUGIN_URL . 'assets/css/admin-buffers.css', array(), PPWC_VERSION );
		wp_enqueue_script( 'ppwc-admin-buffers', PPWC_PLUGIN_URL . 'assets/js/admin-buffers.js', array(), PPWC_VERSION, array( 'in_footer' => true ) );
	}

	/**
	 * Adds the submenu page.
	 */
	public function add_page(): void {
		add_submenu_page(
			SettingsPage::MENU_SLUG,
			__( 'Poczta Polska buffers', 'poczta-polska-for-woocommerce' ),
			__( 'Buffers', 'poczta-polska-for-woocommerce' ),
			SettingsPage::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Returns the screen URL.
	 *
	 * @param array<string, string|int> $args Query arguments.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			return;
		}

		$service    = new ShipmentService();
		$repository = new ShipmentRepository();
		$forgotten  = $service->sync_buffers( true );
		$counts     = $repository->count_by_buffer( Settings::environment() );
		$offices    = Dictionary::posting_offices();
		$cards      = Dictionary::cards();
		$today      = (string) wp_date( 'Y-m-d' );
		$buffers    = array();

		foreach ( $service->buffers()->all() as $buffer ) {
			$shipments = array();

			foreach ( $repository->for_buffer( Settings::environment(), $buffer->id ) as $shipment ) {
				$order       = wc_get_order( $shipment->order_id );
				$shipments[] = array(
					'id'        => $shipment->id,
					'order'     => $order instanceof \WC_Order ? $order->get_order_number() : (string) $shipment->order_id,
					'order_url' => $order instanceof \WC_Order ? $order->get_edit_order_url() : '',
					'recipient' => $order instanceof \WC_Order ? trim( $order->get_formatted_shipping_full_name() ) : '',
					'service'   => OrderShipmentsBox::service_label( $shipment->service ),
					'tracking'  => implode( ', ', $shipment->tracking_numbers() ),
					'status'    => OrderShipmentsBox::status_label( $shipment ),
				);
			}

			$buffers[] = array(
				'id'        => $buffer->id,
				'date'      => $buffer->date,
				'past'      => $buffer->date < $today,
				'office'    => isset( $offices[ $buffer->office_id ] ) ? $offices[ $buffer->office_id ]['print_name'] : (string) $buffer->office_id,
				'card'      => isset( $cards[ $buffer->card_id ] ) ? $buffer->card_id . ' — ' . $cards[ $buffer->card_id ]['description'] : (string) $buffer->card_id,
				'card_id'   => $buffer->card_id,
				'office_id' => $buffer->office_id,
				'count'     => $counts[ $buffer->id ] ?? 0,
				'shipments' => $shipments,
			);
		}

		$envelopes = $repository->envelopes( Settings::environment() );
		$service->refresh_envelope_statuses( array_slice( array_column( $envelopes, 'envelope_id' ), 0, 10 ) );
		$statuses = EnvelopeStatus::all( Settings::environment() );
		$sent     = array();

		foreach ( $envelopes as $envelope ) {
			$status = $statuses[ $envelope['envelope_id'] ]['status'] ?? '';
			$sent[] = array(
				'id'        => $envelope['envelope_id'],
				'sent_at'   => get_date_from_gmt( $envelope['sent_at'], (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
				'office'    => isset( $offices[ $envelope['office_id'] ] ) ? $offices[ $envelope['office_id'] ]['print_name'] : (string) $envelope['office_id'],
				'shipments' => $envelope['shipments'],
				'orders'    => array_map(
					static function ( int $order_id ): array {
						$order = wc_get_order( $order_id );

						return array(
							'number' => $order instanceof \WC_Order ? $order->get_order_number() : (string) $order_id,
							'url'    => $order instanceof \WC_Order ? $order->get_edit_order_url() : '',
						);
					},
					array_slice( $envelope['orders'], 0, 10 )
				),
				'status'    => EnvelopeStatus::label( $status ),
				'error'     => EnvelopeStatus::is_error( $status ),
			);
		}

		// The message is kept per user, not in the URL, so a link cannot show its own text on this screen.
		$stored = get_transient( self::NOTICE . get_current_user_id() );
		$notice = is_array( $stored ) ? (string) ( $stored['message'] ?? '' ) : '';
		$type   = is_array( $stored ) && 'error' === ( $stored['type'] ?? '' ) ? 'error' : 'success';

		delete_transient( self::NOTICE . get_current_user_id() );

		Template::view(
			'admin/buffers-page.php',
			array(
				'title'         => get_admin_page_title(),
				'env_label'     => Settings::environment_labels()[ Settings::environment() ],
				'buffers'       => $buffers,
				'action'        => self::ACTION,
				'notice'        => $notice,
				'notice_type'   => $type,
				'sent'          => $sent,
				'forgotten'     => $forgotten,
				'courier_hours' => self::COURIER_HOURS,
				'courier_date'  => (string) wp_date( 'Y-m-d', time() + ( (int) wp_date( 'G' ) >= 14 ? DAY_IN_SECONDS : 0 ) ),
				'today'         => $today,
			)
		);
	}

	/**
	 * Handles the buffer and courier forms (admin-post).
	 */
	public function handle(): void {
		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'poczta-polska-for-woocommerce' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$buffer_id = isset( $_POST['buffer_id'] ) ? absint( wp_unslash( $_POST['buffer_id'] ) ) : 0;
		$ids       = isset( $_POST['shipments'] ) && is_array( $_POST['shipments'] ) ? array_map( 'absint', wp_unslash( $_POST['shipments'] ) ) : array();
		$service   = new ShipmentService();

		try {
			$message = $this->run( $service, $operation, $buffer_id, $ids );
		} catch ( ShipmentException $exception ) {
			$this->back( html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ), 'error' );
		}

		$this->back( $message, 'success' );
	}

	/**
	 * Runs an operation and returns the message for the shop manager.
	 *
	 * @param ShipmentService $service   Shipment service.
	 * @param string          $operation Operation.
	 * @param int             $buffer_id Buffer ID.
	 * @param int[]           $ids       Selected shipment IDs.
	 * @throws ShipmentException When the operation cannot be done.
	 */
	private function run( ShipmentService $service, string $operation, int $buffer_id, array $ids ): string {
		$selected = array_values(
			array_filter(
				( new ShipmentRepository() )->find_many( $ids ),
				static fn( Shipment $shipment ): bool => Settings::environment() === $shipment->environment
			)
		);

		switch ( $operation ) {
			case 'labels':
				wp_safe_redirect( OrderShipmentsBox::labels_url( array_map( static fn( Shipment $shipment ): int => $shipment->id, $selected ) ) );
				exit;

			case 'delete_shipments':
				foreach ( $selected as $shipment ) {
					$service->delete( $shipment );
				}

				/* translators: %d: number of shipments. */
				return sprintf( _n( '%d shipment deleted.', '%d shipments deleted.', count( $selected ), 'poczta-polska-for-woocommerce' ), count( $selected ) );

			case 'move':
				$target = isset( $_POST['target'] ) ? absint( wp_unslash( $_POST['target'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in handle().
				$errors = $service->move( $selected, $target );

				if ( array() !== $errors ) {
					throw new ShipmentException( esc_html( implode( ' ', $errors ) ) );
				}

				/* translators: %d: number of shipments. */
				return sprintf( _n( '%d shipment moved.', '%d shipments moved.', count( $selected ), 'poczta-polska-for-woocommerce' ), count( $selected ) );

			case 'today':
				$renumbered = $service->move_buffer_to_today( $buffer_id );

				return 0 === $renumbered
					? __( 'The buffer date was changed to today.', 'poczta-polska-for-woocommerce' )
					/* translators: %d: number of shipments. */
					: sprintf( _n( 'The buffer date was changed to today. %d shipment got a new tracking number, print its label again.', 'The buffer date was changed to today. %d shipments got new tracking numbers, print their labels again.', $renumbered, 'poczta-polska-for-woocommerce' ), $renumbered );

			case 'send':
				$sent = $service->send_buffer( $buffer_id );

				/* translators: 1: envelope ID, 2: envelope status. */
				return sprintf( __( 'The buffer was sent to the post office (envelope %1$d, status %2$s).', 'poczta-polska-for-woocommerce' ), $sent['envelope_id'], $sent['status'] );

			case 'refresh_envelopes':
				$repository = new ShipmentRepository();
				$errors     = $service->refresh_envelope_statuses( array_column( $repository->envelopes( Settings::environment() ), 'envelope_id' ), true );

				if ( array() !== $errors ) {
					throw new ShipmentException( esc_html( implode( ' ', $errors ) ) );
				}

				return __( 'The envelope statuses were refreshed.', 'poczta-polska-for-woocommerce' );

			case 'delete_buffer':
				$service->delete_buffer( $buffer_id );

				return __( 'The buffer was deleted with its shipments.', 'poczta-polska-for-woocommerce' );

			case 'courier':
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in handle().
				$date   = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
				$hours  = isset( $_POST['hours'] ) ? sanitize_text_field( wp_unslash( $_POST['hours'] ) ) : '';
				$count  = isset( $_POST['count'] ) ? absint( wp_unslash( $_POST['count'] ) ) : 1;
				$weight = isset( $_POST['weight'] ) ? absint( wp_unslash( $_POST['weight'] ) ) : 1;
				// phpcs:enable

				if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date < (string) wp_date( 'Y-m-d' ) || ! in_array( $hours, self::COURIER_HOURS, true ) ) {
					throw new ShipmentException( esc_html__( 'Choose a pickup date from today on and an hour range.', 'poczta-polska-for-woocommerce' ) );
				}

				$service->order_courier( $date, $hours, $count, $weight * 1000 );

				/* translators: 1: date, 2: hour range. */
				return sprintf( __( 'The courier was ordered for %1$s, %2$s. Elektroniczny Nadawca sends the confirmation by email.', 'poczta-polska-for-woocommerce' ), $date, $hours );
		}

		throw new ShipmentException( esc_html__( 'Unknown operation.', 'poczta-polska-for-woocommerce' ) );
	}

	/**
	 * Redirects back to the screen with a message.
	 *
	 * @param string $message Message.
	 * @param string $type    success or error.
	 * @return never
	 */
	private function back( string $message, string $type ) {
		set_transient(
			self::NOTICE . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
			),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( self::url() );
		exit;
	}
}
