<?php
/**
 * Unit tests for the gym/targeted-content block render callback.
 *
 * @package Gym_Core\Tests\Unit\Gamification
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Gamification;

use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Gamification\BadgeEngine;
use Gym_Core\Gamification\StreakTracker;
use Gym_Core\Gamification\TargetedContent;
use Gym_Core\Location\Manager as LocationManager;
use Gym_Core\Rank\RankStore;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Tests for TargetedContent::render_block().
 */
class TargetedContentBlockTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var TargetedContent
	 */
	private TargetedContent $sut;

	/**
	 * Mock rank store.
	 *
	 * @var RankStore|Mockery\MockInterface
	 */
	private $rank_store;

	/**
	 * Mock attendance store.
	 *
	 * @var AttendanceStore|Mockery\MockInterface
	 */
	private $attendance_store;

	/**
	 * Mock location manager.
	 *
	 * @var LocationManager|Mockery\MockInterface
	 */
	private $location_manager;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_option' )->justReturn( false );

		$this->rank_store       = Mockery::mock( RankStore::class );
		$this->attendance_store = Mockery::mock( AttendanceStore::class );
		$this->location_manager = Mockery::mock( LocationManager::class );

		$streaks      = new StreakTracker( $this->attendance_store );
		$badges       = new BadgeEngine( $this->attendance_store, $streaks );
		$foundations   = new FoundationsClearance( $this->attendance_store );

		$this->sut = new TargetedContent(
			$this->rank_store,
			$this->attendance_store,
			$streaks,
			$badges,
			$foundations,
			$this->location_manager
		);
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

	/**
	 * @test
	 */
	public function render_block_returns_content_when_no_rules_set(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$attributes = array(
			'loggedIn'        => false,
			'membersOnly'     => false,
			'foundationsOnly' => false,
			'program'         => '',
			'minBelt'         => '',
			'location'        => '',
			'minClasses'      => 0,
			'minStreak'       => 0,
			'fallback'        => '',
		);

		$result = $this->sut->render_block( $attributes, '<p>Hello</p>' );

		$this->assertSame( '<p>Hello</p>', $result );
	}

	/**
	 * @test
	 */
	public function render_block_hides_content_for_logged_out_user_when_logged_in_required(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$attributes = array(
			'loggedIn'        => true,
			'membersOnly'     => false,
			'foundationsOnly' => false,
			'program'         => '',
			'minBelt'         => '',
			'location'        => '',
			'minClasses'      => 0,
			'minStreak'       => 0,
			'fallback'        => '',
		);

		$result = $this->sut->render_block( $attributes, '<p>Secret</p>' );

		$this->assertSame( '', $result );
	}

	/**
	 * @test
	 */
	public function render_block_shows_fallback_when_rules_not_met(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );
		Functions\when( 'esc_html' )->alias( function ( $text ) { return $text; } );

		$attributes = array(
			'loggedIn'        => true,
			'membersOnly'     => false,
			'foundationsOnly' => false,
			'program'         => '',
			'minBelt'         => '',
			'location'        => '',
			'minClasses'      => 0,
			'minStreak'       => 0,
			'fallback'        => 'Members only content',
		);

		$result = $this->sut->render_block( $attributes, '<p>Secret</p>' );

		$this->assertStringContainsString( 'Members only content', $result );
		$this->assertStringContainsString( 'gym-targeted-fallback', $result );
	}

	/**
	 * @test
	 */
	public function render_block_returns_content_for_logged_in_user(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 42 );

		$attributes = array(
			'loggedIn'        => true,
			'membersOnly'     => false,
			'foundationsOnly' => false,
			'program'         => '',
			'minBelt'         => '',
			'location'        => '',
			'minClasses'      => 0,
			'minStreak'       => 0,
			'fallback'        => '',
		);

		$result = $this->sut->render_block( $attributes, '<p>Welcome</p>' );

		$this->assertSame( '<p>Welcome</p>', $result );
	}

	/**
	 * @test
	 */
	public function render_block_hides_content_when_program_not_matched(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\when( 'sanitize_text_field' )->alias( function ( $text ) { return $text; } );

		$this->rank_store
			->shouldReceive( 'get_rank' )
			->with( 42, 'adult-bjj' )
			->andReturnNull();

		$attributes = array(
			'loggedIn'        => false,
			'membersOnly'     => false,
			'foundationsOnly' => false,
			'program'         => 'adult-bjj',
			'minBelt'         => '',
			'location'        => '',
			'minClasses'      => 0,
			'minStreak'       => 0,
			'fallback'        => '',
		);

		$result = $this->sut->render_block( $attributes, '<p>BJJ Content</p>' );

		$this->assertSame( '', $result );
	}
}
