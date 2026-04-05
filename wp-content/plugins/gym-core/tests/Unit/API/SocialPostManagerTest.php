<?php
/**
 * Unit tests for SocialPostManager.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Social\SocialPostManager;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SocialPostManager REST endpoint handlers.
 */
class SocialPostManagerTest extends TestCase {

	/**
	 * The System Under Test.
	 *
	 * @var SocialPostManager
	 */
	private SocialPostManager $sut;

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

		$this->sut = new SocialPostManager();
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
	 * Builds a mock post object.
	 *
	 * @param array<string, mixed> $fields Override default field values.
	 * @return \stdClass
	 */
	private function make_post( array $fields = [] ): \stdClass {
		$defaults = array(
			'ID'           => 100,
			'post_status'  => 'pending',
			'post_title'   => 'Congrats to our new blue belts!',
			'post_content' => 'Great job everyone at belt testing today.',
			'post_date'    => '2026-04-04 10:00:00',
			'post_author'  => 1,
		);

		return (object) array_merge( $defaults, $fields );
	}

	// -------------------------------------------------------------------------
	// handle_draft
	// -------------------------------------------------------------------------

	/**
	 * @testdox handle_draft should return 201 with post_id on success.
	 */
	public function test_handle_draft_returns_201_with_post_id_on_success(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'wp_insert_post' )->justReturn( 100 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_term_by' )->justReturn( false );
		Functions\when( 'get_edit_post_link' )->justReturn( 'https://example.com/wp-admin/post.php?post=100&action=edit' );

		$request  = $this->make_request(
			array(
				'title'    => 'Congrats to our new blue belts!',
				'content'  => 'Great job everyone at belt testing today.',
				'category' => 'general',
			)
		);
		$response = $this->sut->handle_draft( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 100, $body['data']['post_id'] );
		$this->assertSame( 'pending', $body['data']['status'] );
		$this->assertArrayHasKey( 'edit_url', $body['data'] );
	}

	/**
	 * @testdox handle_draft should return 500 when wp_insert_post fails.
	 */
	public function test_handle_draft_returns_500_when_wp_insert_post_fails(): void {
		$wp_error = new \WP_Error( 'db_insert_error', 'Could not insert post into the database.' );

		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'wp_insert_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->alias(
			static function ( mixed $val ): bool {
				return $val instanceof \WP_Error;
			}
		);

		$request = $this->make_request(
			array(
				'title'    => 'Failed Post',
				'content'  => 'This will fail.',
				'category' => 'general',
			)
		);
		$result  = $this->sut->handle_draft( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'social_draft_failed', $result->get_error_code() );
		$this->assertSame( array( 'status' => 500 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// handle_approve
	// -------------------------------------------------------------------------

	/**
	 * @testdox handle_approve should return success for a valid pending social post.
	 */
	public function test_handle_approve_returns_success_for_valid_pending_social_post(): void {
		$post = $this->make_post();

		Functions\when( 'get_current_user_id' )->justReturn( 2 );
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->justReturn( true );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_update_post' )->justReturn( 100 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'do_action' )->justReturn( null );

		$request  = $this->make_request( array( 'post_id' => 100 ) );
		$response = $this->sut->handle_approve( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertSame( 100, $body['data']['post_id'] );
		$this->assertSame( 'publish', $body['data']['status'] );
	}

	/**
	 * @testdox handle_approve should return 400 for a non-pending post.
	 */
	public function test_handle_approve_returns_400_for_non_pending_post(): void {
		$post = $this->make_post( array( 'post_status' => 'publish' ) );

		Functions\when( 'get_current_user_id' )->justReturn( 2 );
		Functions\when( 'get_post' )->justReturn( $post );

		$request = $this->make_request( array( 'post_id' => 100 ) );
		$result  = $this->sut->handle_approve( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'social_approve_failed', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// handle_get_pending
	// -------------------------------------------------------------------------

	/**
	 * @testdox handle_get_pending should return array of pending posts.
	 */
	public function test_handle_get_pending_returns_array_of_pending_posts(): void {
		$post_a = $this->make_post( array( 'ID' => 100, 'post_title' => 'Post A' ) );
		$post_b = $this->make_post( array( 'ID' => 101, 'post_title' => 'Post B' ) );

		\WP_Query::$__test_result = array(
			'posts'         => array( $post_a, $post_b ),
			'found_posts'   => 2,
			'max_num_pages' => 1,
		);

		Functions\when( 'get_post_meta' )->justReturn( 'gandalf' );
		Functions\when( 'get_edit_post_link' )->justReturn( 'https://example.com/wp-admin/post.php?post=100&action=edit' );

		$request  = $this->make_request();
		$response = $this->sut->handle_get_pending( $request );

		\WP_Query::__test_reset();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );
		$this->assertCount( 2, $body['data'] );
		$this->assertSame( 100, $body['data'][0]['id'] );
		$this->assertSame( 'Post A', $body['data'][0]['title'] );
		$this->assertSame( 101, $body['data'][1]['id'] );
		$this->assertSame( 'Post B', $body['data'][1]['title'] );
		$this->assertArrayHasKey( 'suggested_by', $body['data'][0] );
		$this->assertArrayHasKey( 'edit_url', $body['data'][0] );
	}

	// -------------------------------------------------------------------------
	// permissions_manage_announcements
	// -------------------------------------------------------------------------

	/**
	 * @testdox permissions_manage_announcements should return true with gym_manage_announcements capability.
	 */
	public function test_permissions_manage_announcements_returns_true_with_capability(): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'gym_manage_announcements' === $cap;
			}
		);

		$request = $this->make_request();
		$result  = $this->sut->permissions_manage_announcements( $request );

		$this->assertTrue( $result );
	}

	/**
	 * @testdox permissions_manage_announcements should return WP_Error without capability.
	 */
	public function test_permissions_manage_announcements_returns_error_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );

		$request = $this->make_request();
		$result  = $this->sut->permissions_manage_announcements( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( array( 'status' => 403 ), $result->get_error_data() );
	}
}
