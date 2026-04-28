<?php
/**
 * Unit tests for TableManager.
 *
 * @package Gym_Core\Tests\Unit\Data
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Data;

use Gym_Core\Data\TableManager;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for the TableManager class.
 */
class TableManagerTest extends TestCase {

/**
	 * Set up Brain\Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

/**
	 * Tear down Brain\Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

/**
	 * Test that get_table_names returns all four expected table identifiers.
	 */
	public function test_get_table_names_returns_all_tables(): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb         = new \stdClass();
		$wpdb->prefix = 'wp_';

		$tables = TableManager::get_table_names();

		$this->assertArrayHasKey( 'ranks', $tables );
		$this->assertArrayHasKey( 'rank_history', $tables );
		$this->assertArrayHasKey( 'attendance', $tables );
		$this->assertArrayHasKey( 'achievements', $tables );
	}

/**
	 * Test that table names use the correct prefix.
	 */
	public function test_table_names_use_wpdb_prefix(): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb         = new \stdClass();
		$wpdb->prefix = 'test_';

		$tables = TableManager::get_table_names();

		$this->assertSame( 'test_gym_ranks', $tables['ranks'] );
		$this->assertSame( 'test_gym_rank_history', $tables['rank_history'] );
		$this->assertSame( 'test_gym_attendance', $tables['attendance'] );
		$this->assertSame( 'test_gym_achievements', $tables['achievements'] );
	}

/**
	 * Test that maybe_create_tables skips when version is current.
	 */
	public function test_maybe_create_tables_skips_when_current(): void {
		// Return a version >= SCHEMA_VERSION (which is 1).
		Functions\expect( 'get_option' )
			->once()
			->with( 'gym_core_db_version', 0 )
			->andReturn( 999 );

		// update_option should never be called (schema is current).
		Functions\expect( 'update_option' )->never();

		TableManager::maybe_create_tables();

		// If we got here without calling update_option, the skip logic worked.
		$this->assertTrue( true );
	}

/**
	 * Test that drop_tables calls the correct SQL and deletes the version option.
	 */
	public function test_drop_tables_removes_version_option(): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb         = \Mockery::mock( \stdClass::class );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'query' )
			->times( 4 )
			->andReturn( true );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'gym_core_db_version' );

		TableManager::drop_tables();

		$this->assertTrue( true );
	}
}
