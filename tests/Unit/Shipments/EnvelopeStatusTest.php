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

/**
 * Envelope statuses.
 */
final class EnvelopeStatusTest extends TestCase {

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
}
