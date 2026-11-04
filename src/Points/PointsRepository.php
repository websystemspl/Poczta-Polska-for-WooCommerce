<?php
/**
 * Local copy of the Poczta Polska delivery points.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Points;

use PocztaPolska\WooCommerce\Database\Schema;
use PocztaPolska\WooCommerce\En\Dto\PickupPoint;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the ppwc_points table.
 *
 * The copy is used to validate the point chosen at checkout (type, COD limit)
 * and as a fallback when the map widget is not available.
 */
final class PointsRepository {

	/**
	 * Rows per INSERT statement.
	 */
	private const BATCH = 200;

	/**
	 * Columns in insert order.
	 *
	 * @var list<string>
	 */
	private const COLUMNS = array( 'pni', 'name', 'print_name', 'network', 'type', 'street', 'house_number', 'flat_number', 'postcode', 'city', 'voivodeship', 'voivodeship_code', 'description', 'latitude', 'longitude', 'cod_limit', 'business_pickup', 'updated_at' );

	/**
	 * Returns a point by PNI.
	 *
	 * @param int $pni Point ID.
	 */
	public function find( int $pni ): ?PickupPoint {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, primary key lookup.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE pni = %d', Schema::table( Schema::POINTS ), $pni ), ARRAY_A );

		return is_array( $row ) ? self::to_point( $row ) : null;
	}

	/**
	 * Searches pickup points by postcode, city or street (fallback when the map is unavailable).
	 *
	 * @param string $term     Search term: postcode (00-000 or 00000), city or street.
	 * @param string $type     PUNKT or AUTOMAT.
	 * @param float  $cod      COD amount in PLN the point must accept, 0 for no COD.
	 * @param string $network  Required network, e.g. AUTOMAT POCZTEX, empty for any.
	 * @param int    $limit    Maximum number of results.
	 * @return list<PickupPoint>
	 */
	public function search( string $term, string $type, float $cod = 0.0, string $network = '', int $limit = 20 ): array {
		global $wpdb;

		$term = trim( $term );

		if ( mb_strlen( $term ) < 2 ) {
			return array();
		}

		$digits = (string) preg_replace( '/\D/', '', $term );
		$like   = '%' . $wpdb->esc_like( $term ) . '%';
		// A full postcode searches the area: points rarely share the customer's exact postcode.
		$postcode = strlen( $digits ) >= 2 ? $wpdb->esc_like( substr( $digits, 0, 3 ) ) . '%' : 'x';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, user search.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE business_pickup = 1 AND type = %s AND ( %s = %s OR network = %s ) AND cod_limit >= %f AND ( %f = 0 OR cod_limit > 0 ) AND ( postcode LIKE %s OR city LIKE %s OR street LIKE %s ) ORDER BY postcode = %s DESC, city = %s DESC, city, street LIMIT %d',
				Schema::table( Schema::POINTS ),
				$type,
				$network,
				'',
				$network,
				$cod,
				$cod,
				$postcode,
				$like,
				$like,
				$digits,
				$term,
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);

		return array_values( array_map( array( self::class, 'to_point' ), is_array( $rows ) ? $rows : array() ) );
	}

	/**
	 * Returns the number of points in the copy.
	 */
	public function count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, admin status.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', Schema::table( Schema::POINTS ) ) );
	}

	/**
	 * Replaces the points of a voivodeship.
	 *
	 * Inserts or updates the given points, then deletes the points of the voivodeship
	 * that EN no longer returns.
	 *
	 * @param string        $voivodeship_code GUS code.
	 * @param PickupPoint[] $points           Points returned by EN.
	 * @return int Number of saved points.
	 */
	public function replace_voivodeship( string $voivodeship_code, array $points ): int {
		global $wpdb;

		$table   = Schema::table( Schema::POINTS );
		$started = gmdate( 'Y-m-d H:i:s' );

		foreach ( array_chunk( $points, self::BATCH ) as $chunk ) {
			$placeholders = array();
			$values       = array( $table );

			foreach ( $chunk as $point ) {
				$placeholders[] = '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %f, %f, %f, %d, %s)';
				array_push(
					$values,
					$point->pni,
					mb_substr( $point->name, 0, 191 ),
					mb_substr( $point->print_name, 0, 191 ),
					mb_substr( $point->network, 0, 50 ),
					mb_substr( $point->type, 0, 10 ),
					mb_substr( $point->street, 0, 191 ),
					mb_substr( $point->house_number, 0, 20 ),
					mb_substr( $point->flat_number, 0, 20 ),
					mb_substr( $point->postcode, 0, 10 ),
					mb_substr( $point->city, 0, 100 ),
					mb_substr( $point->voivodeship, 0, 50 ),
					$voivodeship_code,
					$point->description,
					$point->latitude,
					$point->longitude,
					$point->cod_limit,
					$point->business_pickup ? 1 : 0,
					$started
				);
			}

			// Only placeholders and the column names from self::COLUMNS, no data.
			$sql = 'INSERT INTO %i (' . implode( ', ', self::COLUMNS ) . ') VALUES ' . implode( ', ', $placeholders )
				. ' ON DUPLICATE KEY UPDATE ' . implode( ', ', array_map( static fn( string $column ): string => $column . ' = VALUES(' . $column . ')', array_slice( self::COLUMNS, 1 ) ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built above from placeholders and constants only, every value goes through prepare(). One statement per 200 points instead of 200 statements.
			$wpdb->query( $wpdb->prepare( $sql, $values ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, removes points closed by Poczta Polska.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE voivodeship_code = %s AND updated_at < %s', $table, $voivodeship_code, $started ) );

		return count( $points );
	}

	/**
	 * Maps a table row to a DTO.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	private static function to_point( array $row ): PickupPoint {
		return new PickupPoint(
			(int) $row['pni'],
			(string) $row['name'],
			(string) $row['print_name'],
			(string) $row['network'],
			(string) $row['type'],
			(string) $row['street'],
			(string) $row['house_number'],
			(string) $row['flat_number'],
			(string) $row['postcode'],
			(string) $row['city'],
			(string) $row['voivodeship'],
			(string) ( $row['description'] ?? '' ),
			(float) $row['latitude'],
			(float) $row['longitude'],
			(float) $row['cod_limit'],
			(bool) (int) $row['business_pickup']
		);
	}
}
