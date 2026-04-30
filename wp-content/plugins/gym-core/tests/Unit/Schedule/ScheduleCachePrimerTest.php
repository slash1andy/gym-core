<?php
/**
 * Unit tests for ScheduleCachePrimer.
 *
 * @package Gym_Core\Tests\Unit\Schedule
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Schedule;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Schedule\ScheduleCachePrimer;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use WP_Query;

/**
 * Verifies the bulk-prime helper does the right thing when called from
 * schedule controllers.
 */
class ScheduleCachePrimerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_list_pluck' )->alias(
			static function ( array $items, string $field ): array {
				return array_map(
					static fn( $item ) => is_object( $item ) ? $item->$field : ( $item[ $field ] ?? null ),
					$items
				);
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	#[TestDox('No-op on an empty WP_Query result so callers don\'t have to guard.')]
	public function test_prime_is_safe_on_empty_query(): void {
		$query        = new WP_Query();
		$query->posts = array();

		Functions\expect( 'update_meta_cache' )->never();
		Functions\expect( 'update_object_term_cache' )->never();
		Functions\expect( 'cache_users' )->never();

		ScheduleCachePrimer::prime( $query );

		$this->assertTrue( true );
	}

	#[TestDox('Primes meta + term + instructor caches in bulk for a populated query.')]
	public function test_prime_warms_caches_in_bulk(): void {
		$post_a = (object) array( 'ID' => 11 );
		$post_b = (object) array( 'ID' => 22 );
		$post_c = (object) array( 'ID' => 33 );

		$query        = new WP_Query();
		$query->posts = array( $post_a, $post_b, $post_c );

		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				$instructors = array(
					11 => 5,
					22 => 7,
					33 => 5,
				);
				return $instructors[ $post_id ] ?? '';
			}
		);

		Functions\expect( 'update_meta_cache' )
			->once()
			->with( 'post', array( 11, 22, 33 ) );
		Functions\expect( 'update_object_term_cache' )
			->once()
			->with( array( 11, 22, 33 ), 'gym_class' );
		Functions\expect( 'cache_users' )
			->once()
			->with( Mockery_match_unique_instructor_ids() );

		ScheduleCachePrimer::prime( $query );
		$this->assertTrue( true );
	}

	#[TestDox('Skips cache_users when no class has an instructor assigned.')]
	public function test_prime_skips_user_cache_when_no_instructors(): void {
		$post = (object) array( 'ID' => 99 );

		$query        = new WP_Query();
		$query->posts = array( $post );

		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\expect( 'update_meta_cache' )->once();
		Functions\expect( 'update_object_term_cache' )->once();
		Functions\expect( 'cache_users' )->never();

		ScheduleCachePrimer::prime( $query );
		$this->assertTrue( true );
	}
}

/**
 * Tiny matcher: instructor IDs may arrive in any order — assert content set
 * is exactly {5, 7} regardless of ordering.
 */
function Mockery_match_unique_instructor_ids() {
	return \Mockery::on(
		static function ( $ids ): bool {
			sort( $ids );
			return array( 5, 7 ) === $ids;
		}
	);
}
