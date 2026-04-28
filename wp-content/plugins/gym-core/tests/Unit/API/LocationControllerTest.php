<?php
/**
 * Unit tests for LocationController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\LocationController;
use Gym_Core\Location\Manager;
use Gym_Core\Location\Taxonomy;
use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for the LocationController REST endpoint handlers.
 *
 * Each test stubs the WordPress/WooCommerce functions used by the handler
 * under test so no database or WordPress install is required.
 */
class LocationControllerTest extends TestCase {

/**
	 * The System Under Test.
	 *
	 * @var LocationController
	 */
	private LocationController $sut;

	/**
	 * Mock Location Manager.
	 *
	 * @var Manager&\Mockery\MockInterface
	 */
	private Manager $manager;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Passthrough stubs for common WP utility functions used in every handler.
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);

		// Stub cache to return location labels so Taxonomy::get_location_labels()
		// short-circuits without calling get_terms / is_wp_error during is_valid().
		Functions\when( 'wp_cache_get' )->justReturn(
			array(
				'rockford' => 'Rockford',
				'beloit'   => 'Beloit',
			)
		);
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_cache_delete' )->justReturn( true );

		$this->manager = Mockery::mock( Manager::class );
		$this->sut     = new LocationController( $this->manager );
	}

/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

/**
	 * Creates a mock WP_REST_Request with the given parameters.
	 *
	 * @param array<string, mixed> $params Query/body parameters.
	 * @return \WP_REST_Request&\Mockery\MockInterface
	 */
	private function make_request( array $params = [] ): \WP_REST_Request {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->allows( 'get_param' )->andReturnUsing(
			static function ( string $key ) use ( $params ): mixed {
				return $params[ $key ] ?? null;
			}
		);

		return $request;
	}

/**
	 * Builds a mock WP_Term with the given field values.
	 *
	 * @param array<string, mixed> $fields Overrides for term properties.
	 * @return \WP_Term
	 */
	private function make_term( array $fields = [] ): \WP_Term {
		return new \WP_Term(
			(object) array_merge(
				array(
					'term_id'     => 1,
					'slug'        => 'rockford',
					'name'        => 'Rockford',
					'description' => '',
					'count'       => 0,
					'taxonomy'    => Taxonomy::SLUG,
				),
				$fields
			)
		);
	}

