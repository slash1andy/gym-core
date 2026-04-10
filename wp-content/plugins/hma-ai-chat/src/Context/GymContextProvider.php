<?php
/**
 * Gym context provider — injects real-time operational context into AI system prompts.
 *
 * Makes Gandalf aware of current gym conditions (schedules, pricing, member data,
 * financials) so responses are grounded in live data rather than generic knowledge.
 *
 * @package HMA_AI_Chat\Context
 * @since   0.3.0
 */

declare( strict_types=1 );

namespace HMA_AI_Chat\Context;

use WP_REST_Request;

/**
 * Provides persona-specific operational context appended to AI system prompts.
 *
 * Uses internal REST dispatch (rest_do_request) to query gym/v1 and wc/v3
 * endpoints — same zero-overhead pattern as ToolExecutor.
 *
 * @since 0.3.0
 */
class GymContextProvider {

	/**
	 * Cache group for context data.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'hma_ai_context';

	/**
	 * Cache TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Get operational context for a persona, appended to its system prompt.
	 *
	 * Content varies by persona to keep prompts focused and token-efficient.
	 *
	 * @since 0.3.0
	 *
	 * @param string $persona Agent slug (sales, coaching, finance, admin).
	 * @param int    $user_id Optional WordPress user ID for member-specific context.
	 * @return string Context block to append to the system prompt.
	 */
	public function get_context_for_persona( string $persona, int $user_id = 0 ): string {
		$sections = array();

		switch ( $persona ) {
			case 'sales':
				$sections[] = $this->get_pricing_context();
				$sections[] = $this->get_trial_context();
				$sections[] = $this->get_schedule_context();
				$sections[] = $this->get_announcements_context();
				$sections[] = $this->get_pipeline_summary();
				$sections[] = $this->get_recent_leads();
				break;

			case 'coaching':
				$sections[] = $this->get_schedule_context();
				$sections[] = $this->get_foundations_summary();
				$sections[] = $this->get_promotion_candidates();
				$sections[] = $this->get_todays_rosters();
				if ( $user_id > 0 ) {
					$sections[] = $this->get_member_context( $user_id );
				}
				break;

			case 'finance':
				$sections[] = $this->get_subscription_summary();
				$sections[] = $this->get_mrr_context();
				$sections[] = $this->get_failed_payments_context();
				$sections[] = $this->get_new_signups_context();
				$sections[] = $this->get_churn_summary();
				$sections[] = $this->get_pipeline_summary();
				break;

			case 'admin':
				$sections[] = $this->get_attendance_summary();
				$sections[] = $this->get_announcements_context();
				$sections[] = $this->get_pending_social_context();
				$sections[] = $this->get_schedule_context();
				$sections[] = $this->get_pipeline_summary();
				$sections[] = $this->get_churn_summary();
				break;

			default:
				return '';
		}

		// Filter empty sections and build the context block.
		$sections = array_filter( $sections );

		if ( empty( $sections ) ) {
			return '';
		}

		$context = implode( "\n\n", $sections );

		/**
		 * Filters the context block before it is appended to the system prompt.
		 *
		 * @param string $context  The assembled context block.
		 * @param string $persona  Agent slug.
		 * @param int    $user_id  User ID (0 if no member context).
		 *
		 * @since 0.3.0
		 */
		$context = apply_filters( 'hma_ai_chat_persona_context', $context, $persona, $user_id );

		return "\n\n--- Current Gym Context ---\n" . $context;
	}

