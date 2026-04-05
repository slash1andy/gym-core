<?php
/**
 * Unit tests for Taxonomy.
 *
 * @package Gym_Core\Tests\Unit\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Location;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Location\Taxonomy;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Taxonomy class.
 */
class TaxonomyTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var Taxonomy
	 */
	private Taxonomy $sut;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Stub cache functions used by Taxonomy::get_location_labels().
		// Stub cache to return location labels so Taxonomy::get_location_labels()
		// short-circuits without calling get_terms / is_wp_error.
		Functions\when( 'wp_cache_get' )->justReturn(
			array(
				'rockford' => 'Rockford',
				'beloit'   => 'Beloit',
			)
		);
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_cache_delete' )->justReturn( true );

		$this->sut = new Taxonomy();
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
	 * @testdox Should return true for the rockford slug.
	 */
	public function test_is_valid_returns_true_for_rockford(): void {
		$this->assertTrue( Taxonomy::is_valid( 'rockford' ) );
	}

	/**
	 * @testdox Should return true for the beloit slug.
	 */
	public function test_is_valid_returns_true_for_beloit(): void {
		$this->assertTrue( Taxonomy::is_valid( 'beloit' ) );
	}

	/**
	 * @testdox Should return false for an empty string.
	 */
	public function test_is_valid_returns_false_for_empty_string(): void {
		$this->assertFalse( Taxonomy::is_valid( '' ) );
	}

	/**
	 * @testdox Should return false for an unrecognised location slug.
	 *
	 * @dataProvider invalid_slug_provider
	 */
	public function test_is_valid_returns_false_for_unrecognised_slug( string $slug ): void {
		$this->assertFalse( Taxonomy::is_valid( $slug ) );
	}

	/**
	 * Data provider for invalid location slugs.
	 *
	 * @return array<string, array<string>>
	 */
	public function invalid_slug_provider(): array {
		return array(
			'arbitrary string'   => array( 'chicago' ),
			'SQL injection'      => array( "' OR 1=1 --" ),
			'xss attempt'        => array( '<script>alert(1)</script>' ),
			'numeric string'     => array( '12345' ),
			'case mismatch'      => array( 'Rockford' ),
			'extra whitespace'   => array( ' rockford' ),
		);
	}

	/**
	 * @testdox VALID_LOCATIONS constant should contain both expected slugs.
	 */
	public function test_valid_locations_contains_rockford_and_beloit(): void {
		$this->assertContains( 'rockford', Taxonomy::VALID_LOCATIONS );
		$this->assertContains( 'beloit', Taxonomy::VALID_LOCATIONS );
	}

	/**
	 * @testdox VALID_LOCATIONS constant should contain exactly two entries.
	 */
	public function test_valid_locations_contains_exactly_two_entries(): void {
		$this->assertCount( 2, Taxonomy::VALID_LOCATIONS );
	}

	/**
	 * @testdox SLUG constant should equal 'gym_location'.
	 */
	public function test_slug_constant_value(): void {
		$this->assertSame( 'gym_location', Taxonomy::SLUG );
	}

	/**
	 * @testdox seed_terms should skip insertion when taxonomy does not exist.
	 */
	public function test_seed_terms_skips_when_taxonomy_not_registered(): void {
		Functions\expect( 'taxonomy_exists' )
			->once()
			->with( Taxonomy::SLUG )
			->andReturn( false );

		// wp_insert_term should NOT be called.
		Functions\expect( 'term_exists' )->never();
		Functions\expect( 'wp_insert_term' )->never();

		Taxonomy::seed_terms();

		// Brain\Monkey verifies expectations in tearDown; explicit assertion for PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @testdox seed_terms should insert both terms when neither exists.
	 */
	public function test_seed_terms_inserts_both_terms_when_none_exist(): void {
		Functions\expect( 'taxonomy_exists' )
			->once()
			->with( Taxonomy::SLUG )
			->andReturn( true );

		Functions\expect( 'term_exists' )
			->twice()
			->andReturn( null ); // null = term does not exist.

		Functions\expect( 'wp_insert_term' )
			->twice()
			->andReturn( array( 'term_id' => 1, 'term_taxonomy_id' => 1 ) );

		Taxonomy::seed_terms();

		// Brain\Monkey verifies expectations in tearDown; explicit assertion for PHPUnit.
		$this->assertTrue( true );
	}

	/**
	 * @testdox seed_terms should skip insertion for terms that already exist.
	 */
	public function test_seed_terms_skips_existing_terms(): void {
		Functions\expect( 'taxonomy_exists' )
			->once()
			->with( Taxonomy::SLUG )
			->andReturn( true );

		Functions\expect( 'term_exists' )
			->twice()
			->andReturn( array( 'term_id' => '1' ) ); // Non-null = term exists.

		Functions\expect( 'wp_insert_term' )->never();

		Taxonomy::seed_terms();

		// Brain\Monkey verifies expectations in tearDown; explicit assertion for PHPUnit.
		$this->assertTrue( true );
	}
}
