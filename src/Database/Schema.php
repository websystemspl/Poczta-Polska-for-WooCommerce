<?php
/**
 * Database tables.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin tables with dbDelta().
 *
 * Orders are never stored here: they stay in WooCommerce (HPOS or posts),
 * the shipments table only refers to them by order ID.
 */
final class Schema {

	/**
	 * Schema version. Bump it after every change in create_statements().
	 */
	public const VERSION = '1';

	public const VERSION_OPTION = 'ppwc_db_version';

	/**
	 * Copy of Poczta Polska delivery points (getPlacowkiPocztowe), refreshed daily.
	 */
	public const POINTS = 'ppwc_points';

	/**
	 * Shipments created in Elektroniczny Nadawca.
	 */
	public const SHIPMENTS = 'ppwc_shipments';

	/**
	 * Administrative operations with the WordPress user who made them.
	 */
	public const AUDIT_LOG = 'ppwc_audit_log';

	/**
	 * Returns a table name with the site prefix.
	 *
	 * @param string $table Table name without the prefix, see the constants.
	 */
	public static function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * Creates or upgrades the tables when the stored schema version differs.
	 *
	 * Runs on activation and on every load, because updates do not trigger
	 * the activation hook.
	 */
	public static function maybe_upgrade(): void {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Creates or upgrades all tables.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::create_statements() );

		update_option( self::VERSION_OPTION, self::VERSION, true );
	}

	/**
	 * Drops tables.
	 *
	 * @param string[] $tables Table names without the prefix.
	 */
	public static function drop( array $tables ): void {
		global $wpdb;

		foreach ( $tables as $table ) {
			if ( ! in_array( $table, array( self::POINTS, self::SHIPMENTS, self::AUDIT_LOG ), true ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing own tables on uninstall.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table( $table ) ) );
		}
	}

	/**
	 * CREATE TABLE statements in the format dbDelta() expects.
	 *
	 * @return string[]
	 */
	private static function create_statements(): array {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();

		return array(
			'CREATE TABLE ' . self::table( self::POINTS ) . " (
  pni bigint(20) unsigned NOT NULL,
  name varchar(191) NOT NULL DEFAULT '',
  print_name varchar(191) NOT NULL DEFAULT '',
  network varchar(50) NOT NULL DEFAULT '',
  type varchar(10) NOT NULL DEFAULT '',
  street varchar(191) NOT NULL DEFAULT '',
  house_number varchar(20) NOT NULL DEFAULT '',
  flat_number varchar(20) NOT NULL DEFAULT '',
  postcode varchar(10) NOT NULL DEFAULT '',
  city varchar(100) NOT NULL DEFAULT '',
  voivodeship varchar(50) NOT NULL DEFAULT '',
  voivodeship_code char(2) NOT NULL DEFAULT '',
  description text NULL,
  latitude decimal(9,6) NOT NULL DEFAULT 0,
  longitude decimal(9,6) NOT NULL DEFAULT 0,
  cod_limit decimal(10,2) NOT NULL DEFAULT 0,
  business_pickup tinyint(1) NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (pni),
  KEY postcode (postcode),
  KEY city (city),
  KEY type_network (type,network),
  KEY voivodeship_updated (voivodeship_code,updated_at)
) $collate;",
			'CREATE TABLE ' . self::table( self::SHIPMENTS ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  parent_id bigint(20) unsigned NULL,
  environment varchar(10) NOT NULL DEFAULT '',
  guid char(32) NOT NULL,
  method varchar(30) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT '',
  tracking_number varchar(40) NULL,
  buffer_id bigint(20) unsigned NULL,
  card_id bigint(20) unsigned NULL,
  posting_office_id bigint(20) unsigned NULL,
  envelope_id bigint(20) unsigned NULL,
  label_printed_at datetime NULL,
  data longtext NULL,
  last_error text NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY guid (guid),
  KEY order_id (order_id),
  KEY parent_id (parent_id),
  KEY buffer_status (environment,buffer_id,status),
  KEY status (status)
) $collate;",
			'CREATE TABLE ' . self::table( self::AUDIT_LOG ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(50) NOT NULL DEFAULT '',
  object_type varchar(30) NOT NULL DEFAULT '',
  object_id varchar(64) NOT NULL DEFAULT '',
  details text NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY object (object_type,object_id)
) $collate;",
		);
	}
}
