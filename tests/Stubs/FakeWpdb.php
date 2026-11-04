<?php
/**
 * In-memory replacement of $wpdb for the queries of the plugin repositories.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Stubs;

/**
 * Keeps rows per table in arrays and understands the exact queries of ShipmentRepository,
 * PointsRepository::find() and AuditLog::record(). Any other query fails the test.
 */
final class FakeWpdb {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * ID of the last inserted row.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Rows by table name, keyed by ID.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	public array $tables = array();

	/**
	 * Number of read queries run.
	 *
	 * @var int
	 */
	public int $queries = 0;

	/**
	 * Returns the query with its arguments (the fake does not build SQL).
	 *
	 * @param string $query   Query with placeholders.
	 * @param mixed  ...$args Arguments, also as one array.
	 * @return array{query: string, args: list<mixed>}
	 */
	public function prepare( string $query, ...$args ): array {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return array(
			'query' => $query,
			'args'  => array_values( $args ),
		);
	}

	/**
	 * Escapes LIKE wildcards (not needed in memory).
	 *
	 * @param string $text Text.
	 */
	public function esc_like( string $text ): string {
		return $text;
	}

	/**
	 * Inserts a row.
	 *
	 * @param string               $table Table.
	 * @param array<string, mixed> $row   Row.
	 */
	public function insert( string $table, array $row ): int {
		$id                            = count( $this->tables[ $table ] ?? array() ) + 1;
		$this->tables[ $table ][ $id ] = array( 'id' => $id ) + $row;
		$this->insert_id               = $id;

		return 1;
	}

	/**
	 * Updates rows matching the ID.
	 *
	 * @param string               $table Table.
	 * @param array<string, mixed> $row   New values.
	 * @param array{id: int}       $where Condition.
	 */
	public function update( string $table, array $row, array $where ): int {
		if ( ! isset( $this->tables[ $table ][ $where['id'] ] ) ) {
			return 0;
		}

		$this->tables[ $table ][ $where['id'] ] = array_merge( $this->tables[ $table ][ $where['id'] ], $row );

		return 1;
	}

	/**
	 * Deletes a row.
	 *
	 * @param string         $table Table.
	 * @param array{id: int} $where Condition.
	 */
	public function delete( string $table, array $where ): int {
		unset( $this->tables[ $table ][ $where['id'] ] );

		return 1;
	}

	/**
	 * Returns one row.
	 *
	 * @param array{query: string, args: list<mixed>} $prepared Prepared query.
	 * @return array<string, mixed>|null
	 */
	public function get_row( array $prepared ): ?array {
		$rows = $this->get_results( $prepared );

		return $rows[0] ?? null;
	}

	/**
	 * Returns rows of a supported query.
	 *
	 * @param array{query: string, args: list<mixed>} $prepared Prepared query.
	 * @return list<array<string, mixed>>
	 */
	public function get_results( array $prepared ): array {
		++$this->queries;

		$query = $prepared['query'];
		$args  = $prepared['args'];
		$rows  = array_values( $this->tables[ (string) array_shift( $args ) ] ?? array() );

		$filter = static fn( callable $matches ): array => array_values( array_filter( $rows, $matches ) );

		return match ( true ) {
			str_contains( $query, 'WHERE id = %d' )             => $filter( static fn( array $row ): bool => (int) $row['id'] === (int) $args[0] ),
			str_contains( $query, 'WHERE pni = %d' )            => $filter( static fn( array $row ): bool => (int) $row['pni'] === (int) $args[0] ),
			str_contains( $query, 'WHERE id IN (' )             => $filter( static fn( array $row ): bool => in_array( (int) $row['id'], array_map( 'intval', $args ), true ) ),
			str_contains( $query, 'WHERE order_id = %d' )       => array_reverse( $filter( static fn( array $row ): bool => (int) $row['order_id'] === (int) $args[0] ) ),
			str_contains( $query, 'WHERE order_id IN (' )       => $filter( static fn( array $row ): bool => in_array( (int) $row['order_id'], array_map( 'intval', $args ), true ) ),
			str_contains( $query, 'AND buffer_id = %d AND status IN' ) => $filter( static fn( array $row ): bool => $row['environment'] === $args[0] && (int) $row['buffer_id'] === (int) $args[1] && in_array( $row['status'], array( $args[2], $args[3] ), true ) ),
			str_contains( $query, 'AND envelope_id = %d' )      => $filter( static fn( array $row ): bool => $row['environment'] === $args[0] && (int) ( $row['envelope_id'] ?? 0 ) === (int) $args[1] ),
			default => throw new \LogicException( 'FakeWpdb does not support: ' . $query ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test code.
		};
	}

	/**
	 * Rows of a table.
	 *
	 * @param string $table Table without prefix.
	 * @return list<array<string, mixed>>
	 */
	public function rows( string $table ): array {
		return array_values( $this->tables[ $this->prefix . $table ] ?? array() );
	}
}
