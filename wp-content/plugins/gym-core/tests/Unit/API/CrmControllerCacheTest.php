<?php
/**
 * Unit tests for the CrmController pipeline-cache invalidator.
 *
 * @package Gym_Core\Tests\Unit\API
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\API;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\API\CrmController;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class CrmControllerCacheTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	#[TestDox('invalidate_pipeline_cache deletes the cached pipeline summary.')]
	public function test_invalidate_pipeline_cache_calls_wp_cache_delete(): void {
		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'pipeline_summary', 'gym_core_crm' );

		CrmController::invalidate_pipeline_cache();
		$this->assertTrue( true );
	}
}
