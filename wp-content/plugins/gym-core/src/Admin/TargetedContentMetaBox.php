<?php
/**
 * Targeted content meta box for posts and pages.
 *
 * Adds a meta box that allows editors to set content targeting rules
 * (logged-in only, role, location, program, min belt, members only,
 * foundations only, min classes, min streak) without writing shortcodes.
 * Rules are stored as post meta `_gym_content_target_rules`.
 *
 * @package Gym_Core
 * @since   5.3.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

use Gym_Core\Gamification\TargetedContent;
use Gym_Core\Rank\RankDefinitions;
use Gym_Core\Location\Taxonomy as LocationTaxonomy;

/**
 * Registers and renders the targeted content meta box.
 */
final class TargetedContentMetaBox {

	/**
	 * Nonce action for saving targeting rules.
	 */
	private const NONCE_ACTION = 'gym_targeted_content_save';

	/**
	 * Nonce field name.
	 */
	private const NONCE_NAME = '_gym_targeted_content_nonce';

	/**
	 * Registers hooks for the meta box.
	 *
	 * @since 5.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Registers the meta box on posts and pages.
	 *
	 * @since 5.3.0
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		$post_types = array( 'post', 'page' );

		/**
		 * Filters the post types that support the targeted content meta box.
		 *
		 * @since 5.3.0
		 *
		 * @param array<string> $post_types Post type slugs.
		 */
		$post_types = apply_filters( 'gym_core_targeted_content_post_types', $post_types );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'gym-targeted-content-rules',
				__( 'Content Targeting', 'gym-core' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renders the meta box UI.
	 *
	 * @since 5.3.0
	 *
	 * @param \WP_Post $post The current post.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$rules = get_post_meta( $post->ID, TargetedContent::META_KEY, true );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$defaults = array(
			'logged_in'        => '',
			'role'             => '',
			'location'         => '',
			'program'          => '',
			'min_belt'         => '',
			'members_only'     => '',
			'foundations_only'  => '',
			'min_classes'       => '',
			'min_streak'        => '',
		);

		$rules = wp_parse_args( $rules, $defaults );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$programs         = RankDefinitions::get_programs();
		$location_labels  = LocationTaxonomy::get_location_labels();

		?>
		<p class="description">
			<?php esc_html_e( 'Set rules to restrict who can see this content. All specified rules must match (AND logic). Leave empty to show to everyone.', 'gym-core' ); ?>
		</p>

		<p>
			<label>
				<input type="checkbox" name="gym_target[logged_in]" value="true" <?php checked( 'true', $rules['logged_in'] ); ?> />
				<?php esc_html_e( 'Logged-in users only', 'gym-core' ); ?>
			</label>
		</p>

		<p>
			<label>
				<input type="checkbox" name="gym_target[members_only]" value="true" <?php checked( 'true', $rules['members_only'] ); ?> />
				<?php esc_html_e( 'Active members only', 'gym-core' ); ?>
			</label>
		</p>

		<p>
			<label>
				<input type="checkbox" name="gym_target[foundations_only]" value="true" <?php checked( 'true', $rules['foundations_only'] ); ?> />
				<?php esc_html_e( 'Foundations students only', 'gym-core' ); ?>
			</label>
		</p>

		<p>
			<label for="gym-target-role"><?php esc_html_e( 'Roles (comma-separated)', 'gym-core' ); ?></label><br />
			<input type="text" id="gym-target-role" name="gym_target[role]" value="<?php echo esc_attr( $rules['role'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g., customer,subscriber', 'gym-core' ); ?>" />
		</p>

		<?php if ( ! empty( $location_labels ) ) : ?>
		<fieldset>
			<legend><?php esc_html_e( 'Location', 'gym-core' ); ?></legend>
			<?php
			$selected_locations = '' !== $rules['location']
				? array_map( 'trim', explode( ',', $rules['location'] ) )
				: array();
			foreach ( $location_labels as $slug => $label ) :
				?>
				<label style="display:block;">
					<input type="checkbox" name="gym_target_locations[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_locations, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php endif; ?>

		<?php if ( ! empty( $programs ) ) : ?>
		<fieldset>
			<legend><?php esc_html_e( 'Program', 'gym-core' ); ?></legend>
			<?php
			$selected_programs = '' !== $rules['program']
				? array_map( 'trim', explode( ',', $rules['program'] ) )
				: array();
			foreach ( $programs as $slug => $label ) :
				?>
				<label style="display:block;">
					<input type="checkbox" name="gym_target_programs[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_programs, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php endif; ?>

		<p>
			<label for="gym-target-min-belt"><?php esc_html_e( 'Minimum belt', 'gym-core' ); ?></label><br />
			<select id="gym-target-min-belt" name="gym_target[min_belt]" class="widefat">
				<option value=""><?php esc_html_e( '-- Any --', 'gym-core' ); ?></option>
				<?php
				// Show belts from adult BJJ as the primary hierarchy.
				$adult_belts = RankDefinitions::get_ranks( 'adult-bjj' );
				foreach ( $adult_belts as $belt ) :
					?>
					<option value="<?php echo esc_attr( $belt['slug'] ); ?>" <?php selected( $rules['min_belt'], $belt['slug'] ); ?>>
						<?php echo esc_html( $belt['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'Requires a program to be selected. Uses the matched program\'s hierarchy.', 'gym-core' ); ?></span>
		</p>

		<p>
			<label for="gym-target-min-classes"><?php esc_html_e( 'Minimum classes', 'gym-core' ); ?></label><br />
			<input type="number" id="gym-target-min-classes" name="gym_target[min_classes]" value="<?php echo esc_attr( $rules['min_classes'] ); ?>" class="widefat" min="0" step="1" />
		</p>

		<p>
			<label for="gym-target-min-streak"><?php esc_html_e( 'Minimum streak (weeks)', 'gym-core' ); ?></label><br />
			<input type="number" id="gym-target-min-streak" name="gym_target[min_streak]" value="<?php echo esc_attr( $rules['min_streak'] ); ?>" class="widefat" min="0" step="1" />
		</p>
		<?php
	}

	/**
	 * Saves the targeting rules from the meta box.
	 *
	 * @since 5.3.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ),
			self::NONCE_ACTION
		) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below per field.
		$input = isset( $_POST['gym_target'] ) ? (array) wp_unslash( $_POST['gym_target'] ) : array();
		// phpcs:enable

		// Build the locations from checkbox array.
		$locations = array();
		if ( isset( $_POST['gym_target_locations'] ) && is_array( $_POST['gym_target_locations'] ) ) {
			$locations = array_map( 'sanitize_text_field', wp_unslash( $_POST['gym_target_locations'] ) );
		}

		// Build the programs from checkbox array.
		$programs = array();
		if ( isset( $_POST['gym_target_programs'] ) && is_array( $_POST['gym_target_programs'] ) ) {
			$programs = array_map( 'sanitize_text_field', wp_unslash( $_POST['gym_target_programs'] ) );
		}

		$rules = array(
			'logged_in'        => isset( $input['logged_in'] ) && 'true' === $input['logged_in'] ? 'true' : '',
			'role'             => isset( $input['role'] ) ? sanitize_text_field( $input['role'] ) : '',
			'location'         => implode( ',', $locations ),
			'program'          => implode( ',', $programs ),
			'min_belt'         => isset( $input['min_belt'] ) ? sanitize_text_field( $input['min_belt'] ) : '',
			'members_only'     => isset( $input['members_only'] ) && 'true' === $input['members_only'] ? 'true' : '',
			'foundations_only' => isset( $input['foundations_only'] ) && 'true' === $input['foundations_only'] ? 'true' : '',
			'min_classes'      => isset( $input['min_classes'] ) ? sanitize_text_field( $input['min_classes'] ) : '',
			'min_streak'       => isset( $input['min_streak'] ) ? sanitize_text_field( $input['min_streak'] ) : '',
		);

		// Only store if at least one rule is set.
		$has_rules = false;
		foreach ( $rules as $value ) {
			if ( '' !== $value ) {
				$has_rules = true;
				break;
			}
		}

		if ( $has_rules ) {
			update_post_meta( $post_id, TargetedContent::META_KEY, $rules );
		} else {
			delete_post_meta( $post_id, TargetedContent::META_KEY );
		}
	}
}
