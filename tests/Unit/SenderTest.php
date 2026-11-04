<?php
/**
 * Tests of the sender details.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PocztaPolska\WooCommerce\Sender;

/**
 * Sender.
 */
final class SenderTest extends TestCase {

	/**
	 * Addresses.
	 *
	 * @return array<string, array{string, string, string, string}>
	 */
	public static function addresses(): array {
		return array(
			'street prefix'   => array( 'ul. Testowa 1', 'ul. Testowa', '1', '' ),
			'flat with slash' => array( 'Długa 12A/5', 'Długa', '12A', '5' ),
			'flat with m.'    => array( 'Długa 12 m. 5', 'Długa', '12', '5' ),
			'comma'           => array( 'Aleja Wyzwolenia, 81', 'Aleja Wyzwolenia', '81', '' ),
			'multi-word'      => array( 'Plac Defilad 1', 'Plac Defilad', '1', '' ),
			'no number'       => array( 'Rynek', 'Rynek', '', '' ),
			'extra spaces'    => array( '  Pługowa   39 ', 'Pługowa', '39', '' ),
		);
	}

	#[DataProvider( 'addresses' )]
	public function test_split_address( string $address, string $street, string $house, string $flat ): void {
		$this->assertSame(
			array(
				'street'       => $street,
				'house_number' => $house,
				'flat_number'  => $flat,
			),
			Sender::split_address( $address )
		);
	}
}
