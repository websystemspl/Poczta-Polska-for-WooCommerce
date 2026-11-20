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
use PocztaPolska\WooCommerce\Shipping\ServiceRegistry;

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

	public function test_pocztex_today_has_its_own_shipment_defaults(): void {
		$clean = Settings::sanitize(
			array(
				'_tab'                       => SettingsSchema::TAB_SHIPMENTS,
				'pocztex_format'             => 'M',
				'pocztex_weight'             => '1500',
				'pocztex_content'            => 'Towar',
				'pocztex_description'        => 'Zamówienie {order_number}',
				'pocztex_insurance'          => '1000',
				'pocztex_value'              => '200',
				'pocztex_fragile'            => 'yes',
				'pocztex_confirmation'       => 'SMS',
				'pocztex_hour'               => 'DO_GODZ_12',
				'pocztex_envelope'           => 'yes',
				'pocztex_today_format'       => 'L',
				'pocztex_today_weight'       => '2000',
				'pocztex_today_content'      => 'Kwiaty',
				'pocztex_today_description'  => 'Na dziś {order_number}',
				'pocztex_today_insurance'    => '5000',
				'pocztex_today_value'        => '',
				'pocztex_today_oversize'     => 'yes',
				'pocztex_today_confirmation' => 'EMAIL',
			)
		);

		$this->assertSame( array(), $this->errors );
		$this->assertSame( 'M', $clean['pocztex_format'] );
		$this->assertSame( '200', $clean['pocztex_value'] );
		$this->assertSame( 'yes', $clean['pocztex_fragile'] );
		$this->assertSame( '', $clean['pocztex_oversize'] );
		$this->assertSame( 'DO_GODZ_12', $clean['pocztex_hour'] );
		$this->assertSame( 'yes', $clean['pocztex_envelope'] );
		$this->assertSame( 'L', $clean['pocztex_today_format'] );
		$this->assertSame( '0', $clean['pocztex_today_value'] );
		$this->assertSame( '', $clean['pocztex_today_fragile'] );
		$this->assertSame( 'yes', $clean['pocztex_today_oversize'] );
		$this->assertSame( 'EMAIL', $clean['pocztex_today_confirmation'] );
		$this->assertArrayNotHasKey( 'pocztex_today_hour', $clean, 'The delivery hour exists for Pocztex Kurier only.' );
		$this->assertArrayNotHasKey( 'pocztex_today_envelope', $clean );
	}

	public function test_only_the_delivery_by_noon_can_be_saved(): void {
		foreach ( array( 'DO_GODZ_8', 'DO_GODZ_9', 'PO_GODZ_17' ) as $hour ) {
			$this->errors = array();
			$clean        = Settings::sanitize(
				array(
					'_tab'         => SettingsSchema::TAB_SHIPMENTS,
					'pocztex_hour' => $hour,
				)
			);

			$this->assertSame( '', $clean['pocztex_hour'] );
			$this->assertContains( 'ppwc_invalid_pocztex_hour', $this->errors );
		}
	}

	public function test_services_read_the_defaults_of_their_group(): void {
		update_option(
			Settings::OPTION,
			array(
				'pocztex_fragile'       => 'yes',
				'pocztex_today_fragile' => '',
			)
		);

		$this->assertSame( 'yes', Settings::pocztex( ServiceRegistry::get( ServiceRegistry::POCZTEX_LOCKER ), 'fragile' ) );
		$this->assertSame( '', Settings::pocztex( ServiceRegistry::get( ServiceRegistry::POCZTEX_TODAY_CITY ), 'fragile' ) );
	}
}
