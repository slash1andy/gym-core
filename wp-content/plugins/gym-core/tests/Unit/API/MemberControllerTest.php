<?php
/**
 * Unit tests for MemberController.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\MemberController;
use Gym_Core\Rank\RankStore;
use Gym_Core\Attendance\AttendanceStore;
use Gym_Core\Attendance\FoundationsClearance;
use Gym_Core\Gamification\StreakTracker;
use Gym_Core\Gamification\BadgeEngine;
use Mockery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for the MemberController REST endpoint handler.
 *
 * Covers the aggregated member dashboard endpoint: authentication gates,
 * response structure, section resilience, and gamification null handling.
 */
class MemberControllerTest extends TestCase {

/**
	 * The System Under Test.
	 *
	 * @var MemberController
	 */
	private MemberController $sut;

	/**
	 * Mock Rank store.
	 *
	 * @var RankStore&\Mockery\MockInterface
	 */
	private RankStore $ranks;

	/**
	 * Mock Attendance store.
	 *
	 * @var AttendanceStore&\Mockery\MockInterface
	 */
	private AttendanceStore $attendance;

	/**
	 * Mock Foundations clearance.
	 *
	 * @var FoundationsClearance&\Mockery\MockInterface
	 */
	private FoundationsClearance $foundations;

	/**
	 * Mock Streak tracker.
	 *
	 * @var StreakTracker&\Mockery\MockInterface
	 */
	private StreakTracker $streaks;

	/**
	 * Mock Badge engine.
	 *
	 * @var BadgeEngine&\Mockery\MockInterface
	 */
	private BadgeEngine $badges;

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
		Functions\when( 'absint' )->alias(
			static function ( mixed $val ): int {
				return abs( (int) $val );
			}
		);

		// Stub wc_get_logger so the section builders' catch blocks (which call
		// wc_get_logger()->warning()) don't fatal in tests. Brain Monkey leaks
		// function definitions across tests once any test stubs them, so a
		// section that throws in a later, unrelated test would otherwise crash.
		$logger = Mockery::mock();
		$logger->allows( 'warning' );
		$logger->allows( 'error' );
		Functions\when( 'wc_get_logger' )->justReturn( $logger );

		$this->ranks       = Mockery::mock( RankStore::class );
		$this->attendance  = Mockery::mock( AttendanceStore::class );
		$this->foundations = Mockery::mock( FoundationsClearance::class );
		$this->streaks     = Mockery::mock( StreakTracker::class );
		$this->badges      = Mockery::mock( BadgeEngine::class );

		$this->sut = new MemberController(
			$this->ranks,
			$this->attendance,
			$this->foundations,
			$this->streaks,
			$this->badges
		);
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
	 * Creates a mock WP_User with common properties.
	 *
	 * @param int    $id           User ID.
	 * @param string $display_name Display name.
	 * @param string $user_email   Email address.
	 * @return \WP_User&\Mockery\MockInterface
	 */
	private function make_user( int $id = 42, string $display_name = 'John Doe', string $user_email = 'john@example.com' ): \WP_User {
		$user = Mockery::mock( 'WP_User' );
		$user->ID           = $id;
		$user->display_name = $display_name;
		$user->user_email   = $user_email;

		return $user;
	}

/**
	 * Stubs all WordPress functions needed for a successful dashboard response.
	 *
	 * @param int      $user_id User ID to return from get_current_user_id().
	 * @param \WP_User $user    Mock WP_User to return from get_userdata().
	 * @return void
	 */
	private function stub_successful_dashboard( int $user_id, \WP_User $user ): void {
		Functions\when( 'get_current_user_id' )->justReturn( $user_id );
		Functions\when( 'get_userdata' )->justReturn( $user );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'get_term_by' )->justReturn( false );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( 'no' );

