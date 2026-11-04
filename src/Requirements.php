<?php
/**
 * Environment requirement checks.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the server environment the plugin depends on.
 */
final class Requirements {

	/**
	 * First release of each PHP branch with the fixes for CVE-2026-109618 and
	 * CVE-2026-109619 ("PHP2Shell", 21.10.2026).
	 *
	 * Update this list with every PHP security release.
	 *
	 * @var array<string, string>
	 */
	public const PATCHED_PHP_VERSIONS = array(
		'8.2' => '8.2.35',
		'8.3' => '8.3.36',
		'8.4' => '8.4.27',
		'8.5' => '8.5.12',
	);

	/**
	 * PHP version being checked.
	 *
	 * @var string
	 */
	private string $php_version;

	/**
	 * Constructor.
	 *
	 * @param string|null $php_version PHP version to check. Defaults to the running version.
	 */
	public function __construct( ?string $php_version = null ) {
		$this->php_version = $php_version ?? PHP_VERSION;
	}

	/**
	 * Whether the SOAP extension needed by the Elektroniczny Nadawca client is loaded.
	 */
	public function has_soap(): bool {
		return extension_loaded( 'soap' ) && class_exists( \SoapClient::class );
	}

	/**
	 * Returns the patched version for the PHP branch in use, or null when the
	 * branch has no known security release to compare against.
	 */
	public function patched_php_version(): ?string {
		$parts  = explode( '.', $this->php_version );
		$branch = $parts[0] . '.' . ( $parts[1] ?? '0' );

		return self::PATCHED_PHP_VERSIONS[ $branch ] ?? null;
	}

	/**
	 * Whether the PHP version includes the latest known security fixes.
	 *
	 * Branches newer than the list are treated as patched. Linux distributions
	 * backport fixes without changing the version number, so a false result is
	 * a warning for the administrator, never a reason to stop working.
	 */
	public function is_php_patched(): bool {
		$patched = $this->patched_php_version();

		if ( null !== $patched ) {
			return version_compare( $this->php_version, $patched, '>=' );
		}

		$newest = (string) array_key_last( self::PATCHED_PHP_VERSIONS );

		return version_compare( $this->php_version, $newest, '>' );
	}

	/**
	 * Returns the PHP version being checked.
	 */
	public function php_version(): string {
		return $this->php_version;
	}
}
