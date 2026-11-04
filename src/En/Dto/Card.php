<?php
/**
 * Contract card.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\En\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Contract card (karta umowy) from getKarty.
 */
final class Card {

	/**
	 * Card type used for sending shipments.
	 */
	public const TYPE_SHIPPING = 1;

	/**
	 * Constructor.
	 *
	 * @param int                                       $id          Card ID (idKarta).
	 * @param string                                    $description Card description (opis).
	 * @param bool                                      $active      Whether the card is the active card of the EN user (aktywna).
	 * @param int                                       $type        Card type: 1 for shipping, 2 for OPNA addresses.
	 * @param list<array{symbol: string, name: string}> $products    Products available on the card.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $description,
		public readonly bool $active,
		public readonly int $type,
		public readonly array $products,
	) {
	}

	/**
	 * Whether shipments can be sent with this card.
	 */
	public function is_shipping_card(): bool {
		return self::TYPE_SHIPPING === $this->type;
	}

	/**
	 * Whether the card has a product with the given symbol (symbolIWD), e.g. "855" for Pocztex.
	 *
	 * @param string $symbol Product symbol.
	 */
	public function has_product( string $symbol ): bool {
		foreach ( $this->products as $product ) {
			if ( $product['symbol'] === $symbol ) {
				return true;
			}
		}

		return false;
	}
}
