<?php
/**
 * Tests of the audit log of settings changes.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit;

use PocztaPolska\WooCommerce\AuditLog;

/**
 * Only the names of really changed settings are recorded, never their values.
 */
final class AuditLogTest extends TestCase {

	use WordPressStore;

	protected function setUp(): void {
		parent::setUp();
		$this->install_store();
	}

	protected function tearDown(): void {
		$this->uninstall_store();
		parent::tearDown();
	}

	/**
	 * Recorded entries.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function entries(): array {
		return $this->wpdb->rows( 'ppwc_audit_log' );
	}

	public function test_changed_keys_are_recorded_without_values(): void {
		( new AuditLog() )->settings_updated(
			array(
				'environment'      => 'test',
				'en_test_password' => 'ppwc1:old',
			),
			array(
				'environment'      => 'production',
				'en_test_password' => 'ppwc1:new',
			)
		);

		$entries = $this->entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( array( 'changed' => array( 'environment', 'en_test_password' ) ), json_decode( (string) $entries[0]['details'], true ) );
		$this->assertStringNotContainsString( 'ppwc1:', (string) $entries[0]['details'] );
	}

	public function test_new_empty_setting_is_not_a_change(): void {
		( new AuditLog() )->settings_updated(
			array( 'environment' => 'test' ),
			array(
				'environment' => 'test',
				'cod_account' => '',
			)
		);

		$this->assertSame( array(), $this->entries() );
	}

	public function test_removed_setting_is_a_change(): void {
		( new AuditLog() )->settings_updated( array( 'en_test_password' => 'ppwc1:old' ), array() );

		$this->assertSame( array( 'changed' => array( 'en_test_password' ) ), json_decode( (string) $this->entries()[0]['details'], true ) );
	}

	public function test_first_save_records_the_filled_settings(): void {
		( new AuditLog() )->settings_added(
			'ppwc_settings',
			array(
				'environment' => 'test',
				'log_level'   => '',
			)
		);

		$this->assertSame( array( 'changed' => array( 'environment' ) ), json_decode( (string) $this->entries()[0]['details'], true ) );
	}

	public function test_long_values_are_cut_to_the_column_sizes(): void {
		AuditLog::record( str_repeat( 'a', 80 ), str_repeat( 'b', 40 ), str_repeat( '9', 70 ) );

		$entry = $this->entries()[0];

		$this->assertSame( 50, strlen( (string) $entry['action'] ) );
		$this->assertSame( 30, strlen( (string) $entry['object_type'] ) );
		$this->assertSame( 64, strlen( (string) $entry['object_id'] ) );
		$this->assertNull( $entry['details'] );
		$this->assertSame( 1, $entry['user_id'] );
	}
}
