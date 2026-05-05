<?php
/**
 * Frontend page handler for the magic-link Coach Briefing surface.
 *
 * Intercepts requests to /coach-briefing/?gym_briefing_token=... and
 * renders the briefing in a minimal full-page chrome. This is the surface
 * coaches reach by tapping the SMS link on their phone.
 *
 * No template modification of the active theme is required — the handler
 * intercepts `template_redirect` and emits a self-contained page, so the
 * feature works on any theme. Only the brand-aligned inline CSS from
 * BriefingRenderer is loaded.
 *
 * @package Gym_Core\Briefing
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Briefing;

/**
 * Handles /coach-briefing/ URL with token verification and rendering.
 */
final class MagicLinkPage {

	/**
	 * Path-part of the public URL — `/coach-briefing/`.
	 *
	 * @var string
	 */
	public const PATH = 'coach-briefing';

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
	 * @param BriefingGenerator $generator Briefing generator engine.
	 * @param BriefingRenderer  $renderer  Briefing renderer.
	 */
	public function __construct( BriefingGenerator $generator, BriefingRenderer $renderer ) {
		$this->generator = $generator;
		$this->renderer  = $renderer;
	}

	/**
	 * Registers the URL handler hooks.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
	}

	/**
	 * Registers the rewrite rule and query vars.
	 *
	 * @return void
	 */
	public function register_rewrite(): void {
		add_rewrite_rule(
			'^' . preg_quote( self::PATH, '/' ) . '/?$',
			'index.php?gym_briefing_page=1',
			'top'
		);
	}

	/**
	 * Adds query vars used by the rewrite rule.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'gym_briefing_page';
		$vars[] = MagicLink::QUERY_VAR;
		$vars[] = MagicLink::QUERY_VAR_CLASS;
		return $vars;
	}

	/**
	 * Renders the briefing page on a matched request.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		// Either the rewrite query var is set, or the URL path matches and
		// the token query var is present (covers the case where rewrite
		// rules have not been flushed on the live site).
		$matched_qv = '1' === (string) get_query_var( 'gym_briefing_page' );
		$path_match = false;
		$req_uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' !== $req_uri ) {
			$path = wp_parse_url( $req_uri, PHP_URL_PATH ) ?: '';
			if ( '/' . trim( self::PATH, '/' ) . '/' === $path || '/' . trim( self::PATH, '/' ) === $path ) {
				$path_match = isset( $_GET[ MagicLink::QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		if ( ! $matched_qv && ! $path_match ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token IS the auth.
		$token = isset( $_GET[ MagicLink::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ MagicLink::QUERY_VAR ] ) ) : '';

		$payload = MagicLink::verify( $token );

		nocache_headers();

		if ( is_wp_error( $payload ) ) {
			$this->render_error( $payload );
			exit;
		}

		$briefing = $this->generator->generate( (int) $payload['class_id'] );

		if ( is_wp_error( $briefing ) ) {
			$this->render_error( $briefing );
			exit;
		}

		$this->render_page( $briefing );
		exit;
	}

	/**
	 * Renders a successful briefing page.
	 *
	 * @param array $briefing Briefing DTO.
	 * @return void
	 */
	private function render_page( array $briefing ): void {
		$class_name = $briefing['class']['name'] ?? __( 'Coach Briefing', 'gym-core' );

		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<meta name="robots" content="noindex,nofollow">';
		echo '<title>' . esc_html( sprintf( /* translators: %s: class name */ __( 'Briefing — %s', 'gym-core' ), $class_name ) ) . '</title>';
		echo '<style>body{margin:0;background:#F4F1EC;}' . BriefingRenderer::get_inline_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</head><body>';
		echo $this->renderer->render( $briefing, 'magic_link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</body></html>';
	}

	/**
	 * Renders an error page (invalid/expired token).
	 *
	 * @param \WP_Error $error WP_Error from MagicLink::verify or generator.
	 * @return void
	 */
	private function render_error( \WP_Error $error ): void {
		$status  = (int) ( $error->get_error_data()['status'] ?? 400 );
		$message = $error->get_error_message();

		status_header( $status );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<meta name="robots" content="noindex,nofollow">';
		echo '<title>' . esc_html__( 'Briefing unavailable', 'gym-core' ) . '</title>';
		echo '<style>body{margin:0;background:#F4F1EC;font-family:Inter,sans-serif;color:#1a1a1a;}.gym-briefing-err{max-width:560px;margin:4rem auto;padding:2rem;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.06);}h1{font-family:"Barlow Condensed","Inter",sans-serif;color:#0032A0;text-transform:uppercase;}</style>';
		echo '</head><body><div class="gym-briefing-err"><h1>' . esc_html__( 'Briefing unavailable', 'gym-core' ) . '</h1><p>' . esc_html( $message ) . '</p><p>' . esc_html__( 'Ask the head coach to resend the briefing link, or open the dashboard from wp-admin.', 'gym-core' ) . '</p></div></body></html>';
	}
}
