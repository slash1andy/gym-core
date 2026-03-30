<?php
/**
 * Stub for WooCommerce Blocks IntegrationInterface.
 *
 * Allows BlockIntegration.php to be autoloaded in unit tests
 * without the WooCommerce Blocks package installed.
 *
 * @package Gym_Core\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

namespace Automattic\WooCommerce\Blocks\Integrations;

/**
 * Stub interface matching the WC Blocks IntegrationInterface contract.
 */
interface IntegrationInterface {

	/**
	 * Get integration name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Initialize the integration.
	 *
	 * @return void
	 */
	public function initialize(): void;

	/**
	 * Get frontend script handles.
	 *
	 * @return array<string>
	 */
	public function get_script_handles(): array;

	/**
	 * Get editor script handles.
	 *
	 * @return array<string>
	 */
	public function get_editor_script_handles(): array;

	/**
	 * Get data to pass to scripts.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array;
}
