<?php
/**
 * Unit tests for MessageTemplates.
 *
 * @package Gym_Core\Tests\Unit\SMS
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\SMS;

use Gym_Core\SMS\MessageTemplates;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Tests for SMS message templates.
 */
class MessageTemplatesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'__' => static function ( string $text ): string {
					return $text;
				},
				'apply_filters' => static function ( string $hook, $value ) {
					return $value;
				},
				'sanitize_text_field' => static function ( string $text ): string {
					return $text;
				},
				'home_url' => static function (): string {
					return 'https://www.teamhaanpaa.com';
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_all_returns_templates(): void {
		$templates = MessageTemplates::get_all();

		$this->assertNotEmpty( $templates );
		$this->assertArrayHasKey( 'lead_followup', $templates );
		$this->assertArrayHasKey( 'class_reminder', $templates );
		$this->assertArrayHasKey( 'belt_promotion', $templates );
	}

	public function test_every_template_has_required_fields(): void {
		$templates = MessageTemplates::get_all();

		foreach ( $templates as $slug => $template ) {
			$this->assertArrayHasKey( 'name', $template, "Template '{$slug}' missing 'name'" );
			$this->assertArrayHasKey( 'body', $template, "Template '{$slug}' missing 'body'" );
			$this->assertArrayHasKey( 'description', $template, "Template '{$slug}' missing 'description'" );
		}
	}

	public function test_get_single_template(): void {
		$template = MessageTemplates::get( 'belt_promotion' );

		$this->assertNotNull( $template );
		$this->assertStringContainsString( '{belt}', $template['body'] );
	}

	public function test_get_unknown_returns_null(): void {
		$this->assertNull( MessageTemplates::get( 'nonexistent' ) );
	}

	public function test_render_substitutes_variables(): void {
		$result = MessageTemplates::render(
			'belt_promotion',
			array(
				'first_name' => 'John',
				'belt'       => 'Blue Belt',
				'program'    => 'Adult BJJ',
			)
		);

		$this->assertNotNull( $result );
		$this->assertStringContainsString( 'John', $result );
		$this->assertStringContainsString( 'Blue Belt', $result );
		$this->assertStringContainsString( 'Adult BJJ', $result );
		$this->assertStringNotContainsString( '{first_name}', $result );
	}

	public function test_render_includes_site_url(): void {
		$result = MessageTemplates::render( 'payment_failed', array( 'first_name' => 'Jane' ) );

		$this->assertNotNull( $result );
		$this->assertStringContainsString( 'teamhaanpaa.com', $result );
	}

	public function test_render_unknown_template_returns_null(): void {
		$this->assertNull( MessageTemplates::render( 'nonexistent' ) );
	}

	public function test_get_slugs_returns_all_slugs(): void {
		$slugs     = MessageTemplates::get_slugs();
		$templates = MessageTemplates::get_all();

		$this->assertSame( array_keys( $templates ), $slugs );
	}

	public function test_templates_under_1600_chars(): void {
		$templates = MessageTemplates::get_all();

		foreach ( $templates as $slug => $template ) {
			$this->assertLessThanOrEqual(
				1600,
				mb_strlen( $template['body'] ),
				"Template '{$slug}' exceeds 1600 character SMS limit"
			);
		}
	}
}
