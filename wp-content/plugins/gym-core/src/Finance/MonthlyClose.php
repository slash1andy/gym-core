<?php
/**
 * Monthly close orchestrator.
 *
 * Runs Joy's bookkeeping close checklist as a deterministic, replayable
 * sequence of steps. Each step records its result in a `gym_core_finance_close`
 * option keyed by `Y-m`, so a re-run for the same month is idempotent — it
 * returns the cached result rather than executing twice.
 *
 * The sign-off step is a *notification*, not a write. Joy still has to walk
 * the report to Darby; the orchestrator just files the prompt in the pending
 * action queue (handled by hma-ai-chat) and records that the prompt was sent.
 *
 * @package Gym_Core\Finance
 * @since   5.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Finance;

/**
 * Monthly close workflow.
 *
 * The orchestrator does not call the WooPayments API directly — that lives
 * behind the `gym_core_finance_woopayments_payouts` filter so unit tests and
 * sites without WooPayments configured can still drive the workflow without
 * a live network call. The default implementation falls back to a documented
 * empty-list shape.
 *
 * @since 5.0.0
 */
class MonthlyClose {

	/**
	 * Option key prefix for cached close results.
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'gym_core_finance_close_';

	/**
	 * Step keys, in execution order.
	 *
	 * @var list<string>
	 */
	public const STEPS = array(
		'reconcile_payouts',
		'flag_refunded_subscriptions',
		'export_payroll_attendance',
		'request_signoff',
	);

	/**
	 * AR aggregator dependency.
	 *
	 * @var ARAggregator
	 */
	private ARAggregator $ar;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param ARAggregator|null $ar Optional injection seam for tests.
	 */
	public function __construct( ?ARAggregator $ar = null ) {
		$this->ar = $ar ?? new ARAggregator();
	}

	/**
	 * Runs the full close for the given month.
	 *
	 * Returns the same payload on a re-run — no double work, no double
	 * sign-off prompts. Pass `force = true` to bypass the cache.
	 *
	 * @since 5.0.0
	 *
	 * @param string $month   Month in `YYYY-MM` form.
	 * @param bool   $force   When true, re-run every step regardless of cache.
	 * @return array<string, mixed>
	 */
	public function run( string $month, bool $force = false ): array {
		$month = $this->normalize_month( $month );
		if ( '' === $month ) {
			return $this->error( 'invalid_month', 'Month must be in YYYY-MM format.' );
		}

		$cached = $this->load_cached( $month );
		if ( null !== $cached && ! $force ) {
			$cached['from_cache'] = true;
			return $cached;
		}

		$range = $this->month_range( $month );
		$steps = array();

		$steps['reconcile_payouts']            = $this->reconcile_payouts( $range );
		$steps['flag_refunded_subscriptions']  = $this->flag_refunded_subscriptions( $range );
		$steps['export_payroll_attendance']    = $this->export_payroll_attendance( $range );
		$steps['request_signoff']              = $this->request_signoff( $month, $steps );

		$payload = array(
			'success'      => true,
			'month'        => $month,
			'completed'    => $this->all_succeeded( $steps ),
			'steps'        => $steps,
			'completed_at' => gmdate( 'c' ),
			'from_cache'   => false,
		);

		$this->save_cached( $month, $payload );

		return $payload;
	}

	/**
	 * Returns the cached close payload for a month, or null when missing.
	 *
	 * @since 5.0.0
	 *
	 * @param string $month YYYY-MM.
	 * @return array<string, mixed>|null
	 */
	public function get_status( string $month ): ?array {
		$month = $this->normalize_month( $month );
		if ( '' === $month ) {
			return null;
		}
		return $this->load_cached( $month );
	}

	/**
	 * Step 1: reconcile WooPayments payouts against bank deposits.
	 *
	 * Pulls payouts from the WooPayments API (filterable for tests + offline
	 * sites), pairs them with completed orders in the period, and reports
	 * the variance. The function never auto-corrects — variance reporting
	 * is Joy's call to escalate.
	 *
	 * @since 5.0.0
	 *
	 * @param array{start: string, end: string} $range Inclusive date range.
	 * @return array<string, mixed>
	 */
	private function reconcile_payouts( array $range ): array {
		/**
		 * Filters the WooPayments payouts for a close window.
		 *
		 * Default returns an empty list so sites without WooPayments wired
		 * still close cleanly — variance is just zero.
		 *
		 * @since 5.0.0
		 *
		 * @param array<int, array<string, mixed>> $payouts Each row should expose
		 *                                                  `id`, `amount`, `arrival_date`.
		 * @param array{start: string, end: string} $range  Date range.
		 */
		$payouts = (array) apply_filters( 'gym_core_finance_woopayments_payouts', array(), $range );

		$payout_total = 0.0;
		foreach ( $payouts as $payout ) {
			if ( is_array( $payout ) && isset( $payout['amount'] ) ) {
				$payout_total += (float) $payout['amount'];
			}
		}

		$paid_total = $this->sum_paid_orders( $range );
		$variance   = round( $paid_total - $payout_total, 2 );

		return array(
			'status'        => 'ok',
			'payouts_count' => count( $payouts ),
			'payouts_total' => round( $payout_total, 2 ),
			'orders_total'  => round( $paid_total, 2 ),
			'variance'      => $variance,
		);
	}

