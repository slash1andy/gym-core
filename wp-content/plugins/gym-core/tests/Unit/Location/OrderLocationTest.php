<?php
/**
 * Unit tests for OrderLocation.
 *
 * @package Gym_Core\Tests\Unit\Location
 */

declare( strict_types=1 );

namespace Gym_Core\Tests\Unit\Location;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Gym_Core\Location\Manager;
use Gym_Core\Location\OrderLocation;
use Gym_Core\Location\Taxonomy;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WC_Order;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Tests for the OrderLocation class.
 */
class OrderLocationTest extends TestCase {

	use MockeryPHPUnitIntegration;

/**
	 * The System Under Test.
	 *
	 * @var OrderLocation
	 */
	private OrderLocation $sut;

	/**
	 * Mock of the location manager.
	 *
	 * @var Manager&\Mockery\MockInterface
	 */
	private $manager;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_key' )->returnArg( 1 );

		$this->manager = Mockery::mock( Manager::class );
		$this->sut     = new OrderLocation( $this->manager );
	}

/**
	 * Tear down the test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	// ----- save_location_to_order -----

	#[TestDox('Should save the active location to order meta.')]
	public function test_save_location_to_order_saves_meta_when_location_is_set(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( Taxonomy::ROCKFORD );

		$order = Mockery::mock( WC_Order::class );
		$order->expects( 'update_meta_data' )
			->once()
			->with( OrderLocation::META_KEY, Taxonomy::ROCKFORD );
		$order->expects( 'save_meta_data' )->once();

		$this->sut->save_location_to_order( $order );
	}

	#[TestDox('Should not write to order meta when no location is active.')]
	public function test_save_location_to_order_skips_when_no_location(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( '' );

		$order = Mockery::mock( WC_Order::class );
		$order->expects( 'update_meta_data' )->never();
		$order->expects( 'save_meta_data' )->never();

		$this->sut->save_location_to_order( $order );
	}

	#[TestDox('Should save beloit as the location when beloit is the active location.')]
	public function test_save_location_to_order_saves_beloit(): void {
		$this->manager->allows( 'get_current_location' )->andReturn( Taxonomy::BELOIT );

		$order = Mockery::mock( WC_Order::class );
		$order->expects( 'update_meta_data' )
			->once()
			->with( OrderLocation::META_KEY, Taxonomy::BELOIT );
		$order->expects( 'save_meta_data' )->once();

		$this->sut->save_location_to_order( $order );
	}

	// ----- get_order_location -----

	#[TestDox('Should return the location slug stored in order meta.')]
	public function test_get_order_location_returns_stored_meta(): void {
		$order = Mockery::mock( WC_Order::class );
		$order->allows( 'get_meta' )
			->with( OrderLocation::META_KEY, true )
			->andReturn( Taxonomy::ROCKFORD );

		$result = $this->sut->get_order_location( $order );

		$this->assertSame( Taxonomy::ROCKFORD, $result );
	}

	#[TestDox('Should return empty string when order has no location meta.')]
	public function test_get_order_location_returns_empty_string_when_no_meta(): void {
		$order = Mockery::mock( WC_Order::class );
		$order->allows( 'get_meta' )
			->with( OrderLocation::META_KEY, true )
			->andReturn( '' );

		$result = $this->sut->get_order_location( $order );

		$this->assertSame( '', $result );
	}

	#[TestDox('Should return empty string when order meta is not a string.')]
	public function test_get_order_location_returns_empty_when_meta_is_not_string(): void {
		$order = Mockery::mock( WC_Order::class );
		$order->allows( 'get_meta' )
			->with( OrderLocation::META_KEY, true )
			->andReturn( false ); // Non-string value.

		$result = $this->sut->get_order_location( $order );

		$this->assertSame( '', $result );
	}

	// ----- META_KEY constant -----

	#[TestDox('META_KEY constant should be prefixed with an underscore.')]
	public function test_meta_key_is_prefixed(): void {
		$this->assertStringStartsWith( '_', OrderLocation::META_KEY );
	}

	#[TestDox('META_KEY constant should contain the gym namespace.')]
	public function test_meta_key_contains_gym_namespace(): void {
		$this->assertStringContainsString( 'gym', OrderLocation::META_KEY );
	}
}