		$this->ranks->allows( 'get_all_ranks' )->andReturn( array() );
		$this->attendance->allows( 'get_total_count' )->andReturn( 10 );
	}

	// -------------------------------------------------------------------------
	// Authentication
	// -------------------------------------------------------------------------

	#[TestDox('get_dashboard returns 401 when no user is logged in.')]
	public function test_get_dashboard_returns_401_when_not_logged_in(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$request  = $this->make_request();
		$response = $this->sut->get_dashboard( $request );

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertSame( 'rest_not_logged_in', $response->get_error_code() );
		$this->assertSame( array( 'status' => 401 ), $response->get_error_data() );
	}

	#[TestDox('get_dashboard returns 404 when user does not exist.')]
	public function test_get_dashboard_returns_404_when_user_not_found(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 99 );
		Functions\when( 'get_userdata' )->justReturn( false );

		$request  = $this->make_request();
		$response = $this->sut->get_dashboard( $request );

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertSame( 'invalid_user', $response->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $response->get_error_data() );
	}

	// -------------------------------------------------------------------------
	// Successful dashboard — response structure
	// -------------------------------------------------------------------------

	#[TestDox('get_dashboard returns success response with all expected section keys.')]
	public function test_get_dashboard_returns_success_with_all_section_keys(): void {
		$user = $this->make_user();
		$this->stub_successful_dashboard( 42, $user );

		// FoundationsClearance::is_enabled() is called as a static method.
		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);

		$this->streaks->allows( 'get_streak' )->andReturn(
			array( 'current_streak' => 3, 'longest_streak' => 5 )
		);
		$this->badges->allows( 'get_user_badges' )->andReturn( array() );

		$request  = $this->make_request();
		$response = $this->sut->get_dashboard( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$body = $response->get_data();
		$this->assertTrue( $body['success'] );

		$expected_keys = array(
			'member',
			'memberships',
			'billing',
			'upcoming_classes',
			'rank',
			'foundations',
			'gamification',
			'quick_links',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $body['data'], "Missing section key: {$key}" );
		}
	}

	#[TestDox('get_dashboard includes member section with user info.')]
	public function test_get_dashboard_includes_member_section_with_user_info(): void {
		$user = $this->make_user( 42, 'Jane Doe', 'jane@example.com' );
		$this->stub_successful_dashboard( 42, $user );

		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);
		$this->streaks->allows( 'get_streak' )->andReturn(
			array( 'current_streak' => 0, 'longest_streak' => 0 )
		);
		$this->badges->allows( 'get_user_badges' )->andReturn( array() );

		$request  = $this->make_request();
		$response = $this->sut->get_dashboard( $request );
		$member   = $response->get_data()['data']['member'];

		$this->assertSame( 42, $member['id'] );
		$this->assertSame( 'Jane Doe', $member['display_name'] );
		$this->assertSame( 'jane@example.com', $member['email'] );
		$this->assertArrayHasKey( 'location', $member );
	}

	// -------------------------------------------------------------------------
	// Memberships — no subscription plugin
	// -------------------------------------------------------------------------

	#[TestDox('get_dashboard returns empty memberships when no subscription plugin is available.')]
	public function test_get_dashboard_returns_empty_memberships_without_subscription_plugin(): void {
		$user = $this->make_user();
		$this->stub_successful_dashboard( 42, $user );

		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);
		$this->streaks->allows( 'get_streak' )->andReturn(
			array( 'current_streak' => 0, 'longest_streak' => 0 )
		);
		$this->badges->allows( 'get_user_badges' )->andReturn( array() );

		$request      = $this->make_request();
		$response     = $this->sut->get_dashboard( $request );
		$memberships  = $response->get_data()['data']['memberships'];

		$this->assertSame( array(), $memberships );
	}

	// -------------------------------------------------------------------------
	// Billing — WC_Payment_Tokens lookup
	// -------------------------------------------------------------------------

	/**
	 * Builds a Mockery WC_Subscription preconfigured for the billing-section tests.
	 *
	 * Returns a mock that responds to every method MemberController touches in the
	 * subscription flow (memberships + billing): get_status, get_date for any key,
	 * get_items, get_total, get_payment_method_title, get_meta.
	 *
	 * @param int                $token_id     Token id stored on '_payment_tokens' meta.
	 * @param \DateTimeImmutable $next_payment Date returned for the 'next_payment' key.
	 * @return \WC_Subscription&\Mockery\MockInterface
	 */
	private function make_billing_subscription( int $token_id, \DateTimeImmutable $next_payment ): \WC_Subscription {
		$subscription = Mockery::mock( \WC_Subscription::class );
		$subscription->allows( 'get_status' )->andReturn( 'active' );
		$subscription->allows( 'get_date' )->andReturnUsing(
			static function ( string $key ) use ( $next_payment ): mixed {
				return 'next_payment' === $key ? $next_payment : '';
			}
		);
		$subscription->allows( 'get_items' )->andReturn( array() );
		$subscription->allows( 'get_payment_method_title' )->andReturn( 'Credit card' );
		$subscription->allows( 'get_meta' )->with( '_payment_tokens' )->andReturn( $token_id );
		$subscription->allows( 'get_total' )->andReturn( '99.00' );

		return $subscription;
	}

	#[TestDox('build_billing_section uses the matching WC_Payment_Token display name when WC_Payment_Tokens is available.')]
	public function test_billing_uses_token_display_name_when_payment_tokens_class_exists(): void {
		$user = $this->make_user();
		$this->stub_successful_dashboard( 42, $user );

		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);
		$this->streaks->allows( 'get_streak' )->andReturn(
			array( 'current_streak' => 0, 'longest_streak' => 0 )
		);
		$this->badges->allows( 'get_user_badges' )->andReturn( array() );

		$next_payment = new \DateTimeImmutable( '2026-06-01T00:00:00Z' );
		$subscription = $this->make_billing_subscription( 7, $next_payment );

		Functions\when( 'wcs_get_users_subscriptions' )->justReturn( array( $subscription ) );

		\WC_Payment_Tokens::$__customer_tokens = array(
			42 => array(
				new \WC_Payment_Token( 3, 'Mastercard ending in 1111' ),
				new \WC_Payment_Token( 7, 'Visa ending in 4242' ),
			),
		);

		try {
			$request  = $this->make_request();
			$response = $this->sut->get_dashboard( $request );
			$billing  = $response->get_data()['data']['billing'];

			$this->assertNotNull( $billing );
			$this->assertSame( '2026-06-01', $billing['next_payment_date'] );
			$this->assertSame( '99.00', $billing['next_payment_amount'] );
			$this->assertSame( 'Visa ending in 4242', $billing['payment_method_summary'] );
		} finally {
			\WC_Payment_Tokens::__test_reset();
		}
	}

	#[TestDox('build_billing_section falls back to payment method title when no saved token matches the subscription.')]
	public function test_billing_falls_back_to_payment_method_title_when_no_token_matches(): void {
		$user = $this->make_user();
		$this->stub_successful_dashboard( 42, $user );

		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);
		$this->streaks->allows( 'get_streak' )->andReturn(
			array( 'current_streak' => 0, 'longest_streak' => 0 )
		);
		$this->badges->allows( 'get_user_badges' )->andReturn( array() );

		$next_payment = new \DateTimeImmutable( '2026-06-01T00:00:00Z' );
		$subscription = $this->make_billing_subscription( 999, $next_payment );

		Functions\when( 'wcs_get_users_subscriptions' )->justReturn( array( $subscription ) );

		\WC_Payment_Tokens::$__customer_tokens = array(
			42 => array( new \WC_Payment_Token( 7, 'Visa ending in 4242' ) ),
		);

		try {
			$request  = $this->make_request();
			$response = $this->sut->get_dashboard( $request );
			$billing  = $response->get_data()['data']['billing'];

			$this->assertNotNull( $billing );
			$this->assertSame( 'Credit card', $billing['payment_method_summary'] );
		} finally {
			\WC_Payment_Tokens::__test_reset();
		}
	}

	// -------------------------------------------------------------------------
	// Gamification — with engines
	// -------------------------------------------------------------------------

	#[TestDox('get_dashboard includes gamification section when streak and badge engines are available.')]
	public function test_get_dashboard_includes_gamification_when_engines_available(): void {
		$user = $this->make_user();
		$this->stub_successful_dashboard( 42, $user );

		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);
		$this->streaks->allows( 'get_streak' )->with( 42 )->andReturn(
			array( 'current_streak' => 3, 'longest_streak' => 5 )
		);
		$this->badges->allows( 'get_user_badges' )->with( 42 )->andReturn( array() );

		$request       = $this->make_request();
		$response      = $this->sut->get_dashboard( $request );
		$gamification  = $response->get_data()['data']['gamification'];

		$this->assertNotNull( $gamification );
		$this->assertSame( 3, $gamification['current_streak_weeks'] );
		$this->assertSame( 0, $gamification['badges_earned_count'] );
		$this->assertSame( 10, $gamification['total_classes'] );
	}

	// -------------------------------------------------------------------------
	// Gamification — null engines
	// -------------------------------------------------------------------------

	#[TestDox('get_dashboard returns null gamification when streak and badge engines are null.')]
	public function test_get_dashboard_returns_null_gamification_when_engines_null(): void {
		// Construct a controller with null gamification engines.
		$sut = new MemberController(
			$this->ranks,
			$this->attendance,
			$this->foundations,
			null,
			null
		);

		$user = $this->make_user();
		Functions\when( 'get_current_user_id' )->justReturn( 42 );
		Functions\when( 'get_userdata' )->justReturn( $user );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'get_term_by' )->justReturn( false );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( 'no' );

		$this->ranks->allows( 'get_all_ranks' )->andReturn( array() );
		$this->attendance->allows( 'get_total_count' )->andReturn( 5 );
		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);

		$request       = $this->make_request();
		$response      = $sut->get_dashboard( $request );
		$gamification  = $response->get_data()['data']['gamification'];

		$this->assertNull( $gamification );
	}

	// -------------------------------------------------------------------------
	// Quick links
	// -------------------------------------------------------------------------

	#[TestDox('get_dashboard includes quick_links with expected URL keys.')]
	public function test_get_dashboard_includes_quick_links_with_expected_url_keys(): void {
		$user = $this->make_user();
		$this->stub_successful_dashboard( 42, $user );

		$this->foundations->allows( 'get_status' )->andReturn(
			array( 'in_foundations' => false, 'cleared' => true )
		);
		$this->streaks->allows( 'get_streak' )->andReturn(
			array( 'current_streak' => 0, 'longest_streak' => 0 )
		);
		$this->badges->allows( 'get_user_badges' )->andReturn( array() );

		$request      = $this->make_request();
		$response     = $this->sut->get_dashboard( $request );
		$quick_links  = $response->get_data()['data']['quick_links'];

		$expected_url_keys = array(
			'update_payment_url',
			'billing_history_url',
			'schedule_url',
			'shop_url',
		);

		foreach ( $expected_url_keys as $key ) {
			$this->assertArrayHasKey( $key, $quick_links, "Missing quick_links key: {$key}" );
			$this->assertNotEmpty( $quick_links[ $key ], "Quick link '{$key}' should not be empty." );
		}
	}
}
