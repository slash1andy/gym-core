<?php
declare(strict_types=1);

namespace HMA_AI_Chat\Tests\Unit\Tools;

use Brain\Monkey;
use HMA_AI_Chat\Tools\ToolExecutor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[\PHPUnit\Framework\Attributes\CoversClass( \HMA_AI_Chat\Tools\ToolExecutor::class )]
class ToolExecutorTest extends TestCase {

	private ToolExecutor $executor;
	private ReflectionMethod $resolve_route;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->executor = $this->getMockBuilder( ToolExecutor::class )
			->disableOriginalConstructor()
			->getMock();

		$this->resolve_route = new ReflectionMethod( ToolExecutor::class, 'resolve_route' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function resolve( string $endpoint, array &$params ): string {
		$args = [ $endpoint, &$params ];
		return $this->resolve_route->invokeArgs( $this->executor, $args );
	}

	public function test_gym_endpoint_gets_namespace_prepended(): void {
		$params = [];
		$result = $this->resolve( '/members', $params );

		$this->assertStringStartsWith( '/gym/v1/members', $result );
	}

	public function test_wc_endpoint_is_not_modified(): void {
		$params = [];
		$result = $this->resolve( '/wc/v3/orders', $params );

		$this->assertSame( '/wc/v3/orders', $result );
	}

	public function test_placeholder_is_substituted_from_params(): void {
		$params = [ 'id' => 42 ];
		$result = $this->resolve( '/members/{id}/rank', $params );

		$this->assertStringContainsString( '42', $result );
	}

	public function test_placeholder_param_is_consumed(): void {
		$params = [ 'id' => 42 ];
		$this->resolve( '/members/{id}/rank', $params );

		$this->assertArrayNotHasKey( 'id', $params );
	}

	public function test_unknown_placeholder_is_left_as_is(): void {
		$params = [];
		$result = $this->resolve( '/members/{unknown}/rank', $params );

		$this->assertStringContainsString( '{unknown}', $result );
	}

	public function test_multiple_placeholders_are_all_substituted(): void {
		$params = [ 'user_id' => 7, 'program' => 'bjj' ];
		$result = $this->resolve( '/members/{user_id}/programs/{program}', $params );

		$this->assertStringContainsString( '7', $result );
		$this->assertStringContainsString( 'bjj', $result );
		$this->assertEmpty( $params );
	}

	public function test_non_placeholder_params_are_not_consumed(): void {
		$params = [ 'id' => 1, 'extra' => 'foo' ];
		$this->resolve( '/members/{id}/rank', $params );

		$this->assertArrayHasKey( 'extra', $params );
	}
}
