<?php
/**
 * Twilio settings page helper.
 *
 * Thin companion to `\Gym_Core\Admin\Settings`: enqueues the vanilla-JS
 * test-button script + minimal styling on the WC > Settings > Gym Core > SMS
 * section, and localises the REST URL and nonce the script needs.
 *
 * The actual settings fields live in `Admin\Settings::get_sms_settings()` so
 * we have a single configuration surface. This class exists only to wire up
 * the asset and to expose a small `gym_core_twilio_settings_url()` helper for
 * "Manage credentials" links elsewhere in the plugin.
 *
 * @package Gym_Core\SMS
 * @since   4.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\SMS;

/**
 * Registers asset enqueue + helpers for the Twilio settings UI.
 */
final class SettingsPage {

	/**
	 * Asset handle for the Twilio settings JS.
	 *
	 * @var string
	 */
	private const SCRIPT_HANDLE = 'gym-core-twilio-settings';

	/**
	 * Asset handle for the inline style block.
	 *
	 * @var string
	 */
	private const STYLE_HANDLE = 'gym-core-twilio-settings-style';

	/**
	 * Registers admin asset enqueue hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
	}

	/**
	 * Returns the admin URL for the Twilio settings section.
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=gym_core&section=sms' );
	}

	/**
	 * Enqueues the test-button script + brand-aligned styles only when the
	 * admin is viewing the SMS settings section.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function maybe_enqueue_assets( string $hook_suffix ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( (string) $_GET['section'] ) ) : '';

		if ( 'gym_core' !== $tab || 'sms' !== $section ) {
			return;
		}

		$src = GYM_CORE_URL . 'assets/js/twilio-settings.js';

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$src,
			array(),
			GYM_CORE_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'gymCoreTwilioSettings',
			array(
				'restUrl' => esc_url_raw( rest_url( 'gym/v1/twilio/test-message' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'sending'       => __( 'Sending...', 'gym-core' ),
					'success'       => __( 'Test SMS sent.', 'gym-core' ),
					'genericError'  => __( 'Something went wrong.', 'gym-core' ),
					'noPhone'       => __( 'No billing phone is set on your user profile.', 'gym-core' ),
					'saveFirstHint' => __( 'Save settings before sending a test.', 'gym-core' ),
				),
			)
		);

		// Brand-aligned form styles for the SMS section: Gray 100 borders + Royal Blue focus ring.
		wp_register_style( self::STYLE_HANDLE, false, array(), GYM_CORE_VERSION );
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_add_inline_style(
			self::STYLE_HANDLE,
			'#mainform .forminp input[type="text"].regular-text,'
			. ' #mainform .forminp input[type="password"].regular-text,'
			. ' #mainform .forminp input[type="number"].regular-text {'
			. ' border:1px solid #E5E5E7; border-radius:4px; transition:box-shadow .15s ease, border-color .15s ease; }'
			. ' #mainform .forminp input[type="text"].regular-text:focus,'
			. ' #mainform .forminp input[type="password"].regular-text:focus,'
			. ' #mainform .forminp input[type="number"].regular-text:focus {'
			. ' border-color:#0032A0; box-shadow:0 0 0 3px rgba(0, 50, 160, 0.15); outline:0; }'
			. ' .gym-core-twilio-test-status.is-success { color:#2E7D32; }'
			. ' .gym-core-twilio-test-status.is-error { color:#C62828; }'
		);
	}
}
