<?php
/**
 * Shared cache-priming helper for class-schedule queries.
 *
 * Used by ClassScheduleController and MemberController to avoid N+1 queries
 * when iterating across the same WP_Query for meta, terms, and instructor
 * users.
 *
 * @package Gym_Core\Schedule
 * @since   2.4.0
 */

declare( strict_types=1 );

namespace Gym_Core\Schedule;

use WP_Query;

/**
 * Bulk-primes object caches that schedule renderers will read in their inner loops.
 */
final class ScheduleCachePrimer {

	/**
	 * Primes post-meta, term, and instructor-user caches for the posts in
	 * a class WP_Query result so subsequent get_post_meta(),
	 * get_the_terms(), and get_userdata() calls are object-cache hits.
	 *
	 * Safe to call with an empty result set (no-ops).
	 *
	 * @param WP_Query $query Class query whose posts will be iterated.
	 * @return void
	 */
	public static function prime( WP_Query $query ): void {
		if ( empty( $query->posts ) ) {
			return;
		}

		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		update_meta_cache( 'post', $post_ids );
		update_object_term_cache( $post_ids, ClassPostType::POST_TYPE );

		$instructor_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $post ) => (int) get_post_meta( $post->ID, '_gym_class_instructor', true ),
						$query->posts
					)
				)
			)
		);

		if ( ! empty( $instructor_ids ) ) {
			cache_users( $instructor_ids );
		}
	}
}
