<?php
/**
 * Unit tests for KioskEndpoint::get_kiosk_location() cookie validation.
 *
 * @package Gym_Core\Tests\Unit\Sales
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Sales;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Sales\KioskEndpoint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Verifies that an attacker-controlled gym_location cookie cannot bypass the
 * known-locations whitelist (Taxonomy::is_valid).
 */
class KioskEndpointLocationTest extends TestCase {

	private KioskEndpoint $endpoint;
	private ReflectionMethod $method;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'sanitize_text_field' => static fn ( string $value ): string => trim( $value ),
				'wp_unslash'          => static fn ( $value ) => $value,
				'get_current_user_id' => static fn (): int => 0,
				'is_wp_error'         => static fn ( $value ): bool => false,
				'wp_cache_set'        => static fn (): bool => true,
			)
		);

		$_COOKIE = array();

		$this->endpoint = new KioskEndpoint();
		$this->method   = new ReflectionMethod( KioskEndpoint::class, 'get_kiosk_location' );
	}

	protected function tearDown(): void {
		$_COOKIE = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Stubs the term lookup that backs Taxonomy::is_valid + get_location_labels.
	 *
	 * @param array<string, string> $labels Slug => label map.
	 */
	private function stub_locations( array $labels ): void {
		$terms = array();
		foreach ( $labels as $slug => $name ) {
			$terms[] = (object) array(
				'slug' => $slug,
				'name' => $name,
			);
		}

		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'get_terms' )->justReturn( $terms );
		Functions\when( 'get_user_meta' )->justReturn( '' );
	}

	#[Test]
	public function valid_cookie_is_returned_unchanged(): void {
		$this->stub_locations( array( 'rockford' => 'Rockford', 'beloit' => 'Beloit' ) );
		$_COOKIE['gym_location'] = 'beloit';

		$this->assertSame( 'beloit', $this->method->invoke( $this->endpoint ) );
	}

	#[Test]
	public function invalid_cookie_falls_back_to_first_location(): void {
		$this->stub_locations( array( 'rockford' => 'Rockford', 'beloit' => 'Beloit' ) );
		$_COOKIE['gym_location'] = '../../etc/passwd';

		$this->assertSame( 'rockford', $this->method->invoke( $this->endpoint ) );
	}

	#[Test]
	public function empty_cookie_falls_back_to_first_location(): void {
		$this->stub_locations( array( 'rockford' => 'Rockford' ) );

		$this->assertSame( 'rockford', $this->method->invoke( $this->endpoint ) );
	}
}
