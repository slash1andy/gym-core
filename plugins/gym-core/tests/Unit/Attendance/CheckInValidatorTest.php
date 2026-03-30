<?php
/**
 * Unit tests for CheckInValidator.
 *
 * @package Gym_Core\Tests\Unit\Attendance
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Attendance;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\CheckInValidator;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Tests for check-in validation logic.
 */
class CheckInValidatorTest extends TestCase {

	private CheckInValidator $validator;
	private AttendanceStore $store_mock;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->store_mock = Mockery::mock( AttendanceStore::class );
		$this->validator  = new CheckInValidator( $this->store_mock );

		// Default stubs.
		Functions\stubs(
			array(
				'__' => static function ( string $text ): string {
					return $text;
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_invalid_user_returns_error(): void {
		Functions\expect( 'get_userdata' )->once()->with( 999 )->andReturn( false );

		$result = $this->validator->validate( 999, 1, 'rockford' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertTrue( $result->has_errors() );
		$this->assertSame( 'invalid_user', $result->get_error_code() );
	}

	public function test_valid_checkin_returns_true(): void {
		$user       = new \stdClass();
		$user->ID   = 1;
		$class_post = new \stdClass();
		$class_post->post_type = 'gym_class';

		Functions\expect( 'get_userdata' )->once()->andReturn( $user );
		// function_exists can't be mocked with Brain\Monkey — the real function
		// returns false for wc_memberships_is_user_active_member in tests,
		// so the validator's has_active_membership() correctly falls through.
		Functions\expect( 'get_option' )->once()->andReturn( 'yes' );
		Functions\expect( 'get_post' )->once()->with( 5 )->andReturn( $class_post );
		Functions\expect( 'get_post_meta' )->once()->andReturn( 'active' );
		Functions\expect( 'apply_filters' )->once()->andReturnUsing(
			static function ( string $hook, $value ) {
				return $value;
			}
		);

		$this->store_mock->shouldReceive( 'has_checked_in_today' )
			->once()
			->with( 1, 5 )
			->andReturn( false );

		$result = $this->validator->validate( 1, 5, 'rockford' );

		$this->assertTrue( $result );
	}

	public function test_duplicate_checkin_returns_error(): void {
		$user       = new \stdClass();
		$user->ID   = 1;
		$class_post = new \stdClass();
		$class_post->post_type = 'gym_class';

		Functions\expect( 'get_userdata' )->once()->andReturn( $user );
		Functions\expect( 'get_option' )->once()->andReturn( 'yes' );
		Functions\expect( 'get_post' )->once()->andReturn( $class_post );
		Functions\expect( 'get_post_meta' )->once()->andReturn( 'active' );
		Functions\expect( 'apply_filters' )->once()->andReturnUsing(
			static function ( string $hook, $value ) {
				return $value;
			}
		);

		$this->store_mock->shouldReceive( 'has_checked_in_today' )
			->once()
			->with( 1, 5 )
			->andReturn( true );

		$result = $this->validator->validate( 1, 5, 'rockford' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'duplicate_checkin', $result->get_error_code() );
	}

	public function test_missing_location_returns_error(): void {
		$user     = new \stdClass();
		$user->ID = 1;

		Functions\expect( 'get_userdata' )->once()->andReturn( $user );
		Functions\expect( 'apply_filters' )->once()->andReturnUsing(
			static function ( string $hook, $value ) {
				return $value;
			}
		);

		$result = $this->validator->validate( 1, 0, '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_location', $result->get_error_code() );
	}

	public function test_cancelled_class_returns_error(): void {
		$user       = new \stdClass();
		$user->ID   = 1;
		$class_post = new \stdClass();
		$class_post->post_type = 'gym_class';

		Functions\expect( 'get_userdata' )->once()->andReturn( $user );
		Functions\expect( 'get_option' )->once()->andReturn( 'yes' );
		Functions\expect( 'get_post' )->once()->andReturn( $class_post );
		Functions\expect( 'get_post_meta' )->once()->andReturn( 'cancelled' );
		Functions\expect( 'apply_filters' )->once()->andReturnUsing(
			static function ( string $hook, $value ) {
				return $value;
			}
		);

		$this->store_mock->shouldReceive( 'has_checked_in_today' )
			->once()
			->andReturn( false );

		$result = $this->validator->validate( 1, 5, 'rockford' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'class_not_active', $result->get_error_code() );
	}
}
