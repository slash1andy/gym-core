<?php
/**
 * Regression tests for N+1 query fixes.
 *
 * These tests assert that batched-query entry points issue a constant number
 * of calls regardless of how many users or locations are in the input set.
 * No real database is required — all DB access is mocked at the dependency
 * layer, and call-count assertions (->once()) prove the O(1) invariant.
 *
 * Covers:
 *   - AttendanceStore::get_today_all_locations()  — one query for N locations
 *   - FoundationsClearance::get_status_for_users() — one batched attendance query for N users
 *   - PromotionEligibility::check_for_users()      — one rank + one attendance query for N users
 *   - BriefingGenerator roster enrichment          — bounded query calls for N-student roster
 *
 * @package Gym_Core\Tests\Unit\Perf
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Perf;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Attendance\PromotionEligibility;
use Gym_Core\Briefing\BriefingGenerator;
use Gym_Core\Data\TableManager;
use Gym_Core\Rank\RankStore;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that all N+1-hotspot fixes hold their O(1) query-count invariant.
 */
class RosterBatchTest extends TestCase {

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Common WP function stubs used by multiple test subjects.
		Functions\stubs(
			array(
				'__'            => static fn( string $text ): string => $text,
				'get_option'    => static fn(): string => 'yes',
				'get_user_meta' => static fn(): mixed => false,
				'get_userdata'  => static function ( int $id ): \stdClass {
					$u               = new \stdClass();
					$u->ID           = $id;
					$u->display_name = "User {$id}";
					return $u;
				},
				'cache_users'   => static function ( array $ids ): void {},
				'wp_cache_get'  => static fn(): mixed => false,
				'wp_cache_set'  => static function ( string $key, mixed $data, string $group = '', int $expire = 0 ): void {},
			)
		);
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Data providers
	// -------------------------------------------------------------------------

	/**
	 * Returns arrays of N user IDs for the parametrised tests below.
	 *
	 * @return array<string, array{int[]}>
	 */
	public static function user_set_provider(): array {
		return array(
			'N=1'  => array( array( 1 ) ),
			'N=5'  => array( array( 1, 2, 3, 4, 5 ) ),
			'N=20' => array( range( 1, 20 ) ),
		);
	}

	/**
	 * Returns arrays of N location slugs for the location-batch test.
	 *
	 * @return array<string, array{string[]}>
	 */
	public static function location_set_provider(): array {
		return array(
			'N=1'  => array( array( 'rockford' ) ),
			'N=5'  => array( array( 'rockford', 'beloit', 'south', 'north', 'east' ) ),
			'N=20' => array( array_map( static fn( int $i ) => "loc{$i}", range( 1, 20 ) ) ),
		);
	}

	// -------------------------------------------------------------------------
	// Test 1 — AttendanceStore::get_today_all_locations()
	//
	// A fake $wpdb whose get_results() is instrumented with a call-count
	// verifies that exactly ONE query is issued regardless of how many
	// location slugs are passed.
	// -------------------------------------------------------------------------

	#[TestDox('get_today_all_locations issues exactly 1 query for N locations (N=1, 5, 20)')]
	#[DataProvider('location_set_provider')]
	public function test_get_today_all_locations_is_one_query( array $locations ): void {
		$query_count = 0;
		$tables      = TableManager::get_table_names();
		$today       = gmdate( 'Y-m-d' );

		// Minimal $wpdb stub that counts prepare()+get_results() round-trips.
		$wpdb        = new \stdClass();
		$wpdb->users = 'wp_users';
		$wpdb->prepare = null; // Will be replaced by closure below.

		// Each location entry maps to an empty row set — we only care about call count.
		$canned_rows = array_map(
			static function ( string $slug ) use ( $today ): \stdClass {
				$row               = new \stdClass();
				$row->location     = $slug;
				$row->display_name = 'Test User';
				$row->checked_in_at = $today . ' 10:00:00';
				$row->user_id      = 1;
				$row->id           = 1;
				$row->class_id     = 0;
				$row->method       = 'qr';
				return $row;
			},
			$locations
		);

		// Prepare just passes values through; get_results increments counter.
		$prepared_sql = '';
		$wpdb         = new class( $canned_rows ) {
			/** @var int */
			public int $query_count = 0;
			/** @var string */
			public string $users = 'wp_users';
			/** @var object[] */
			private array $rows;

			/** @param object[] $rows */
			public function __construct( array $rows ) {
				$this->rows = $rows;
			}

			/** @param mixed ...$args */
			public function prepare( string $sql, ...$args ): string {
				return $sql; // Good enough for test purposes.
			}

			/** @return object[] */
			public function get_results( string $sql ): array {
				++$this->query_count;
				return $this->rows;
			}
		};

		$GLOBALS['wpdb'] = $wpdb;

		$store  = new AttendanceStore();
		$result = $store->get_today_all_locations( $locations );

		// Exactly ONE query regardless of N.
		$this->assertSame( 1, $wpdb->query_count, "get_today_all_locations must issue exactly 1 query for " . count( $locations ) . " locations." );

		// Result is keyed by location slug; all input slugs present.
		foreach ( $locations as $slug ) {
			$this->assertArrayHasKey( $slug, $result );
		}

		// Restore global (other tests may set their own stub).
		unset( $GLOBALS['wpdb'] );
	}

