<?php
/**
 * Unit tests for MilestoneTracker async scheduling logic.
 *
 * @package Gym_Core\Tests\Unit\Attendance
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Attendance;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\MilestoneTracker;
use Mockery;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MilestoneTracker scheduling hooks.
 *
 * Mirrors BadgeEngineTest — verifies milestone evaluation is deferred to
 * Action Scheduler so the synchronous count-query work no longer blocks
 * the gym_core_attendance_recorded hook.
 */
class MilestoneTrackerAsyncTest extends TestCase {

	private MilestoneTracker $sut;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$attendance = Mockery::mock( AttendanceStore::class );
		$this->sut  = new MilestoneTracker( $attendance );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	#[TestDox( 'Skips scheduling for imported check-in records.' )]
	public function test_skips_imported_records(): void {
		Functions\expect( 'as_enqueue_async_action' )->never();
		Functions\expect( 'as_has_scheduled_action' )->never();

		$this->sut->check_milestones( 1, 42, 10, 'rockford', 'imported' );

		$this->assertTrue( true );
	}

	#[TestDox( 'Enqueues async action when Action Scheduler is available and no evaluation is pending.' )]
	public function test_enqueues_async_action(): void {
		Functions\expect( 'as_has_scheduled_action' )
			->once()
			->with( 'gym_core_evaluate_milestones', array( 42 ), 'gym-core' )
			->andReturn( false );

		Functions\expect( 'as_enqueue_async_action' )
			->once()
			->with( 'gym_core_evaluate_milestones', array( 42 ), 'gym-core' )
			->andReturn( 1 );

		$this->sut->check_milestones( 1, 42, 10, 'rockford', 'name_search' );

		$this->assertTrue( true );
	}

	#[TestDox( 'Skips enqueue when an evaluation is already pending for the user.' )]
	public function test_deduplicates_pending_actions(): void {
		Functions\expect( 'as_has_scheduled_action' )
			->once()
			->with( 'gym_core_evaluate_milestones', array( 42 ), 'gym-core' )
			->andReturn( true );

		Functions\expect( 'as_enqueue_async_action' )->never();

		$this->sut->check_milestones( 1, 42, 10, 'rockford', 'name_search' );

		$this->assertTrue( true );
	}
}
