<?php
/**
 * Kiosk Coach Mode — adds a coach-facing briefing surface to /sales/.
 *
 * The existing sales kiosk is the closest tablet our coaches reach when
 * they walk into the gym. Coach Mode reuses that hardware: when a user
 * with the `gym_coach` (or `gym_head_coach`) role visits
 * /sales/?coach_mode=1, the sales UI is replaced with the same
 * BriefingRenderer output that the wp-admin page and the SMS magic-link
 * page emit. One renderer, three surfaces, one visual contract.
 *
 * This handler hooks BEFORE KioskEndpoint::render_kiosk (priority 5 vs 10)
 * so it can short-circuit the sales template when coach mode is active.
 *
 * @package Gym_Core\Sales
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Sales;

use Gym_Core\Briefing\BriefingGenerator;
use Gym_Core\Briefing\BriefingRenderer;

/**
 * Renders Coach Mode at /sales/?coach_mode=1.
 */
final class KioskCoachMode {

	/**
	 * Briefing generator (DI).
	 *
	 * @var BriefingGenerator
	 */
	private BriefingGenerator $generator;

	/**
	 * Briefing renderer (DI).
	 *
	 * @var BriefingRenderer
	 */
	private BriefingRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param BriefingGenerator $generator Briefing generator.
	 * @param BriefingRenderer  $renderer  Briefing renderer.
	 */
	public function __construct( BriefingGenerator $generator, BriefingRenderer $renderer ) {
		$this->generator = $generator;
		$this->renderer  = $renderer;
	}

	/**
	 * Registers the intercept hook.
	 *
	 * Priority 5 — earlier than KioskEndpoint::render_kiosk (default 10) —
	 * so coach mode short-circuits the sales template.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'template_redirect', array( $this, 'maybe_render_coach_mode' ), 5 );
	}

	/**
	 * Renders Coach Mode if the URL and the user are right.
	 *
	 * @return void
	 */
	public function maybe_render_coach_mode(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		if ( ! get_query_var( 'gym_sales_kiosk' ) || empty( $_GET['coach_mode'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/sales/?coach_mode=1' ) ) );
			exit;
		}

		if ( ! $this->user_is_coach() ) {
			// Coach mode is gated to coach roles. Other staff see the sales UI.
			return;
		}

		$class_ids = $this->generator->get_todays_classes();
		$user_id   = get_current_user_id();

		// If the user is a regular coach (not head coach / admin), restrict to their classes.
		$mine_only = ! current_user_can( 'manage_woocommerce' );
		if ( $mine_only ) {
			$class_ids = array_filter(
				$class_ids,
				static fn( $id ) => (int) get_post_meta( (int) $id, '_gym_class_instructor', true ) === $user_id
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		$selected = isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : ( $class_ids ? (int) reset( $class_ids ) : 0 );

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

		$this->render_html( $class_ids, $selected );
		exit;
	}

	/**
	 * Returns true when the current user is a coach or head coach.
	 *
	 * @return bool
	 */
	private function user_is_coach(): bool {
		$user = wp_get_current_user();
		if ( ! $user || 0 === $user->ID ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( 'gym_coach', $roles, true ) || in_array( 'gym_head_coach', $roles, true ) || user_can( $user, 'manage_woocommerce' );
	}

	/**
	 * Emits the full Coach Mode HTML (header, tab strip, briefing card).
	 *
	 * @param array<int, int> $class_ids Class IDs to surface.
	 * @param int             $selected  Currently-selected class ID.
	 * @return void
	 */
	private function render_html( array $class_ids, int $selected ): void {
		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">';
		echo '<title>' . esc_html__( 'Coach Mode', 'gym-core' ) . '</title>';
		echo '<style>html,body{margin:0;background:#1a1a1a;color:#fff;font-family:Inter,sans-serif;} .gym-coach-mode__chrome{position:sticky;top:0;background:rgba(26,26,26,0.85);backdrop-filter:blur(12px);padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center;z-index:10;} .gym-coach-mode__brand{font-family:"Barlow Condensed",sans-serif;text-transform:uppercase;letter-spacing:0.15em;color:#fff;font-weight:600;} .gym-coach-mode__exit{color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,0.3);padding:0.5rem 1rem;border-radius:4px;font-size:0.875rem;} .gym-coach-mode__exit:hover{background:rgba(255,255,255,0.1);} .gym-coach-mode__tabs{display:flex;gap:0.5rem;padding:1rem 1.5rem;background:#111;flex-wrap:wrap;} .gym-coach-mode__tab{color:#fff;text-decoration:none;padding:0.5rem 1rem;border-radius:4px;background:rgba(255,255,255,0.1);font-size:0.875rem;} .gym-coach-mode__tab.is-active{background:#0032A0;} .gym-coach-mode__tab:hover{background:rgba(255,255,255,0.2);} .gym-coach-mode__body{background:#F4F1EC;color:#1a1a1a;min-height:80vh;}' . BriefingRenderer::get_inline_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</head><body>';
		echo '<header class="gym-coach-mode__chrome">';
		echo '<span class="gym-coach-mode__brand">' . esc_html__( 'Coach Mode', 'gym-core' ) . '</span>';
		echo '<a class="gym-coach-mode__exit" href="' . esc_url( home_url( '/sales/' ) ) . '">' . esc_html__( 'Back to Sales', 'gym-core' ) . '</a>';
		echo '</header>';

		echo '<nav class="gym-coach-mode__tabs">';
		if ( empty( $class_ids ) ) {
			echo '<span style="color:#aaa;">' . esc_html__( 'No classes scheduled for you today.', 'gym-core' ) . '</span>';
		}
		foreach ( $class_ids as $class_id ) {
			$post = get_post( (int) $class_id );
			if ( ! $post ) {
				continue;
			}
			$start = (string) get_post_meta( (int) $class_id, '_gym_class_start_time', true );
			$label = trim( $post->post_title . ' ' . $start );
			$url   = add_query_arg(
				array(
					'coach_mode' => '1',
					'class_id'   => (int) $class_id,
				),
				home_url( '/sales/' )
			);
			$is_active = (int) $class_id === $selected;
			echo '<a class="gym-coach-mode__tab' . ( $is_active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		echo '<main class="gym-coach-mode__body">';
		if ( $selected > 0 ) {
			$briefing = $this->generator->generate( $selected );
			if ( ! is_wp_error( $briefing ) ) {
				echo $this->renderer->render( $briefing, 'kiosk' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</main>';
		echo '</body></html>';
	}
}
