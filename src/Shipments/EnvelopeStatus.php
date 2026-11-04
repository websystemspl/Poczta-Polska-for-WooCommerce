<?php
/**
 * Statuses of sent envelopes.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

defined( 'ABSPATH' ) || exit;

/**
 * Remembers the last status of each sent envelope (getEnvelopeStatus), so screens do not ask EN
 * on every page view.
 */
final class EnvelopeStatus {

	public const OPTION = 'ppwc_envelope_status';

	/**
	 * Statuses after which EN does not change the envelope any more.
	 */
	private const FINAL = array( 'PRZYJETY', 'BLEDNY' );

	/**
	 * Keeps only the newest entries per environment.
	 */
	private const LIMIT = 200;

	/**
	 * Returns the stored statuses of an environment.
	 *
	 * @param string $environment Environment.
	 * @return array<int, array{status: string, checked_at: int}> Envelope ID => status and time of the check.
	 */
	public static function all( string $environment ): array {
		$stored  = get_option( self::OPTION, array() );
		$entries = is_array( $stored ) && isset( $stored[ $environment ] ) && is_array( $stored[ $environment ] ) ? $stored[ $environment ] : array();
		$clean   = array();

		foreach ( $entries as $id => $entry ) {
			if ( is_array( $entry ) ) {
				$clean[ (int) $id ] = array(
					'status'     => (string) ( $entry['status'] ?? '' ),
					'checked_at' => (int) ( $entry['checked_at'] ?? 0 ),
				);
			}
		}

		return $clean;
	}

	/**
	 * Stores the status of an envelope.
	 *
	 * @param string $environment Environment.
	 * @param int    $envelope_id Envelope ID.
	 * @param string $status      Status from EN.
	 */
	public static function store( string $environment, int $envelope_id, string $status ): void {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$entries                 = isset( $stored[ $environment ] ) && is_array( $stored[ $environment ] ) ? $stored[ $environment ] : array();
		$entries[ $envelope_id ] = array(
			'status'     => $status,
			'checked_at' => time(),
		);

		krsort( $entries );
		$stored[ $environment ] = array_slice( $entries, 0, self::LIMIT, true );

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Whether EN can still change the status.
	 *
	 * @param string $status Status from EN.
	 */
	public static function is_final( string $status ): bool {
		return in_array( $status, self::FINAL, true );
	}

	/**
	 * Whether the status needs the shop manager's attention.
	 *
	 * @param string $status Status from EN.
	 */
	public static function is_error( string $status ): bool {
		return 'BLEDNY' === $status;
	}

	/**
	 * Status for the shop manager.
	 *
	 * @param string $status Status from EN, empty when not checked yet.
	 */
	public static function label( string $status ): string {
		return match ( $status ) {
			'WYSLANY'     => __( 'Sent', 'poczta-polska-for-woocommerce' ),
			'DOSTARCZONY' => __( 'Delivered', 'poczta-polska-for-woocommerce' ),
			'WALIDOWANY'  => __( 'Validated', 'poczta-polska-for-woocommerce' ),
			'PRZYJETY'    => __( 'Accepted', 'poczta-polska-for-woocommerce' ),
			'BLEDNY'      => __( 'Faulty: check the envelope in Elektroniczny Nadawca', 'poczta-polska-for-woocommerce' ),
			''            => __( 'Not checked yet', 'poczta-polska-for-woocommerce' ),
			default       => $status,
		};
	}
}