/**
	 * Builds a mock WC_Product with the given return values.
	 *
	 * @param array<string, mixed> $values Method return values.
	 * @return \WC_Product&\Mockery\MockInterface
	 */
	private function make_product( array $values = [] ): \WC_Product {
		$defaults = array(
			'get_id'            => 10,
			'get_name'          => 'Adult BJJ Membership',
			'get_slug'          => 'adult-bjj-membership',
			'get_price'         => '120.00',
			'get_regular_price' => '120.00',
			'get_status'        => 'publish',
			'get_image_id'      => 0,
		);

		$product = Mockery::mock( \WC_Product::class );
		$merged  = array_merge( $defaults, $values );

		foreach ( $merged as $method => $return ) {
			$product->allows( $method )->andReturn( $return );
		}

		return $product;
	}

	// -------------------------------------------------------------------------
	// get_locations
	// -------------------------------------------------------------------------

	#[TestDox('get_locations should return a success response with formatted terms.')]
	public function test_get_locations_returns_formatted_location_list(): void {
		$rockford = $this->make_term( array( 'slug' => 'rockford', 'name' => 'Rockford' ) );
		$beloit   = $this->make_term( array( 'slug' => 'beloit', 'name' => 'Beloit', 'term_id' => 2 ) );

		Functions\when( 'get_terms' )->justReturn( array( $rockford, $beloit ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_term_link' )->justReturn( 'https://example.com/location/rockford/' );

		$request  = $this->make_request();
		$response = $this->sut->get_locations( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 2, $body['data'] );
		$this->assertSame( 'rockford', $body['data'][0]['slug'] );
		$this->assertSame( 'Rockford', $body['data'][0]['name'] );
		$this->assertSame( 'beloit', $body['data'][1]['slug'] );
	}

	#[TestDox('get_locations should return 500 when get_terms fails.')]
	public function test_get_locations_returns_500_on_taxonomy_error(): void {
		$wp_error = new \WP_Error( 'db_error', 'Database error.' );

		Functions\when( 'get_terms' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$request = $this->make_request();
		$result  = $this->sut->get_locations( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_locations_fetch_failed', $result->get_error_code() );
		$this->assertSame( array( 'status' => 500 ), $result->get_error_data() );
	}

	#[TestDox('get_locations should return an empty array when no terms exist.')]
	public function test_get_locations_returns_empty_array_when_no_terms(): void {
		Functions\when( 'get_terms' )->justReturn( array() );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$request  = $this->make_request();
		$response = $this->sut->get_locations( $request );

		$body = $response->get_data();
		$this->assertSame( array(), $body['data'] );
	}

	// -------------------------------------------------------------------------
	// get_location
	// -------------------------------------------------------------------------

	#[TestDox('get_location should return 404 for an unrecognised slug.')]
	public function test_get_location_returns_404_for_invalid_slug(): void {
		$request = $this->make_request( array( 'slug' => 'chicago' ) );

		$result = $this->sut->get_location( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_location_not_found', $result->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	#[TestDox('get_location should return 404 when the taxonomy term does not exist.')]
	public function test_get_location_returns_404_when_term_absent(): void {
		$request = $this->make_request( array( 'slug' => Taxonomy::ROCKFORD ) );

		Functions\when( 'get_term_by' )->justReturn( false );

		$result = $this->sut->get_location( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_location_not_found', $result->get_error_code() );
	}

	#[TestDox('get_location should return formatted location data for a valid slug.')]
	public function test_get_location_returns_formatted_location_data(): void {
		$term    = $this->make_term( array( 'slug' => 'rockford', 'name' => 'Rockford', 'count' => 3 ) );
		$request = $this->make_request( array( 'slug' => Taxonomy::ROCKFORD ) );

		Functions\when( 'get_term_by' )->justReturn( $term );
		Functions\when( 'get_term_link' )->justReturn( 'https://example.com/location/rockford/' );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$response = $this->sut->get_location( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 'rockford', $body['data']['slug'] );
		$this->assertSame( 'Rockford', $body['data']['name'] );
		$this->assertSame( 3, $body['data']['count'] );
		$this->assertSame( 'https://example.com/location/rockford/', $body['data']['link'] );
	}

	#[TestDox('get_location should return empty link string when get_term_link fails.')]
	public function test_get_location_returns_empty_link_on_term_link_error(): void {
		$term    = $this->make_term( array( 'slug' => Taxonomy::BELOIT, 'name' => 'Beloit' ) );
		$request = $this->make_request( array( 'slug' => Taxonomy::BELOIT ) );
		$error   = new \WP_Error( 'invalid_term', 'Term does not exist.' );

		Functions\when( 'get_term_by' )->justReturn( $term );
		Functions\when( 'get_term_link' )->justReturn( $error );
		Functions\when( 'is_wp_error' )
			->alias(
				static function ( mixed $val ): bool {
					return $val instanceof \WP_Error;
				}
			);

		$response = $this->sut->get_location( $request );
		$body     = $response->get_data();

		$this->assertSame( '', $body['data']['link'] );
	}

	// -------------------------------------------------------------------------
	// get_location_products
	// -------------------------------------------------------------------------

	#[TestDox('get_location_products should return 404 for an invalid slug.')]
	public function test_get_location_products_returns_404_for_invalid_slug(): void {
		$request = $this->make_request( array( 'slug' => 'chicago' ) );

		$result = $this->sut->get_location_products( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_location_not_found', $result->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	#[TestDox('get_location_products should return paginated products with meta.')]
	public function test_get_location_products_returns_paginated_products(): void {
		$product = $this->make_product(
			array(
				'get_id'   => 5,
				'get_name' => 'Youth BJJ Membership',
				'get_slug' => 'youth-bjj-membership',
			)
		);

		$paginated_result              = new \stdClass();
		$paginated_result->products    = array( $product );
		$paginated_result->total       = 1;
		$paginated_result->max_num_pages = 1;

		$request = $this->make_request(
			array(
				'slug'     => Taxonomy::ROCKFORD,
				'page'     => 1,
				'per_page' => 10,
			)
		);

		Functions\when( 'wc_get_products' )->justReturn( $paginated_result );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/product/youth-bjj-membership/' );
		Functions\when( 'wp_get_attachment_url' )->justReturn( false );

		$response = $this->sut->get_location_products( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 1, $body['data'] );
		$this->assertSame( 5, $body['data'][0]['id'] );
		$this->assertSame( 'Youth BJJ Membership', $body['data'][0]['name'] );

		// Pagination meta must be present.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 1, $body['meta']['pagination']['total'] );
		$this->assertSame( 1, $body['meta']['pagination']['total_pages'] );
		$this->assertSame( 1, $body['meta']['pagination']['page'] );
		$this->assertSame( 10, $body['meta']['pagination']['per_page'] );
	}

	#[TestDox('get_location_products should include image URL when product has an image.')]
	public function test_get_location_products_includes_image_url_when_present(): void {
		$product = $this->make_product(
			array(
				'get_image_id' => 42,
			)
		);

		$paginated_result              = new \stdClass();
		$paginated_result->products    = array( $product );
		$paginated_result->total       = 1;
		$paginated_result->max_num_pages = 1;

		$request = $this->make_request( array( 'slug' => Taxonomy::ROCKFORD ) );

		Functions\when( 'wc_get_products' )->justReturn( $paginated_result );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/product/test/' );
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://example.com/wp-content/uploads/image.jpg' );

		$response = $this->sut->get_location_products( $request );
		$body     = $response->get_data();

		$this->assertSame( 'https://example.com/wp-content/uploads/image.jpg', $body['data'][0]['image'] );
	}

	#[TestDox('get_location_products should use empty string for image when product has none.')]
	public function test_get_location_products_uses_empty_string_for_missing_image(): void {
		$product = $this->make_product( array( 'get_image_id' => 0 ) );

		$paginated_result              = new \stdClass();
		$paginated_result->products    = array( $product );
		$paginated_result->total       = 1;
		$paginated_result->max_num_pages = 1;

		$request = $this->make_request( array( 'slug' => Taxonomy::BELOIT ) );

		Functions\when( 'wc_get_products' )->justReturn( $paginated_result );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/product/test/' );

		$response = $this->sut->get_location_products( $request );
		$body     = $response->get_data();

		$this->assertSame( '', $body['data'][0]['image'] );
	}

	// -------------------------------------------------------------------------
	// get_user_location
	// -------------------------------------------------------------------------

	#[TestDox('get_user_location should return null data when no location is stored.')]
	public function test_get_user_location_returns_null_when_no_location_stored(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		$this->manager->allows( 'get_user_location' )->with( 7 )->andReturn( '' );

		$request  = $this->make_request();
		$response = $this->sut->get_user_location( $request );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertNull( $body['data'] );
	}

	#[TestDox('get_user_location should return the slug and label when a location is stored.')]
	public function test_get_user_location_returns_slug_and_label(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 12 );
		$this->manager->allows( 'get_user_location' )->with( 12 )->andReturn( Taxonomy::ROCKFORD );

		$request  = $this->make_request();
		$response = $this->sut->get_user_location( $request );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( Taxonomy::ROCKFORD, $body['data']['location'] );
		$this->assertSame( 'Rockford', $body['data']['label'] );
	}

	#[TestDox('get_user_location should return correct label for beloit.')]
	public function test_get_user_location_returns_correct_label_for_beloit(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 3 );
		$this->manager->allows( 'get_user_location' )->with( 3 )->andReturn( Taxonomy::BELOIT );

		$request  = $this->make_request();
		$response = $this->sut->get_user_location( $request );

		$body = $response->get_data();
		$this->assertSame( Taxonomy::BELOIT, $body['data']['location'] );
		$this->assertSame( 'Beloit', $body['data']['label'] );
	}

	// -------------------------------------------------------------------------
	// set_user_location
	// -------------------------------------------------------------------------

	#[TestDox('set_user_location should return 422 when the manager rejects the slug.')]
	public function test_set_user_location_returns_422_for_invalid_slug(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 5 );
		$this->manager->allows( 'set_user_location' )->andReturn( false );

		$request = $this->make_request( array( 'location' => 'chicago' ) );
		$result  = $this->sut->set_user_location( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_invalid_location', $result->get_error_code() );
		$this->assertSame( array( 'status' => 422 ), $result->get_error_data() );
	}

	#[TestDox('set_user_location should return success with the updated location.')]
	public function test_set_user_location_returns_success_with_location_data(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 9 );
		$this->manager
			->expects( 'set_user_location' )
			->once()
			->with( 9, Taxonomy::ROCKFORD )
			->andReturn( true );

		$request  = $this->make_request( array( 'location' => Taxonomy::ROCKFORD ) );
		$response = $this->sut->set_user_location( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( Taxonomy::ROCKFORD, $body['data']['location'] );
		$this->assertSame( 'Rockford', $body['data']['label'] );
	}

	#[TestDox('set_user_location should call set_user_location with the current user ID.')]
	public function test_set_user_location_uses_current_user_id(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		$this->manager
			->expects( 'set_user_location' )
			->once()
			->with( 42, Taxonomy::BELOIT )
			->andReturn( true );

		$request = $this->make_request( array( 'location' => Taxonomy::BELOIT ) );
		$this->sut->set_user_location( $request );

		// Mockery expectation counts as the assertion; verified in tearDown.
		$this->addToAssertionCount( 1 );
	}

	// -------------------------------------------------------------------------
	// get_public_item_schema
	// -------------------------------------------------------------------------

	#[TestDox('get_public_item_schema should define the expected location properties.')]
	public function test_get_public_item_schema_defines_location_properties(): void {
		$schema = $this->sut->get_public_item_schema();

		$this->assertSame( 'gym-location', $schema['title'] );
		$this->assertArrayHasKey( 'slug', $schema['properties'] );
		$this->assertArrayHasKey( 'name', $schema['properties'] );
		$this->assertArrayHasKey( 'description', $schema['properties'] );
		$this->assertArrayHasKey( 'count', $schema['properties'] );
		$this->assertArrayHasKey( 'link', $schema['properties'] );
	}
}
