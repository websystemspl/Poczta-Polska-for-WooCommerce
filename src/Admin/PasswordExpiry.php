<?php
/**
 * Elektroniczny Nadawca password expiry.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\En\ClientFactory;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Lifecycle;
use PocztaPolska\WooCommerce\Logger;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the EN password expiry date once a day and warns before it expires.
 */
final class PasswordExpiry {

	public const OPTION = 'ppwc_password_expiry';

	public const HOOK = 'ppwc_check_password_expiry';

	/**
	 * Days before expiry when the warning appears.
	 */
	private const WARNING_DAYS = 14;

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( self::HOOK, array( self::class, 'check' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Schedules the daily check.
	 */
	public function schedule(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( self::HOOK, array(), Lifecycle::ACTION_GROUP ) ) {
			return;
		}

		as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), Lifecycle::ACTION_GROUP );
	}

	/**
	 * Reads the expiry date of the active environment from EN and stores it.
	 */
	public static function check(): void {
		$environment = Settings::environment();

		if ( '' === Settings::get( 'en_' . $environment . '_login' ) || ! Settings::has_en_password( $environment ) ) {
			return;
		}

		try {
			self::store( $environment, ClientFactory::create( $environment )->password_expiry_date() );
		} catch ( EnException $exception ) {
			Logger::log( 'warning', 'EN password expiry check failed.', array( 'error' => $exception->getMessage() ) );
		}
	}

	/**
	 * Stores the expiry date of an environment.
	 *
	 * @param string             $environment Environment.
	 * @param \DateTimeImmutable $date        Expiry date.
	 */
	public static function store( string $environment, \DateTimeImmutable $date ): void {
		$stored                 = get_option( self::OPTION, array() );
		$stored                 = is_array( $stored ) ? $stored : array();
		$stored[ $environment ] = array(
			'date'  => $date->format( 'Y-m-d' ),
			// The login the date belongs to, so a new login does not inherit the old warning.
			'login' => Settings::get( 'en_' . $environment . '_login' ),
		);

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Returns the number of days until the password of an environment expires, null when unknown.
	 *
	 * @param string $environment Environment.
	 */
	public static function days_left( string $environment ): ?int {
		$stored = get_option( self::OPTION, array() );
		$entry  = is_array( $stored ) ? ( $stored[ $environment ] ?? null ) : null;

		if ( ! is_array( $entry ) || ( $entry['login'] ?? '' ) !== Settings::get( 'en_' . $environment . '_login' ) ) {
			return null;
		}

		$expiry = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) ( $entry['date'] ?? '' ), wp_timezone() );

		if ( false === $expiry ) {
			return null;
		}

		$today = new \DateTimeImmutable( 'today', wp_timezone() );

		return (int) $today->diff( $expiry )->format( '%r%a' );
	}

	/**
	 * Shows a warning on WooCommerce and plugin screens when the password expires soon.
	 */
	public function notice(): void {
		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || ! preg_match( '/^(dashboard|plugins|woocommerce_page_|toplevel_page_ppwc|poczta-polska_page_|shop_order|edit-shop_order)/', $screen->id ) ) {
			return;
		}

		$days = self::days_left( Settings::environment() );

		if ( null === $days || $days > self::WARNING_DAYS ) {
			return;
		}

		$message = $days < 0
			? __( 'The Elektroniczny Nadawca password has expired. Shipments cannot be created until you change it in Elektroniczny Nadawca and enter it in the plugin settings.', 'poczta-polska-for-woocommerce' )
			: sprintf(
				/* translators: %d: number of days. */
				_n(
					'The Elektroniczny Nadawca password expires in %d day. Change it in Elektroniczny Nadawca and enter it in the plugin settings.',
					'The Elektroniczny Nadawca password expires in %d days. Change it in Elektroniczny Nadawca and enter it in the plugin settings.',
					$days,
					'poczta-polska-for-woocommerce'
				),
				$days
			);

		Template::view(
			'admin/notice.php',
			array(
				'type'    => $days < 0 ? 'error' : 'warning',
				'message' => $message,
				'links'   => array(
					array(
						'url'  => SettingsPage::url(),
						'text' => __( 'Poczta Polska settings', 'poczta-polska-for-woocommerce' ),
					),
				),
			)
		);
	}
}
