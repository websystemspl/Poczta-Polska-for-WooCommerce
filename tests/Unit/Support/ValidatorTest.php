<?php
/**
 * Tests of the validator.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PocztaPolska\WooCommerce\Support\Validator;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;

/**
 * Validator.
 */
final class ValidatorTest extends TestCase {

	/**
	 * Postcodes.
	 *
	 * @return array<string, array{string, string|null}>
	 */
	public static function postcodes(): array {
		return array(
			'with dash'    => array( '94-238', '94238' ),
			'without dash' => array( '00590', '00590' ),
			'spaces'       => array( ' 01-560 ', '01560' ),
			'too short'    => array( '0059', null ),
			'letters'      => array( 'AB-123', null ),
			'german'       => array( '10115', '10115' ),
			'empty'        => array( '', null ),
		);
	}

	#[DataProvider( 'postcodes' )]
	public function test_postcode( string $input, ?string $expected ): void {
		$this->assertSame( $expected, Validator::postcode( $input ) );
	}

	/**
	 * Mobile numbers.
	 *
	 * @return array<string, array{string, string|null}>
	 */
	public static function mobiles(): array {
		return array(
			'plain'           => array( '601302890', '601302890' ),
			'spaces'          => array( '601 302 890', '601302890' ),
			'dashes'          => array( '601-302-890', '601302890' ),
			'plus 48'         => array( '+48 601 302 890', '601302890' ),
			'0048'            => array( '0048601302890', '601302890' ),
			'48 without plus' => array( '48601302890', '601302890' ),
			'landline'        => array( '226203384', null ),
			'too short'       => array( '60130289', null ),
			'foreign'         => array( '+49 151 12345678', null ),
			'empty'           => array( '', null ),
		);
	}

	#[DataProvider( 'mobiles' )]
	public function test_mobile( string $input, ?string $expected ): void {
		$this->assertSame( $expected, Validator::mobile( $input ) );
	}

	public function test_phone(): void {
		$this->assertSame( '226203384', Validator::phone( '22 620-33-84' ) );
		$this->assertSame( '+48226203384', Validator::phone( '+48 22 620 33 84' ) );
		$this->assertNull( Validator::phone( 'abc' ) );
		$this->assertNull( Validator::phone( '123' ) );
	}

	/**
	 * Account numbers.
	 *
	 * @return array<string, array{string, string|null}>
	 */
	public static function account_numbers(): array {
		return array(
			'valid from EN tests' => array( '61109010140000071219812874', '61109010140000071219812874' ),
			'valid with spaces'   => array( '61 1090 1014 0000 0712 1981 2874', '61109010140000071219812874' ),
			'valid with PL'       => array( 'PL61109010140000071219812874', '61109010140000071219812874' ),
			'wrong checksum'      => array( '62109010140000071219812874', null ),
			'too short'           => array( '6110901014000007121981287', null ),
			'letters'             => array( '6110901014000007121981287A', null ),
		);
	}

	#[DataProvider( 'account_numbers' )]
	public function test_nrb( string $input, ?string $expected ): void {
		$this->assertSame( $expected, Validator::nrb( $input ) );
	}

	public function test_fits_counts_characters_not_bytes(): void {
		$this->assertTrue( Validator::fits( str_repeat( 'ż', 30 ), 30 ) );
		$this->assertFalse( Validator::fits( str_repeat( 'ż', 31 ), 30 ) );
	}
}
