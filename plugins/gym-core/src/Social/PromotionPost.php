<?php
/**
 * Auto-creates a celebratory blog post when a student earns a new belt.
 *
 * Published posts are automatically shared to connected social accounts
 * via Jetpack Publicize — no additional integration needed.
 *
 * @package Gym_Core
 * @since   2.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Social;

use Gym_Core\Rank\RankDefinitions;

/**
 * Listens for belt promotions and creates shareable blog posts.
 */
final class PromotionPost {

	/**
	 * Option key that gates automatic promotion posts.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'gym_core_auto_promotion_posts';

	/**
	 * Post category name for promotion announcements.
	 *
	 * @var string
	 */
	private const CATEGORY_NAME = 'Promotions';

	/**
	 * Registers action hooks.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'gym_core_rank_changed', array( $this, 'maybe_create_post' ), 20, 6 );
	}

	/**
	 * Creates a celebratory blog post when a student earns a new belt.
	 *
	 * Only fires for actual belt changes (not stripe additions) and only
	 * when the feature is enabled via the gym_core_auto_promotion_posts option.
	 *
	 * @since 2.3.0
	 *
	 * @param int         $user_id     Promoted user ID.
	 * @param string      $program     Program slug.
	 * @param string      $new_belt    New belt slug.
	 * @param int         $new_stripes New stripe/degree count.
	 * @param string|null $from_belt   Previous belt slug (null if first rank).
	 * @param int         $promoted_by Coach user ID.
	 */
	public function maybe_create_post( int $user_id, string $program, string $new_belt, int $new_stripes, ?string $from_belt, int $promoted_by ): void {
		// Gate: feature must be enabled.
		if ( 'yes' !== get_option( self::OPTION_KEY, 'yes' ) ) {
			return;
		}

		// Only create posts for belt changes, not stripe additions.
		if ( $new_belt === $from_belt ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$belt_label    = $this->get_rank_label( $program, $new_belt );
		$program_label = $this->get_program_label( $program );
		$display_name  = $user->display_name;
		$gym_name      = get_bloginfo( 'name' );

		$title   = $this->build_title( $display_name, $belt_label );
		$content = $this->build_content( $display_name, $belt_label, $program_label, $gym_name );

		$category_id = $this->get_or_create_category();

		$post_data = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => 'publish',
			'post_author'   => $promoted_by,
			'post_category' => array( $category_id ),
			'post_type'     => 'post',
			'meta_input'    => array(
				'_gym_core_promotion_user_id' => $user_id,
				'_gym_core_promotion_program' => $program,
				'_gym_core_promotion_belt'    => $new_belt,
			),
		);

		/**
		 * Filters the promotion post data before insertion.
		 *
		 * @since 2.3.0
		 *
		 * @param array  $post_data The wp_insert_post arguments.
		 * @param int    $user_id   The promoted student's user ID.
		 * @param string $program   Program slug.
		 * @param string $new_belt  New belt slug.
		 */
		$post_data = apply_filters( 'gym_core_promotion_post_data', $post_data, $user_id, $program, $new_belt );

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[gym-core] Failed to create promotion post for user %d: %s',
						$user_id,
						$post_id->get_error_message()
					)
				);
			}
			return;
		}

		/**
		 * Fires after a promotion blog post is created.
		 *
		 * @since 2.3.0
		 *
		 * @param int $post_id The created post ID.
		 * @param int $user_id The promoted student's user ID.
		 */
		do_action( 'gym_core_promotion_post_created', $post_id, $user_id );
	}

	/**
	 * Builds the post title.
	 *
	 * @param string $name       Student display name.
	 * @param string $belt_label Belt display name.
	 * @return string
	 */
	private function build_title( string $name, string $belt_label ): string {
		return sprintf(
			/* translators: 1: student name, 2: belt name (e.g. "Blue Belt") */
			__( 'Congratulations to %1$s on earning their %2$s!', 'gym-core' ),
			$name,
			$belt_label
		);
	}

	/**
	 * Builds the post content.
	 *
	 * @param string $name          Student display name.
	 * @param string $belt_label    Belt display name.
	 * @param string $program_label Program display name.
	 * @param string $gym_name      Site/gym name.
	 * @return string
	 */
	private function build_content( string $name, string $belt_label, string $program_label, string $gym_name ): string {
		$content = sprintf(
			/* translators: 1: student name, 2: belt name, 3: program name, 4: gym name */
			__(
				'We are proud to announce that %1$s has been promoted to %2$s in our %3$s program at %4$s! This is the result of dedication, hard work, and countless hours on the mat. Please join us in congratulating %1$s on this well-deserved achievement.',
				'gym-core'
			),
			esc_html( $name ),
			esc_html( $belt_label ),
			esc_html( $program_label ),
			esc_html( $gym_name )
		);

		return wp_kses_post( sprintf( '<p>%s</p>', $content ) );
	}

	/**
	 * Returns the "Promotions" category ID, creating it if it does not exist.
	 *
	 * @return int Category term ID.
	 */
	private function get_or_create_category(): int {
		$term = get_term_by( 'name', self::CATEGORY_NAME, 'category' );

		if ( $term instanceof \WP_Term ) {
			return $term->term_id;
		}

		$result = wp_insert_term(
			self::CATEGORY_NAME,
			'category',
			array(
				'slug'        => 'promotions',
				'description' => __( 'Automatic belt promotion announcements.', 'gym-core' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			// Fall back to the default category if creation fails.
			return (int) get_option( 'default_category', 1 );
		}

		return (int) $result['term_id'];
	}

	/**
	 * Gets the display label for a rank.
	 *
	 * @param string $program   Program slug.
	 * @param string $rank_slug Rank slug.
	 * @return string
	 */
	private function get_rank_label( string $program, string $rank_slug ): string {
		$ranks = RankDefinitions::get_ranks( $program );

		foreach ( $ranks as $rank ) {
			if ( $rank['slug'] === $rank_slug ) {
				return $rank['name'];
			}
		}

		return ucwords( str_replace( '-', ' ', $rank_slug ) );
	}

	/**
	 * Gets the display label for a program.
	 *
	 * @param string $program Program slug.
	 * @return string
	 */
	private function get_program_label( string $program ): string {
		$programs = RankDefinitions::get_programs();
		return $programs[ $program ] ?? ucwords( str_replace( '-', ' ', $program ) );
	}
}