	/**
	 * Step 2: flag refunded subscriptions in the period.
	 *
	 * @since 5.0.0
	 *
	 * @param array{start: string, end: string} $range Inclusive date range.
	 * @return array<string, mixed>
	 */
	private function flag_refunded_subscriptions( array $range ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'status' => 'skipped',
				'reason' => 'WooCommerce not loaded.',
				'count'  => 0,
				'orders' => array(),
			);
		}

		$orders = wc_get_orders(
			array(
				'status'       => array( 'refunded' ),
				'date_created' => $range['start'] . '...' . $range['end'],
				'limit'        => 200,
				'type'         => 'shop_order',
			)
		);

		$orders = is_array( $orders ) ? $orders : array();
		$rows   = array();

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
				continue;
			}

			$rows[] = array(
				'order_id'    => (int) $order->get_id(),
				'total'       => method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0,
				'customer_id' => method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0,
			);
		}

		return array(
			'status' => 'ok',
			'count'  => count( $rows ),
			'orders' => $rows,
		);
	}

	/**
	 * Step 3: export coach payroll attendance CSV.
	 *
	 * Returns the path of the generated CSV under `wp-content/uploads/gym-core/finance/`.
	 * The actual coach-roll export logic is delegated to the existing
	 * AttendanceStore via the `gym_core_finance_payroll_rows` filter so this
	 * step stays a thin orchestration shim.
	 *
	 * @since 5.0.0
	 *
	 * @param array{start: string, end: string} $range Inclusive date range.
	 * @return array<string, mixed>
	 */
	private function export_payroll_attendance( array $range ): array {
		/**
		 * Filters payroll attendance rows for the close window.
		 *
		 * Sites with the briefing module wired return one row per
		 * coach-class-roll. Default returns an empty list — the orchestrator
		 * still produces a valid (empty) CSV so the consuming step records
		 * "no payroll activity" instead of failing.
		 *
		 * @since 5.0.0
		 *
		 * @param array<int, array<string, mixed>> $rows  Coach-roll rows.
		 * @param array{start: string, end: string} $range Date range.
		 */
		$rows = (array) apply_filters( 'gym_core_finance_payroll_rows', array(), $range );

		$rel_path = $this->write_payroll_csv( $rows, $range );

		return array(
			'status'   => 'ok',
			'count'    => count( $rows ),
			'csv_path' => $rel_path,
		);
	}

	/**
	 * Step 4: queue a sign-off prompt for Darby.
	 *
	 * The prompt is filed via an action hook so hma-ai-chat (which owns the
	 * pending-action table) can pick it up without gym-core depending on
	 * that plugin's classes.
	 *
	 * @since 5.0.0
	 *
	 * @param string               $month YYYY-MM.
	 * @param array<string, mixed> $steps Already-run steps for context.
	 * @return array<string, mixed>
	 */
	private function request_signoff( string $month, array $steps ): array {
		$payload = array(
			'month'                  => $month,
			'reconcile_payouts'      => $steps['reconcile_payouts']['variance'] ?? null,
			'refunded_subscriptions' => $steps['flag_refunded_subscriptions']['count'] ?? 0,
			'payroll_csv'            => $steps['export_payroll_attendance']['csv_path'] ?? '',
		);

		/**
		 * Fires when the monthly close requests a sign-off from leadership.
		 *
		 * Listeners (hma-ai-chat) are expected to enqueue a pending-action
		 * card for Darby to acknowledge. Listeners are responsible for their
		 * own deduping; the close itself is idempotent so the same hook may
		 * fire on a re-run without `force`.
		 *
		 * @since 5.0.0
		 *
		 * @param string               $month   YYYY-MM.
		 * @param array<string, mixed> $payload Summary of the close.
		 */
		do_action( 'gym_core_finance_close_signoff_requested', $month, $payload );

		return array(
			'status'  => 'ok',
			'queued'  => true,
			'month'   => $month,
			'summary' => $payload,
		);
	}

	/**
	 * Sums paid-order totals (processing + completed) for a window.
	 *
	 * @since 5.0.0
	 *
	 * @param array{start: string, end: string} $range Inclusive date range.
	 * @return float
	 */
	private function sum_paid_orders( array $range ): float {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0.0;
		}

		$orders = wc_get_orders(
			array(
				'status'    => array( 'completed', 'processing' ),
				'date_paid' => $range['start'] . '...' . $range['end'],
				'limit'     => -1,
				'type'      => 'shop_order',
				'return'    => 'objects',
			)
		);

		$orders = is_array( $orders ) ? $orders : array();
		$total  = 0.0;

		foreach ( $orders as $order ) {
			if ( is_object( $order ) && method_exists( $order, 'get_total' ) ) {
				$total += (float) $order->get_total();
			}
		}

		return $total;
	}

	/**
	 * Writes the payroll CSV to the uploads dir.
	 *
	 * @since 5.0.0
	 *
	 * @param array<int, array<string, mixed>>  $rows  Payroll rows.
	 * @param array{start: string, end: string} $range Date range.
	 * @return string Relative path under uploads dir, or empty string on failure.
	 */
	private function write_payroll_csv( array $rows, array $range ): string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}

		$upload = wp_upload_dir();
		if ( ! is_array( $upload ) || empty( $upload['basedir'] ) ) {
			return '';
		}

		$dir = trailingslashit( (string) $upload['basedir'] ) . 'gym-core/finance';
		if ( ! function_exists( 'wp_mkdir_p' ) || ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		$file = $dir . '/payroll-' . sanitize_file_name( $range['start'] ) . '.csv';
		$fh   = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $fh ) {
			return '';
		}

		fputcsv( $fh, array( 'coach_id', 'coach_name', 'class_id', 'date', 'hours' ), ',', '"', '\\' );
		foreach ( $rows as $row ) {
			fputcsv(
				$fh,
				array(
					(string) ( $row['coach_id'] ?? '' ),
					(string) ( $row['coach_name'] ?? '' ),
					(string) ( $row['class_id'] ?? '' ),
					(string) ( $row['date'] ?? '' ),
					(string) ( $row['hours'] ?? '' ),
				),
				',',
				'"',
				'\\'
			);
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return str_replace( (string) $upload['basedir'], '', $file );
	}

	/**
	 * Loads a cached close payload from options.
	 *
	 * @param string $month YYYY-MM.
	 * @return array<string, mixed>|null
	 */
	private function load_cached( string $month ): ?array {
		$value = get_option( self::OPTION_PREFIX . $month, null );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Persists a close payload as an option.
	 *
	 * @param string               $month   YYYY-MM.
	 * @param array<string, mixed> $payload Result payload.
	 * @return void
	 */
	private function save_cached( string $month, array $payload ): void {
		update_option( self::OPTION_PREFIX . $month, $payload, false );
	}

	/**
	 * Validates and trims a YYYY-MM input.
	 *
	 * @param string $month Input month.
	 * @return string Normalized YYYY-MM, or '' when invalid.
	 */
	private function normalize_month( string $month ): string {
		$month = trim( $month );
		if ( 1 !== preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			return '';
		}
		return $month;
	}

	/**
	 * Returns inclusive ISO-8601 start/end strings for a YYYY-MM month.
	 *
	 * @param string $month YYYY-MM.
	 * @return array{start: string, end: string}
	 */
	private function month_range( string $month ): array {
		$start_ts = strtotime( $month . '-01 00:00:00 UTC' );
		$end_ts   = strtotime( '+1 month', (int) $start_ts ) - 1;

		return array(
			'start' => gmdate( 'Y-m-d\TH:i:s', (int) $start_ts ),
			'end'   => gmdate( 'Y-m-d\TH:i:s', (int) $end_ts ),
		);
	}

	/**
	 * Returns true when every step has a non-error status.
	 *
	 * @param array<string, array<string, mixed>> $steps Step results.
	 * @return bool
	 */
	private function all_succeeded( array $steps ): bool {
		foreach ( $steps as $step ) {
			$status = $step['status'] ?? 'error';
			if ( 'ok' !== $status && 'skipped' !== $status ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Builds an error envelope with the standard shape.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return array<string, mixed>
	 */
	private function error( string $code, string $message ): array {
		return array(
			'success' => false,
			'code'    => $code,
			'message' => $message,
		);
	}
}
