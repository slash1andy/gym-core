<?php
/**
 * Lead Sources admin report.
 *
 * Adds a `Gym → Lead Sources` submenu under the consolidated Gym admin menu
 * showing per-source acquisition metrics for the last 30 / 90 / 365 days, plus
 * a CSV export. Columns per source per range:
 *
 *   - count                   — distinct leads captured (kiosk orders + trial CPT).
 *   - conversion-to-member %  — share of leads in that range that produced an
 *                               order with `customer_id > 0` (i.e. trial → member).
 *                               Trial-CPT leads count toward the denominator only;
 *                               they convert by becoming an order tagged with the
 *                               same source and a customer attached.
 *   - total revenue (LTV)     — sum of `WC_Order::get_total()` for orders with
 *                               this source meta over the range. "LTV-to-date"
 *                               in v1 means: revenue we've recognised so far on
 *                               orders attributed to this source.
 *
 * Source of truth for option labels is {@see LeadSourceField::get_choices()}.
 *
 * Voice and visual treatment follow brand-guide §6 Tables (Royal Blue header,
 * Gray 100 row borders) and §3 Color System.
 *
 * @package Gym_Core\Reports
 * @since   4.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Reports;

use Gym_Core\Sales\LeadSourceField;

/**
 * Renders the lead-sources admin report and handles CSV export.
 */
final class LeadSourceReport {

	/**
	 * Submenu slug under the `gym-core` top-level page.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'gym-lead-sources';

	/**
	 * `admin-post.php` action used for the CSV export endpoint.
	 *
	 * @var string
	 */
	public const EXPORT_ACTION = 'gym_core_lead_sources_export';

	/**
	 * Capability required to view and export the report.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Date-range windows used by the report. Keys are days; values are i18n labels.
	 *
	 * @return array<int, string>
	 */
	public static function get_ranges(): array {
		return array(
			30  => __( 'Last 30 days', 'gym-core' ),
			90  => __( 'Last 90 days', 'gym-core' ),
			365 => __( 'Last 365 days', 'gym-core' ),
		);
	}

