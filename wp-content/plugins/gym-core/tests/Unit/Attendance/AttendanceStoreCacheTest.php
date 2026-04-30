<?php
/**
 * Unit tests for AttendanceStore cache invalidation + batched lookup edge cases.
 *
 * Covers the cache layer added for the per-user total-count read and the
 * batched get_last_attended_for_users() entry point — both targets of the
 * gym-core marketplace review (MINOR-15, MAJOR-01).
 *
 * @package Gym_Core\Tests\Unit\Attendance
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Attendance;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Attendance\AttendanceStore;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class AttendanceStoreCacheTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	#[TestDox('invalidate_total_count_cache deletes the per-user count entry.')]
	public function test_invalidate_total_count_cache_calls_wp_cache_delete(): void {
		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'total_count_42', AttendanceStore::COUNT_CACHE_GROUP );

		AttendanceStore::invalidate_total_count_cache( 42 );
		$this->assertTrue( true );
	}

	#[TestDox('get_last_attended_for_users returns empty without touching the DB when given no IDs.')]
	public function test_get_last_attended_for_users_short_circuits_on_empty(): void {
		// $wpdb is never accessed, so leaving it unset proves the early return.
		$store = new AttendanceStore();
		$this->assertSame( array(), $store->get_last_attended_for_users( array() ) );
		$this->assertSame( array(), $store->get_last_attended_for_users( array( 0, 0, '' ) ) );
	}
}
