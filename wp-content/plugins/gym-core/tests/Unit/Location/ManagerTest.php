<?php
/**
 * Unit tests for Manager.
 *
 * @package Gym_Core\Tests\Unit\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Location;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Location\Manager;
use Gym_Core\Location\Taxonomy;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the location Manager class.
 */
class ManagerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var Manager
	 */
	private Manager $sut;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Passthrough stubs for common WP utility functions.
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->returnArg( 1 );

		// Stub cache to return location labels so Taxonomy::get_location_labels()
		// never reaches get_terms / is_wp_error during is_valid() calls.
		Functions\when( 'wp_cache_get' )->justReturn(
			array(
				'rockford' => 'Rockford',
				'beloit'   => 'Beloit',
			)
		);
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_cache_delete' )->justReturn( true );

		$this->sut = new Manager();
	}

	/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Clean up any cookie state modified during tests.
		unset( $_COOKIE[ Manager::COOKIE_NAME ] );

		Monkey\tearDown();
		parent::tearDown();
	}

	// ----- get_current_location -----

	/**
	 * @testdox Should return empty string when no cookie and no login.
	 */
	public function test_get_current_location_returns_empty_with_no_signals(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		unset( $_COOKIE[ Manager::COOKIE_NAME ] );

		$result = $this->sut->get_current_location();

		$this->assertSame( '', $result );
	}

	/**
	 * @testdox Should return location slug from a valid cookie for a guest visitor.
	 */
	public function test_get_current_location_reads_from_cookie_for_guest(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$_COOKIE[ Manager::COOKIE_NAME ] = Taxonomy::ROCKFORD;

		$result = $this->sut->get_current_location();

		$this->assertSame( Taxonomy::ROCKFORD, $result );
	}

	/**
	 * @testdox Should return empty string when cookie contains an invalid slug.
	 */
	public function test_get_current_location_rejects_invalid_cookie(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$_COOKIE[ Manager::COOKIE_NAME ] = 'chicago';

		$result = $this->sut->get_current_location();

		$this->assertSame( '', $result );
	}

	/**
	 * @testdox Should prefer user meta over cookie for a logged-in user.
	 */
	public function test_get_current_location_prefers_user_meta_over_cookie(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\when( 'get_user_meta' )->justReturn( Taxonomy::BELOIT );

		// Cookie holds a different (also valid) location.
		$_COOKIE[ Manager::COOKIE_NAME ] = Taxonomy::ROCKFORD;

		$result = $this->sut->get_current_location();

		$this->assertSame( Taxonomy::BELOIT, $result );
	}

	/**
	 * @testdox Should fall back to cookie when user meta is empty for a logged-in user.
	 */
	public function test_get_current_location_falls_back_to_cookie_for_logged_in_user(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$_COOKIE[ Manager::COOKIE_NAME ] = Taxonomy::BELOIT;

		$result = $this->sut->get_current_location();

		$this->assertSame( Taxonomy::BELOIT, $result );
	}

	/**
	 * @testdox Should cache the resolved location so subsequent calls are consistent.
	 */
	public function test_get_current_location_is_cached_within_request(): void {
		// is_user_logged_in is only called once even across two get_current_location() calls.
		Functions\expect( 'is_user_logged_in' )
			->once()
			->andReturn( false );

		$_COOKIE[ Manager::COOKIE_NAME ] = Taxonomy::ROCKFORD;

		$first  = $this->sut->get_current_location();
		$second = $this->sut->get_current_location();

		$this->assertSame( $first, $second );
	}

	/**
	 * @testdox reset_cache should cause get_current_location to re-resolve on the next call.
	 */
	public function test_reset_cache_clears_cached_location(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$_COOKIE[ Manager::COOKIE_NAME ] = Taxonomy::ROCKFORD;
		$first = $this->sut->get_current_location();

		$this->sut->reset_cache();

		$_COOKIE[ Manager::COOKIE_NAME ] = Taxonomy::BELOIT;
		$second = $this->sut->get_current_location();

		$this->assertSame( Taxonomy::ROCKFORD, $first );
		$this->assertSame( Taxonomy::BELOIT, $second );
	}

	// ----- set_location -----

	/**
	 * @testdox Should return true when setting a valid rockford slug.
	 */
	public function test_set_location_returns_true_for_rockford(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'headers_sent' )->justReturn( true ); // Prevent setcookie() call.

		$result = $this->sut->set_location( Taxonomy::ROCKFORD );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox Should return true when setting a valid beloit slug.
	 */
	public function test_set_location_returns_true_for_beloit(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'headers_sent' )->justReturn( true );

		$result = $this->sut->set_location( Taxonomy::BELOIT );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox Should return false for an unrecognised slug.
	 *
	 * @dataProvider invalid_location_provider
	 */
	public function test_set_location_returns_false_for_invalid_slug( string $slug ): void {
		$result = $this->sut->set_location( $slug );

		$this->assertFalse( $result );
	}

	/**
	 * Data provider for invalid location slugs.
	 *
	 * @return array<string, array<string>>
	 */
	public function invalid_location_provider(): array {
		return array(
			'empty string'  => array( '' ),
			'unknown city'  => array( 'chicago' ),
			'uppercase'     => array( 'Rockford' ),
			'with space'    => array( 'rock ford' ),
			'sql injection' => array( "'; DROP TABLE--" ),
		);
	}

	/**
	 * @testdox Should update get_current_location immediately after a successful set.
	 */
	public function test_set_location_updates_current_location(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'headers_sent' )->justReturn( true );

		$this->sut->set_location( Taxonomy::BELOIT );

		$this->assertSame( Taxonomy::BELOIT, $this->sut->get_current_location() );
	}

	/**
	 * @testdox Should save to user meta when user is logged in.
	 */
	public function test_set_location_saves_user_meta_for_logged_in_user(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\when( 'headers_sent' )->justReturn( true );

		Functions\expect( 'update_user_meta' )
			->once()
			->with( 7, Manager::USER_META_KEY, Taxonomy::ROCKFORD );

		$this->sut->set_location( Taxonomy::ROCKFORD );

		// Brain\Monkey verifies expectations in tearDown; explicit assertion for PHPUnit.
		$this->assertTrue( true );
	}

	// ----- set_user_location -----

	/**
	 * @testdox Should return false when user ID is zero.
	 */
	public function test_set_user_location_returns_false_for_zero_user_id(): void {
		$result = $this->sut->set_user_location( 0, Taxonomy::ROCKFORD );

		$this->assertFalse( $result );
	}

	/**
	 * @testdox Should return false when user ID is negative.
	 */
	public function test_set_user_location_returns_false_for_negative_user_id(): void {
		$result = $this->sut->set_user_location( -1, Taxonomy::BELOIT );

		$this->assertFalse( $result );
	}

	/**
	 * @testdox Should return false for an invalid location slug.
	 */
	public function test_set_user_location_returns_false_for_invalid_slug(): void {
		$result = $this->sut->set_user_location( 5, 'invalid' );

		$this->assertFalse( $result );
	}

	/**
	 * @testdox Should return true and call update_user_meta for valid inputs.
	 */
	public function test_set_user_location_returns_true_and_saves_meta(): void {
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 5, Manager::USER_META_KEY, Taxonomy::BELOIT );

		$result = $this->sut->set_user_location( 5, Taxonomy::BELOIT );

		$this->assertTrue( $result );
	}

	// ----- get_user_location -----

	/**
	 * @testdox Should return the stored location for a user.
	 */
	public function test_get_user_location_returns_stored_meta(): void {
		Functions\when( 'get_user_meta' )->justReturn( Taxonomy::ROCKFORD );

		$result = $this->sut->get_user_location( 3 );

		$this->assertSame( Taxonomy::ROCKFORD, $result );
	}

	/**
	 * @testdox Should return empty string when no user meta is stored.
	 */
	public function test_get_user_location_returns_empty_when_no_meta(): void {
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$result = $this->sut->get_user_location( 3 );

		$this->assertSame( '', $result );
	}
}
