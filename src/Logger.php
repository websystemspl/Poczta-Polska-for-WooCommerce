<?php
/**
 * Logging to the WooCommerce log.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Writes plugin events to the WooCommerce log with sensitive data masked.
 */
final class Logger {

	public const SOURCE = 'poczta-polska';

	private const MASK = '***';

	/**
	 * Context keys whose values never reach the log (compared case-insensitively).
	 *
	 * @var string[]
	 */
	private const SENSITIVE_KEYS = array(
		'password',
		'haslo',
		'pass',
		'authorization',
		'nrb',
		'iban',
		'email',
		'telefon',
		'mobile',
		'phone',
		'nazwa',
		'nazwa2',
		'ulica',
		'numerdomu',
		'numerlokalu',
		'first_name',
		'last_name',
		'address_1',
		'address_2',
	);

	/**
	 * Logs a message.
	 *
	 * @param string               $level   PSR-3 level: emergency, alert, critical, error, warning, notice, info, debug.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Extra data, masked before writing.
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) || ! self::is_enabled( $level ) ) {
			return;
		}

		$context           = self::mask( $context );
		$context['source'] = self::SOURCE;

		wc_get_logger()->log( $level, $message, $context );
	}

	/**
	 * Logs an error.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Extra data.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Logs debug details.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Extra data.
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( 'debug', $message, $context );
	}

	/**
	 * Whether a level passes the "Log level" setting.
	 *
	 * Reads the option directly: Settings::all() builds translated defaults, which must
	 * not run before init.
	 *
	 * @param string $level PSR-3 level.
	 */
	public static function is_enabled( string $level ): bool {
		$severity = array(
			'debug'     => 0,
			'info'      => 1,
			'notice'    => 1,
			'warning'   => 1,
			'error'     => 2,
			'critical'  => 2,
			'alert'     => 2,
			'emergency' => 2,
		);
		$settings = get_option( Settings::OPTION, array() );
		$minimum  = is_array( $settings ) ? (string) ( $settings['log_level'] ?? 'error' ) : 'error';

		return ( $severity[ $level ] ?? 2 ) >= ( $severity[ $minimum ] ?? 2 );
	}

	/**
	 * Replaces sensitive values in a (nested) array.
	 *
	 * @param array<mixed> $data Data to mask.
	 * @return array<mixed>
	 */
	public static function mask( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && in_array( strtolower( $key ), self::SENSITIVE_KEYS, true ) ) {
				$data[ $key ] = self::MASK;
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = self::mask( $value );
			}
		}

		return $data;
	}
}
