<?php
/**
 * Unit tests for BriefingController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\BriefingController;
use Gym_Core\Briefing\BriefingGenerator;
use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for the BriefingController REST endpoint handlers.
 */
class BriefingControllerTest extends TestCase {

/**
	 * The System Under Test.
	 *
	 * @var BriefingController
	 */
	private BriefingController $sut;

	/**
	 * Mock BriefingGenerator.
	 *
	 * @var BriefingGenerator&\Mockery\MockInterface
	 */
	private BriefingGenerator $generator;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);

		$this->generator = Mockery::mock( BriefingGenerator::class );
		$this->sut       = new BriefingController( $this->generator );
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
	 * Returns sample briefing data for a class.
	 *
	 * @param int    $class_id   Class ID.
	 * @param string $name       Class name.
	 * @param string $start_time Start time.
	 * @return array<string, mixed>
	 */
	private function make_briefing( int $class_id = 1, string $name = 'Adult BJJ', string $start_time = '18:00' ): array {
		return array(
			'class'      => array(
				'id'         => $class_id,
				'name'       => $name,
				'start_time' => $start_time,
			),
			'attendance' => array(
				'expected'   => 12,
				'checked_in' => 8,
			),
			'notes'      => array(
				'Two new students today.',
			),
		);
	}

	// -------------------------------------------------------------------------
	// get_class_briefing
	// -------------------------------------------------------------------------

	#[TestDox('get_class_briefing should return success with briefing data.')]
	public function test_get_class_briefing_returns_success_with_briefing_data(): void {
		$briefing = $this->make_briefing( 1, 'Adult BJJ', '18:00' );

		$this->generator
			->expects( 'generate' )
			->once()
			->with( 1 )
			->andReturn( $briefing );

		Functions\when( 'is_wp_error' )->justReturn( false );

		$request  = $this->make_request( array( 'class_id' => 1 ) );
		$response = $this->sut->get_class_briefing( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 1, $body['data']['class']['id'] );
		$this->assertSame( 'Adult BJJ', $body['data']['class']['name'] );
		$this->assertSame( '18:00', $body['data']['class']['start_time'] );
		$this->assertArrayHasKey( 'attendance', $body['data'] );
		$this->assertArrayHasKey( 'notes', $body['data'] );
	}

	#[TestDox('get_class_briefing should forward WP_Error from generator.')]
	public function test_get_class_briefing_forwards_wp_error_from_generator(): void {
		$wp_error = new \WP_Error( 'class_not_found', 'Class not found.', array( 'status' => 404 ) );

		$this->generator
			->expects( 'generate' )
			->once()
			->with( 99 )
			->andReturn( $wp_error );

		Functions\when( 'is_wp_error' )->alias(
			static function ( mixed $val ): bool {
				return $val instanceof \WP_Error;
			}
		);

		$request = $this->make_request( array( 'class_id' => 99 ) );
		$result  = $this->sut->get_class_briefing( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'class_not_found', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// get_today_briefings
	// -------------------------------------------------------------------------

	#[TestDox('get_today_briefings should return sorted briefings array.')]
	public function test_get_today_briefings_returns_sorted_briefings_array(): void {
		$this->generator
			->expects( 'get_todays_classes' )
			->once()
			->with( '' )
			->andReturn( array( 1, 2, 3 ) );

		$briefing_a = $this->make_briefing( 1, 'Adult BJJ', '18:00' );
		$briefing_b = $this->make_briefing( 2, 'Kids Karate', '09:00' );
		$briefing_c = $this->make_briefing( 3, 'Open Mat', '12:00' );

		$this->generator
			->allows( 'generate' )
			->andReturnUsing(
				static function ( int $class_id ) use ( $briefing_a, $briefing_b, $briefing_c ): array {
					return match ( $class_id ) {
						1 => $briefing_a,
						2 => $briefing_b,
						3 => $briefing_c,
					};
				}
			);

		Functions\when( 'is_wp_error' )->justReturn( false );

		$request  = $this->make_request();
		$response = $this->sut->get_today_briefings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 3, $body['data'] );

		// Should be sorted by start_time: 09:00, 12:00, 18:00.
		$this->assertSame( '09:00', $body['data'][0]['class']['start_time'] );
		$this->assertSame( '12:00', $body['data'][1]['class']['start_time'] );
		$this->assertSame( '18:00', $body['data'][2]['class']['start_time'] );

		// Pagination meta should be present.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 3, $body['meta']['pagination']['total'] );
	}

	// -------------------------------------------------------------------------
	// get_announcements
	// -------------------------------------------------------------------------

	#[TestDox('get_announcements should return paginated announcements.')]
	public function test_get_announcements_returns_paginated_announcements(): void {
		// Preset WP_Query stub results for AnnouncementPostType::get_active_announcements().
		$post_a              = new \stdClass();
		$post_a->ID          = 10;
		$post_a->post_title  = 'Holiday Hours';
		$post_a->post_content = 'We will be closed on Monday.';
		$post_a->post_author  = 1;

		$post_b              = new \stdClass();
		$post_b->ID          = 11;
		$post_b->post_title  = 'New Schedule';
		$post_b->post_content = 'Check the updated schedule.';
		$post_b->post_author  = 2;

		$post_c              = new \stdClass();
		$post_c->ID          = 12;
		$post_c->post_title  = 'Belt Testing';
		$post_c->post_content = 'Belt testing this Saturday.';
		$post_c->post_author  = 1;

		\WP_Query::$__test_result = array(
			'posts'         => array( $post_a, $post_b, $post_c ),
			'found_posts'   => 3,
			'max_num_pages' => 1,
		);

		// Mock get_post_meta to return announcement metadata.
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key, bool $single ) {
				$meta = array(
					10 => array(
						'_gym_announcement_type'            => 'global',
						'_gym_announcement_target_location' => '',
						'_gym_announcement_target_program'  => '',
						'_gym_announcement_start_date'      => '2020-01-01',
						'_gym_announcement_end_date'        => '2099-12-31',
						'_gym_announcement_pinned'          => 'yes',
					),
					11 => array(
						'_gym_announcement_type'            => 'location',
						'_gym_announcement_target_location' => 'rockford',
						'_gym_announcement_target_program'  => '',
						'_gym_announcement_start_date'      => '2020-01-01',
						'_gym_announcement_end_date'        => '2099-12-31',
						'_gym_announcement_pinned'          => 'no',
					),
					12 => array(
						'_gym_announcement_type'            => 'program',
						'_gym_announcement_target_location' => '',
						'_gym_announcement_target_program'  => 'bjj',
						'_gym_announcement_start_date'      => '2020-01-01',
						'_gym_announcement_end_date'        => '2099-12-31',
						'_gym_announcement_pinned'          => 'no',
					),
				);

				return $meta[ $post_id ][ $key ] ?? '';
			}
		);

		$author               = new \stdClass();
		$author->display_name = 'Coach';
		Functions\when( 'get_userdata' )->justReturn( $author );

		$request  = $this->make_request(
			array(
				'location' => 'rockford',
				'program'  => '',
				'page'     => 1,
				'per_page' => 2,
			)
		);
		$response = $this->sut->get_announcements( $request );

		\WP_Query::__test_reset();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );

		// per_page=2, so only 2 items on page 1.
		$this->assertCount( 2, $body['data'] );

		// Pagination meta.
		$this->assertArrayHasKey( 'meta', $body );
		$this->assertSame( 2, $body['meta']['pagination']['per_page'] );
		$this->assertSame( 1, $body['meta']['pagination']['page'] );
	}

	// -------------------------------------------------------------------------
	// create_announcement
	// -------------------------------------------------------------------------

	#[TestDox('create_announcement should return 201 on success.')]
	public function test_create_announcement_returns_201_on_success(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'wp_insert_post' )->justReturn( 42 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$post               = new \stdClass();
		$post->post_title   = 'Test Announcement';
		$post->post_content = 'Test content.';
		$post->post_author  = 1;

		Functions\when( 'get_post' )->justReturn( $post );

		$author               = new \stdClass();
		$author->display_name = 'Admin';
		Functions\when( 'get_userdata' )->justReturn( $author );

		$request  = $this->make_request(
			array(
				'title'           => 'Test Announcement',
				'content'         => 'Test content.',
				'type'            => 'global',
				'target_location' => '',
				'target_program'  => '',
				'start_date'      => '2026-04-01',
				'end_date'        => '2026-04-30',
				'pinned'          => false,
			)
		);
		$response = $this->sut->create_announcement( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 42, $body['data']['id'] );
		$this->assertSame( 'Test Announcement', $body['data']['title'] );
	}

	#[TestDox('create_announcement should return 500 when wp_insert_post fails.')]
	public function test_create_announcement_returns_500_when_wp_insert_post_fails(): void {
		$wp_error = new \WP_Error( 'db_insert_error', 'Could not insert post.' );

		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'wp_insert_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->alias(
			static function ( mixed $val ): bool {
				return $val instanceof \WP_Error;
			}
		);

		$request = $this->make_request(
			array(
				'title'           => 'Failed Announcement',
				'content'         => 'This will fail.',
				'type'            => 'global',
				'target_location' => '',
				'target_program'  => '',
				'start_date'      => '',
				'end_date'        => '',
				'pinned'          => false,
			)
		);
		$result  = $this->sut->create_announcement( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'announcement_create_failed', $result->get_error_code() );
		$this->assertSame( array( 'status' => 500 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// permissions_view_briefing
	// -------------------------------------------------------------------------

	#[TestDox('permissions_view_briefing should return true with gym_view_briefing capability.')]
	public function test_permissions_view_briefing_returns_true_with_cap(): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'gym_view_briefing' === $cap;
			}
		);

		$request = $this->make_request();
		$result  = $this->sut->permissions_view_briefing( $request );

		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// permissions_manage_announcements
	// -------------------------------------------------------------------------

	#[TestDox('permissions_manage_announcements should return WP_Error without capability.')]
	public function test_permissions_manage_announcements_returns_error_without_cap(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$request = $this->make_request();
		$result  = $this->sut->permissions_manage_announcements( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}
}
