<?php
/**
 * Unit tests for RankStore cache layer.
 *
 * Covers the cache invalidator that promote() fires inline, per the
 * gym-core marketplace review MINOR-15 finding.
 *
 * @package Gym_Core\Tests\Unit\Rank
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Rank;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Rank\RankStore;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class RankStoreCacheTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	#[TestDox('invalidate_rank_cache deletes the per-(user, program) entry.')]
	public function test_invalidate_rank_cache_calls_wp_cache_delete(): void {
		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'rank_7_adult-bjj', RankStore::RANK_CACHE_GROUP );

		RankStore::invalidate_rank_cache( 7, 'adult-bjj' );
		$this->assertTrue( true );
	}
}
