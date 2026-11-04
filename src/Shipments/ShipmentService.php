<?php
/**
 * Shipment operations.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

use PocztaPolska\WooCommerce\AuditLog;
use PocztaPolska\WooCommerce\En\ClientFactory;
use PocztaPolska\WooCommerce\En\ClientInterface;
use PocztaPolska\WooCommerce\En\Exception\ConnectionException;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Logger;
use PocztaPolska\WooCommerce\Sender;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\Support\Lock;

defined( 'ABSPATH' ) || exit;

/**
 * Creates, changes and sends shipments in Elektroniczny Nadawca and keeps their state in the shop.
 *
 * Duplicates are avoided by storing the GUID before calling EN. When a call ends without an answer
 * (timeout), the shipment stays pending and the next operation checks in EN whether it exists.
 */
final class ShipmentService {

	/**
	 * Maximum shipments per addShipment call (EN).
	 */
	private const BATCH = 500;

	/**
	 * EN error: the buffer (zbiór) does not exist.
	 */
	private const BUFFER_NOT_FOUND = 96001;

	/**
	 * EN error of addShipment: the buffer (zbiór) does not exist.
	 */
	private const BUFFER_NOT_FOUND_ADD = 20603;

	/**
	 * Whether the buffers were compared with EN in this request.
	 *
	 * @var bool
	 */
	private static bool $buffers_synced = false;

	/**
	 * EN client.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $client;

	/**
	 * Repository.
	 *
	 * @var ShipmentRepository
	 */
	private ShipmentRepository $repository;

	/**
	 * Buffers.
	 *
	 * @var BufferService
	 */
	private BufferService $buffers;

	/**
	 * Environment.
	 *
	 * @var string
	 */
	private string $environment;

	/**
	 * Constructor.
	 *
	 * @param ClientInterface|null    $client      EN client, from the settings by default.
	 * @param ShipmentRepository|null $repository  Repository.
	 * @param string|null             $environment Environment, the active one by default.
	 */
	public function __construct( ?ClientInterface $client = null, ?ShipmentRepository $repository = null, ?string $environment = null ) {
		$this->environment = $environment ?? Settings::environment();
		$this->client      = $client ?? ClientFactory::create( $this->environment );
		$this->repository  = $repository ?? new ShipmentRepository();
		$this->buffers     = new BufferService( $this->client, $this->environment );
	}

	/**
	 * Returns the buffer service of the same environment.
	 */
	public function buffers(): BufferService {
		return $this->buffers;
	}

	/**
	 * Creates shipments for orders, in batches of up to 500 per buffer.
	 *
	 * @param \WC_Order[]                      $orders    Orders.
	 * @param array<int, array<string, mixed>> $overrides Changes per order ID (see DraftFactory::from_order()).
	 * @return array<int, Shipment|string> Order ID => created shipment or error message.
	 */
	public function create( array $orders, array $overrides = array() ): array {
		$results = array();
		$batches = array();
		$locked  = array();

		// A buffer deleted or sent outside the plugin must not receive new shipments.
		$this->sync_buffers();

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();

			try {
				$batches[ $this->reserve( $order, $overrides[ $order_id ] ?? array(), $locked ) ][] = $this->last_reserved;
			} catch ( ShipmentException $exception ) {
				$results[ $order_id ] = html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' );
			}
		}

		foreach ( $batches as $buffer_id => $items ) {
			foreach ( array_chunk( $items, self::BATCH ) as $chunk ) {
				$results = $this->send_batch( (int) $buffer_id, $chunk ) + $results;
			}
		}

		foreach ( $locked as $order_id ) {
			Lock::release( 'order_' . $order_id );
		}

