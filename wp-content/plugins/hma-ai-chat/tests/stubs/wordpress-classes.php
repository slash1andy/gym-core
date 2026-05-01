<?php
/**
 * Minimal stubs for WordPress classes used by the hma-ai-chat plugin.
 *
 * Brain\Monkey handles WordPress *functions*; these stubs cover WordPress
 * *classes* that are extended or referenced via instanceof in the plugin code.
 *
 * @package HMA_AI_Chat\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

if ( ! class_exists( 'WP_User' ) ) {
	/**
	 * Stub for WP_User.
	 */
	class WP_User { // phpcs:ignore
		/** @var int */
		public int $ID = 0;
		/** @var string */
		public string $user_login = '';
		/** @var string */
		public string $user_email = '';

		/**
		 * Constructor.
		 *
		 * @param int    $id    User ID.
		 * @param string $login User login.
		 */
		public function __construct( int $id = 0, string $login = '' ) {
			$this->ID         = $id;
			$this->user_login = $login;
		}
	}
}

if ( ! class_exists( 'WP_User_Query' ) ) {
	/**
	 * Stub for WP_User_Query.
	 */
	class WP_User_Query { // phpcs:ignore
		/** @var array<int, WP_User> */
		public static array $__test_results = array();

		/**
		 * Constructor.
		 *
		 * @param array<string, mixed> $args Query arguments.
		 */
		public function __construct( array $args = array() ) {}

		/**
		 * Returns the query results.
		 *
		 * @return array<int, WP_User>
		 */
		public function get_results(): array {
			return self::$__test_results;
		}

		/**
		 * Sets a query variable.
		 *
		 * @param string $key   Variable name.
		 * @param mixed  $value Variable value.
		 * @return void
		 */
		public function set( string $key, mixed $value ): void {}

		/**
		 * Gets a query variable.
		 *
		 * @param string $key Variable name.
		 * @return mixed
		 */
		public function get( string $key ): mixed {
			return null;
		}

		/**
		 * Resets the test preset. Call in tearDown().
		 *
		 * @return void
		 */
		public static function __test_reset(): void {
			self::$__test_results = array();
		}
	}
}
