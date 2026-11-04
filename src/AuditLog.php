<?php
/**
 * Audit log of administrative operations.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use PocztaPolska\WooCommerce\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Records who changed the settings or removed shipments and buffers (contract § 11).
 */
final class AuditLog {

	public const ACTION_SETTINGS_UPDATED = 'settings_updated';

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'settings_updated' ), 10, 2 );
		add_action( 'add_option_' . Settings::OPTION, array( $this, 'settings_added' ), 10, 2 );
	}

	/**
	 * Records an operation of the current user.
	 *
	 * @param string               $action      Action, e.g. settings_updated, shipment_deleted.
	 * @param string               $object_type Object type, e.g. settings, shipment, buffer.
	 * @param string               $object_id   Object ID.
	 * @param array<string, mixed> $details     Details, never personal data or passwords.
	 */
	public static function record( string $action, string $object_type, string $object_id = '', array $details = array() ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, insert only.
		$wpdb->insert(
			Schema::table( Schema::AUDIT_LOG ),
			array(
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
				'user_id'     => get_current_user_id(),
				'action'      => substr( $action, 0, 50 ),
				'object_type' => substr( $object_type, 0, 30 ),
				'object_id'   => substr( $object_id, 0, 64 ),
				'details'     => array() === $details ? null : wp_json_encode( $details ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Returns the latest entries.
	 *
	 * @param int $limit Number of entries.
	 * @return list<array{created_at: string, user_id: int, action: string, object_type: string, object_id: string, details: array<string, mixed>}>
	 */
	public static function latest( int $limit = 50 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, admin screen only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT created_at, user_id, action, object_type, object_id, details FROM %i ORDER BY id DESC LIMIT %d',
				Schema::table( Schema::AUDIT_LOG ),
				max( 1, $limit )
			),
			ARRAY_A
		);

		$entries = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$details   = json_decode( (string) ( $row['details'] ?? '' ), true );
			$entries[] = array(
				'created_at'  => (string) $row['created_at'],
				'user_id'     => (int) $row['user_id'],
				'action'      => (string) $row['action'],
				'object_type' => (string) $row['object_type'],
				'object_id'   => (string) $row['object_id'],
				'details'     => is_array( $details ) ? $details : array(),
			);
		}

		return $entries;
	}

	/**
	 * Records changed settings keys (values are not stored).
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 */
	public function settings_updated( $old_value, $value ): void {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$value     = is_array( $value ) ? $value : array();
		$changed   = array();

		foreach ( array_unique( array_merge( array_keys( $old_value ), array_keys( $value ) ) ) as $key ) {
			// A key added with an empty value (a new setting saved for the first time) is not a change.
			if ( wp_json_encode( $old_value[ $key ] ?? '' ) !== wp_json_encode( $value[ $key ] ?? '' ) ) {
				$changed[] = (string) $key;
			}
		}

		if ( array() !== $changed ) {
			self::record( self::ACTION_SETTINGS_UPDATED, 'settings', '', array( 'changed' => $changed ) );
		}
	}

	/**
	 * Records the first save of the settings.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public function settings_added( string $option, $value ): void {
		unset( $option );
		$this->settings_updated( array(), $value );
	}
}
