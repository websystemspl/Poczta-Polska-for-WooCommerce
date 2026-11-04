<?php
/**
 * Buffers created by the plugin.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Shipments;

use PocztaPolska\WooCommerce\En\ClientInterface;
use PocztaPolska\WooCommerce\En\Dto\Buffer;
use PocztaPolska\WooCommerce\En\Exception\EnException;
use PocztaPolska\WooCommerce\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the list of buffers the plugin created and picks the buffer for new shipments:
 * one per card, posting office, sender profile and day.
 *
 * Other users and integrations may work on the same EN account, so the plugin only
 * touches buffers it created (PP answer of 24.09.2026, no. 4).
 */
final class BufferService {

	public const OPTION = 'ppwc_buffers';

	/**
	 * Prefix of the buffer description in EN.
	 */
	private const DESCRIPTION = 'WooCommerce';

	/**
	 * EN client.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $client;

	/**
	 * Environment.
	 *
	 * @var string
	 */
	private string $environment;

	/**
	 * Constructor.
	 *
	 * @param ClientInterface $client      EN client of the environment.
	 * @param string          $environment Environment.
	 */
	public function __construct( ClientInterface $client, string $environment ) {
		$this->client      = $client;
		$this->environment = $environment;
	}

	/**
	 * Returns today's buffer for a card and a posting office, creating it when needed.
	 *
	 * @param int $card_id   Contract card.
	 * @param int $office_id Posting office (OPZ II.2.4.d), 0 for the default office from the settings.
	 * @throws ShipmentException When the posting office is not set or EN cannot create the buffer.
	 */
	public function buffer_for_card( int $card_id, int $office_id = 0 ): Buffer {
		$office  = $office_id > 0 ? $office_id : (int) Settings::get_for_env( 'posting_office_id', $this->environment );
		$profile = (int) Settings::get_for_env( 'profile_id', $this->environment );

		if ( $card_id <= 0 || $office <= 0 ) {
			throw new ShipmentException( esc_html__( 'Choose the contract card and the posting office in the Poczta Polska settings (tab "Contract and posting").', 'poczta-polska-for-woocommerce' ) );
		}

		$today = wp_date( 'Y-m-d' );

		foreach ( $this->all() as $buffer ) {
			if ( $buffer->card_id === $card_id && $buffer->office_id === $office && $buffer->profile_id === $profile && $buffer->date === $today ) {
				return $buffer;
			}
		}

		$buffer = new Buffer( 0, $card_id, $office, $profile, (string) $today, self::DESCRIPTION . ' ' . $today );

		try {
			$id = $this->client->create_buffer( $buffer );
		} catch ( EnException $exception ) {
			throw new ShipmentException( esc_html( html_entity_decode( $exception->getMessage(), ENT_QUOTES, 'UTF-8' ) ), 0, $exception ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous exception, not output.
		}
		$buffer = new Buffer( $id, $card_id, $office, $profile, (string) $today, $buffer->description );

		$this->remember( $buffer );

		return $buffer;
	}

	/**
	 * Returns the buffers created by the plugin in this environment.
	 *
	 * @return array<int, Buffer> Buffer ID => buffer.
	 */
	public function all(): array {
		$stored  = get_option( self::OPTION, array() );
		$entries = is_array( $stored ) && isset( $stored[ $this->environment ] ) && is_array( $stored[ $this->environment ] ) ? $stored[ $this->environment ] : array();
		$buffers = array();

		foreach ( $entries as $id => $entry ) {
			if ( is_array( $entry ) ) {
				$buffers[ (int) $id ] = new Buffer(
					(int) $id,
					(int) ( $entry['card_id'] ?? 0 ),
					(int) ( $entry['office_id'] ?? 0 ),
					(int) ( $entry['profile_id'] ?? 0 ),
					(string) ( $entry['date'] ?? '' ),
					(string) ( $entry['description'] ?? '' )
				);
			}
		}

		return $buffers;
	}

	/**
	 * Returns a buffer created by the plugin.
	 *
	 * @param int $id Buffer ID.
	 * @throws ShipmentException When the plugin did not create the buffer.
	 */
	public function get( int $id ): Buffer {
		$buffers = $this->all();

		if ( ! isset( $buffers[ $id ] ) ) {
			throw new ShipmentException( esc_html__( 'The buffer was not found among the buffers created by the plugin.', 'poczta-polska-for-woocommerce' ) );
		}

		return $buffers[ $id ];
	}

	/**
	 * Stores a buffer.
	 *
	 * @param Buffer $buffer Buffer.
	 */
	public function remember( Buffer $buffer ): void {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$stored[ $this->environment ][ $buffer->id ] = array(
			'card_id'     => $buffer->card_id,
			'office_id'   => $buffer->office_id,
			'profile_id'  => $buffer->profile_id,
			'date'        => $buffer->date,
			'description' => $buffer->description,
		);

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Forgets a buffer (after it was sent or deleted).
	 *
	 * @param int $id Buffer ID.
	 */
	public function forget( int $id ): void {
		$stored = get_option( self::OPTION, array() );

		if ( is_array( $stored ) && isset( $stored[ $this->environment ][ $id ] ) ) {
			unset( $stored[ $this->environment ][ $id ] );
			update_option( self::OPTION, $stored, false );
		}
	}
}
