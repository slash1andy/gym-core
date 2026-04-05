<?php
/**
 * Frontend location selector.
 *
 * @package Gym_Core\Frontend
 */

declare( strict_types=1 );

namespace Gym_Core\Frontend;

use Gym_Core\Location\Manager;
use Gym_Core\Location\Taxonomy;

/**
 * Renders the location selector banner and enqueues its assets.
 *
 * The banner is injected directly after <body> via wp_body_open and shows two
 * pill buttons — one per location. The active location receives a visual
 * highlight and aria-pressed="true". Switching location fires an AJAX request
 * to Manager::handle_ajax_set_location(), then reloads the page so product
 * grids update to reflect the new location filter.
 *
 * First-time visitors (no location set) see the banner with a highlighted
 * call-to-action background until they make a selection.
 */
class LocationSelector {

	/**
	 * Script handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SCRIPT_HANDLE = 'gym-location-selector';

	/**
	 * Style handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STYLE_HANDLE = 'gym-location-selector';

	/**
	 * The location manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Manager $manager The location manager.
	 */
	public function __construct( Manager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_body_open', array( $this, 'render_location_selector' ) );
	}

	/**
	 * Enqueues the location selector CSS and JS.
	 *
	 * The script depends on wp-util for jQuery.ajax access and is loaded in
	 * the footer to avoid render-blocking.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Skip on REST/AJAX requests and when location selector is disabled.
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( 'yes' !== get_option( 'gym_core_require_location', 'yes' ) ) {
			return;
		}

		// Skip on the kiosk page (it has its own assets).
		if ( get_query_var( 'gym_kiosk' ) ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			GYM_CORE_URL . 'assets/css/location-selector.css',
			array(),
			GYM_CORE_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			GYM_CORE_URL . 'assets/js/location-selector.js',
			array( 'wp-util' ),
			GYM_CORE_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'gymLocation',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'gym_location_nonce' ),
				'current'    => $this->manager->get_current_location(),
				'locations'  => $this->get_location_coordinates(),
				'i18n'       => array(
					'switching' => __( 'Switching location…', 'gym-core' ),
					'error'     => __( 'Could not switch location. Please try again.', 'gym-core' ),
				),
			)
		);
	}

	/**
	 * Renders the location selector HTML immediately after <body>.
	 *
	 * Uses semantic markup with role="navigation" and aria-pressed for
	 * screen-reader accessibility. The data-current-location attribute is
	 * read by the frontend JS to determine which button is active.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_location_selector(): void {
		$current = $this->manager->get_current_location();

		$locations = Taxonomy::get_location_labels();

		$banner_class = 'gym-location-selector';
		if ( '' === $current ) {
			$banner_class .= ' gym-location-selector--no-selection';
		}
		?>
		<nav
			id="gym-location-selector"
			class="<?php echo esc_attr( $banner_class ); ?>"
			aria-label="<?php esc_attr_e( 'Select your location', 'gym-core' ); ?>"
			data-current-location="<?php echo esc_attr( $current ); ?>"
		>
			<span class="gym-location-selector__label">
				<?php esc_html_e( 'Your location:', 'gym-core' ); ?>
			</span>

			<div class="gym-location-selector__buttons" role="group" aria-label="<?php esc_attr_e( 'Locations', 'gym-core' ); ?>">
				<?php foreach ( $locations as $slug => $label ) : ?>
					<button
						type="button"
						class="gym-location-selector__button<?php echo esc_attr( $current === $slug ? ' gym-location-selector__button--active' : '' ); ?>"
						data-location="<?php echo esc_attr( $slug ); ?>"
						aria-pressed="<?php echo esc_attr( $current === $slug ? 'true' : 'false' ); ?>"
					>
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php if ( '' !== $current ) : ?>
				<span class="gym-location-selector__helper">
					<?php
					$current_label = $locations[ $current ] ?? ucfirst( $current );
					printf(
						/* translators: %s: location name */
						esc_html__( 'Showing classes and memberships for %s', 'gym-core' ),
						esc_html( $current_label )
					);
					?>
				</span>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Returns location coordinates for browser geolocation matching.
	 *
	 * Coordinates are stored as a filterable option so they can be updated
	 * without code changes if a location moves.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array{lat: float, lng: float}>
	 */
	private function get_location_coordinates(): array {
		$defaults = array(
			'rockford' => array(
				'lat' => 42.2711,
				'lng' => -89.0940,
			),
			'beloit'   => array(
				'lat' => 42.5083,
				'lng' => -89.0318,
			),
		);

		/**
		 * Filters the GPS coordinates used for browser-based location detection.
		 *
		 * @since 4.0.0
		 *
		 * @param array<string, array{lat: float, lng: float}> $coordinates Location slug => { lat, lng }.
		 */
		return apply_filters( 'gym_core_location_coordinates', $defaults );
	}
}
