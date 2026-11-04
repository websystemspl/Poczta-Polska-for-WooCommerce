<?php
/**
 * Shipments table.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

use PocztaPolska\WooCommerce\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the ppwc_shipments table. Orders stay in WooCommerce, rows refer to them by ID.
 */
final class ShipmentRepository {

	/**
	 * Columns that update() may change.
	 *
	 * @var array<string, string> Column => format.
	 */
	private const COLUMNS = array(
		'order_id'          => '%d',
		'environment'       => '%s',
		'guid'              => '%s',
		'method'            => '%s',
		'status'            => '%s',
		'tracking_number'   => '%s',
		'buffer_id'         => '%d',
		'card_id'           => '%d',
		'posting_office_id' => '%d',
		'envelope_id'       => '%d',
		'label_printed_at'  => '%s',
		'data'              => '%s',
		'last_error'        => '%s',
	);

	/**
	 * Inserts a shipment and returns its ID.
	 *
	 * @param array<string, mixed> $fields Columns (see COLUMNS); data as an array.
	 */
	public function insert( array $fields ): int {
		global $wpdb;

		$now  = gmdate( 'Y-m-d H:i:s' );
		$row  = self::prepare( $fields ) + array(
			'created_at' => $now,
			'updated_at' => $now,
		);
		$keys = array_keys( $row );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table.
		$wpdb->insert( Schema::table( Schema::SHIPMENTS ), $row, self::formats( $keys ) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates columns of a shipment.
	 *
	 * @param int                  $id     Shipment ID.
	 * @param array<string, mixed> $fields Columns to change; data as an array.
	 */
	public function update( int $id, array $fields ): void {
		global $wpdb;

		$row               = self::prepare( $fields );
		$row['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->update( Schema::table( Schema::SHIPMENTS ), $row, array( 'id' => $id ), self::formats( array_keys( $row ) ), array( '%d' ) );
	}

	/**
	 * Deletes a shipment row.
	 *
	 * @param int $id Shipment ID.
	 */
	public function delete( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$wpdb->delete( Schema::table( Schema::SHIPMENTS ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Returns a shipment.
	 *
	 * @param int $id Shipment ID.
	 */
	public function find( int $id ): ?Shipment {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::table( Schema::SHIPMENTS ), $id ), ARRAY_A );

		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * Returns shipments by ID.
	 *
	 * @param int[] $ids Shipment IDs.
	 * @return list<Shipment>
	 */
	public function find_many( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( array() === $ids ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own table, one %d placeholder per ID.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') ORDER BY id', array_merge( array( Schema::table( Schema::SHIPMENTS ) ), $ids ) ), ARRAY_A );

		return self::hydrate_all( $rows );
	}

	/**
	 * Returns the shipments of an order, newest first.
	 *
	 * @param int $order_id Order ID.
	 * @return list<Shipment>
	 */
	public function for_order( int $order_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d ORDER BY id DESC', Schema::table( Schema::SHIPMENTS ), $order_id ), ARRAY_A );

		return self::hydrate_all( $rows );
	}

	/**
	 * Returns the shipments of many orders with one query, grouped by order and oldest first.
	 *
	 * @param int[] $order_ids Order IDs.
	 * @return array<int, list<Shipment>>
	 */
	public function for_orders( array $order_ids ): array {
		global $wpdb;

		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );

		if ( array() === $order_ids ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own table, one %d placeholder per ID.
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id IN (' . implode( ',', array_fill( 0, count( $order_ids ), '%d' ) ) . ') ORDER BY id', array_merge( array( Schema::table( Schema::SHIPMENTS ) ), $order_ids ) ), ARRAY_A );
		$grouped = array();

		foreach ( self::hydrate_all( $rows ) as $shipment ) {
			$grouped[ $shipment->order_id ][] = $shipment;
		}

		return $grouped;
	}

	/**
	 * Returns the shipments in a buffer.
	 *
	 * @param string $environment Environment.
	 * @param int    $buffer_id   Buffer ID.
	 * @return list<Shipment>
	 */
	public function for_buffer( string $environment, int $buffer_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE environment = %s AND buffer_id = %d AND status IN (%s, %s) ORDER BY id', Schema::table( Schema::SHIPMENTS ), $environment, $buffer_id, Shipment::STATUS_CREATED, Shipment::STATUS_PENDING ), ARRAY_A );

		return self::hydrate_all( $rows );
	}

	/**
	 * Returns the shipments of a sent envelope.
	 *
	 * @param string $environment Environment.
	 * @param int    $envelope_id Envelope ID.
	 * @return list<Shipment>
	 */
	public function for_envelope( string $environment, int $envelope_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE environment = %s AND envelope_id = %d ORDER BY id', Schema::table( Schema::SHIPMENTS ), $environment, $envelope_id ), ARRAY_A );

		return self::hydrate_all( $rows );
	}

	/**
	 * Returns the newest sent envelopes with their shipments counted.
	 *
	 * @param string $environment Environment.
	 * @param int    $limit       Maximum envelopes.
	 * @return list<array{envelope_id: int, sent_at: string, shipments: int, office_id: int, card_id: int, orders: list<int>}>
	 */
	public function envelopes( string $environment, int $limit = 20 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, admin screen.
		$rows      = $wpdb->get_results( $wpdb->prepare( 'SELECT envelope_id, MIN(updated_at) AS sent_at, COUNT(*) AS shipments, MAX(posting_office_id) AS office_id, MAX(card_id) AS card_id, GROUP_CONCAT(DISTINCT order_id ORDER BY order_id) AS orders FROM %i WHERE environment = %s AND status = %s AND envelope_id > 0 GROUP BY envelope_id ORDER BY envelope_id DESC LIMIT %d', Schema::table( Schema::SHIPMENTS ), $environment, Shipment::STATUS_SENT, $limit ), ARRAY_A );
		$envelopes = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$envelopes[] = array(
				'envelope_id' => (int) $row['envelope_id'],
				'sent_at'     => (string) $row['sent_at'],
				'shipments'   => (int) $row['shipments'],
				'office_id'   => (int) $row['office_id'],
				'card_id'     => (int) $row['card_id'],
				'orders'      => array_values( array_map( 'intval', explode( ',', (string) $row['orders'] ) ) ),
			);
		}

		return $envelopes;
	}

	/**
	 * Counts unsent shipments per buffer.
	 *
	 * @param string $environment Environment.
	 * @return array<int, int> Buffer ID => number of shipments.
	 */
	public function count_by_buffer( string $environment ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, admin screen.
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT buffer_id, COUNT(*) AS shipments FROM %i WHERE environment = %s AND status IN (%s, %s) GROUP BY buffer_id', Schema::table( Schema::SHIPMENTS ), $environment, Shipment::STATUS_CREATED, Shipment::STATUS_PENDING ), ARRAY_A );
		$counts = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$counts[ (int) $row['buffer_id'] ] = (int) $row['shipments'];
		}

		return $counts;
	}

	/**
	 * Converts fields to column values.
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, scalar|null>
	 */
	private static function prepare( array $fields ): array {
		$row = array();

		foreach ( array_intersect_key( $fields, self::COLUMNS ) as $column => $value ) {
			if ( 'data' === $column ) {
				$value = wp_json_encode( is_array( $value ) ? $value : array() );
			}

			$row[ $column ] = is_scalar( $value ) || null === $value ? $value : '';
		}

		return $row;
	}

	/**
	 * Returns the formats of columns.
	 *
	 * @param string[] $columns Columns.
	 * @return list<string>
	 */
	private static function formats( array $columns ): array {
		return array_values( array_map( static fn( string $column ): string => self::COLUMNS[ $column ] ?? '%s', $columns ) );
	}

	/**
	 * Converts rows to objects.
	 *
	 * @param mixed $rows Query result.
	 * @return list<Shipment>
	 */
	private static function hydrate_all( $rows ): array {
		return array_values( array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? array_filter( $rows, 'is_array' ) : array() ) );
	}

	/**
	 * Converts a row to an object.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	private static function hydrate( array $row ): Shipment {
		$data = json_decode( (string) ( $row['data'] ?? '' ), true );

		return new Shipment(
			(int) $row['id'],
			(int) $row['order_id'],
			(string) $row['environment'],
			(string) $row['guid'],
			(string) $row['method'],
			(string) $row['status'],
			(string) ( $row['tracking_number'] ?? '' ),
			(int) ( $row['buffer_id'] ?? 0 ),
			(int) ( $row['card_id'] ?? 0 ),
			(int) ( $row['posting_office_id'] ?? 0 ),
			(int) ( $row['envelope_id'] ?? 0 ),
			str_starts_with( (string) ( $row['label_printed_at'] ?? '' ), '0000-00-00' ) ? '' : (string) ( $row['label_printed_at'] ?? '' ),
			is_array( $data ) ? $data : array(),
			(string) ( $row['last_error'] ?? '' ),
			(string) $row['created_at']
		);
	}
}
