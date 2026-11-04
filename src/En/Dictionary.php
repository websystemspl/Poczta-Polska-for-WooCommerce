<?php
/**
 * Cached Elektroniczny Nadawca dictionaries.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En;

use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Contract cards and posting offices, cached per environment and login.
 *
 * They change rarely, and the settings screen and shipping methods need them on
 * every load, so they are kept in transients as plain arrays.
 */
final class Dictionary {

	private const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Transient name prefix. uninstall.php removes all transients with this prefix.
	 */
	public const TRANSIENT_PREFIX = 'ppwc_dict_';

	/**
	 * Last error per dictionary, for the settings screen.
	 *
	 * @var array<string, string>
	 */
	private static array $errors = array();

	/**
	 * Returns the shipping contract cards (type 1): ID => description.
	 *
	 * @param string|null $environment Environment, the active one by default.
	 * @return array<int, array{description: string, active: bool, products: list<string>}>
	 */
	public static function cards( ?string $environment = null ): array {
		$cards = self::remember(
			'cards',
			$environment,
			static function ( ClientInterface $client ): array {
				$cards = array();

				foreach ( $client->cards() as $card ) {
					if ( ! $card->is_shipping_card() ) {
						continue;
					}

					$cards[ $card->id ] = array(
						'description' => $card->description,
						'active'      => $card->active,
						'products'    => array_values( array_unique( array_column( $card->products, 'symbol' ) ) ),
					);
				}

				return $cards;
			}
		);

		$clean = array();

		foreach ( $cards ?? array() as $id => $card ) {
			if ( is_int( $id ) && is_array( $card ) ) {
				$clean[ $id ] = array(
					'description' => (string) ( $card['description'] ?? '' ),
					'active'      => ! empty( $card['active'] ),
					'products'    => array_values( array_map( 'strval', (array) ( $card['products'] ?? array() ) ) ),
				);
			}
		}

		return $clean;
	}

	/**
	 * Returns the posting offices: ID => name printed on labels.
	 *
	 * @param string|null $environment Environment, the active one by default.
	 * @return array<int, array{description: string, print_name: string}>
	 */
	public static function posting_offices( ?string $environment = null ): array {
		$offices = self::remember(
			'offices',
			$environment,
			static function ( ClientInterface $client ): array {
				$offices = array();

				foreach ( $client->posting_offices() as $office ) {
					$offices[ $office->id ] = array(
						'description' => $office->description,
						'print_name'  => $office->print_name,
					);
				}

				return $offices;
			}
		);

		$clean = array();

		foreach ( $offices ?? array() as $id => $office ) {
			if ( is_int( $id ) && is_array( $office ) ) {
				$clean[ $id ] = array(
					'description' => (string) ( $office['description'] ?? '' ),
					'print_name'  => (string) ( $office['print_name'] ?? '' ),
				);
			}
		}

		return $clean;
	}

	/**
	 * Returns the sender profiles: ID => name and address.
	 *
	 * @param string|null $environment Environment, the active one by default.
	 * @return array<int, array{name: string, address: string}>
	 */
	public static function profiles( ?string $environment = null ): array {
		$profiles = self::remember(
			'profiles',
			$environment,
			static function ( ClientInterface $client ): array {
				$profiles = array();

				foreach ( $client->profiles() as $profile ) {
					$profiles[ $profile->id ] = array(
						'name'    => '' !== $profile->short_name ? $profile->short_name . ' — ' . $profile->name : $profile->name,
						'address' => $profile->address,
					);
				}

				return $profiles;
			}
		);

		$clean = array();

		foreach ( $profiles ?? array() as $id => $profile ) {
			if ( is_int( $id ) && is_array( $profile ) ) {
				$clean[ $id ] = array(
					'name'    => (string) ( $profile['name'] ?? '' ),
					'address' => (string) ( $profile['address'] ?? '' ),
				);
			}
		}

		return $clean;
	}

	/**
	 * Removes the cached dictionaries of an environment.
	 *
	 * @param string|null $environment Environment, the active one by default.
	 */
	public static function flush( ?string $environment = null ): void {
		foreach ( array( 'cards', 'offices', 'profiles' ) as $name ) {
			delete_transient( self::transient_name( $name, $environment ?? Settings::environment() ) );
		}
	}

	/**
	 * Returns the error of the last failed load of a dictionary, empty when none.
	 *
	 * @param string $name Dictionary: cards or offices.
	 */
	public static function error( string $name ): string {
		return self::$errors[ $name ] ?? '';
	}

	/**
	 * Returns a cached dictionary, loading it from EN when missing.
	 *
	 * Failures are not cached, so the next screen load tries again.
	 * Callers normalize the result, because a transient can hold anything.
	 *
	 * @param string      $name        Dictionary name.
	 * @param string|null $environment Environment.
	 * @param callable    $load        Loader.
	 * @phpstan-param callable(ClientInterface): array<mixed> $load
	 * @return array<mixed>|null Null when EN could not be reached, see error().
	 */
	private static function remember( string $name, ?string $environment, callable $load ): ?array {
		$environment = $environment ?? Settings::environment();
		$transient   = self::transient_name( $name, $environment );
		$cached      = get_transient( $transient );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$data = $load( ClientFactory::create( $environment ) );
		} catch ( EnException $exception ) {
			self::$errors[ $name ] = $exception->getMessage();

			return null;
		}

		set_transient( $transient, $data, self::TTL );

		return $data;
	}

	/**
	 * Transient name, specific to the environment and login, so new credentials never see old data.
	 *
	 * @param string $name        Dictionary name.
	 * @param string $environment Environment.
	 */
	private static function transient_name( string $name, string $environment ): string {
		return self::TRANSIENT_PREFIX . $name . '_' . $environment . '_' . substr( md5( Settings::get( 'en_' . $environment . '_login' ) ), 0, 8 );
	}
}
