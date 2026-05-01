<?php
/**
 * Unit tests for SmsOptOut.
 *
 * @package Gym_Core\Tests\Unit\SMS
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\SMS;

use Gym_Core\SMS\SmsOptOut;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for TCPA opt-out storage and lookup.
 */
class SmsOptOutTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- is_opted_out ---

	public function test_not_opted_out_when_option_is_empty(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '[]' );

		$opt_out = new SmsOptOut();
		$this->assertFalse( $opt_out->is_opted_out( '+15551234567' ) );
	}

	public function test_opted_out_when_phone_is_in_store(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '["' . '+15551234567' . '"]' );

		$opt_out = new SmsOptOut();
		$this->assertTrue( $opt_out->is_opted_out( '+15551234567' ) );
	}

	public function test_not_opted_out_for_different_phone(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '["+15559999999"]' );

		$opt_out = new SmsOptOut();
		$this->assertFalse( $opt_out->is_opted_out( '+15551234567' ) );
	}

	public function test_empty_phone_returns_false(): void {
		$opt_out = new SmsOptOut();
		// No get_option call expected because sanitize_phone returns '' first.
		$this->assertFalse( $opt_out->is_opted_out( '' ) );
	}

	// --- handle_opt_out ---

	public function test_handle_opt_out_adds_phone_to_store(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '[]' );

		Functions\expect( 'update_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, \Mockery::type( 'string' ) );

		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( fn( $v ) => json_encode( $v ) );

		$opt_out = new SmsOptOut();
		$opt_out->handle_opt_out( '+15551234567' );
	}

	public function test_handle_opt_out_does_not_duplicate(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '["+15551234567"]' );

		// update_option should NOT be called — no change needed.
		Functions\expect( 'update_option' )->never();

		$opt_out = new SmsOptOut();
		$opt_out->handle_opt_out( '+15551234567' );
	}

	// --- handle_opt_in ---

	public function test_handle_opt_in_removes_phone_from_store(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '["+15551234567"]' );

		Functions\expect( 'update_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, \Mockery::type( 'string' ) );

		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( fn( $v ) => json_encode( $v ) );

		$opt_out = new SmsOptOut();
		$opt_out->handle_opt_in( '+15551234567' );
	}

	public function test_handle_opt_in_no_update_when_not_opted_out(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '[]' );

		Functions\expect( 'update_option' )->never();

		$opt_out = new SmsOptOut();
		$opt_out->handle_opt_in( '+15551234567' );
	}

	// --- Phone normalisation ---

	public function test_raw_us_phone_is_normalised_before_lookup(): void {
		// '5551234567' should normalise to '+15551234567'.
		Functions\expect( 'get_option' )
			->once()
			->with( SmsOptOut::OPTION_KEY, '[]' )
			->andReturn( '["+15551234567"]' );

		$opt_out = new SmsOptOut();
		$this->assertTrue( $opt_out->is_opted_out( '5551234567' ) );
	}
}
