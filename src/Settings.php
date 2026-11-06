<?php
/**
 * Plugin settings storage.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use PocztaPolska\WooCommerce\Security\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes the plugin settings stored in a single option.
 */
final class Settings {

	public const OPTION = 'ppwc_settings';

	public const ENV_PRODUCTION = 'production';

	public const ENV_TEST = 'test';

	/**
	 * Default values, built once per request.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $defaults = null;

	/**
	 * Elektroniczny Nadawca environments and their WebAPI endpoints.
	 *
	 * @var array<string, string>
	 */
	public const EN_ENDPOINTS = array(
		self::ENV_PRODUCTION => 'https://e-nadawca.api.poczta-polska.pl/websrv/',
		self::ENV_TEST       => 'https://int-e-nadawca.api.poczta-polska.pl/websrv/',
	);

	/**
	 * Returns all settings merged with the defaults.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Returns a single setting.
	 *
	 * @param string $key Setting key.
	 */
	public static function get( string $key ): string {
		return (string) ( self::all()[ $key ] ?? '' );
	}

	/**
	 * Returns the active Elektroniczny Nadawca environment.
	 */
	public static function environment(): string {
		return self::ENV_PRODUCTION === self::get( 'environment' ) ? self::ENV_PRODUCTION : self::ENV_TEST;
	}

	/**
	 * Returns the environment names for the admin screens.
	 *
	 * @return array<string, string>
	 */
	public static function environment_labels(): array {
		return array(
			self::ENV_TEST       => __( 'Test (INT)', 'poczta-polska-for-woocommerce' ),
			self::ENV_PRODUCTION => __( 'Production', 'poczta-polska-for-woocommerce' ),
		);
	}

	/**
	 * Returns the decrypted Elektroniczny Nadawca password for an environment.
	 *
	 * Never pass the result to any output.
	 *
	 * @param string $environment Environment, see ENV_* constants.
	 */
	public static function en_password( string $environment ): string {
		return Crypto::decrypt( self::get( 'en_' . $environment . '_password' ) );
	}

	/**
	 * Whether a password is stored for an environment.
	 *
	 * @param string $environment Environment, see ENV_* constants.
	 */
	public static function has_en_password( string $environment ): bool {
		return '' !== self::get( 'en_' . $environment . '_password' );
	}

	/**
	 * Returns a per-environment setting (see SettingsSchema "per_env") for the active environment.
	 *
	 * @param string      $key         Setting key without the environment suffix.
	 * @param string|null $environment Environment, the active one by default.
	 */
	public static function get_for_env( string $key, ?string $environment = null ): string {
		return self::get( $key . '_' . ( $environment ?? self::environment() ) );
	}

	/**
	 * Default values.
	 *
	 * Test environment by default, so a fresh install never sends shipments to production.
	 *
	 * Call after init: the defaults contain translated strings.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}

		self::$defaults = array_merge(
			array(
				'environment'            => self::ENV_TEST,
				'en_production_login'    => '',
				'en_production_password' => '',
				'en_test_login'          => '',
				'en_test_password'       => '',
			),
			SettingsSchema::defaults()
		);

		return self::$defaults;
	}

	/**
	 * Sanitizes the submitted settings (register_setting sanitize callback).
	 *
	 * The settings screen submits one tab at a time with its slug in "_tab", and only the
	 * fields of that tab change. Without "_tab" (e.g. the second call WordPress makes when the
	 * option is saved for the first time) all tabs are sanitized from the given values.
	 *
	 * Invalid values keep the stored value and add a settings error.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string, string>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = self::all();
		$tab   = isset( $input['_tab'] ) ? sanitize_key( (string) $input['_tab'] ) : '';
		$tabs  = '' === $tab ? array_keys( SettingsSchema::tabs() ) : array( $tab );

		if ( in_array( SettingsSchema::TAB_CONNECTION, $tabs, true ) ) {
			$clean = self::sanitize_connection( $input, $clean );
		}

		foreach ( $tabs as $current_tab ) {
			foreach ( SettingsSchema::fields_for_tab( $current_tab ) as $field ) {
				// The screen edits per-environment fields of the active environment only.
				$environments = ! empty( $field['per_env'] ) && '' === $tab ? array_keys( self::EN_ENDPOINTS ) : array( self::environment() );

				foreach ( $environments as $environment ) {
					$key = SettingsSchema::storage_key( $field, $environment );

					if ( '' === $tab && ! array_key_exists( $key, $input ) ) {
						continue;
					}

					$value = SettingsSchema::sanitize_value( $field, $input[ $key ] ?? null );

					if ( null === $value ) {
						add_settings_error(
							self::OPTION,
							'ppwc_invalid_' . $key,
							sprintf(
								/* translators: 1: field label, 2: error description. */
								__( '%1$s: %2$s The previous value was kept.', 'poczta-polska-for-woocommerce' ),
								$field['label'],
								$field['error'] ?? __( 'Invalid value.', 'poczta-polska-for-woocommerce' )
							)
						);
						continue;
					}

					$clean[ $key ] = $value;
				}
			}
		}

		// Posting in a parcel locker needs the locker.
		if ( 'APM' === ( $clean['posting_channel'] ?? '' ) && '' === ( $clean['posting_locker_pni'] ?? '' ) ) {
			add_settings_error(
				self::OPTION,
				'ppwc_posting_locker_missing',
				__( 'Choose the parcel locker where you post parcels. The previous posting method was kept.', 'poczta-polska-for-woocommerce' )
			);
			$clean['posting_channel'] = self::all()['posting_channel'];
		}

		return array_intersect_key( $clean, self::defaults() );
	}

	/**
	 * Sanitizes the connection tab: environment, logins and encrypted passwords.
	 *
	 * An empty password field keeps the stored password. The "clear" checkbox removes it.
	 *
	 * @param array<mixed>          $input Submitted values.
	 * @param array<string, string> $clean Current values.
	 * @return array<string, string>
	 */
	private static function sanitize_connection( array $input, array $clean ): array {
		$environment          = sanitize_key( (string) ( $input['environment'] ?? $clean['environment'] ) );
		$clean['environment'] = isset( self::EN_ENDPOINTS[ $environment ] ) ? $environment : self::ENV_TEST;

		foreach ( array_keys( self::EN_ENDPOINTS ) as $env ) {
			$clean[ 'en_' . $env . '_login' ] = sanitize_text_field( (string) ( $input[ 'en_' . $env . '_login' ] ?? $clean[ 'en_' . $env . '_login' ] ) );

			// Passwords are stored as entered: sanitizing would change valid characters.
			$password = (string) ( $input[ 'en_' . $env . '_password' ] ?? '' );

			if ( ! empty( $input[ 'en_' . $env . '_password_clear' ] ) ) {
				$clean[ 'en_' . $env . '_password' ] = '';
			} elseif ( Crypto::is_encrypted( $password ) && '' !== Crypto::decrypt( $password ) ) {
				// WordPress runs the sanitize callback twice when the option is saved for the first time
				// (update_option() falls back to add_option()), so the second pass gets the
				// already encrypted value. Anything with the prefix that does not decrypt is treated
				// as a new password below.
				$clean[ 'en_' . $env . '_password' ] = $password;
			} elseif ( '' !== $password ) {
				$clean[ 'en_' . $env . '_password' ] = Crypto::encrypt( $password );
			}
		}

		return $clean;
	}
}
