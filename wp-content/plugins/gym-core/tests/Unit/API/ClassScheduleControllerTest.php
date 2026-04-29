<?php
/**
 * Unit tests for ClassScheduleController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\ClassScheduleController;
use Gym_Core\Schedule\ClassPostType;
use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for the ClassScheduleController REST endpoint handlers.
 *
 * Each test stubs the WordPress functions used by the handler
 * under test so no database or WordPress install is required.
 */
class ClassScheduleControllerTest extends TestCase {

/**
	 * The System Under Test.
	 *
	 * @var ClassScheduleController
	 */
	private ClassScheduleController $sut;

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
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);
		Functions\when( 'wp_kses_post' )->returnArg( 1 );

		// ScheduleCachePrimer is invoked from get_classes/get_schedule; stub the
		// underlying WP cache APIs so tests don't need to know about priming.
		Functions\when( 'wp_list_pluck' )->alias(
			static function ( array $items, string $field ): array {
				return array_map(
					static fn( $item ) => is_object( $item ) ? ( $item->$field ?? null ) : ( $item[ $field ] ?? null ),
					$items
				);
			}
		);
		Functions\when( 'update_meta_cache' )->justReturn( true );
		Functions\when( 'update_object_term_cache' )->justReturn( true );
		Functions\when( 'cache_users' )->justReturn( null );

		$this->sut = new ClassScheduleController();
	}

/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\WP_Query::__test_reset();
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
	 * Builds a mock WP_Post with the given field values.
	 *
	 * @param array<string, mixed> $fields Overrides for post properties.
	 * @return \WP_Post
	 */
	private function make_post( array $fields = [] ): \WP_Post {
		$defaults = array(
			'ID'           => 10,
			'post_title'   => 'Adult BJJ',
			'post_content' => 'Brazilian Jiu-Jitsu class for adults.',
			'post_type'    => ClassPostType::POST_TYPE,
		);

		return new \WP_Post( (object) array_merge( $defaults, $fields ) );
	}

/**
	 * Presets the WP_Query stub to return the given posts.
	 *
	 * @param \WP_Post[] $posts         Posts to return.
	 * @param int        $found_posts   Total found posts.
	 * @param int        $max_num_pages Max pages.
	 * @return void
	 */
	private function preset_wp_query( array $posts, int $found_posts = 1, int $max_num_pages = 1 ): void {
		\WP_Query::$__test_result = array(
			'posts'         => $posts,
			'found_posts'   => $found_posts,
			'max_num_pages' => $max_num_pages,
		);
	}

/**
	 * Stubs the post meta calls used by format_class and get_schedule.
	 *
	 * @param array<string, string> $meta Key-value pairs of meta to return.
	 * @return void
	 */
	private function stub_post_meta( array $meta = [] ): void {
		$defaults = array(
			'_gym_class_day_of_week'  => 'monday',
			'_gym_class_start_time'   => '18:00',
			'_gym_class_end_time'     => '19:00',
			'_gym_class_capacity'     => '20',
			'_gym_class_recurrence'   => 'weekly',
			'_gym_class_status'       => 'active',
			'_gym_class_instructor'   => '5',
		);

		$merged = array_merge( $defaults, $meta );

		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single = false ) use ( $merged ): string {
				return $merged[ $key ] ?? '';
			}
		);
	}

