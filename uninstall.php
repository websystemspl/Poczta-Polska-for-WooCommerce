<?php
/**
 * Removes plugin data when the plugin is deleted.
 *
 * Always removed: settings, shipping method instances, the pickup point copy and scheduled jobs.
 * Shipments and the audit log stay unless "Delete all data on uninstall" is enabled,
 * because tracking numbers belong to the order history.
 *
 * @package PocztaPolska\WooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the data of the current site.
 *
 * @return void
 */
function ppwc_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'ppwc_settings', array() );
	$tables   = array( 'ppwc_points' );

	if ( is_array( $settings ) && 'yes' === ( $settings['delete_data_on_uninstall'] ?? '' ) ) {
		$tables[] = 'ppwc_shipments';
		$tables[] = 'ppwc_audit_log';
		// The buffer list and envelope statuses belong to the shipments: they stay while the shipments stay.
		delete_option( 'ppwc_buffers' );
		delete_option( 'ppwc_envelope_status' );
	}

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing own tables on uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
	}

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'ppwc' );
	}

	delete_option( 'ppwc_version' );
	delete_option( 'ppwc_settings' );
	delete_option( 'ppwc_password_expiry' );
	delete_option( 'ppwc_points_sync' );
	delete_transient( 'ppwc_products_without_weight' );

	// Locks (Support\Lock).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No API deletes options by prefix.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ppwc_lock_' ) . '%' ) );

	// Cached EN dictionaries and points, bulk action results and screen messages.
	foreach ( array( 'ppwc_dict_', 'ppwc_point_', 'ppwc_bulk_result_', 'ppwc_buffers_notice_' ) as $ppwc_prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No API deletes transients by prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $ppwc_prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $ppwc_prefix ) . '%'
			)
		);
	}

	// Shipping method instances in zones: without the plugin they would stay as orphans.
	// Order shipping lines keep their own copy of the method data, so order history is not affected.
	$ppwc_zone_methods = $wpdb->prefix . 'woocommerce_shipping_zone_methods';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce may be inactive during uninstall.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $ppwc_zone_methods ) ) ) === $ppwc_zone_methods ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
		$ppwc_instances = $wpdb->get_results( $wpdb->prepare( 'SELECT instance_id, method_id FROM %i WHERE method_id LIKE %s', $ppwc_zone_methods, $wpdb->esc_like( 'ppwc_' ) . '%' ) );

		foreach ( $ppwc_instances as $ppwc_instance ) {
			delete_option( 'woocommerce_' . $ppwc_instance->method_id . '_' . (int) $ppwc_instance->instance_id . '_settings' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE method_id LIKE %s', $ppwc_zone_methods, $wpdb->esc_like( 'ppwc_' ) . '%' ) );

		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}

	// The points table is always dropped, so a reinstall must recreate the schema.
	delete_option( 'ppwc_db_version' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $ppwc_site_id ) {
		switch_to_blog( (int) $ppwc_site_id );
		ppwc_uninstall_site();
		restore_current_blog();
	}
} else {
	ppwc_uninstall_site();
}
