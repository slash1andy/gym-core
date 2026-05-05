<?php
/**
 * Finance Copilot admin dashboard.
 *
 * Registers the WooCommerce → Reports → Finance Copilot submenu and renders
 * the two-pane layout: NLP query box (top), AR aging table + recovery queue
 * widget (bottom). The page is a thin shell; all data is loaded via the
 * `gym/v1/finance/*` REST endpoints so the same payloads serve hma-ai-chat
 * tools and the admin UI.
 *
 * @package Gym_Core\Finance
 * @since   5.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Finance;

/**
 * Admin page for the Finance Copilot.
 *
 * Hooks under `WooCommerce → Reports` rather than the gym-core top-level
 * menu so it lives where Joy already runs revenue reports. Capability is
 * `manage_woocommerce`, the same gate Woo uses for its built-in reports.
 *
 * @since 5.0.0
 */
class DashboardController {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'gym-finance-copilot';

	/**
	 * Required capability for the admin page and its assets.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Registers the admin menu and asset hooks.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the submenu under WooCommerce.
	 *
	 * Lands under WooCommerce so Joy doesn't context-switch between the
	 * gym-core menu (member-facing tooling) and the bookkeeping report.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Finance Copilot', 'gym-core' ),
			__( 'Finance Copilot', 'gym-core' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues the page-specific CSS + JS.
	 *
	 * Vanilla JS — no jQuery — per gym-core conventions. The script
	 * receives REST nonce + endpoints via `wp_localize_script()` so it can
	 * call `gym/v1/finance/*` without leaking the nonce into markup.
	 *
	 * @since 5.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, self::PAGE_SLUG ) ) {
			return;
		}

		$plugin_url = defined( 'GYM_CORE_PLUGIN_URL' ) ? (string) constant( 'GYM_CORE_PLUGIN_URL' ) : plugins_url( '/', dirname( __DIR__, 2 ) . '/gym-core.php' );
		$version    = defined( 'GYM_CORE_VERSION' ) ? (string) constant( 'GYM_CORE_VERSION' ) : '5.0.0';

		wp_enqueue_style(
			'gym-core-finance-copilot',
			$plugin_url . 'assets/finance/css/finance-copilot.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'gym-core-finance-copilot',
			$plugin_url . 'assets/finance/js/finance-copilot.js',
			array( 'wp-api-fetch' ),
			$version,
			true
		);

		wp_localize_script(
			'gym-core-finance-copilot',
			'gymCoreFinanceCopilot',
			array(
				'restNamespace' => 'gym/v1',
				'endpoints'     => array(
					'arAging'       => '/gym/v1/finance/ar-aging',
					'recoveryQueue' => '/gym/v1/finance/recovery-queue',
					'dunningDraft'  => '/gym/v1/finance/dunning/draft',
					'closeRun'      => '/gym/v1/finance/close',
					'nlpQuery'      => '/gym/v1/finance/nlp', // Reserved for the NLP endpoint added by hma-ai-chat.
				),
				'currentMonth'  => gmdate( 'Y-m' ),
				'i18n'          => array(
					'askPlaceholder'  => __( 'Ask Pippin a finance question, e.g. "How did Rockford kids\' membership revenue change month over month?"', 'gym-core' ),
					'ask'             => __( 'Ask', 'gym-core' ),
					'arAgingTitle'    => __( 'Accounts receivable aging', 'gym-core' ),
					'recoveryTitle'   => __( 'Failed-payment recovery queue', 'gym-core' ),
					'closeTitle'      => __( 'Monthly close', 'gym-core' ),
					'draftOutreach'   => __( 'Draft outreach', 'gym-core' ),
					'queued'          => __( 'Drafted — pending Joy\'s approval.', 'gym-core' ),
					'noReceivables'   => __( 'No outstanding receivables. Nice.', 'gym-core' ),
					'noRecovery'      => __( 'No failed payments waiting on retry.', 'gym-core' ),
					'runClose'        => __( 'Run monthly close', 'gym-core' ),
					'closeIdempotent' => __( 'Already closed for this month — re-running is safe.', 'gym-core' ),
				),
			)
		);
	}

	/**
	 * Renders the admin page shell.
	 *
	 * Markup is a static skeleton; population happens client-side via
	 * `wp.apiFetch` against the gym/v1/finance/* routes. Renders nothing
	 * server-side beyond the structural cards so screen-reader landmarks
	 * stay stable while data loads.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'gym-core' ) );
		}

		?>
		<div class="wrap gym-finance-copilot">
			<h1><?php esc_html_e( 'Finance Copilot', 'gym-core' ); ?></h1>
			<p class="gym-finance-copilot__lede">
				<?php esc_html_e( 'Pippin answers natural-language finance questions and shows aged receivables. Outreach drafts always go through approval — nothing sends automatically.', 'gym-core' ); ?>
			</p>

			<section class="gym-finance-copilot__card" aria-labelledby="gym-finance-copilot-ask-title">
				<h2 id="gym-finance-copilot-ask-title"><?php esc_html_e( 'Ask Pippin', 'gym-core' ); ?></h2>
				<form class="gym-finance-copilot__ask" data-role="ask-form">
					<label class="screen-reader-text" for="gym-finance-copilot-question">
						<?php esc_html_e( 'Finance question', 'gym-core' ); ?>
					</label>
					<input
						type="text"
						id="gym-finance-copilot-question"
						class="regular-text"
						data-role="ask-input"
						autocomplete="off"
					/>
					<button type="submit" class="button button-primary" data-role="ask-submit">
						<?php esc_html_e( 'Ask', 'gym-core' ); ?>
					</button>
				</form>
				<div class="gym-finance-copilot__answer" data-role="ask-answer" aria-live="polite"></div>
			</section>

			<section class="gym-finance-copilot__card" aria-labelledby="gym-finance-copilot-ar-title">
				<h2 id="gym-finance-copilot-ar-title"><?php esc_html_e( 'Accounts receivable aging', 'gym-core' ); ?></h2>
				<div class="gym-finance-copilot__ar" data-role="ar-aging" aria-live="polite">
					<p class="gym-finance-copilot__loading"><?php esc_html_e( 'Loading…', 'gym-core' ); ?></p>
				</div>
			</section>

			<section class="gym-finance-copilot__card" aria-labelledby="gym-finance-copilot-recovery-title">
				<h2 id="gym-finance-copilot-recovery-title"><?php esc_html_e( 'Failed-payment recovery queue', 'gym-core' ); ?></h2>
				<div class="gym-finance-copilot__recovery" data-role="recovery-queue" aria-live="polite">
					<p class="gym-finance-copilot__loading"><?php esc_html_e( 'Loading…', 'gym-core' ); ?></p>
				</div>
			</section>

			<section class="gym-finance-copilot__card" aria-labelledby="gym-finance-copilot-close-title">
				<h2 id="gym-finance-copilot-close-title"><?php esc_html_e( 'Monthly close', 'gym-core' ); ?></h2>
				<div class="gym-finance-copilot__close" data-role="close-panel">
					<label for="gym-finance-copilot-close-month">
						<?php esc_html_e( 'Month', 'gym-core' ); ?>
					</label>
					<input
						type="month"
						id="gym-finance-copilot-close-month"
						data-role="close-month"
						value="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>"
					/>
					<button type="button" class="button button-primary" data-role="close-run">
						<?php esc_html_e( 'Run monthly close', 'gym-core' ); ?>
					</button>
					<div class="gym-finance-copilot__close-result" data-role="close-result" aria-live="polite"></div>
				</div>
			</section>
		</div>
		<?php
	}
}
