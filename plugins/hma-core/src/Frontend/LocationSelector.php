<?php
/**
 * Frontend location selector.
 *
 * @package HMA_Core\Frontend
 */

declare( strict_types=1 );

namespace HMA_Core\Frontend;

use HMA_Core\Location\Manager;
use HMA_Core\Location\Taxonomy;

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
	const SCRIPT_HANDLE = 'hma-location-selector';

	/**
	 * Style handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STYLE_HANDLE = 'hma-location-selector';

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
		wp_enqueue_style(
			self::STYLE_HANDLE,
			HMA_CORE_URL . 'assets/css/location-selector.css',
			array(),
			HMA_CORE_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			HMA_CORE_URL . 'assets/js/location-selector.js',
			array( 'wp-util' ),
			HMA_CORE_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'hmaLocation',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hma_location_nonce' ),
				'current' => $this->manager->get_current_location(),
				'i18n'    => array(
					'switching' => __( 'Switching location\u2026', 'hma-core' ),
					'error'     => __( 'Could not switch location. Please try again.', 'hma-core' ),
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

		$locations = array(
			Taxonomy::ROCKFORD => __( 'Rockford', 'hma-core' ),
			Taxonomy::BELOIT   => __( 'Beloit', 'hma-core' ),
		);

		$banner_class = 'hma-location-selector';
		if ( '' === $current ) {
			$banner_class .= ' hma-location-selector--no-selection';
		}
		?>
		<nav
			id="hma-location-selector"
			class="<?php echo esc_attr( $banner_class ); ?>"
			aria-label="<?php esc_attr_e( 'Select your location', 'hma-core' ); ?>"
			data-current-location="<?php echo esc_attr( $current ); ?>"
		>
			<span class="hma-location-selector__label">
				<?php esc_html_e( 'Your location:', 'hma-core' ); ?>
			</span>

			<div class="hma-location-selector__buttons" role="group" aria-label="<?php esc_attr_e( 'Locations', 'hma-core' ); ?>">
				<?php foreach ( $locations as $slug => $label ) : ?>
					<button
						type="button"
						class="hma-location-selector__button<?php echo esc_attr( $current === $slug ? ' hma-location-selector__button--active' : '' ); ?>"
						data-location="<?php echo esc_attr( $slug ); ?>"
						aria-pressed="<?php echo esc_attr( $current === $slug ? 'true' : 'false' ); ?>"
					>
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</nav>
		<?php
	}
}