	/**
	 * Registers WordPress hooks for the report.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 30 );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
	}

	/**
	 * Adds the `Lead Sources` submenu under the `gym-core` top-level menu.
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'gym-core',
			__( 'Lead Sources', 'gym-core' ),
			__( 'Lead Sources', 'gym-core' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the report HTML.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this report.', 'gym-core' ) );
		}

		$ranges = self::get_ranges();
		$data   = array();
		foreach ( array_keys( $ranges ) as $days ) {
			$data[ $days ] = self::aggregate_by_source( $days );
		}

		$choices       = LeadSourceField::get_choices();
		$choices['']   = __( 'Not captured', 'gym-core' );

		$export_url_base = admin_url( 'admin-post.php' );
		?>
		<div class="wrap gym-lead-sources-report">
			<h1><?php esc_html_e( 'Lead Sources', 'gym-core' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'How prospects are finding the academy. Counts include kiosk orders and free-trial signups; revenue and conversion are sourced from WooCommerce orders carrying the lead-source meta.', 'gym-core' ); ?>
			</p>

			<style>
				.gym-lead-sources-report .wp-list-table thead th { color: #0032A0; text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.8rem; }
				.gym-lead-sources-report .wp-list-table { border-color: #E5E5E7; margin-bottom: 24px; }
				.gym-lead-sources-report .wp-list-table tbody td { border-top: 1px solid #E5E5E7; padding: 12px 16px; }
				.gym-lead-sources-report .wp-list-table tbody tr:last-child td { border-bottom: 1px solid #E5E5E7; }
				.gym-lead-sources-report .wp-list-table .total-row td { font-weight: 700; background: #F5F5F7; }
				.gym-lead-sources-report .button-primary { background: #0032A0; border-color: #0032A0; }
				.gym-lead-sources-report .button-primary:hover, .gym-lead-sources-report .button-primary:focus { background: #0041CC; border-color: #0041CC; }
				.gym-lead-sources-report .export-actions { margin: 16px 0 24px; display: flex; gap: 8px; flex-wrap: wrap; }
				.gym-lead-sources-report h2 { font-family: 'Barlow Condensed', 'Arial Narrow', sans-serif; text-transform: uppercase; letter-spacing: 0.04em; color: #0A0A0A; margin-top: 32px; }
				.gym-lead-sources-report td.numeric, .gym-lead-sources-report th.numeric { text-align: right; font-variant-numeric: tabular-nums; }
			</style>

			<div class="export-actions">
				<?php foreach ( $ranges as $days => $label ) : ?>
					<?php
					$export_url = wp_nonce_url(
						add_query_arg(
							array(
								'action' => self::EXPORT_ACTION,
								'days'   => $days,
							),
							$export_url_base
						),
						self::EXPORT_ACTION
					);
					?>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: range label, e.g. Last 30 days. */
								__( 'Export CSV — %s', 'gym-core' ),
								$label
							)
						);
						?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $ranges as $days => $range_label ) : ?>
				<h2><?php echo esc_html( $range_label ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Source', 'gym-core' ); ?></th>
							<th scope="col" class="numeric"><?php esc_html_e( 'Leads', 'gym-core' ); ?></th>
							<th scope="col" class="numeric"><?php esc_html_e( 'Conversion to member', 'gym-core' ); ?></th>
							<th scope="col" class="numeric"><?php esc_html_e( 'Revenue (LTV-to-date)', 'gym-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$totals = array(
							'leads'   => 0,
							'members' => 0,
							'revenue' => 0.0,
						);
						foreach ( $choices as $slug => $label ) :
							$row     = isset( $data[ $days ][ $slug ] ) ? $data[ $days ][ $slug ] : self::empty_row();
							$leads   = (int) $row['leads'];
							$members = (int) $row['members'];
							$revenue = (float) $row['revenue'];
							$rate    = $leads > 0 ? round( ( $members / $leads ) * 100, 1 ) : 0.0;

							$totals['leads']   += $leads;
							$totals['members'] += $members;
							$totals['revenue'] += $revenue;
							?>
							<tr>
								<td><?php echo esc_html( $label ); ?></td>
								<td class="numeric"><?php echo esc_html( (string) $leads ); ?></td>
								<td class="numeric"><?php echo esc_html( $leads > 0 ? number_format_i18n( $rate, 1 ) . '%' : '—' ); ?></td>
								<td class="numeric"><?php echo wp_kses_post( wc_price( $revenue ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<tr class="total-row">
							<td><?php esc_html_e( 'Total', 'gym-core' ); ?></td>
							<td class="numeric"><?php echo esc_html( (string) $totals['leads'] ); ?></td>
							<td class="numeric">
								<?php
								echo esc_html(
									$totals['leads'] > 0
										? number_format_i18n( round( ( $totals['members'] / $totals['leads'] ) * 100, 1 ), 1 ) . '%'
										: '—'
								);
								?>
							</td>
							<td class="numeric"><?php echo wp_kses_post( wc_price( $totals['revenue'] ) ); ?></td>
						</tr>
					</tbody>
				</table>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Returns an empty row template for a source with no captured data.
	 *
	 * @return array{leads: int, members: int, revenue: float}
	 */
	private static function empty_row(): array {
		return array(
			'leads'   => 0,
			'members' => 0,
			'revenue' => 0.0,
		);
	}

	/**
	 * Aggregates per-source acquisition metrics over the last `$days` days.
	 *
	 * Combines counts from:
	 *  - WooCommerce orders carrying `_gym_lead_source` meta (HPOS-safe via
	 *    `wc_get_orders`). Each order contributes to leads + revenue, and to
	 *    member-conversion when the order has a non-zero `customer_id`.
	 *  - hp_trial_lead CPT entries (the free-trial fallback when Jetpack CRM
	 *    isn't installed) annotated with `gym_lead_source` post meta. Trial
	 *    posts contribute to `leads` only — conversion is measured at the order.
	 *
	 * @param int $days Lookback window in days. Must be >= 1.
	 * @return array<string, array{leads: int, members: int, revenue: float}>
	 *         Map of source slug => metric bucket. Includes an empty-string
	 *         key for "Not captured".
	 */
	public static function aggregate_by_source( int $days ): array {
		if ( $days < 1 ) {
			$days = 30;
		}

		$out = array();
		foreach ( LeadSourceField::get_choices() as $slug => $label ) {
			$out[ $slug ] = self::empty_row();
		}
		$out[''] = self::empty_row();

		$cutoff = time() - ( $days * DAY_IN_SECONDS );
		$after  = gmdate( 'Y-m-d H:i:s', $cutoff );

		// 1. WooCommerce orders (HPOS-safe via wc_get_orders).
		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'        => -1,
					'date_created' => '>=' . $after,
					'return'       => 'objects',
				)
			);

			foreach ( (array) $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}
				$slug = (string) $order->get_meta( LeadSourceField::ORDER_META_KEY );
				if ( '' === $slug || ! LeadSourceField::is_valid_source( $slug ) ) {
					$slug = '';
				}
				if ( ! isset( $out[ $slug ] ) ) {
					$out[ $slug ] = self::empty_row();
				}

				++$out[ $slug ]['leads'];
				if ( (int) $order->get_customer_id() > 0 ) {
					++$out[ $slug ]['members'];
				}
				$out[ $slug ]['revenue'] += (float) $order->get_total();
			}
		}

		// 2. Free-trial fallback CPT (`hp_trial_lead`) — used when Jetpack CRM
		//    is not installed. See Haanpaa\Jetpack_CRM::handle_trial(). Trial
		//    leads count toward `leads` only — they don't (yet) constitute a
		//    member because no order has been created.
		$trial_posts = get_posts(
			array(
				'post_type'      => 'hp_trial_lead',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'date_query'     => array(
					array(
						'after'     => $after,
						'inclusive' => true,
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( (array) $trial_posts as $post_id ) {
			$slug = (string) get_post_meta( (int) $post_id, LeadSourceField::TRIAL_CPT_META_KEY, true );
			if ( '' === $slug || ! LeadSourceField::is_valid_source( $slug ) ) {
				$slug = '';
			}
			if ( ! isset( $out[ $slug ] ) ) {
				$out[ $slug ] = self::empty_row();
			}
			++$out[ $slug ]['leads'];
		}

		return $out;
	}

	/**
	 * Handles `admin-post.php?action=gym_core_lead_sources_export` requests.
	 *
	 * Streams a CSV with one row per source for the requested range plus a
	 * totals row. Headers: source_slug, source_label, leads, members,
	 * conversion_pct, revenue, range_days.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to export this report.', 'gym-core' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::EXPORT_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked above.
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		if ( ! array_key_exists( $days, self::get_ranges() ) ) {
			$days = 30;
		}

		$data = self::aggregate_by_source( $days );

		$rows = $this->build_csv_rows( $data, $days );

		$filename = sprintf( 'gym-lead-sources-last-%d-days-%s.csv', $days, gmdate( 'Y-m-d' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open CSV stream.', 'gym-core' ) );
		}

		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming CSV to php://output; WP_Filesystem is not applicable.
		exit;
	}

	/**
	 * Builds CSV rows from an aggregate bucket.
	 *
	 * Pure function so it can be unit-tested without the WordPress runtime.
	 *
	 * @param array<string, array{leads: int, members: int, revenue: float}> $data Aggregate by source slug.
	 * @param int                                                            $days Range in days, used in the row trailer.
	 * @return array<int, array<int, string>>
	 */
	public function build_csv_rows( array $data, int $days ): array {
		$rows   = array();
		$rows[] = array( 'source_slug', 'source_label', 'leads', 'members', 'conversion_pct', 'revenue', 'range_days' );

		$choices     = LeadSourceField::get_choices();
		$choices[''] = __( 'Not captured', 'gym-core' );

		$total_leads   = 0;
		$total_members = 0;
		$total_revenue = 0.0;

		foreach ( $choices as $slug => $label ) {
			$row     = isset( $data[ $slug ] ) ? $data[ $slug ] : self::empty_row();
			$leads   = (int) $row['leads'];
			$members = (int) $row['members'];
			$revenue = (float) $row['revenue'];
			$rate    = $leads > 0 ? round( ( $members / $leads ) * 100, 1 ) : 0.0;

			$rows[] = array(
				(string) $slug,
				(string) $label,
				(string) $leads,
				(string) $members,
				$leads > 0 ? number_format( $rate, 1, '.', '' ) : '',
				number_format( $revenue, 2, '.', '' ),
				(string) $days,
			);

			$total_leads   += $leads;
			$total_members += $members;
			$total_revenue += $revenue;
		}

		$total_rate = $total_leads > 0 ? round( ( $total_members / $total_leads ) * 100, 1 ) : 0.0;
		$rows[]     = array(
			'total',
			__( 'Total', 'gym-core' ),
			(string) $total_leads,
			(string) $total_members,
			$total_leads > 0 ? number_format( $total_rate, 1, '.', '' ) : '',
			number_format( $total_revenue, 2, '.', '' ),
			(string) $days,
		);

		return $rows;
	}
}
