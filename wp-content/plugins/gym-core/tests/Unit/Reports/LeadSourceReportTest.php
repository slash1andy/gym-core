<?php
/**
 * Unit tests for {@see LeadSourceReport::build_csv_rows()}.
 *
 * The CSV builder is a pure function so it can be exercised without the
 * WordPress runtime. We assert header shape, per-source rows, blank conversion
 * cell when leads = 0, and totals math.
 *
 * @package Gym_Core\Tests\Unit\Reports
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Reports;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Reports\LeadSourceReport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class LeadSourceReportTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs(
			array(
				'__'           => static fn ( string $text ): string => $text,
				'sanitize_key' => static function ( $key ): string {
					$key = strtolower( (string) $key );
					return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
				},
			)
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				return $value;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	#[Test]
	#[TestDox( 'CSV header row exposes the expected columns' )]
	public function csv_header_row_columns(): void {
		$report = new LeadSourceReport();
		$rows   = $report->build_csv_rows( array(), 30 );

		$this->assertSame(
			array( 'source_slug', 'source_label', 'leads', 'members', 'conversion_pct', 'revenue', 'range_days' ),
			$rows[0]
		);
	}

	#[Test]
	#[TestDox( 'CSV body computes conversion %, leaves it blank when leads = 0, and totals correctly' )]
	public function csv_body_math(): void {
		$report = new LeadSourceReport();

		$data = array(
			'google'    => array( 'leads' => 10, 'members' => 4, 'revenue' => 2000.00 ),
			'walk_in'   => array( 'leads' => 0,  'members' => 0, 'revenue' => 0.0 ),
			'referral'  => array( 'leads' => 5,  'members' => 5, 'revenue' => 5000.00 ),
			'facebook'  => array( 'leads' => 3,  'members' => 0, 'revenue' => 0.0 ),
			'instagram' => array( 'leads' => 0,  'members' => 0, 'revenue' => 0.0 ),
			'other'     => array( 'leads' => 0,  'members' => 0, 'revenue' => 0.0 ),
			''          => array( 'leads' => 0,  'members' => 0, 'revenue' => 0.0 ),
		);

		$rows = $report->build_csv_rows( $data, 30 );

		// Header + 7 source rows (6 canonical + "Not captured") + totals.
		$this->assertCount( 9, $rows );

		// Find the Google row.
		$google_row = null;
		foreach ( $rows as $row ) {
			if ( 'google' === $row[0] ) {
				$google_row = $row;
				break;
			}
		}
		$this->assertNotNull( $google_row );
		$this->assertSame( 'Google', $google_row[1] );
		$this->assertSame( '10', $google_row[2] );
		$this->assertSame( '4', $google_row[3] );
		$this->assertSame( '40.0', $google_row[4] );
		$this->assertSame( '2000.00', $google_row[5] );
		$this->assertSame( '30', $google_row[6] );

		// Walk-in: leads = 0 → conversion column is empty.
		$walk_in_row = null;
		foreach ( $rows as $row ) {
			if ( 'walk_in' === $row[0] ) {
				$walk_in_row = $row;
				break;
			}
		}
		$this->assertNotNull( $walk_in_row );
		$this->assertSame( '0', $walk_in_row[2] );
		$this->assertSame( '', $walk_in_row[4] );

		// Totals row: 18 leads / 9 members / $7000 / 50.0% conversion.
		$total = $rows[ count( $rows ) - 1 ];
		$this->assertSame( 'total', $total[0] );
		$this->assertSame( '18', $total[2] );
		$this->assertSame( '9', $total[3] );
		$this->assertSame( '50.0', $total[4] );
		$this->assertSame( '7000.00', $total[5] );
	}
}
