<?php
/**
 * Plugin deactivation handler.
 *
 * @package HMA_AI_Chat
 */

namespace HMA_AI_Chat;

/**
 * Handles plugin deactivation tasks.
 *
 * @since 0.1.0
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	public static function deactivate() {
		// Clean up any scheduled events.
		self::clear_scheduled_events();
	}

	/**
	 * Clear all scheduled events for this plugin.
	 *
	 * @since 0.1.0
	 * @internal
	 */
	private static function clear_scheduled_events() {
		wp_clear_scheduled_hook( Plugin::PURGE_CRON_HOOK );
	}
}