/**
	 * Stubs taxonomy and user lookups used by format_class.
	 *
	 * @return void
	 */
	private function stub_taxonomy_and_user(): void {
		$program_term       = new \stdClass();
		$program_term->name = 'Brazilian Jiu-Jitsu';
		$program_term->slug = 'bjj';

		$location_term       = new \stdClass();
		$location_term->slug = 'rockford';
		$location_term->name = 'Rockford';

		Functions\when( 'get_the_terms' )->alias(
			static function ( int $post_id, string $taxonomy ) use ( $program_term, $location_term ) {
				if ( ClassPostType::PROGRAM_TAXONOMY === $taxonomy ) {
					return array( $program_term );
				}
				if ( 'gym_location' === $taxonomy ) {
					return array( $location_term );
				}
				return false;
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );

		$user                = new \stdClass();
		$user->display_name  = 'Darby Haanpaa';
		Functions\when( 'get_userdata' )->justReturn( $user );
	}

	// -------------------------------------------------------------------------
	// get_classes
	// -------------------------------------------------------------------------

	#[TestDox('get_classes should return a success response with formatted class list and pagination meta.')]
	public function test_get_classes_returns_formatted_list_with_pagination(): void {
		$post = $this->make_post();

		$this->preset_wp_query( array( $post ), 1, 1 );
		$this->stub_post_meta();
		$this->stub_taxonomy_and_user();

		$request  = $this->make_request( array( 'per_page' => 10, 'page' => 1 ) );
		$response = $this->sut->get_classes( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 1, $body['data'] );
		$this->assertSame( 10, $body['data'][0]['id'] );
		$this->assertSame( 'Adult BJJ', $body['data'][0]['name'] );
		$this->assertSame( 'Brazilian Jiu-Jitsu class for adults.', $body['data'][0]['description'] );
		$this->assertSame( 'Brazilian Jiu-Jitsu', $body['data'][0]['program'] );
		$this->assertSame( 'monday', $body['data'][0]['day_of_week'] );
		$this->assertSame( '18:00', $body['data'][0]['start_time'] );
		$this->assertSame( '19:00', $body['data'][0]['end_time'] );
		$this->assertSame( 20, $body['data'][0]['capacity'] );
		$this->assertSame( 'rockford', $body['data'][0]['location'] );

		// Pagination meta.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 1, $body['meta']['pagination']['total'] );
		$this->assertSame( 1, $body['meta']['pagination']['total_pages'] );
		$this->assertSame( 1, $body['meta']['pagination']['page'] );
		$this->assertSame( 10, $body['meta']['pagination']['per_page'] );
	}

	// -------------------------------------------------------------------------
	// get_class
	// -------------------------------------------------------------------------

	#[TestDox('get_class should return 404 when post is not found.')]
	public function test_get_class_returns_404_when_post_not_found(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$request = $this->make_request( array( 'id' => 999 ) );
		$result  = $this->sut->get_class( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'class_not_found', $result->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	#[TestDox('get_class should return 404 when post is wrong post type.')]
	public function test_get_class_returns_404_for_wrong_post_type(): void {
		$post = $this->make_post( array( 'post_type' => 'post' ) );
		Functions\when( 'get_post' )->justReturn( $post );

		$request = $this->make_request( array( 'id' => 10 ) );
		$result  = $this->sut->get_class( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'class_not_found', $result->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	#[TestDox('get_class should return success response for a valid class post.')]
	public function test_get_class_returns_success_for_valid_post(): void {
		$post = $this->make_post( array( 'ID' => 42, 'post_title' => 'Kids Karate' ) );

		Functions\when( 'get_post' )->justReturn( $post );
		$this->stub_post_meta();
		$this->stub_taxonomy_and_user();

		$request  = $this->make_request( array( 'id' => 42 ) );
		$response = $this->sut->get_class( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 42, $body['data']['id'] );
		$this->assertSame( 'Kids Karate', $body['data']['name'] );
		$this->assertSame( 'weekly', $body['data']['recurrence'] );
		$this->assertSame( 'active', $body['data']['status'] );
	}

	// -------------------------------------------------------------------------
	// get_schedule
	// -------------------------------------------------------------------------

	#[TestDox('get_schedule should return 7 days with correct structure.')]
	public function test_get_schedule_returns_seven_days(): void {
		$post = $this->make_post( array( 'ID' => 15, 'post_title' => 'Morning BJJ' ) );

		$this->preset_wp_query( array( $post ), 1, 1 );

		// Stub meta: this class runs on monday.
		$meta = array(
			'_gym_class_day_of_week' => 'monday',
			'_gym_class_start_time'  => '06:00',
			'_gym_class_end_time'    => '07:00',
			'_gym_class_capacity'    => '15',
			'_gym_class_status'      => 'active',
			'_gym_class_instructor'  => '3',
		);
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single = false ) use ( $meta ): string {
				return $meta[ $key ] ?? '';
			}
		);

		// Stub taxonomy lookups for get_class_program and get_instructor_name.
		$program_term       = new \stdClass();
		$program_term->name = 'BJJ';
		$program_term->slug = 'bjj';

		Functions\when( 'get_the_terms' )->alias(
			static function ( int $post_id, string $taxonomy ) use ( $program_term ) {
				if ( ClassPostType::PROGRAM_TAXONOMY === $taxonomy ) {
					return array( $program_term );
				}
				return false;
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );

		$instructor                = new \stdClass();
		$instructor->display_name  = 'Darby Haanpaa';
		Functions\when( 'get_userdata' )->justReturn( $instructor );

		$request  = $this->make_request( array( 'location' => 'rockford', 'week_of' => '', 'program' => null ) );
		$response = $this->sut->get_schedule( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 7, $body['data'] );

		// Verify day structure.
		$day_names = array_map( static fn( $day ) => $day['day_name'], $body['data'] );
		$this->assertSame(
			array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
			$day_names
		);

		// Each day should have date, day_name, and classes keys.
		foreach ( $body['data'] as $day ) {
			$this->assertArrayHasKey( 'date', $day );
			$this->assertArrayHasKey( 'day_name', $day );
			$this->assertArrayHasKey( 'classes', $day );
		}

		// Monday should have the class; other days should be empty.
		$monday = $body['data'][0];
		$this->assertCount( 1, $monday['classes'] );
		$this->assertSame( 15, $monday['classes'][0]['id'] );
		$this->assertSame( 'Morning BJJ', $monday['classes'][0]['name'] );
		$this->assertSame( '06:00', $monday['classes'][0]['start_time'] );
		$this->assertSame( '07:00', $monday['classes'][0]['end_time'] );
		$this->assertSame( 'rockford', $monday['classes'][0]['location'] );
		$this->assertSame( 'BJJ', $monday['classes'][0]['program'] );
		$this->assertSame( 'Darby Haanpaa', $monday['classes'][0]['instructor'] );
		$this->assertSame( 15, $monday['classes'][0]['capacity'] );

		// Tuesday through Sunday should have no classes.
		for ( $i = 1; $i < 7; $i++ ) {
			$this->assertCount( 0, $body['data'][ $i ]['classes'] );
		}
	}
}