	// -------------------------------------------------------------------------
	// Test 2 — FoundationsClearance::get_status_for_users()
	//
	// All N users are "active in Foundations" (pending attendance count).
	// The batched AttendanceStore::get_counts_since_for_users() must be
	// called exactly once regardless of N.
	// -------------------------------------------------------------------------

	#[TestDox('FoundationsClearance::get_status_for_users issues 1 batched attendance query for N users (N=1, 5, 20)')]
	#[DataProvider('user_set_provider')]
	public function test_foundations_get_status_for_users_batches_attendance( array $user_ids ): void {
		$enrolled_at      = '2024-01-01 00:00:00';
		$foundations_meta = array(
			'enrolled_at' => $enrolled_at,
			'cleared_at'  => null,
		);

		// get_user_meta is called twice per user:
		//   1. Pass 1: _gym_foundations_status  → enrolled meta (not cleared).
		//   2. Pass 3: _gym_foundations_coach_rolls → empty array (no rolls yet).
		// Return values differentiated by meta key.
		Functions\when( 'get_user_meta' )->alias(
			static function ( int $user_id, string $key, bool $single = false ) use ( $foundations_meta ): mixed {
				if ( '_gym_foundations_status' === $key ) {
					return $foundations_meta;
				}
				// COACH_ROLLS_KEY or anything else.
				return array();
			}
		);

		// AttendanceStore mock — get_counts_since_for_users must be called ONCE.
		$attendance_mock = Mockery::mock( AttendanceStore::class );
		$attendance_mock
			->shouldReceive( 'get_counts_since_for_users' )
			->once()
			->withArgs(
				static function ( array $user_to_since ) use ( $user_ids ): bool {
					// Verify every user ID appears in the map.
					foreach ( $user_ids as $uid ) {
						if ( ! isset( $user_to_since[ $uid ] ) ) {
							return false;
						}
					}
					return true;
				}
			)
			->andReturn( array_fill_keys( $user_ids, 5 ) );

		$clearance = new FoundationsClearance( $attendance_mock );
		$result    = $clearance->get_status_for_users( $user_ids );

		$this->assertCount( count( $user_ids ), $result );
		foreach ( $user_ids as $uid ) {
			$this->assertArrayHasKey( $uid, $result );
			$this->assertTrue( $result[ $uid ]['in_foundations'] );
		}
	}

	// -------------------------------------------------------------------------
	// Test 3 — PromotionEligibility::check_for_users()
	//
	// All N users have a rank record. The fix replaces a per-user chain of
	// get_rank() + get_count_since() with:
	//   - RankStore::get_ranks_for_users()        — exactly once
	//   - AttendanceStore::get_counts_since_for_users() — exactly once
	//   - FoundationsClearance::get_status_for_users()  — exactly once
	// -------------------------------------------------------------------------

	#[TestDox('PromotionEligibility::check_for_users issues 1 rank + 1 attendance + 1 foundations query for N users (N=1, 5, 20)')]
	#[DataProvider('user_set_provider')]
	public function test_promotion_check_for_users_batches_all_queries( array $user_ids ): void {
		// Build a fake rank row for each user.
		$rank_rows = array();
		foreach ( $user_ids as $uid ) {
			$rank              = new \stdClass();
			$rank->user_id     = $uid;
			$rank->program     = 'bjj';
			$rank->belt        = 'white';
			$rank->stripes     = 0;
			$rank->promoted_at = '2024-01-01 00:00:00';
			$rank->promoted_by = 0;
			$rank_rows[ $uid ] = $rank;
		}

		// RankStore: get_ranks_for_users called exactly once.
		$rank_mock = Mockery::mock( RankStore::class );
		$rank_mock
			->shouldReceive( 'get_ranks_for_users' )
			->once()
			->with( $user_ids, 'bjj' )
			->andReturn( $rank_rows );

		// AttendanceStore: get_counts_since_for_users called exactly once.
		$attendance_mock = Mockery::mock( AttendanceStore::class );
		$attendance_mock
			->shouldReceive( 'get_counts_since_for_users' )
			->once()
			->andReturn( array_fill_keys( $user_ids, 10 ) );

		// FoundationsClearance: get_status_for_users called exactly once.
		$foundations_mock = Mockery::mock( FoundationsClearance::class );
		$foundations_mock
			->shouldReceive( 'get_status_for_users' )
			->once()
			->with( $user_ids )
			->andReturn(
				array_fill_keys(
					$user_ids,
					array(
						'in_foundations' => false,
						'cleared'        => false,
						'phase'          => 'not_enrolled',
					)
				)
			);

		// get_option and get_user_meta are already stubbed in setUp.
		Functions\when( 'get_user_meta' )->justReturn( array() );

		$eligibility = new PromotionEligibility( $attendance_mock, $rank_mock, $foundations_mock );
		$result      = $eligibility->check_for_users( $user_ids, 'bjj' );

		$this->assertCount( count( $user_ids ), $result );
		foreach ( $user_ids as $uid ) {
			$this->assertArrayHasKey( $uid, $result );
			$this->assertIsBool( $result[ $uid ]['eligible'] );
		}
	}

