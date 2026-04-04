<?php
/**
 * Unit tests for BadgeEngine scheduling logic.
 *
 * @package Gym_Core\Tests\Unit\Gamification
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Gamification;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Gamification\BadgeEngine;
use Gym_Core\Gamification\StreakTracker;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Tests for BadgeEngine scheduling and evaluation hooks.
 *
 * Note: BadgeEngine and StreakTracker are final classes with strict type hints.
 * These tests use real instances with stubbed dependencies for the scheduling
 * path, which only interacts with Action Scheduler functions.
 */
class BadgeEngineTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var BadgeEngine
	 */
	private BadgeEngine $sut;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stub functions used by StreakTracker constructor (get_option).
		Functions\when( 'get_option' )->justReturn( false );

		$attendance = Mockery::mock( AttendanceStore::class );
		$streaks    = new StreakTracker( $attendance );

		$this->sut = new BadgeEngine( $attendance, $streaks );
	}

	/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ---- schedule_checkin_evaluation ----

	/**
	 * @testdox Should skip scheduling for imported check-in records.
	 */
	public function test_schedule_skips_imported_records(): void {
		// Define Action Scheduler functions to verify they are NOT called.
		Functions\expect( 'as_enqueue_async_action' )->never();
		Functions\expect( 'as_has_scheduled_action' )->never();

		$this->sut->schedule_checkin_evaluation( 1, 42, 10, 'rockford', 'imported' );

		// Brain\Monkey verifies the 'never' expectations in tearDown.
		$this->assertTrue( true );
	}

	/**
	 * @testdox Should enqueue async action when Action Scheduler is available and no pending action.
	 */
	public function test_schedule_enqueues_async_action(): void {
		Functions\expect( 'as_has_scheduled_action' )
			->once()
			->with( 'gym_core_async_evaluate_badges', array( 42 ), 'gym-core' )
			->andReturn( false );

		Functions\expect( 'as_enqueue_async_action' )
			->once()
			->with( 'gym_core_async_evaluate_badges', array( 42 ), 'gym-core' )
			->andReturn( 1 );

		$this->sut->schedule_checkin_evaluation( 1, 42, 10, 'rockford', 'name_search' );

		$this->assertTrue( true );
	}

	/**
	 * @testdox Should skip enqueue when an evaluation is already pending for the user.
	 */
	public function test_schedule_deduplicates_pending_actions(): void {
		Functions\expect( 'as_has_scheduled_action' )
			->once()
			->with( 'gym_core_async_evaluate_badges', array( 42 ), 'gym-core' )
			->andReturn( true );

		// as_enqueue_async_action should NOT be called.
		Functions\expect( 'as_enqueue_async_action' )->never();

		$this->sut->schedule_checkin_evaluation( 1, 42, 10, 'rockford', 'name_search' );

		$this->assertTrue( true );
	}

	// Note: the synchronous fallback path (when Action Scheduler is unavailable)
	// cannot be unit-tested with Brain\Monkey because function stubs from earlier
	// tests persist across the test run. The fallback is a trivial else branch
	// that calls evaluate_on_checkin() directly — covered by integration tests.
}
