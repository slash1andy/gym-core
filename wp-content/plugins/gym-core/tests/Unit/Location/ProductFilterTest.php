<?php
/**
 * Unit tests for ProductFilter.
 *
 * @package Gym_Core\Tests\Unit\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Location;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Location\Manager;
use Gym_Core\Location\ProductFilter;
use Gym_Core\Location\Taxonomy;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ProductFilter class.
 */
class ProductFilterTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var ProductFilter
	 */
	private ProductFilter $sut;

	/**
	 * Mock of the location manager.
	 *
	 * @var Manager&\Mockery\MockInterface
	 */
	private $manager;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->manager = Mockery::mock( Manager::class );
		$this->sut     = new ProductFilter( $this->manager );
	}

	/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ----- filter_shortcode_query -----

	/**
	 * @testdox Should return query args unchanged when no location is active.
	 */
	public function test_filter_shortcode_query_returns_unchanged_args_when_no_location(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( '' );

		$args   = array( 'post_type' => 'product', 'posts_per_page' => 12 );
		$result = $this->sut->filter_shortcode_query( $args );

		$this->assertSame( $args, $result );
	}

	/**
	 * @testdox Should append a location tax_query when rockford is active.
	 */
	public function test_filter_shortcode_query_appends_tax_query_for_rockford(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( Taxonomy::ROCKFORD );

		$args   = array( 'post_type' => 'product' );
		$result = $this->sut->filter_shortcode_query( $args );

		$this->assertArrayHasKey( 'tax_query', $result );
		$this->assertCount( 1, $result['tax_query'] );

		$clause = $result['tax_query'][0];
		$this->assertSame( Taxonomy::SLUG, $clause['taxonomy'] );
		$this->assertSame( 'slug', $clause['field'] );
		$this->assertSame( array( Taxonomy::ROCKFORD ), $clause['terms'] );
		$this->assertSame( 'IN', $clause['operator'] );
	}

	/**
	 * @testdox Should append a location tax_query when beloit is active.
	 */
	public function test_filter_shortcode_query_appends_tax_query_for_beloit(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( Taxonomy::BELOIT );

		$result = $this->sut->filter_shortcode_query( array() );

		$clause = $result['tax_query'][0];
		$this->assertSame( array( Taxonomy::BELOIT ), $clause['terms'] );
	}

	/**
	 * @testdox Should preserve existing tax_query entries when appending a location clause.
	 */
	public function test_filter_shortcode_query_preserves_existing_tax_query(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( Taxonomy::ROCKFORD );

		$existing = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array( 'gi' ),
			),
		);

		$result = $this->sut->filter_shortcode_query( array( 'tax_query' => $existing ) );

		// Original entry preserved.
		$this->assertSame( $existing['relation'], $result['tax_query']['relation'] );

		// Location clause added.
		$clauses = array_values(
			array_filter(
				$result['tax_query'],
				fn( $c ) => is_array( $c ) && isset( $c['taxonomy'] ) && Taxonomy::SLUG === $c['taxonomy']
			)
		);
		$this->assertCount( 1, $clauses );
	}

	// ----- filter_product_query -----

	/**
	 * @testdox Should not modify WP_Query when no location is active.
	 */
	public function test_filter_product_query_does_not_modify_query_when_no_location(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( '' );

		$query = Mockery::mock( \WP_Query::class );
		// Neither get() nor set() should be called.
		$query->expects( 'get' )->never();
		$query->expects( 'set' )->never();

		$this->sut->filter_product_query( $query );
	}

	/**
	 * @testdox Should append a tax_query clause to WP_Query when location is active.
	 */
	public function test_filter_product_query_appends_tax_query_when_location_is_active(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( Taxonomy::ROCKFORD );

		$query = Mockery::mock( \WP_Query::class );
		$query->expects( 'get' )
			->once()
			->with( 'tax_query' )
			->andReturn( array() );

		$query->expects( 'set' )
			->once()
			->with(
				'tax_query',
				Mockery::on(
					function ( $tax_query ) {
						return is_array( $tax_query )
							&& 1 === count( $tax_query )
							&& Taxonomy::SLUG === $tax_query[0]['taxonomy']
							&& array( Taxonomy::ROCKFORD ) === $tax_query[0]['terms'];
					}
				)
			);

		$this->sut->filter_product_query( $query );
	}

	/**
	 * @testdox Should merge with existing tax_query on WP_Query.
	 */
	public function test_filter_product_query_merges_with_existing_tax_query(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( Taxonomy::BELOIT );

		$existing = array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array( 'bjj' ),
			),
		);

		$query = Mockery::mock( \WP_Query::class );
		$query->expects( 'get' )->once()->with( 'tax_query' )->andReturn( $existing );
		$query->expects( 'set' )
			->once()
			->with(
				'tax_query',
				Mockery::on(
					fn( $tax_query ) => 2 === count( $tax_query )
				)
			);

		$this->sut->filter_product_query( $query );
	}
}