	// -------------------------------------------------------------------------
	// Test 4 — BriefingGenerator roster enrichment
	//
	// enrich_roster() internally calls:
	//   - AttendanceStore::get_last_attended_for_users()  — once
	//   - AttendanceStore::get_total_counts_for_users()   — once
	//   - FoundationsClearance::get_status_for_users()    — once
	//   - PromotionEligibility::check_for_users()         — once
	//   - RankStore::get_ranks_for_users()                — once
	//
	// All five calls must fire exactly once regardless of roster size.
	// -------------------------------------------------------------------------

	#[TestDox('BriefingGenerator::enrich_roster batches all 5 per-user lookups (N=1, 5, 20)')]
	#[DataProvider('user_set_provider')]
	public function test_briefing_roster_enrichment_issues_bounded_queries( array $user_ids ): void {
		$roster = array_map(
			static fn( int $uid ) => array( 'user_id' => $uid, 'attendance_rate' => 0.8 ),
			$user_ids
		);

		// AttendanceStore — assert both batched lookups each called once.
		$attendance_mock = Mockery::mock( AttendanceStore::class );
		$attendance_mock
			->shouldReceive( 'get_last_attended_for_users' )
			->once()
			->with( $user_ids )
			->andReturn( array_fill_keys( $user_ids, '2024-12-01 10:00:00' ) );
		$attendance_mock
			->shouldReceive( 'get_total_counts_for_users' )
			->once()
			->with( $user_ids )
			->andReturn( array_fill_keys( $user_ids, 5 ) );

		// Build fake rank rows.
		$rank_rows = array();
		foreach ( $user_ids as $uid ) {
			$rank              = new \stdClass();
			$rank->user_id     = $uid;
			$rank->belt        = 'white';
			$rank->stripes     = 0;
			$rank->promoted_at = '2024-01-01 00:00:00';
			$rank_rows[ $uid ] = $rank;
		}

		// RankStore — called once.
		$rank_mock = Mockery::mock( RankStore::class );
		$rank_mock
			->shouldReceive( 'get_ranks_for_users' )
			->once()
			->with( $user_ids, 'bjj' )
			->andReturn( $rank_rows );

		// FoundationsClearance — called once.
		$foundations_mock = Mockery::mock( FoundationsClearance::class );
		$foundations_mock
			->shouldReceive( 'get_status_for_users' )
			->once()
			->with( $user_ids )
			->andReturn(
				array_fill_keys(
					$user_ids,
					array(
						'in_foundations'    => false,
						'cleared'           => false,
						'phase'             => 'not_enrolled',
						'classes_completed' => 0,
					)
				)
			);

		// PromotionEligibility — called once.
		$promotion_mock = Mockery::mock( PromotionEligibility::class );
		$promotion_mock
			->shouldReceive( 'check_for_users' )
			->once()
			->with( $user_ids, 'bjj' )
			->andReturn(
				array_fill_keys(
					$user_ids,
					array(
						'eligible'            => false,
						'in_foundations'      => false,
						'attendance_count'    => 5,
						'attendance_required' => 20,
						'days_at_rank'        => 60,
						'days_required'       => 90,
						'has_recommendation'  => false,
						'next_belt'           => 'blue',
					)
				)
			);

		// get_user_meta stub for medical notes (returns empty string → no medical alert).
		Functions\when( 'get_user_meta' )->justReturn( '' );

		// Use reflection to invoke the private enrich_roster() method directly.
		$generator = new BriefingGenerator(
			$attendance_mock,
			$rank_mock,
			$foundations_mock,
			$promotion_mock
		);

		$reflection = new \ReflectionMethod( BriefingGenerator::class, 'enrich_roster' );
		$reflection->setAccessible( true );
		$enriched = $reflection->invoke( $generator, $roster, 'bjj' );

		$this->assertCount( count( $user_ids ), $enriched );
		foreach ( $enriched as $student ) {
			$this->assertArrayHasKey( 'user_id', $student );
			$this->assertArrayHasKey( 'total_classes', $student );
			$this->assertArrayHasKey( 'days_since_last', $student );
		}
	}
}
