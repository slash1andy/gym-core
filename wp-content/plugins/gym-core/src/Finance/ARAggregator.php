<?php
/**
 * Accounts-Receivable aging aggregator.
 *
 * Computes outstanding balances for failed / on-hold / pending member orders
 * and groups them into the standard 0-30 / 31-60 / 61-90 / 90+ day buckets
 * that bookkeeping uses. The aggregator is read-only — it never mutates an
 * order, never sends a message, and never changes a subscription. Drafting
 * outreach for an aged item goes through the hma-ai-chat pending-action
 * queue so Joy approves every send.
 *
 * @package Gym_Core\Finance
 * @since   5.0.0
 */

declare( strict_types=1 );

namespace Gym_Core\Finance;

/**
 * Aggregates accounts-receivable data into aging buckets.
 *
 * Buckets are inclusive of their lower bound and exclusive of the upper:
 *   0-30   : 0  <= days_overdue < 31
 *   31-60  : 31 <= days_overdue < 61
 *   61-90  : 61 <= days_overdue < 91
 *   90+    : 91 <= days_overdue
 *
 * @since 5.0.0
 */
class ARAggregator {

	/**
	 * Bucket keys exposed to callers, in display order.
	 *
	 * @var list<string>
	 */
	public const BUCKETS = array( '0_30', '31_60', '61_90', '90_plus' );

	/**
	 * Order statuses that indicate an unpaid receivable.
	 *
	 * `failed` is a hard-failure renewal attempt; `on-hold` is a renewal that
	 * could not capture (typically WC Subscriptions retry holds); `pending`
	 * is a created-but-unpaid order. We deliberately exclude `processing`
	 * and `completed` — those are paid.
	 *
	 * @var list<string>
	 */
	private const RECEIVABLE_STATUSES = array( 'failed', 'on-hold', 'pending' );

	/**
	 * Returns aged receivables grouped by bucket.
	 *
	 * Each row contains: order_id, customer_id, customer_name, customer_email,
	 * total, currency, status, date_created (Y-m-d), days_overdue, bucket.
	 *
	 * @since 5.0.0
	 *
	 * @param int|null $now_ts UNIX timestamp treated as "today" (testing seam).
	 *                         When null, the WP-aware current time is used.
	 * @return array{
	 *     totals: array<string, array{count: int, amount: float}>,
	 *     rows: list<array<string, mixed>>,
	 *     as_of: string,
	 *     currency: string
	 * }
	 */
	public function aggregate( ?int $now_ts = null ): array {
		$now_ts = $now_ts ?? (int) ( function_exists( 'current_time' ) ? time() : time() );

		$orders = $this->fetch_orders();
		$rows   = array();
		$totals = array(
			'0_30'    => array(
				'count'  => 0,
				'amount' => 0.0,
			),
			'31_60'   => array(
				'count'  => 0,
				'amount' => 0.0,
			),
			'61_90'   => array(
				'count'  => 0,
				'amount' => 0.0,
			),
			'90_plus' => array(
				'count'  => 0,
				'amount' => 0.0,
			),
		);

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

		foreach ( $orders as $order ) {
			$created = $order->get_date_created();
			if ( null === $created ) {
				continue;
			}

			$days   = $this->days_between( (int) $created->getTimestamp(), $now_ts );
			$bucket = self::bucket_for_days( $days );

			$row = array(
				'order_id'       => (int) $order->get_id(),
				'customer_id'    => (int) $order->get_customer_id(),
				'customer_name'  => $this->format_customer_name( $order ),
				'customer_email' => (string) $order->get_billing_email(),
				'total'          => (float) $order->get_total(),
				'currency'       => (string) ( $order->get_currency() ?: $currency ),
				'status'         => (string) $order->get_status(),
				'date_created'   => (string) $created->date( 'Y-m-d' ),
				'days_overdue'   => $days,
				'bucket'         => $bucket,
			);

			$rows[]            = $row;
			$totals[ $bucket ] = array(
				'count'  => $totals[ $bucket ]['count'] + 1,
				'amount' => $totals[ $bucket ]['amount'] + $row['total'],
			);
		}

		// Newest aging first by bucket, then by days_overdue desc inside.
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				$a_idx = (int) array_search( $a['bucket'], self::BUCKETS, true );
				$b_idx = (int) array_search( $b['bucket'], self::BUCKETS, true );
				if ( $a_idx !== $b_idx ) {
					return $b_idx <=> $a_idx; // 90+ first.
				}
				return (int) $b['days_overdue'] <=> (int) $a['days_overdue'];
			}
		);

		return array(
			'totals'   => $totals,
			'rows'     => $rows,
			'as_of'    => gmdate( 'c', $now_ts ),
			'currency' => $currency,
		);
	}

	/**
	 * Determines the aging bucket for a given days-overdue count.
	 *
	 * Public so tests can pin the boundary behaviour without re-deriving it.
	 *
	 * @since 5.0.0
	 *
	 * @param int $days Days the receivable is overdue (>= 0).
	 * @return string One of self::BUCKETS.
	 */
	public static function bucket_for_days( int $days ): string {
		if ( $days < 0 ) {
			$days = 0;
		}

		if ( $days <= 30 ) {
			return '0_30';
		}
		if ( $days <= 60 ) {
			return '31_60';
		}
		if ( $days <= 90 ) {
			return '61_90';
		}
		return '90_plus';
	}

	/**
	 * Fetches receivable orders via the WooCommerce CRUD layer.
	 *
	 * Uses wc_get_orders() so the query is HPOS-aware. Hard-coded ceiling
	 * keeps a runaway query bounded; sites that exceed 500 simultaneous
	 * receivables already have a bigger problem than dashboard truncation.
	 *
	 * @since 5.0.0
	 *
	 * @return array<int, \WC_Order>
	 */
	protected function fetch_orders(): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'status'  => self::RECEIVABLE_STATUSES,
				'limit'   => 500,
				'orderby' => 'date',
				'order'   => 'ASC',
				'type'    => 'shop_order',
			)
		);

		if ( ! is_array( $orders ) ) {
			return array();
		}

		$result = array();
		foreach ( $orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$result[] = $order;
			}
		}
		return $result;
	}

	/**
	 * Calculates whole-day delta between two UNIX timestamps.
	 *
	 * Floors to whole days so a renewal that failed 26 hours ago counts
	 * as "1 day overdue", not 1.08.
	 *
	 * @since 5.0.0
	 *
	 * @param int $start_ts Earlier timestamp.
	 * @param int $end_ts   Later timestamp.
	 * @return int Non-negative integer day count.
	 */
	private function days_between( int $start_ts, int $end_ts ): int {
		$delta = $end_ts - $start_ts;
		if ( $delta <= 0 ) {
			return 0;
		}
		return (int) floor( $delta / DAY_IN_SECONDS );
	}

	/**
	 * Resolves a display name for an order's customer.
	 *
	 * @since 5.0.0
	 *
	 * @param \WC_Order $order WC_Order instance.
	 * @return string
	 */
	private function format_customer_name( $order ): string {
		/** @var \WC_Order $order */
		$first = (string) $order->get_billing_first_name();
		$last  = (string) $order->get_billing_last_name();
		$name  = trim( $first . ' ' . $last );

		if ( '' !== $name ) {
			return $name;
		}

		$email = (string) $order->get_billing_email();
		if ( '' !== $email ) {
			return $email;
		}

		return '#' . (int) $order->get_id();
	}
}
