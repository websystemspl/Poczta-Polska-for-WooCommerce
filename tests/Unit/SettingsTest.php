<?php
/**
 * Tests of saving the settings.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit;

use Brain\Monkey\Functions;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\SettingsSchema;

/**
 * Settings.
 */
final class SettingsTest extends TestCase {

	use WordPressStore;

	/**
	 * Settings errors.
	 *
	 * @var list<string>
	 */
	private array $errors = array();

	/**
	 * Sets up the store.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->install_store();
		$this->errors = array();

		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'add_settings_error' )->alias(
			function ( string $setting, string $code ): void {
				$this->errors[] = $code;
			}
		);
	}

	public function test_posting_in_a_parcel_locker_does_not_need_the_locker(): void {
		$clean = Settings::sanitize(
			array(
				'_tab'               => SettingsSchema::TAB_CONTRACT,
				'posting_channel'    => 'APM',
				'posting_locker_pni' => '',
			)
		);

		$this->assertSame( 'APM', $clean['posting_channel'] );
		$this->assertSame( '', $clean['posting_locker_pni'] );
		$this->assertNotContains( 'ppwc_posting_locker_missing', $this->errors );
	}

	public function test_the_parcel_locker_can_still_be_named(): void {
		$clean = Settings::sanitize(
			array(
				'_tab'               => SettingsSchema::TAB_CONTRACT,
				'posting_channel'    => 'APM',
				'posting_locker_pni' => '213458',
			)
		);

		$this->assertSame( 'APM', $clean['posting_channel'] );
		$this->assertSame( '213458', $clean['posting_locker_pni'] );
	}
}