	/**
	 * Get member-specific context for personalized responses.
	 *
	 * Returns name, location, memberships, rank, Foundations status,
	 * attendance stats, streak, badges, and last check-in.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Formatted member context block, or empty string on failure.
	 */
	public function get_member_context( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## Member Context';
		$lines[] = sprintf( 'Name: %s', esc_html( $user->display_name ) );

		// Active memberships via WooCommerce Subscriptions.
		$subscriptions = $this->rest_get( '/wc/v3/subscriptions', array(
			'customer' => $user_id,
			'status'   => 'active',
			'per_page' => 10,
		) );

		if ( is_array( $subscriptions ) && ! empty( $subscriptions ) ) {
			$plans = array();
			foreach ( $subscriptions as $sub ) {
				if ( isset( $sub['line_items'] ) ) {
					foreach ( $sub['line_items'] as $item ) {
						$plans[] = $item['name'] ?? 'Unknown plan';
					}
				}
			}
			if ( ! empty( $plans ) ) {
				$lines[] = sprintf( 'Active Memberships: %s', implode( ', ', $plans ) );
			}
		} else {
			$lines[] = 'Active Memberships: None';
		}

		// Location from user meta.
		$location = get_user_meta( $user_id, 'gym_location', true );
		if ( $location ) {
			$lines[] = sprintf( 'Home Location: %s', esc_html( ucfirst( $location ) ) );
		}

		// Rank per program.
		$rank_data = $this->rest_get( '/gym/v1/members/' . $user_id . '/rank' );
		if ( is_array( $rank_data ) ) {
			if ( isset( $rank_data['program'] ) ) {
				// Single program response.
				$lines[] = sprintf(
					'Rank: %s (%s)',
					$rank_data['rank_label'] ?? $rank_data['rank'] ?? 'Unknown',
					$rank_data['program'] ?? 'Unknown'
				);
			} elseif ( ! empty( $rank_data ) ) {
				// Multiple programs.
				$rank_parts = array();
				foreach ( $rank_data as $rank ) {
					if ( isset( $rank['program'], $rank['rank_label'] ) ) {
						$rank_parts[] = sprintf( '%s: %s', ucfirst( $rank['program'] ), $rank['rank_label'] );
					}
				}
				if ( ! empty( $rank_parts ) ) {
					$lines[] = sprintf( 'Ranks: %s', implode( ', ', $rank_parts ) );
				}
			}
		}

		// Foundations status.
		$foundations = $this->rest_get( '/gym/v1/foundations/' . $user_id );
		if ( is_array( $foundations ) && ! empty( $foundations ) ) {
			$status = $foundations['status'] ?? 'unknown';
			$rolls  = $foundations['coach_roll_count'] ?? 0;
			$lines[] = sprintf( 'Foundations: %s (coach rolls: %d)', ucfirst( $status ), $rolls );
		}

		// Attendance stats + last check-in (single request).
		$attendance = $this->rest_get( '/gym/v1/attendance/' . $user_id, array(
			'per_page' => 1,
			'page'     => 1,
		) );

		if ( is_array( $attendance ) && ! empty( $attendance ) ) {
			$last = reset( $attendance );
			if ( isset( $last['checked_in_at'] ) ) {
				$lines[] = sprintf( 'Last Check-in: %s', $last['checked_in_at'] );
			}
		}

		// Streak.
		$streak = $this->rest_get( '/gym/v1/members/' . $user_id . '/streak' );
		if ( is_array( $streak ) ) {
			$current = $streak['current_streak'] ?? $streak['current'] ?? 0;
			$longest = $streak['longest_streak'] ?? $streak['longest'] ?? 0;
			$lines[] = sprintf( 'Streak: %d weeks current, %d weeks longest', $current, $longest );
		}

		// Badges.
		$badges = $this->rest_get( '/gym/v1/members/' . $user_id . '/badges' );
		if ( is_array( $badges ) && ! empty( $badges ) ) {
			$badge_names = array();
			foreach ( $badges as $badge ) {
				$badge_names[] = $badge['name'] ?? $badge['badge_name'] ?? 'Badge';
			}
			$lines[] = sprintf( 'Badges: %s', implode( ', ', $badge_names ) );
		}

		return implode( "\n", $lines );
	}

	// -------------------------------------------------------------------------
	// Sales context helpers
	// -------------------------------------------------------------------------

