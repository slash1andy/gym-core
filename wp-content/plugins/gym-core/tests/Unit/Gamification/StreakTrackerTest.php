<?php
/**
 * Unit tests for StreakTracker.
 *
 * @package Gym_Core\Tests\Unit\Gamification
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Gamification;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Gamification\StreakTracker;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Tests for streak calculation logic.
 */
class StreakTrackerTest extends TestCase {

	private StreakTracker $tracker;
	private AttendanceStore $store_mock;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->store_mock = Mockery::mock( AttendanceStore::class );
		$this->tracker    = new StreakTracker( $this->store_mock );

		Functions\stubs(
			array(
				'get_option'      => static function ( string $key, $default = false ) {
					if ( 'gym_core_streak_freezes_per_quarter' === $key ) {
						return 1;
					}
					return $default;
				},
				'get_user_meta'   => static function () {
					return '';
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_no_attendance_returns_zero_streak(): void {
		$this->store_mock->shouldReceive( 'get_attended_weeks' )->once()->andReturn( array() );

		$result = $this->tracker->get_streak( 1 );

		$this->assertSame( 0, $result['current_streak'] );
		$this->assertSame( 0, $result['longest_streak'] );
		$this->assertSame( 'broken', $result['streak_status'] );
		$this->assertNull( $result['last_check_in_date'] );
	}

	public function test_single_week_returns_streak_of_one(): void {
		$current_monday = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
		$this->store_mock->shouldReceive( 'get_attended_weeks' )
			->once()
			->andReturn( array( $current_monday ) );

		$result = $this->tracker->get_streak( 1 );

		$this->assertSame( 1, $result['current_streak'] );
		$this->assertSame( 1, $result['longest_streak'] );
		$this->assertSame( 'active', $result['streak_status'] );
	}

	public function test_consecutive_weeks_build_streak(): void {
		$monday = strtotime( 'monday this week' );
		$weeks  = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$weeks[] = gmdate( 'Y-m-d', $monday - ( $i * 7 * 86400 ) );
		}

		$this->store_mock->shouldReceive( 'get_attended_weeks' )
			->once()
			->andReturn( $weeks );

		$result = $this->tracker->get_streak( 1 );

		$this->assertSame( 5, $result['current_streak'] );
		$this->assertSame( 5, $result['longest_streak'] );
		$this->assertSame( 'active', $result['streak_status'] );
	}

	public function test_gap_breaks_streak(): void {
		$monday = strtotime( 'monday this week' );
		// This week and last week, then skip a week, then 2 more.
		$weeks = array(
			gmdate( 'Y-m-d', $monday ),
			gmdate( 'Y-m-d', $monday - ( 1 * 7 * 86400 ) ),
			// Gap: skip week 3.
			gmdate( 'Y-m-d', $monday - ( 3 * 7 * 86400 ) ),
			gmdate( 'Y-m-d', $monday - ( 4 * 7 * 86400 ) ),
		);

		$this->store_mock->shouldReceive( 'get_attended_weeks' )
			->once()
			->andReturn( $weeks );

		$result = $this->tracker->get_streak( 1 );

		$this->assertSame( 2, $result['current_streak'] );
		$this->assertSame( 2, $result['longest_streak'] );
	}

	public function test_old_streak_longer_than_current(): void {
		$monday = strtotime( 'monday this week' );
		// Current: 2 weeks. Old: 4 weeks (with a gap between them).
		$weeks = array(
			gmdate( 'Y-m-d', $monday ),
			gmdate( 'Y-m-d', $monday - ( 1 * 7 * 86400 ) ),
			// Gap.
			gmdate( 'Y-m-d', $monday - ( 5 * 7 * 86400 ) ),
			gmdate( 'Y-m-d', $monday - ( 6 * 7 * 86400 ) ),
			gmdate( 'Y-m-d', $monday - ( 7 * 7 * 86400 ) ),
			gmdate( 'Y-m-d', $monday - ( 8 * 7 * 86400 ) ),
		);

		$this->store_mock->shouldReceive( 'get_attended_weeks' )
			->once()
			->andReturn( $weeks );

		$result = $this->tracker->get_streak( 1 );

		$this->assertSame( 2, $result['current_streak'] );
		$this->assertSame( 4, $result['longest_streak'] );
	}

	public function test_last_week_counts_as_active(): void {
		$last_monday = gmdate( 'Y-m-d', strtotime( 'monday last week' ) );
		$this->store_mock->shouldReceive( 'get_attended_weeks' )
			->once()
			->andReturn( array( $last_monday ) );

		$result = $this->tracker->get_streak( 1 );

		$this->assertSame( 1, $result['current_streak'] );
		$this->assertSame( 'active', $result['streak_status'] );
	}

	public function test_freezes_remaining_defaults_to_one(): void {
		$this->store_mock->shouldReceive( 'get_attended_weeks' )->once()->andReturn( array() );

		$result = $this->tracker->get_streak( 1 );

		$this->assertSame( 1, $result['freezes_remaining'] );
	}
}
