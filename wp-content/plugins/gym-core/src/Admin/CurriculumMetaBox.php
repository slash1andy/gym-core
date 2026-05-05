<?php
/**
 * Curriculum-of-the-day meta box on the gym_class CPT.
 *
 * V1 stores a single free-text curriculum description in
 * `_gym_curriculum_today` post meta. Coaches or head coaches pre-fill
 * it; the briefing surfaces the value verbatim. Phase 3 §M replaces this
 * with a full curriculum graph but the meta key stays stable so existing
 * briefings continue to render.
 *
 * @package Gym_Core\Admin
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

use Gym_Core\Schedule\ClassPostType;

/**
 * Renders and persists the per-class curriculum meta box.
 */
final class CurriculumMetaBox {

	/**
	 * Meta key.
	 *
	 * @var string
	 */
	public const META_KEY = '_gym_curriculum_today';

	/**
	 * Registers admin hooks.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes_' . ClassPostType::POST_TYPE, array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . ClassPostType::POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'init', array( $this, 'register_post_meta' ), 20 );
	}

	/**
	 * Registers the post-meta key for REST.
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		register_post_meta(
			ClassPostType::POST_TYPE,
			self::META_KEY,
			array(
				'type'          => 'string',
				'description'   => __( 'Curriculum-of-the-day for this class. Free-text v1, replaced by curriculum graph in Phase 3.', 'gym-core' ),
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static fn() => current_user_can( 'edit_others_posts' ),
			)
		);
	}

	/**
	 * Adds the meta box to the gym_class edit screen.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'gym_class_curriculum_today',
			__( 'Curriculum of the day', 'gym-core' ),
			array( $this, 'render' ),
			ClassPostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Renders the meta box.
	 *
	 * @param \WP_Post $post Class post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$value = (string) get_post_meta( $post->ID, self::META_KEY, true );

		wp_nonce_field( 'gym_curriculum_save', 'gym_curriculum_nonce' );

		?>
		<p>
			<label for="gym_curriculum_today" style="display:block;font-weight:600;margin-bottom:0.25rem;">
				<?php esc_html_e( 'What technique / drill / focus is this class teaching?', 'gym-core' ); ?>
			</label>
			<textarea
				name="<?php echo esc_attr( self::META_KEY ); ?>"
				id="gym_curriculum_today"
				rows="6"
				style="width:100%;"
				placeholder="<?php esc_attr_e( 'e.g. Guard passing series — knee-cut entry, leg drag counter. Drill 5 minutes each side, then positional sparring from open guard.', 'gym-core' ); ?>"
			><?php echo esc_textarea( $value ); ?></textarea>
			<span class="description">
				<?php esc_html_e( 'Renders verbatim in the briefing curriculum card. Markdown / HTML allowed.', 'gym-core' ); ?>
			</span>
		</p>
		<?php
	}

	/**
	 * Saves the meta box value.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ClassPostType::POST_TYPE !== $post->post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['gym_curriculum_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gym_curriculum_nonce'] ) ), 'gym_curriculum_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST[ self::META_KEY ] ) ? (string) wp_unslash( (string) $_POST[ self::META_KEY ] ) : '';
		$raw = wp_kses_post( trim( $raw ) );

		if ( '' === $raw ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, $raw );
		}
	}
}
