<?php
/**
 * Minimal WP_Error stub for unit testing.
 *
 * @package Gym_Core\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

if ( ! class_exists( 'WP_Error' ) ) {

	/**
	 * Stub WP_Error for unit tests without WordPress loaded.
	 */
	class WP_Error {

		/**
		 * Error data storage.
		 *
		 * @var array<string, array<int, string>>
		 */
		private array $errors = array();

		/**
		 * Additional error data (typically contains 'status' key for REST).
		 *
		 * @var mixed
		 */
		private mixed $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Optional error code.
		 * @param string $message Optional error message.
		 * @param mixed  $data    Optional additional data.
		 */
		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			if ( '' !== $code ) {
				$this->add( $code, $message );
			}
			$this->data = $data;
		}

		/**
		 * Adds an error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @return void
		 */
		public function add( string $code, string $message ): void {
			$this->errors[ $code ][] = $message;
		}

		/**
		 * Whether any errors have been added.
		 *
		 * @return bool
		 */
		public function has_errors(): bool {
			return ! empty( $this->errors );
		}

		/**
		 * Returns the first error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			$codes = array_keys( $this->errors );
			return $codes[0] ?? '';
		}

		/**
		 * Returns all error codes.
		 *
		 * @return array<int, string>
		 */
		public function get_error_codes(): array {
			return array_keys( $this->errors );
		}

		/**
		 * Returns the first error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			$code = $this->get_error_code();
			return $this->errors[ $code ][0] ?? '';
		}

		/**
		 * Returns the additional error data.
		 *
		 * @return mixed
		 */
		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}
