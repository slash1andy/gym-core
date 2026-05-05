<?php
/**
 * Medical / injury notes user-edit field.
 *
 * Adds a single textarea to the user-edit screen so Amanda (or any user
 * with `edit_users`) can record an injury or medical flag on a member's
 * profile. Stored in `_gym_medical_notes` user meta and surfaced inside
 * the coach briefing alerts pane.
 *
 * Default-empty is intentional — when the meta is empty the briefing
 * simply omits a medical alert for that student.
 *
 * @package Gym_Core\Admin
 * @since   2.2.0
 */

declare( strict_types=1 );

namespace Gym_Core\Admin;

/**
 * Renders and persists the medical notes user-edit field.
 */
final class MedicalNotesField {

	/**
	 * Meta key.
	 *
	 * @var string
	 */
	public const META_KEY = '_gym_medical_notes';

	/**
	 * Capability required to edit medical notes.
	 *
	 * @var string
	 */
	public const REQUIRED_CAP = 'edit_users';

	/**
	 * Registers the user-edit hooks.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'show_user_profile', array( $this, 'render_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_field' ) );
	}

	/**
	 * Renders the field on the user-edit screen.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public function render_field( \WP_User $user ): void {
		if ( ! current_user_can( self::REQUIRED_CAP, $user->ID ) ) {
			return;
		}

		$notes = (string) get_user_meta( $user->ID, self::META_KEY, true );

		?>
		<h2><?php esc_html_e( 'Gym medical notes', 'gym-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="gym_medical_notes"><?php esc_html_e( 'Injury / medical flag', 'gym-core' ); ?></label>
				</th>
				<td>
					<?php wp_nonce_field( 'gym_medical_notes_save', 'gym_medical_notes_nonce' ); ?>
					<textarea
						name="<?php echo esc_attr( self::META_KEY ); ?>"
						id="gym_medical_notes"
						rows="3"
						class="large-text"
						placeholder="<?php esc_attr_e( 'e.g. ACL surgery 2025 — avoid takedown drills; ask before sparring.', 'gym-core' ); ?>"
					><?php echo esc_textarea( $notes ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Surfaced as a high-priority alert in the coach briefing. Visible only to staff. Leave blank if no flag is required.', 'gym-core' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the field value.
	 *
	 * @param int $user_id User ID being saved.
	 * @return void
	 */
	public function save_field( int $user_id ): void {
		if ( ! current_user_can( self::REQUIRED_CAP, $user_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['gym_medical_notes_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['gym_medical_notes_nonce'] ) ), 'gym_medical_notes_save' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST[ self::META_KEY ] ) ? (string) wp_unslash( (string) $_POST[ self::META_KEY ] ) : '';
		$raw = wp_kses_post( trim( $raw ) );

		if ( '' === $raw ) {
			delete_user_meta( $user_id, self::META_KEY );
		} else {
			update_user_meta( $user_id, self::META_KEY, $raw );
		}
	}
}
