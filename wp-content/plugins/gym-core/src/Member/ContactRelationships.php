<?php
/**
 * Manages parent/child relationships between WordPress users.
 *
 * @package Gym_Core\Member
 */

declare( strict_types=1 );

namespace Gym_Core\Member;

/**
 * Handles bidirectional family relationships (parent/child) between users
 * via user meta, with a UI on the user profile edit screen.
 */
class ContactRelationships {

	/**
	 * Meta key storing an array of parent user IDs.
	 *
	 * @var string
	 */
	private const PARENTS_META_KEY = 'gym_core_parents';

	/**
	 * Meta key storing an array of child user IDs.
	 *
	 * @var string
	 */
	private const CHILDREN_META_KEY = 'gym_core_children';

	/**
	 * Nonce action for relationship form submissions.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'gym_core_relationships_save';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	private const NONCE_NAME = 'gym_core_relationships_nonce';

	/**
	 * Registers WordPress hooks for profile display and save.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'show_user_profile', array( $this, 'render_relationships_section' ) );
		add_action( 'edit_user_profile', array( $this, 'render_relationships_section' ) );
		add_action( 'personal_options_update', array( $this, 'save_relationships' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_relationships' ) );
	}

	/**
	 * Renders the Family Relationships section on the user edit screen.
	 *
	 * @param \WP_User $user The user being edited.
	 * @return void
	 */
	public function render_relationships_section( \WP_User $user ): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$parents  = $this->get_parents( $user->ID );
		$children = $this->get_children( $user->ID );
		?>
		<h2><?php esc_html_e( 'Family Relationships', 'gym-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Parents', 'gym-core' ); ?></th>
				<td>
					<?php if ( empty( $parents ) ) : ?>
						<p class="description"><?php esc_html_e( 'No parents linked.', 'gym-core' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $parents as $parent_id ) : ?>
								<?php $parent_user = get_userdata( $parent_id ); ?>
								<?php if ( $parent_user ) : ?>
									<li>
										<?php echo esc_html( $parent_user->display_name ); ?>
										(<?php echo esc_html( $parent_user->user_email ); ?>)
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array(
											'gym_remove_relationship' => 1,
											'related_id'             => $parent_id,
											'relationship_type'      => 'parent',
										) ), 'gym_core_remove_relationship_' . $parent_id ) ); ?>" class="delete" onclick="return confirm('<?php esc_attr_e( 'Remove this parent relationship?', 'gym-core' ); ?>');">
											<?php esc_html_e( 'Remove', 'gym-core' ); ?>
										</a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Children', 'gym-core' ); ?></th>
				<td>
					<?php if ( empty( $children ) ) : ?>
						<p class="description"><?php esc_html_e( 'No children linked.', 'gym-core' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $children as $child_id ) : ?>
								<?php $child_user = get_userdata( $child_id ); ?>
								<?php if ( $child_user ) : ?>
									<li>
										<?php echo esc_html( $child_user->display_name ); ?>
										(<?php echo esc_html( $child_user->user_email ); ?>)
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array(
											'gym_remove_relationship' => 1,
											'related_id'             => $child_id,
											'relationship_type'      => 'child',
										) ), 'gym_core_remove_relationship_' . $child_id ) ); ?>" class="delete" onclick="return confirm('<?php esc_attr_e( 'Remove this child relationship?', 'gym-core' ); ?>');">
											<?php esc_html_e( 'Remove', 'gym-core' ); ?>
										</a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Add Relationship', 'gym-core' ); ?></th>
				<td>
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<label for="gym_core_related_user">
						<?php esc_html_e( 'User (enter username, email, or ID):', 'gym-core' ); ?>
					</label><br>
					<input type="text" id="gym_core_related_user" name="gym_core_related_user" class="regular-text" value="" />
					<br><br>
					<label for="gym_core_relationship_type">
						<?php esc_html_e( 'Relationship type:', 'gym-core' ); ?>
					</label><br>
					<select id="gym_core_relationship_type" name="gym_core_relationship_type">
						<option value=""><?php esc_html_e( '-- Select --', 'gym-core' ); ?></option>
						<option value="parent"><?php esc_html_e( 'Parent', 'gym-core' ); ?></option>
						<option value="child"><?php esc_html_e( 'Child', 'gym-core' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select the relationship of the user above to this profile.', 'gym-core' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Processes the relationship form submission on profile save.
	 *
	 * Handles both adding new relationships (from form fields) and removing
	 * existing relationships (from query string parameters).
	 *
	 * @param int $user_id The ID of the user being saved.
	 * @return void
	 */
	public function save_relationships( int $user_id ): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		// Handle remove via query string.
		if ( isset( $_GET['gym_remove_relationship'], $_GET['related_id'], $_GET['relationship_type'], $_GET['_wpnonce'] ) ) {
			$related_id = absint( $_GET['related_id'] );
			$type       = sanitize_text_field( wp_unslash( $_GET['relationship_type'] ) );

			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'gym_core_remove_relationship_' . $related_id ) ) {
				$this->remove_relationship( $user_id, $related_id, $type );
			}
			return;
		}

		// Handle add via POST form.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		$related_user_input = isset( $_POST['gym_core_related_user'] ) ? sanitize_text_field( wp_unslash( $_POST['gym_core_related_user'] ) ) : '';
		$relationship_type  = isset( $_POST['gym_core_relationship_type'] ) ? sanitize_text_field( wp_unslash( $_POST['gym_core_relationship_type'] ) ) : '';

		if ( empty( $related_user_input ) || empty( $relationship_type ) ) {
			return;
		}

		if ( ! in_array( $relationship_type, array( 'parent', 'child' ), true ) ) {
			return;
		}

		// Resolve user input to a user ID.
		$related_user = false;
		if ( is_numeric( $related_user_input ) ) {
			$related_user = get_userdata( absint( $related_user_input ) );
		}
		if ( ! $related_user ) {
			$related_user = get_user_by( 'email', $related_user_input );
		}
		if ( ! $related_user ) {
			$related_user = get_user_by( 'login', $related_user_input );
		}

		if ( ! $related_user || $related_user->ID === $user_id ) {
			return;
		}

		$this->add_relationship( $user_id, $related_user->ID, $relationship_type );
	}

	/**
	 * Adds a bidirectional relationship between two users.
	 *
	 * Adding user B as a "parent" of user A also adds user A as a "child" of user B.
	 *
	 * @param int    $user_id    The user being edited.
	 * @param int    $related_id The related user ID.
	 * @param string $type       Relationship type: 'parent' or 'child'.
	 * @return void
	 */
	public function add_relationship( int $user_id, int $related_id, string $type ): void {
		if ( 'parent' === $type ) {
			$this->add_meta_value( $user_id, self::PARENTS_META_KEY, $related_id );
			$this->add_meta_value( $related_id, self::CHILDREN_META_KEY, $user_id );
		} elseif ( 'child' === $type ) {
			$this->add_meta_value( $user_id, self::CHILDREN_META_KEY, $related_id );
			$this->add_meta_value( $related_id, self::PARENTS_META_KEY, $user_id );
		}
	}

	/**
	 * Removes a bidirectional relationship between two users.
	 *
	 * @param int    $user_id    The user being edited.
	 * @param int    $related_id The related user ID.
	 * @param string $type       Relationship type: 'parent' or 'child'.
	 * @return void
	 */
	public function remove_relationship( int $user_id, int $related_id, string $type ): void {
		if ( 'parent' === $type ) {
			$this->remove_meta_value( $user_id, self::PARENTS_META_KEY, $related_id );
			$this->remove_meta_value( $related_id, self::CHILDREN_META_KEY, $user_id );
		} elseif ( 'child' === $type ) {
			$this->remove_meta_value( $user_id, self::CHILDREN_META_KEY, $related_id );
			$this->remove_meta_value( $related_id, self::PARENTS_META_KEY, $user_id );
		}
	}

	/**
	 * Returns the parent user IDs for a given user.
	 *
	 * @param int $user_id The user ID.
	 * @return int[] Array of parent user IDs.
	 */
	public function get_parents( int $user_id ): array {
		$parents = get_user_meta( $user_id, self::PARENTS_META_KEY, true );
		if ( ! is_array( $parents ) ) {
			return array();
		}
		return array_map( 'absint', $parents );
	}

	/**
	 * Returns the child user IDs for a given user.
	 *
	 * @param int $user_id The user ID.
	 * @return int[] Array of child user IDs.
	 */
	public function get_children( int $user_id ): array {
		$children = get_user_meta( $user_id, self::CHILDREN_META_KEY, true );
		if ( ! is_array( $children ) ) {
			return array();
		}
		return array_map( 'absint', $children );
	}

	/**
	 * Adds a user ID to a meta array if not already present.
	 *
	 * @param int    $user_id  The user whose meta to update.
	 * @param string $meta_key The meta key.
	 * @param int    $value    The user ID to add.
	 * @return void
	 */
	private function add_meta_value( int $user_id, string $meta_key, int $value ): void {
		$current = get_user_meta( $user_id, $meta_key, true );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$current = array_map( 'absint', $current );
		if ( ! in_array( $value, $current, true ) ) {
			$current[] = $value;
			update_user_meta( $user_id, $meta_key, $current );
		}
	}

	/**
	 * Removes a user ID from a meta array.
	 *
	 * @param int    $user_id  The user whose meta to update.
	 * @param string $meta_key The meta key.
	 * @param int    $value    The user ID to remove.
	 * @return void
	 */
	private function remove_meta_value( int $user_id, string $meta_key, int $value ): void {
		$current = get_user_meta( $user_id, $meta_key, true );
		if ( ! is_array( $current ) ) {
			return;
		}

		$current = array_map( 'absint', $current );
		$current = array_values( array_diff( $current, array( $value ) ) );
		update_user_meta( $user_id, $meta_key, $current );
	}
}
