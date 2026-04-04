<?php
/**
 * Brand name utility.
 *
 * Provides a single source of truth for the gym's display name,
 * replacing all hardcoded brand references throughout the codebase.
 *
 * @package Gym_Core
 * @since   2.4.0
 */

declare( strict_types=1 );

namespace Gym_Core\Utilities;

/**
 * Brand name helper.
 */
final class Brand {

	/**
	 * Option key for the brand name.
	 */
	private const OPTION_KEY = 'gym_core_brand_name';

	/**
	 * Default brand name.
	 */
	private const DEFAULT_NAME = 'Haanpaa Martial Arts';

	/**
	 * Cached brand name.
	 *
	 * @var string|null
	 */
	private static ?string $cached = null;

	/**
	 * Returns the gym's display name.
	 *
	 * @return string
	 */
	public static function name(): string {
		if ( null === self::$cached ) {
			self::$cached = (string) get_option( self::OPTION_KEY, self::DEFAULT_NAME );
		}

		return self::$cached;
	}

	/**
	 * Resets the internal cache (useful after option updates).
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cached = null;
	}
}
