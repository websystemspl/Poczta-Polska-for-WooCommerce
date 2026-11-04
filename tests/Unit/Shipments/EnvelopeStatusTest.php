<?php
/**
 * Tests of the envelope statuses.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Tests\Unit\Shipments;

use PocztaPolska\WooCommerce\Shipments\EnvelopeStatus;
use PocztaPolska\WooCommerce\Tests\Unit\TestCase;
use PocztaPolska\WooCommerce\Tests\Unit\WordPressStore;

/**
 * Envelope statuses.
 */
final class EnvelopeStatusTest extends TestCase {

	use WordPressStore;

	protected function setUp(): void {
		parent::setUp();
		$this->install_store();
	}

	protected function tearDown(): void {
		$this->uninstall_store();
		parent::tearDown();
	}

	public function test_accepted_and_faulty_envelopes_are_not_checked_again(): void {
		$this->assertTrue( EnvelopeStatus::is_final( 'PRZYJETY' ) );
		$this->assertTrue( EnvelopeStatus::is_final( 'BLEDNY' ) );
		$this->assertFalse( EnvelopeStatus::is_final( 'WYSLANY' ) );
		$this->assertFalse( EnvelopeStatus::is_final( '' ) );
	}

	public function test_only_faulty_envelopes_need_attention(): void {
		$this->assertTrue( EnvelopeStatus::is_error( 'BLEDNY' ) );
		$this->assertFalse( EnvelopeStatus::is_error( 'PRZYJETY' ) );
	}

	public function test_labels(): void {
		$this->assertSame( 'Sent', EnvelopeStatus::label( 'WYSLANY' ) );
		$this->assertSame( 'Not checked yet', EnvelopeStatus::label( '' ) );
		$this->assertSame( 'NOWY', EnvelopeStatus::label( 'NOWY' ), 'Unknown statuses are shown as EN sends them.' );
	}

	public function test_statuses_are_stored_per_environment(): void {
		EnvelopeStatus::store( 'test', 7817740, 'WYSLANY' );
		EnvelopeStatus::store( 'test', 7817740, 'PRZYJETY' );
		EnvelopeStatus::store( 'production', 100, 'WYSLANY' );

		$this->assertSame( array( 7817740 ), array_keys( EnvelopeStatus::all( 'test' ) ) );
		$this->assertSame( 'PRZYJETY', EnvelopeStatus::all( 'test' )[7817740]['status'] );
		$this->assertSame( array( 100 ), array_keys( EnvelopeStatus::all( 'production' ) ) );
	}

	public function test_only_the_newest_envelopes_are_kept(): void {
		for ( $id = 1; $id <= 205; $id++ ) {
			EnvelopeStatus::store( 'test', $id, 'WYSLANY' );
		}

		$ids = array_keys( EnvelopeStatus::all( 'test' ) );

		$this->assertCount( 200, $ids );
		$this->assertSame( 205, max( $ids ) );
		$this->assertSame( 6, min( $ids ) );
	}

	public function test_damaged_option_gives_no_statuses(): void {
		$this->options['ppwc_envelope_status'] = array( 'test' => array( 5 => 'WYSLANY' ) );

		$this->assertSame( array(), EnvelopeStatus::all( 'test' ) );
		$this->assertSame( array(), EnvelopeStatus::all( 'production' ) );
	}
}