	/**
	 * Get current membership pricing across locations.
	 *
	 * @return string
	 */
	private function get_pricing_context(): string {
		$cache_key = 'pricing_context';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$locations = $this->rest_get( '/gym/v1/locations' );

		if ( ! is_array( $locations ) || empty( $locations ) ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## Current Membership Pricing';

		foreach ( $locations as $location ) {
			$slug = $location['slug'] ?? '';
			$name = $location['name'] ?? $slug;

			if ( empty( $slug ) ) {
				continue;
			}

			$products = $this->rest_get( '/gym/v1/locations/' . $slug . '/products' );

			if ( ! is_array( $products ) || empty( $products ) ) {
				continue;
			}

			$lines[] = sprintf( "\n### %s", esc_html( $name ) );

			foreach ( $products as $product ) {
				$product_name  = $product['name'] ?? 'Unknown';
				$price         = $product['price'] ?? $product['regular_price'] ?? 'N/A';
				$description   = $product['short_description'] ?? '';

				$line = sprintf( '- %s: $%s/mo', esc_html( $product_name ), esc_html( (string) $price ) );
				if ( $description ) {
					$line .= sprintf( ' — %s', wp_strip_all_tags( $description ) );
				}
				$lines[] = $line;
			}
		}

		$result = implode( "\n", $lines );

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get trial and drop-in options.
	 *
	 * @return string
	 */
	private function get_trial_context(): string {
		$lines = array( '## Trial & Drop-In Options' );

		// Query WC products tagged as trials.
		$trial_products = $this->rest_get( '/wc/v3/products', array(
			'tag'      => 'trial',
			'status'   => 'publish',
			'per_page' => 5,
		) );

		if ( is_array( $trial_products ) && ! empty( $trial_products ) ) {
			foreach ( $trial_products as $product ) {
				$lines[] = sprintf(
					'- %s: $%s',
					$product['name'] ?? 'Trial',
					$product['price'] ?? '0'
				);
			}
		} else {
			$lines[] = '- Free trial class available for new students';
		}

		return implode( "\n", $lines );
	}

	// -------------------------------------------------------------------------
	// Schedule context
	// -------------------------------------------------------------------------

	/**
	 * Get today's class schedule across locations.
	 *
	 * @return string
	 */
	private function get_schedule_context(): string {
		$cache_key = 'schedule_today';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$today    = wp_date( 'Y-m-d' );
		$day_name = wp_date( 'l' );

		$lines   = array();
		$lines[] = sprintf( "## Today's Schedule (%s, %s)", $day_name, $today );

		$locations = $this->rest_get( '/gym/v1/locations' );

		if ( ! is_array( $locations ) ) {
			return '';
		}

		foreach ( $locations as $location ) {
			$slug = $location['slug'] ?? '';
			$name = $location['name'] ?? $slug;

			if ( empty( $slug ) ) {
				continue;
			}

			$schedule = $this->rest_get( '/gym/v1/schedule', array(
				'location' => $slug,
				'week_of'  => $today,
			) );

			if ( ! is_array( $schedule ) || empty( $schedule ) ) {
				continue;
			}

			$lines[] = sprintf( "\n### %s", esc_html( $name ) );

			// Filter to today's classes.
			$today_lower = strtolower( $day_name );
			foreach ( $schedule as $class ) {
				$class_day = strtolower( $class['day'] ?? $class['day_of_week'] ?? '' );
				if ( $class_day !== $today_lower ) {
					continue;
				}

				$time    = $class['time'] ?? $class['start_time'] ?? '';
				$title   = $class['title'] ?? $class['name'] ?? 'Class';
				$coach   = $class['coach'] ?? $class['instructor'] ?? '';

				$line = sprintf( '- %s: %s', esc_html( $time ), esc_html( $title ) );
				if ( $coach ) {
					$line .= sprintf( ' (Coach: %s)', esc_html( $coach ) );
				}
				$lines[] = $line;
			}
		}

		$result = implode( "\n", $lines );

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	// -------------------------------------------------------------------------
	// Coaching context helpers
	// -------------------------------------------------------------------------

	/**
	 * Get Foundations program summary.
	 *
	 * @return string
	 */
	private function get_foundations_summary(): string {
		$data = $this->rest_get( '/gym/v1/foundations/active' );

		if ( ! is_array( $data ) ) {
			return '';
		}

		$count = isset( $data['total'] ) ? (int) $data['total'] : count( $data );

		return sprintf( "## Foundations Program\nActive Foundations students: %d", $count );
	}

	/**
	 * Get upcoming promotion candidates.
	 *
	 * @return string
	 */
	private function get_promotion_candidates(): string {
		$programs = class_exists( '\Gym_Core\Rank\RankDefinitions' )
			? array_keys( \Gym_Core\Rank\RankDefinitions::get_programs() )
			: array( 'adult-bjj', 'kids-bjj', 'kickboxing' );
		$lines    = array( '## Promotion Candidates' );
		$found    = false;

		foreach ( $programs as $program ) {
			$eligible = $this->rest_get( '/gym/v1/promotions/eligible', array(
				'program' => $program,
			) );

			if ( ! is_array( $eligible ) || empty( $eligible ) ) {
				continue;
			}

			$found   = true;
			$lines[] = sprintf( "\n### %s", ucfirst( str_replace( '-', ' ', $program ) ) );

			foreach ( $eligible as $member ) {
				$name   = $member['display_name'] ?? $member['name'] ?? 'Member';
				$rank   = $member['current_rank'] ?? $member['rank_label'] ?? '';
				$lines[] = sprintf( '- %s (current: %s)', esc_html( $name ), esc_html( $rank ) );
			}
		}

		return $found ? implode( "\n", $lines ) : '';
	}

	// -------------------------------------------------------------------------
	// Finance context helpers
	// -------------------------------------------------------------------------

	/**
	 * Get active subscription count.
	 *
	 * @return string
	 */
	private function get_subscription_summary(): string {
		$subs = $this->rest_get( '/wc/v3/subscriptions', array(
			'status'   => 'active',
			'per_page' => 1,
		) );

		// WC REST API returns total count in response headers; for internal
		// dispatch we check the response envelope or count.
		$count = 0;

		if ( is_array( $subs ) ) {
			// Try to get total from the response data if it includes a total field.
			$count = count( $subs );
		}

		// Use a reports endpoint for a more accurate count.
		$report = $this->rest_get( '/wc/v3/reports/customers/totals' );
		$active_subs = $count;

		if ( is_array( $report ) ) {
			foreach ( $report as $entry ) {
				if ( isset( $entry['slug'] ) && 'paying' === $entry['slug'] ) {
					$active_subs = (int) ( $entry['total'] ?? $count );
					break;
				}
			}
		}

		return sprintf( "## Subscription Overview\nActive subscriptions: %d", $active_subs );
	}

	/**
	 * Get Monthly Recurring Revenue (MRR).
	 *
	 * @return string
	 */
	private function get_mrr_context(): string {
		$first_of_month = wp_date( 'Y-m-01' );
		$today          = wp_date( 'Y-m-d' );

		$revenue = $this->rest_get( '/wc/v3/reports/revenue/stats', array(
			'period'   => 'custom',
			'date_min' => $first_of_month,
			'date_max' => $today,
		) );

		if ( ! is_array( $revenue ) ) {
			return '';
		}

		// WC Analytics nests totals under 'totals'.
		$totals     = $revenue['totals'] ?? $revenue;
		$net        = $totals['net_revenue'] ?? $totals['total_sales'] ?? 0;
		$refunds    = $totals['refunds'] ?? 0;

		$lines   = array();
		$lines[] = '## Monthly Revenue (MTD)';
		$lines[] = sprintf( 'Net Revenue: $%s', number_format( (float) $net, 2 ) );

		if ( $refunds ) {
			$lines[] = sprintf( 'Refunds: $%s', number_format( abs( (float) $refunds ), 2 ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get failed payments this month.
	 *
	 * @return string
	 */
	private function get_failed_payments_context(): string {
		$first_of_month = wp_date( 'Y-m-01\TH:i:s' );

		$failed = $this->rest_get( '/wc/v3/orders', array(
			'status'   => 'failed',
			'after'    => $first_of_month,
			'per_page' => 100,
		) );

		$count = is_array( $failed ) ? count( $failed ) : 0;

		return sprintf( "## Failed Payments\nFailed payments this month: %d", $count );
	}

	/**
	 * Get new signups this month.
	 *
	 * @return string
	 */
	private function get_new_signups_context(): string {
		$first_of_month = wp_date( 'Y-m-01\TH:i:s' );

		$orders = $this->rest_get( '/wc/v3/orders', array(
			'status'   => array( 'completed', 'processing' ),
			'after'    => $first_of_month,
			'per_page' => 100,
		) );

		$count = is_array( $orders ) ? count( $orders ) : 0;

		return sprintf( "## New Signups\nNew orders this month: %d", $count );
	}

	// -------------------------------------------------------------------------
	// Admin context helpers
	// -------------------------------------------------------------------------

	/**
	 * Get today's attendance summary.
	 *
	 * @return string
	 */
	private function get_attendance_summary(): string {
		$data = $this->rest_get( '/gym/v1/attendance/today' );

		if ( ! is_array( $data ) ) {
			return '';
		}

		$count = isset( $data['total'] ) ? (int) $data['total'] : count( $data );

		return sprintf( "## Today's Attendance\nTotal check-ins today: %d", $count );
	}

	/**
	 * Get active announcements.
	 *
	 * @return string
	 */
	private function get_announcements_context(): string {
		$today = wp_date( 'Y-m-d' );

		$args = array(
			'post_type'      => 'gym_announcement',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => '_gym_announcement_end_date',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_gym_announcement_end_date',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## Active Announcements';

		foreach ( $posts as $post ) {
			$pinned = get_post_meta( $post->ID, '_gym_announcement_pinned', true );
			$prefix = $pinned ? '[PINNED] ' : '';
			$lines[] = sprintf(
				'- %s%s: %s',
				$prefix,
				esc_html( $post->post_title ),
				wp_strip_all_tags( wp_trim_words( $post->post_content, 20 ) )
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get pending social posts awaiting approval.
	 *
	 * @return string
	 */
	private function get_pending_social_context(): string {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'pending',
			'posts_per_page' => 10,
			'category_name'  => 'social',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$posts = get_posts( $args );
		$count = count( $posts );

		if ( 0 === $count ) {
			return '';
		}

		$lines   = array();
		$lines[] = sprintf( '## Pending Social Posts (%d awaiting approval)', $count );

		foreach ( $posts as $post ) {
			$lines[] = sprintf(
				'- "%s" — drafted %s',
				esc_html( $post->post_title ),
				wp_date( 'M j', strtotime( $post->post_date ) )
			);
		}

		return implode( "\n", $lines );
	}

	// -------------------------------------------------------------------------
	// CRM context helpers (new)
	// -------------------------------------------------------------------------

	/**
	 * Get CRM pipeline summary — contact counts per stage.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	private function get_pipeline_summary(): string {
		$cache_key = 'pipeline_summary';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->rest_get( '/gym/v1/crm/pipeline' );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## CRM Pipeline';

		$total = 0;
		foreach ( $data as $stage ) {
			$name  = $stage['stage'] ?? 'Unknown';
			$count = (int) ( $stage['count'] ?? 0 );
			$total += $count;
			$lines[] = sprintf( '- %s: %d', esc_html( $name ), $count );
		}

		$lines[] = sprintf( 'Total contacts: %d', $total );

		$result = implode( "\n", $lines );

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get the 5 most recent leads from CRM.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	private function get_recent_leads(): string {
		$cache_key = 'recent_leads';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->rest_get( '/gym/v1/crm/contacts', array(
			'status'         => 'Lead',
			'per_page'       => 5,
			'prospects_only' => true,
		) );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## Recent Leads';

		foreach ( $data as $contact ) {
			$name  = trim( ( $contact['first_name'] ?? '' ) . ' ' . ( $contact['last_name'] ?? '' ) );
			$email = $contact['email'] ?? '';
			if ( '' === $name ) {
				$name = $email ?: 'Unknown';
			}
			$lines[] = sprintf( '- %s (%s)', esc_html( $name ), esc_html( $email ) );
		}

		$result = implode( "\n", $lines );

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	// -------------------------------------------------------------------------
	// Roster context helpers (new)
	// -------------------------------------------------------------------------

	/**
	 * Get today's class rosters with expected student counts.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	private function get_todays_rosters(): string {
		$cache_key = 'todays_rosters';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$day_name = strtolower( wp_date( 'l' ) );
		$today    = wp_date( 'Y-m-d' );

		$locations = $this->rest_get( '/gym/v1/locations' );

		if ( ! is_array( $locations ) ) {
			return '';
		}

		$lines = array( "## Today's Class Rosters" );
		$found = false;

		foreach ( $locations as $location ) {
			$slug = $location['slug'] ?? '';
			if ( empty( $slug ) ) {
				continue;
			}

			$schedule = $this->rest_get( '/gym/v1/schedule', array(
				'location' => $slug,
				'week_of'  => $today,
			) );

			if ( ! is_array( $schedule ) ) {
				continue;
			}

			foreach ( $schedule as $class ) {
				$class_day = strtolower( $class['day'] ?? $class['day_of_week'] ?? '' );
				if ( $class_day !== $day_name ) {
					continue;
				}

				$class_id = $class['id'] ?? $class['class_id'] ?? 0;
				$title    = $class['title'] ?? $class['name'] ?? 'Class';
				$time     = $class['time'] ?? $class['start_time'] ?? '';

				if ( $class_id > 0 ) {
					$roster = $this->rest_get( '/gym/v1/classes/' . $class_id . '/roster' );
					$count  = is_array( $roster ) ? ( $roster['expected_count'] ?? 0 ) : 0;
				} else {
					$count = '?';
				}

				$found   = true;
				$lines[] = sprintf( '- %s %s: %s expected', esc_html( $time ), esc_html( $title ), $count );
			}
		}

		if ( ! $found ) {
			return '';
		}

		$result = implode( "\n", $lines );

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	// -------------------------------------------------------------------------
	// Churn context helpers (new)
	// -------------------------------------------------------------------------

	/**
	 * Get churn summary for the current month.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	private function get_churn_summary(): string {
		$cache_key = 'churn_summary';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->rest_get( '/gym/v1/orders/churn', array( 'days' => 30 ) );

		if ( ! is_array( $data ) ) {
			return '';
		}

		$cancelled = $data['cancelled'] ?? 0;
		$retention = $data['retention_rate'] ?? 100;
		$new       = $data['new_signups'] ?? 0;

		$result = sprintf(
			"## Churn (30 days)\nCancellations: %d | New signups: %d | Retention: %s%%",
			$cancelled,
			$new,
			number_format( (float) $retention, 1 )
		);

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	// -------------------------------------------------------------------------
	// Internal REST dispatch helper
	// -------------------------------------------------------------------------

	/**
	 * Perform an internal REST GET request (zero HTTP overhead).
	 *
	 * Uses the same rest_do_request() pattern as ToolExecutor for consistency.
	 *
	 * @param string $route  Full route path (e.g. "/gym/v1/schedule" or "/wc/v3/orders").
	 * @param array  $params Optional query parameters.
	 * @return array|null Response data on success, null on failure.
	 */
	private function rest_get( string $route, array $params = array() ): ?array {
		// Prepend gym/v1 namespace if the route doesn't start with a known namespace.
		if ( ! str_starts_with( $route, '/wc/' ) && ! str_starts_with( $route, '/gym/' ) ) {
			$route = '/gym/v1' . $route;
		}

		$request = new WP_REST_Request( 'GET', $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );
		$status   = $response->get_status();

		if ( $status >= 200 && $status < 300 ) {
			$data = $response->get_data();
			if ( ! is_array( $data ) ) {
				return null;
			}
			// Unwrap gym/v1 response envelope: { success: true, data: [...] }.
			if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
				$data = $data['data'];
			}
			return $data;
		}

		return null;
	}
}
