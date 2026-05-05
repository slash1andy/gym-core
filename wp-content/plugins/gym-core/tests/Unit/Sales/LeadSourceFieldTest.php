<?php
/**
 * Unit tests for {@see LeadSourceField}.
 *
 * Covers:
 *  - Validation: empty source rejected, unknown slug rejected, "Other" without
 *    detail rejected, valid options accepted, "Other" + detail accepted,
 *    "Other" detail truncated to 200 chars.
 *  - Filter `gym_core_lead_sources` extends + sanitises options.
 *  - `persist_to_order` writes order meta (HPOS-safe via `update_meta_data`).
 *  - `persist_to_user` writes user meta on first capture, no-ops on re-entry.
 *
 * @package Gym_Core\Tests\Unit\Sales
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Sales;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Sales\LeadSourceField;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the lead-source field.
 */
class LeadSourceFieldTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'__'                  => static fn ( string $text ): string => $text,
				'sanitize_key'        => static function ( $key ): string {
					$key = strtolower( (string) $key );
					return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
				},
				'sanitize_text_field' => static function ( $value ): string {
					$value = (string) $value;
					$value = strip_tags( $value );
					$value = preg_replace( '/\s+/', ' ', $value );
					return trim( (string) $value );
				},
			)
		);

		// By default, the filter is a passthrough.
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	#[Test]
	#[TestDox( 'get_options returns the canonical six-option list with valid shape' )]
	public function it_returns_canonical_options(): void {
		$options = LeadSourceField::get_options();

		$this->assertCount( 6, $options );
		$slugs = array_column( $options, 'slug' );
		$this->assertSame(
			array( 'google', 'walk_in', 'referral', 'facebook', 'instagram', 'other' ),
			$slugs
		);

		foreach ( $options as $opt ) {
			$this->assertArrayHasKey( 'slug', $opt );
			$this->assertArrayHasKey( 'label', $opt );
			$this->assertNotSame( '', $opt['slug'] );
			$this->assertNotSame( '', $opt['label'] );
		}
	}

	#[Test]
	#[TestDox( 'gym_core_lead_sources filter extends the option list' )]
	public function filter_can_extend_options(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'gym_core_lead_sources' === $hook ) {
					$value[] = array( 'slug' => 'tiktok', 'label' => 'TikTok' );
				}
				return $value;
			}
		);

		$slugs = array_column( LeadSourceField::get_options(), 'slug' );
		$this->assertContains( 'tiktok', $slugs );
		$this->assertTrue( LeadSourceField::is_valid_source( 'tiktok' ) );
	}

	#[Test]
	#[TestDox( 'gym_core_lead_sources filter drops malformed entries silently' )]
	public function filter_drops_malformed_entries(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'gym_core_lead_sources' === $hook ) {
					return array(
						array( 'slug' => 'google', 'label' => 'Google' ),
						'not-an-array',
						array( 'slug' => '', 'label' => 'Empty slug' ),
						array( 'slug' => 'partial' ), // missing label
						array( 'slug' => 'tiktok', 'label' => 'TikTok' ),
					);
				}
				return $value;
			}
		);

		$options = LeadSourceField::get_options();
		$slugs   = array_column( $options, 'slug' );

		$this->assertSame( array( 'google', 'tiktok' ), $slugs );
	}

	#[Test]
	#[TestDox( 'validate rejects an empty source with WP_Error code gym_lead_source_required' )]
	public function validate_rejects_empty_source(): void {
		$result = LeadSourceField::validate( '', '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_lead_source_required', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 422, $data['status'] );
		$this->assertSame( 'lead_source', $data['field'] );
	}

	#[Test]
	#[TestDox( 'validate rejects an unknown source slug' )]
	public function validate_rejects_unknown_source(): void {
		$result = LeadSourceField::validate( 'craigslist', '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_lead_source_invalid', $result->get_error_code() );
	}

	#[Test]
	#[TestDox( 'validate rejects Other without a detail string' )]
	public function validate_rejects_other_without_text(): void {
		$result = LeadSourceField::validate( 'other', '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gym_lead_source_other_required', $result->get_error_code() );
	}

	#[Test]
	#[TestDox( 'validate accepts a known source and returns normalised pair' )]
	public function validate_accepts_known_source(): void {
		$result = LeadSourceField::validate( 'google', '' );

		$this->assertIsArray( $result );
		$this->assertSame( 'google', $result['source'] );
		$this->assertSame( '', $result['other'] );
	}

	#[Test]
	#[TestDox( 'validate accepts Other plus detail and keeps the detail' )]
	public function validate_accepts_other_with_text(): void {
		$result = LeadSourceField::validate( 'other', 'Saw the gym window' );

		$this->assertIsArray( $result );
		$this->assertSame( 'other', $result['source'] );
		$this->assertSame( 'Saw the gym window', $result['other'] );
	}

	#[Test]
	#[TestDox( 'validate strips Other detail when source is not Other' )]
	public function validate_drops_other_text_when_source_is_not_other(): void {
		$result = LeadSourceField::validate( 'google', 'this should be ignored' );

		$this->assertIsArray( $result );
		$this->assertSame( 'google', $result['source'] );
		$this->assertSame( '', $result['other'] );
	}

	#[Test]
	#[TestDox( 'validate truncates Other detail beyond 200 characters' )]
	public function validate_truncates_long_other_text(): void {
		$long   = str_repeat( 'a', 250 );
		$result = LeadSourceField::validate( 'other', $long );

		$this->assertIsArray( $result );
		$this->assertSame( 200, strlen( (string) $result['other'] ) );
	}

	#[Test]
	#[TestDox( 'persist_to_order calls update_meta_data with canonical keys' )]
	public function persist_to_order_writes_meta(): void {
		$order = Mockery::mock( '\WC_Order' );
		$order->shouldReceive( 'update_meta_data' )
			->once()
			->with( '_gym_lead_source', 'referral' );
		$order->shouldReceive( 'update_meta_data' )
			->once()
			->with( '_gym_lead_source_other', 'My friend Drew' );

		LeadSourceField::persist_to_order( $order, 'referral', 'My friend Drew' );

		$this->addToAssertionCount( 1 );
	}

	#[Test]
	#[TestDox( 'persist_to_order omits the Other meta when text is empty' )]
	public function persist_to_order_omits_other_meta_when_blank(): void {
		$order = Mockery::mock( '\WC_Order' );
		$order->shouldReceive( 'update_meta_data' )
			->once()
			->with( '_gym_lead_source', 'google' );
		$order->shouldNotReceive( 'update_meta_data' )->with( '_gym_lead_source_other', Mockery::any() );

		LeadSourceField::persist_to_order( $order, 'google', '' );

		$this->addToAssertionCount( 1 );
	}

	#[Test]
	#[TestDox( 'persist_to_user writes user meta when no value is set yet' )]
	public function persist_to_user_writes_meta_on_first_capture(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 42, '_gym_lead_source', true )
			->andReturn( '' );

		Functions\expect( 'update_user_meta' )
			->once()
			->with( 42, '_gym_lead_source', 'instagram' )
			->andReturn( true );

		Functions\expect( 'update_user_meta' )
			->once()
			->with( 42, '_gym_lead_source_other', 'Reels ad' )
			->andReturn( true );

		LeadSourceField::persist_to_user( 42, 'instagram', 'Reels ad' );

		// Brain\Monkey expectations are verified on tearDown; record an
		// explicit assertion so PHPUnit doesn't flag the test as risky.
		$this->addToAssertionCount( 1 );
	}

	#[Test]
	#[TestDox( 'persist_to_user is a no-op when the user already carries a captured source (first-touch wins)' )]
	public function persist_to_user_is_idempotent(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 42, '_gym_lead_source', true )
			->andReturn( 'google' );

		Functions\expect( 'update_user_meta' )->never();

		LeadSourceField::persist_to_user( 42, 'instagram', '' );
		$this->addToAssertionCount( 1 );
	}

	#[Test]
	#[TestDox( 'persist_to_user is a no-op when user_id is invalid or source is empty' )]
	public function persist_to_user_short_circuits_on_invalid_input(): void {
		Functions\expect( 'get_user_meta' )->never();
		Functions\expect( 'update_user_meta' )->never();

		LeadSourceField::persist_to_user( 0, 'google', '' );
		LeadSourceField::persist_to_user( 42, '', '' );
		$this->addToAssertionCount( 1 );
	}

	#[Test]
	#[TestDox( 'crm_tags_for produces the lead-source: <slug> tag for valid input' )]
	public function crm_tags_for_returns_tag_for_known_source(): void {
		$tags = LeadSourceField::crm_tags_for( 'facebook' );
		$this->assertSame( array( 'lead-source: facebook' ), $tags );
	}

	#[Test]
	#[TestDox( 'crm_tags_for returns an empty array for empty input' )]
	public function crm_tags_for_returns_empty_for_blank(): void {
		$this->assertSame( array(), LeadSourceField::crm_tags_for( '' ) );
	}

	#[Test]
	#[TestDox( 'label_for returns the canonical label for a known slug' )]
	public function label_for_returns_canonical_label(): void {
		$this->assertSame( 'Walk-in', LeadSourceField::label_for( 'walk_in' ) );
	}

	#[Test]
	#[TestDox( 'label_for falls back to a humanised version of an unknown slug' )]
	public function label_for_falls_back_for_unknown_slugs(): void {
		$this->assertSame( 'Tik Tok', LeadSourceField::label_for( 'tik_tok' ) );
		$this->assertSame( 'Unknown', LeadSourceField::label_for( '' ) );
	}
}
