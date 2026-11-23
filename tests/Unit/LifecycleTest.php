<?php
/**
 * Tests of the one-time changes made when the plugin is updated.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit;

use PocztaPolska\WooCommerce\Lifecycle;
use PocztaPolska\WooCommerce\Settings;

/**
 * Lifecycle.
 */
final class LifecycleTest extends TestCase {

	use WordPressStore;

	/**
	 * Sets up the store.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->install_store();
	}

	public function test_a_missing_version_is_saved_and_the_today_defaults_are_copied(): void {
		$this->options[ Settings::OPTION ] = array(
			'pocztex_format'    => 'L',
			'pocztex_weight'    => '2500',
			'pocztex_insurance' => '0',
		);

		Lifecycle::maybe_upgrade();

		$settings = $this->options[ Settings::OPTION ];

		$this->assertSame( PPWC_VERSION, $this->options[ Lifecycle::VERSION_OPTION ] );
		$this->assertSame( 'L', $settings['pocztex_today_format'] );
		$this->assertSame( '2500', $settings['pocztex_today_weight'] );
		$this->assertSame( '0', $settings['pocztex_insurance'], 'An unknown version must not overwrite the chosen insurance.' );
	}

	public function test_a_missing_version_keeps_the_saved_today_defaults(): void {
		$this->options[ Settings::OPTION ] = array(
			'pocztex_format'       => 'L',
			'pocztex_today_format' => 'S',
		);

		Lifecycle::maybe_upgrade();

		$this->assertSame( 'S', $this->options[ Settings::OPTION ]['pocztex_today_format'] );
	}

	public function test_a_missing_version_on_a_site_without_settings_saves_only_the_version(): void {
		Lifecycle::maybe_upgrade();

		$this->assertSame( PPWC_VERSION, $this->options[ Lifecycle::VERSION_OPTION ] );
		$this->assertArrayNotHasKey( Settings::OPTION, $this->options );
	}

	public function test_the_current_version_changes_nothing(): void {
		$this->options[ Lifecycle::VERSION_OPTION ] = PPWC_VERSION;
		$this->options[ Settings::OPTION ]          = array( 'pocztex_format' => 'L' );

		Lifecycle::maybe_upgrade();

		$this->assertSame( array( 'pocztex_format' => 'L' ), $this->options[ Settings::OPTION ] );
	}
}