		return $results;
	}

	/**
	 * The shipment reserved by the last reserve() call.
	 *
	 * @var array{id: int, order_id: int, draft: ShipmentDraft}
	 */
	private array $last_reserved;

	/**
	 * Validates an order, picks its buffer and stores the shipment as pending before EN is called.
	 *
	 * @param \WC_Order            $order     Order.
	 * @param array<string, mixed> $overrides Changes from the shop manager.
	 * @param int[]                $locked    Locked order IDs, the order is added after locking.
	 * @return int Buffer ID; the reserved shipment is in $last_reserved.
	 * @throws ShipmentException When the order cannot get a shipment.
	 */
	private function reserve( \WC_Order $order, array $overrides, array &$locked ): int {
		$order_id = $order->get_id();

		if ( ! Lock::acquire( 'order_' . $order_id ) ) {
			throw new ShipmentException( esc_html__( 'Another operation on this order is in progress. Try again in a moment.', 'poczta-polska-for-woocommerce' ) );
		}

		$locked[] = $order_id;

		foreach ( $this->repository->for_order( $order_id ) as $existing ) {
			if ( in_array( $existing->status, array( Shipment::STATUS_CREATED, Shipment::STATUS_PENDING ), true ) && empty( $overrides['additional'] ) ) {
				throw new ShipmentException( esc_html__( 'The order already has a shipment. Delete it first or create an additional one.', 'poczta-polska-for-woocommerce' ) );
			}
		}

		$draft    = DraftFactory::from_order( $order, $overrides );
		$problems = ShipmentBuilder::problems( $draft );

		if ( array() !== $problems ) {
			throw new ShipmentException( esc_html( implode( ' ', $problems ) ) );
		}

		$buffer = $this->buffers->buffer_for_card( DraftFactory::card_id( $order ), (int) ( $overrides['office_id'] ?? 0 ) );
		$id     = $this->repository->insert(
			array(
				'order_id'          => $order_id,
				'environment'       => $this->environment,
				'guid'              => $draft->guid,
				'method'            => $draft->service->id,
				'status'            => Shipment::STATUS_PENDING,
				'buffer_id'         => $buffer->id,
				'card_id'           => $buffer->card_id,
				'posting_office_id' => $buffer->office_id,
				'data'              => array( 'draft' => $draft->to_array() ),
			)
		);

		$this->last_reserved = array(
			'id'       => $id,
			'order_id' => $order_id,
			'draft'    => $draft,
		);

		return $buffer->id;
	}

	/**
	 * Checks a pending shipment in EN after a call ended without an answer.
	 *
	 * @param Shipment $shipment Shipment.
	 * @throws ShipmentException When EN cannot be reached.
	 */
	public function reconcile( Shipment $shipment ): Shipment {
		if ( Shipment::STATUS_PENDING !== $shipment->status ) {
			return $shipment;
		}

		$in_buffer = $this->en( fn(): array => $this->client->buffer_shipments( $shipment->buffer_id ) );

		if ( isset( $in_buffer[ $shipment->guid ] ) ) {
			$this->repository->update(
				$shipment->id,
				array(
					'status'          => Shipment::STATUS_CREATED,
					'tracking_number' => $in_buffer[ $shipment->guid ]['tracking'],
					'last_error'      => '',
				)
			);
		} else {
			$this->repository->update(
				$shipment->id,
				array(
					'status'     => Shipment::STATUS_FAILED,
					'last_error' => __( 'Elektroniczny Nadawca did not create the shipment. Create it again.', 'poczta-polska-for-woocommerce' ),
				)
			);
		}

		return $this->repository->find( $shipment->id ) ?? $shipment;
	}

	/**
	 * Deletes a shipment in EN and in the shop (OPZ II.2.1.a, deleting in EN, not only locally).
	 *
	 * @param Shipment $shipment Shipment.
	 * @throws ShipmentException When the shipment cannot be deleted.
	 */
	public function delete( Shipment $shipment ): void {
		if ( ! $shipment->is_deletable() ) {
			throw new ShipmentException( esc_html__( 'A sent shipment cannot be deleted.', 'poczta-polska-for-woocommerce' ) );
		}

		$this->with_lock(
			'order_' . $shipment->order_id,
			function () use ( $shipment ): void {
				if ( Shipment::STATUS_FAILED !== $shipment->status ) {
					$errors = $this->client->delete_shipments( $shipment->buffer_id, array( $shipment->guid ) );
					$error  = $errors[ $shipment->guid ] ?? ( $errors[''] ?? '' );

					// "Shipment does not exist" (50701) means it is already gone in EN.
					if ( '' !== $error && ! str_contains( $error, '50701' ) ) {
						throw new ShipmentException( esc_html( $error ) );
					}
				}

				$this->repository->delete( $shipment->id );

				AuditLog::record(
					'shipment_deleted',
					'shipment',
					(string) $shipment->id,
					array(
						'order'    => $shipment->order_id,
						'tracking' => $shipment->tracking_number,
					)
				);
			}
		);
	}

	/**
	 * Changes a shipment before the label is printed: deletes it in EN and creates it again
	 * (OPZ II.2.1.c-d). The new shipment gets a new tracking number.
	 *
	 * @param Shipment             $shipment  Shipment.
	 * @param array<string, mixed> $overrides Changes (see DraftFactory::from_order()).
	 * @return Shipment|string New shipment or error message.
	 * @throws ShipmentException When the shipment cannot be changed.
	 */
	public function recreate( Shipment $shipment, array $overrides ) {
		if ( ! $shipment->is_editable() && Shipment::STATUS_FAILED !== $shipment->status ) {
			throw new ShipmentException( esc_html__( 'The label was printed or the buffer was sent, so the shipment cannot be changed. You can only delete it.', 'poczta-polska-for-woocommerce' ) );
		}

		$order = wc_get_order( $shipment->order_id );

		if ( ! $order instanceof \WC_Order ) {
			throw new ShipmentException( esc_html__( 'The order does not exist.', 'poczta-polska-for-woocommerce' ) );
		}

		$this->delete( $shipment );

		return $this->create( array( $order ), array( $order->get_id() => $overrides ) )[ $order->get_id() ] ?? '';
	}

	/**
	 * Returns one PDF with the labels and locks the shipments against editing (OPZ II.2.1.e).
	 *
	 * @param Shipment[] $shipments Shipments.
	 * @throws ShipmentException When no shipment can be printed.
	 */
	public function labels( array $shipments ): string {
		$printable = array_values( array_filter( $shipments, static fn( Shipment $shipment ): bool => $shipment->is_in_buffer() ) );

		if ( array() === $printable ) {
			throw new ShipmentException( esc_html__( 'None of the shipments is in a buffer, there is no label to print.', 'poczta-polska-for-woocommerce' ) );
		}

		$files = $this->en( fn(): array => $this->client->labels( array_map( static fn( Shipment $shipment ): string => $shipment->guid, $printable ), true ) );

		foreach ( $printable as $shipment ) {
			if ( '' === $shipment->label_printed ) {
				$this->repository->update( $shipment->id, array( 'label_printed_at' => gmdate( 'Y-m-d H:i:s' ) ) );
			}
		}

		return $files[0];
	}

	/**
	 * Moves shipments to another buffer of the plugin. Tracking numbers stay the same.
	 *
	 * @param Shipment[] $shipments Shipments.
	 * @param int        $target    Target buffer ID.
	 * @return array<int, string> Errors by shipment ID.
	 * @throws ShipmentException When the target buffer is not a buffer of the plugin.
	 */
	public function move( array $shipments, int $target ): array {
		$to     = $this->buffers->get( $target );
		$errors = array();

		foreach ( $this->group_by_buffer( $shipments ) as $from => $group ) {
			if ( $from === $to->id ) {
				continue;
			}

			// The card and the posting office are part of the shipment (punktNadania), so only the date may change.
			$source = $this->buffers->get( $from );

			if ( $source->card_id !== $to->card_id || $source->office_id !== $to->office_id ) {
				foreach ( $group as $shipment ) {
					$errors[ $shipment->id ] = __( 'Shipments can be moved only to a buffer with the same contract card and posting office.', 'poczta-polska-for-woocommerce' );
				}
				continue;
			}

			$failed = $this->en( fn(): array => $this->client->move_shipments( $from, $to->id, array_map( static fn( Shipment $shipment ): string => $shipment->guid, $group ) ) );

			foreach ( $group as $shipment ) {
				if ( isset( $failed[ $shipment->guid ] ) ) {
					$errors[ $shipment->id ] = $failed[ $shipment->guid ];
					continue;
				}

				$this->repository->update(
					$shipment->id,
					array(
						'buffer_id'         => $to->id,
						'posting_office_id' => $to->office_id,
					)
				);
			}

			if ( isset( $failed[''] ) ) {
				$errors[0] = $failed[''];
			}
		}

		return $errors;
	}

	/**
	 * Moves the planned posting date of a buffer to today (EN FAQ 6: a buffer must be sent on the day
	 * its shipments were added). Shipments with a printed label get a new GUID and tracking number,
	 * the shop finds them by numerPrzesylkiKlienta; their labels must be printed again.
	 *
	 * @param int $buffer_id Buffer ID.
	 * @return int Number of shipments with a new tracking number.
	 */
	public function move_buffer_to_today( int $buffer_id ): int {
		$buffer = $this->buffers->get( $buffer_id );
		$today  = (string) wp_date( 'Y-m-d' );

		return $this->with_lock(
			'buffer_' . $buffer_id,
			function () use ( $buffer, $today ): int {
				// The buffer date may have been changed outside the plugin, so EN decides.
				$date = $buffer->date;

				foreach ( $this->client->buffers() as $remote ) {
					if ( $remote->id === $buffer->id ) {
						$date = $remote->date;
					}
				}

				if ( $date !== $today ) {
					$this->client->update_buffer( $buffer->with_date( $today ), true );
				}

				if ( $buffer->date !== $today ) {
					$this->buffers->remember( $buffer->with_date( $today ) );
				}

				$renumbered = $this->sync_buffer_numbers( $buffer->id );

				if ( $date !== $today || $renumbered > 0 ) {
					AuditLog::record(
						'buffer_date_changed',
						'buffer',
						(string) $buffer->id,
						array(
							'from'       => $date,
							'to'         => $today,
							'renumbered' => $renumbered,
						)
					);
				}

				return $renumbered;
			}
		);
	}

	/**
	 * Updates GUIDs and tracking numbers that EN changed in a buffer. Shipments with a printed label
	 * get new ones when the posting date changes; the shop finds them by numerPrzesylkiKlienta.
	 *
	 * @param int $buffer_id Buffer ID.
	 * @return int Number of shipments with a new tracking number.
	 * @throws EnException When EN cannot be reached.
	 */
	private function sync_buffer_numbers( int $buffer_id ): int {
		$by_number = array();

		foreach ( $this->client->buffer_shipments( $buffer_id ) as $guid => $shipment ) {
			$by_number[ $shipment['customer_number'] ] = array( 'guid' => $guid ) + $shipment;
		}

		$renumbered = 0;

		foreach ( $this->repository->for_buffer( $this->environment, $buffer_id ) as $shipment ) {
			$current = $by_number[ $shipment->customer_number() ] ?? null;

			if ( null === $current || $current['guid'] === $shipment->guid ) {
				continue;
			}

			$data  = $shipment->data;
			$draft = isset( $data['draft'] ) && is_array( $data['draft'] ) ? $data['draft'] : array();

			$draft['guid']      = $current['guid'];
			$draft['sub_guids'] = $current['sub_guids'];
			$data['draft']      = $draft;

			// EN does not return the new sub-parcel numbers before sending; they are on the new label.
			if ( array() !== $current['sub_guids'] ) {
				$data['sub_tracking']       = array();
				$data['sub_tracking_stale'] = true;
			}

			$this->repository->update(
				$shipment->id,
				array(
					'guid'             => $current['guid'],
					'tracking_number'  => $current['tracking'],
					// The old label is no longer valid: the shipment is editable until the new one is printed.
					'label_printed_at' => null,
					'data'             => $data,
				)
			);
			++$renumbered;
		}

		return $renumbered;
	}

	/**
	 * Sends a buffer to the posting office (OPZ II.2.1.f). EN deletes the buffer afterwards.
	 *
	 * @param int $buffer_id Buffer ID.
	 * @return array{envelope_id: int, status: string, renumbered: int}
	 */
	public function send_buffer( int $buffer_id ): array {
		$renumbered = $this->move_buffer_to_today( $buffer_id );
		$buffer     = $this->buffers->get( $buffer_id );

		return $this->with_lock(
			'buffer_' . $buffer_id,
			function () use ( $buffer, $renumbered ): array {
				$shipments = $this->repository->for_buffer( $this->environment, $buffer->id );

				foreach ( $shipments as $shipment ) {
					$this->reconcile( $shipment );
				}

				$sent = $this->client->send_buffer( $buffer->id, $buffer->office_id );

				foreach ( $this->repository->for_buffer( $this->environment, $buffer->id ) as $shipment ) {
					$this->repository->update(
						$shipment->id,
						array(
							'status'      => Shipment::STATUS_SENT,
							'envelope_id' => $sent['envelope_id'],
						)
					);
				}

				$this->buffers->forget( $buffer->id );
				EnvelopeStatus::store( $this->environment, $sent['envelope_id'], $sent['status'] );
				$this->sync_envelope_numbers( $sent['envelope_id'] );

				AuditLog::record(
					'buffer_sent',
					'buffer',
					(string) $buffer->id,
					array(
						'envelope'  => $sent['envelope_id'],
						'shipments' => count( $shipments ),
					)
				);

				return $sent + array( 'renumbered' => $renumbered );
			}
		);
	}

	/**
	 * Stores the final tracking numbers from a sent envelope. Only here EN returns the numbers of
	 * sub-parcels that got new ones after a buffer date change. The envelope is already sent, so a
	 * failure is only logged.
	 *
	 * @param int $envelope_id Envelope ID.
	 */
	private function sync_envelope_numbers( int $envelope_id ): void {
		try {
			$parcels = $this->client->envelope_content( $envelope_id );
		} catch ( EnException $exception ) {
			Logger::error(
				'Reading the sent envelope failed.',
				array(
					'envelope' => $envelope_id,
					'error'    => $exception->getMessage(),
				)
			);
			return;
		}

		foreach ( $this->repository->for_envelope( $this->environment, $envelope_id ) as $shipment ) {
			$main = $parcels[ $shipment->guid ]['tracking'] ?? '';
			$data = $shipment->data;
			$subs = array();

			$sub_guids = isset( $data['draft']['sub_guids'] ) && is_array( $data['draft']['sub_guids'] ) ? $data['draft']['sub_guids'] : array();

			foreach ( $sub_guids as $sub_guid ) {
				$subs[] = $parcels[ strtoupper( (string) $sub_guid ) ]['tracking'] ?? '';
			}

			if ( array() !== $sub_guids && ! in_array( '', $subs, true ) ) {
				$data['sub_tracking'] = $subs;
				unset( $data['sub_tracking_stale'] );
			}

			$this->repository->update(
				$shipment->id,
				array(
					'tracking_number' => '' !== $main ? $main : $shipment->tracking_number,
					'data'            => $data,
				)
			);
		}
	}

	/**
	 * Asks EN for the status of sent envelopes that may still change. Statuses checked within the
	 * last 15 minutes are reused unless $force is set.
	 *
	 * @param int[] $envelope_ids Envelope IDs.
	 * @param bool  $force        Ask EN even when the status is fresh.
	 * @return array<int, string> Envelope ID => error, for envelopes EN did not answer about.
	 */
	public function refresh_envelope_statuses( array $envelope_ids, bool $force = false ): array {
		$known  = EnvelopeStatus::all( $this->environment );
		$errors = array();

		foreach ( array_slice( array_values( array_unique( array_map( 'intval', $envelope_ids ) ) ), 0, 20 ) as $envelope_id ) {
			$current = $known[ $envelope_id ] ?? null;

			if ( null !== $current && ( EnvelopeStatus::is_final( $current['status'] ) || ( ! $force && $current['checked_at'] > time() - 15 * MINUTE_IN_SECONDS ) ) ) {
				continue;
			}

			try {
				EnvelopeStatus::store( $this->environment, $envelope_id, $this->client->envelope_status( $envelope_id ) );
			} catch ( EnException $exception ) {
				$errors[ $envelope_id ] = $exception->getMessage();
			}
		}

		return $errors;
	}

	/**
	 * Compares the buffers of the plugin with EN (OPZ: the account may be used outside the plugin).
	 * A buffer that EN no longer knows (deleted or sent in Elektroniczny Nadawca) is forgotten and
	 * its shipments are marked, so the shop manager checks them. Runs once per request unless forced.
	 *
	 * @param bool $force Compare again in this request.
	 * @return list<int> IDs of the forgotten buffers.
	 */
	public function sync_buffers( bool $force = false ): array {
		if ( self::$buffers_synced && ! $force ) {
			return array();
		}

		self::$buffers_synced = true;
		$local                = $this->buffers->all();

		if ( array() === $local ) {
			return array();
		}

		try {
			$remote = array();

			foreach ( $this->client->buffers() as $buffer ) {
				$remote[ $buffer->id ] = $buffer;
			}
		} catch ( EnException $exception ) {
			Logger::error( 'Reading the buffer list failed.', array( 'error' => $exception->getMessage() ) );
			return array();
		}

		$forgotten = array();

		foreach ( $local as $buffer ) {
			if ( isset( $remote[ $buffer->id ] ) ) {
				// The date may have been changed in Elektroniczny Nadawca.
				if ( '' !== $remote[ $buffer->id ]->date && $remote[ $buffer->id ]->date !== $buffer->date ) {
					$this->buffers->remember( $buffer->with_date( $remote[ $buffer->id ]->date ) );
				}
				continue;
			}

			// Missing from the list: confirm with the buffer itself before touching the shipments.
			if ( ! $this->buffer_is_gone( $buffer->id ) ) {
				continue;
			}

			$this->forget_missing_buffer( $buffer->id );
			$forgotten[] = $buffer->id;
		}

		return $forgotten;
	}

	/**
	 * Forgets a buffer that EN no longer knows and marks its shipments, so the shop manager checks
	 * them in Elektroniczny Nadawca (they may have been sent there) before creating them again.
	 *
	 * @param int $buffer_id Buffer ID.
	 */
	private function forget_missing_buffer( int $buffer_id ): void {
		foreach ( $this->repository->for_buffer( $this->environment, $buffer_id ) as $shipment ) {
			$this->repository->update(
				$shipment->id,
				array(
					'status'     => Shipment::STATUS_FAILED,
					'last_error' => __( 'The buffer no longer exists in Elektroniczny Nadawca (deleted or sent there). Check the shipment in Elektroniczny Nadawca before creating it again.', 'poczta-polska-for-woocommerce' ),
				)
			);
		}

		$this->buffers->forget( $buffer_id );
		AuditLog::record( 'buffer_missing', 'buffer', (string) $buffer_id );
	}

	/**
	 * Whether EN confirms that a buffer does not exist (error 96001). Any other answer, including a
	 * connection problem, keeps the buffer.
	 *
	 * @param int $buffer_id Buffer ID.
	 */
	private function buffer_is_gone( int $buffer_id ): bool {
		try {
			$this->client->buffer_shipments( $buffer_id );
		} catch ( EnException $exception ) {
			return self::BUFFER_NOT_FOUND === $exception->get_en_error_number();
		}

		return false;
	}

	/**
	 * Deletes a buffer of the plugin with all its shipments.
	 *
	 * @param int $buffer_id Buffer ID.
	 */
	public function delete_buffer( int $buffer_id ): void {
		$buffer = $this->buffers->get( $buffer_id );

		$this->with_lock(
			'buffer_' . $buffer_id,
			function () use ( $buffer ): void {
				$this->client->delete_buffer( $buffer->id );

				foreach ( $this->repository->for_buffer( $this->environment, $buffer->id ) as $shipment ) {
					$this->repository->delete( $shipment->id );
				}

				$this->buffers->forget( $buffer->id );
				AuditLog::record( 'buffer_deleted', 'buffer', (string) $buffer->id );
			}
		);
	}

	/**
	 * Orders a courier to collect parcels from the pickup address (OPZ II.2.3).
	 *
	 * @param string $date   Date, Y-m-d.
	 * @param string $hours  Hour range, e.g. 08:00-20:00.
	 * @param int    $count  Number of parcels.
	 * @param int    $weight Total weight in grams.
	 * @throws ShipmentException When the pickup address is incomplete.
	 */
	public function order_courier( string $date, string $hours, int $count, int $weight ): void {
		$problems = Sender::problems();

		if ( array() !== $problems ) {
			throw new ShipmentException( esc_html( implode( ' ', $problems ) ) );
		}

		$sender = Sender::data();

		$this->en(
			fn() => $this->client->order_courier(
				array(
					'address' => array_filter(
						array(
							'nazwa'       => $sender['name'],
							'nazwa2'      => $sender['name2'],
							'ulica'       => $sender['street'],
							'numerDomu'   => $sender['house_number'],
							'numerLokalu' => $sender['flat_number'],
							'miejscowosc' => $sender['city'],
							'kodPocztowy' => $sender['postcode'],
							'kraj'        => 'Polska',
							'mobile'      => $sender['mobile'],
							'telefon'     => $sender['phone'],
							'email'       => $sender['email'],
						),
						static fn( string $value ): bool => '' !== $value
					),
					'date'    => $date,
					'hours'   => $hours,
					'count'   => max( 1, $count ),
					'weight'  => max( 1, $weight ),
					'email'   => $sender['email'],
				)
			)
		);

		AuditLog::record(
			'courier_ordered',
			'courier',
			$date,
			array(
				'hours'  => $hours,
				'count'  => $count,
				'weight' => $weight,
			)
		);
	}

	/**
	 * Sends one batch of new shipments and stores the results.
	 *
	 * @param int                                                       $buffer_id Buffer ID.
	 * @param list<array{id: int, order_id: int, draft: ShipmentDraft}> $items     Reserved shipments.
	 * @return array<int, Shipment|string> Order ID => shipment or error.
	 */
	private function send_batch( int $buffer_id, array $items ): array {
		$results = array();

		try {
			$response = $this->client->add_shipments( $buffer_id, array_map( static fn( array $item ): array => ShipmentBuilder::build( $item['draft'] ), $items ) );
		} catch ( ConnectionException $exception ) {
			// The shipments may exist in EN: they stay pending and are checked before any other operation.
			Logger::error( 'addShipment ended without an answer, shipments stay pending.', array( 'buffer' => $buffer_id ) );

			foreach ( $items as $item ) {
				$this->repository->update( $item['id'], array( 'last_error' => html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) ) );
				$results[ $item['order_id'] ] = $this->repository->find( $item['id'] ) ?? '';
			}

			return $results;
		} catch ( EnException $exception ) {
			foreach ( $items as $item ) {
				$this->fail( $item['id'], html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) );
				$results[ $item['order_id'] ] = html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' );
			}

			return $results;
		}

		// A gone buffer (deleted or sent outside the plugin) comes back as an error without a GUID (20603).
		$general = isset( $response[''] ) ? implode( ' ', $response['']->errors ) : '';

		if ( str_contains( $general, (string) self::BUFFER_NOT_FOUND_ADD ) || ( array() === $response && $this->buffer_is_gone( $buffer_id ) ) ) {
			$this->forget_missing_buffer( $buffer_id );

			foreach ( $items as $item ) {
				$message = __( 'The buffer no longer exists in Elektroniczny Nadawca (deleted or sent there). Create the shipment again: it goes to a new buffer.', 'poczta-polska-for-woocommerce' );

				$this->fail( $item['id'], $message );
				$results[ $item['order_id'] ] = $message;
			}

			return $results;
		}

		foreach ( $items as $item ) {
			$draft  = $item['draft'];
			$result = $response[ $draft->guid ] ?? null;

			if ( null === $result || ! $result->is_success() ) {
				$message = null !== $result ? implode( ' ', $result->errors ) : ( '' !== $general ? $general : __( 'Elektroniczny Nadawca did not return a result for the shipment.', 'poczta-polska-for-woocommerce' ) );

				$this->fail( $item['id'], $message );
				$results[ $item['order_id'] ] = $message;
				continue;
			}

			$sub_tracking = array();

			foreach ( $draft->sub_guids as $guid ) {
				$sub_tracking[] = isset( $response[ $guid ] ) ? $response[ $guid ]->tracking_number : '';
			}

			$this->repository->update(
				$item['id'],
				array(
					'status'          => Shipment::STATUS_CREATED,
					'tracking_number' => $result->tracking_number,
					'last_error'      => '',
					'data'            => array(
						'draft'        => $draft->to_array(),
						'sub_tracking' => $sub_tracking,
					),
				)
			);

			$results[ $item['order_id'] ] = $this->repository->find( $item['id'] ) ?? '';
		}

		return $results;
	}

	/**
	 * Marks a shipment as rejected.
	 *
	 * @param int    $id      Shipment ID.
	 * @param string $message Error.
	 */
	private function fail( int $id, string $message ): void {
		$this->repository->update(
			$id,
			array(
				'status'     => Shipment::STATUS_FAILED,
				'last_error' => $message,
			)
		);
	}

	/**
	 * Groups shipments by buffer.
	 *
	 * @param Shipment[] $shipments Shipments.
	 * @return array<int, list<Shipment>>
	 */
	private function group_by_buffer( array $shipments ): array {
		$groups = array();

		foreach ( $shipments as $shipment ) {
			if ( $shipment->is_in_buffer() ) {
				$groups[ $shipment->buffer_id ][] = $shipment;
			}
		}

		return $groups;
	}

	/**
	 * Runs a callback while holding a lock.
	 *
	 * @template T
	 * @param string        $key      Lock name.
	 * @param callable(): T $callback Callback.
	 * @return T
	 * @throws ShipmentException When the lock is taken.
	 */
	private function with_lock( string $key, callable $callback ) {
		if ( ! Lock::acquire( $key ) ) {
			throw new ShipmentException( esc_html__( 'Another operation is in progress. Try again in a moment.', 'poczta-polska-for-woocommerce' ) );
		}

		try {
			return $this->en( $callback );
		} finally {
			Lock::release( $key );
		}
	}

	/**
	 * Runs EN calls and turns EN errors into ShipmentException, the only error the screens handle.
	 *
	 * @template T
	 * @param callable(): T $callback Callback.
	 * @return T
	 * @throws ShipmentException When EN fails.
	 */
	private function en( callable $callback ) {
		try {
			return $callback();
		} catch ( EnException $exception ) {
			throw new ShipmentException( esc_html( html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) ), 0, $exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous exception, not output.
		}
	}
}
