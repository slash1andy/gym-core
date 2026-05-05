<?php
/**
 * Unit tests for the PWA module.
 *
 * @package Gym_Core\Tests\Unit\PWA
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\PWA;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\PWA\PWAController;
use Gym_Core\PWA\PushSubscriptionEndpoint;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for PWAController and PushSubscriptionEndpoint.
 */
class PWAControllerTest extends TestCase {

	/**
	 * Sets up Brain\Monkey before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tears down Brain\Monkey after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @TestDox PWAController::get_manifest_url() returns a non-empty string.
	 *
	 * @return void
	 */
	#[TestDox( 'PWAController::get_manifest_url() returns a non-empty string' )]
	public function test_get_manifest_url_returns_non_empty_string(): void {
		Functions\when( 'home_url' )->returnArg( 1 );

		$url = PWAController::get_manifest_url();

		$this->assertIsString( $url );
		$this->assertNotEmpty( $url );
	}

	/**
	 * @TestDox PushSubscriptionEndpoint::get_route() returns the expected route string.
	 *
	 * @return void
	 */
	#[TestDox( 'PushSubscriptionEndpoint::get_route() returns gym/v1/pwa/push-subscribe' )]
	public function test_push_subscription_endpoint_route(): void {
		$this->assertSame( 'gym/v1/pwa/push-subscribe', PushSubscriptionEndpoint::get_route() );
	}
}
