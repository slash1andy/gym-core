<?php
/**
 * Lead-source field — single source of truth for required lead-source capture.
 *
 * Both intake surfaces (sales kiosk at /sales/ and the public free-trial form)
 * collect a required `lead_source`. This class owns the option list, validation,
 * and persistence helpers used by:
 *
 *   - Gym_Core\Sales\KioskEndpoint        (renders the field)
 *   - Gym_Core\API\SalesController        (validates + persists for kiosk)
 *   - Gym_Core\Sales\OrderBuilder         (persists `_gym_lead_source` order meta)
 *   - Gym_Core\Reports\LeadSourceReport   (admin reporting + CSV export)
 *   - Haanpaa\Jetpack_CRM                 (validates + persists for free-trial)
 *
 * The option list (`OPTIONS`) is filterable via `gym_core_lead_source_options`
 * so admins can extend without touching plugin code.
 *
 * Voice/tone aligns with brand-guide §8 ("academy", "join our community").
 *
 * @package Gym_Core\Sales
 * @since   4.1.0
 */

declare( strict_types=1 );

namespace Gym_Core\Sales;

/**
 * Lead-source option registry, validator, and persistence helpers.
 */
final class LeadSourceField {

	/**
	 * Order meta key persisted on every WooCommerce order created from intake.
	 *
	 * Stored as the canonical option slug (e.g. `google`, `walk_in`).
	 *
	 * @var string
	 */
	public const ORDER_META_KEY = '_gym_lead_source';

	/**
	 * Order meta key holding the free-text "Other" detail when source = `other`.
	 *
	 * @var string
	 */
	public const ORDER_META_OTHER = '_gym_lead_source_other';

	/**
	 * WordPress user meta key used to carry the lead source from order to member.
	 *
	 * Mirrors the order meta so a converted trial-class purchaser keeps the
	 * acquisition signal on their user profile (used by the Lead Sources report
	 * and downstream cohort analysis in Phase 3 §P).
	 *
	 * @var string
	 */
	public const USER_META_KEY = '_gym_lead_source';

	/**
	 * WordPress user meta key holding the free-text "Other" detail.
	 *
	 * @var string
	 */
	public const USER_META_OTHER = '_gym_lead_source_other';

	/**
	 * Jetpack CRM contact custom-field key persisted on contact creation.
	 *
	 * @var string
	 */
	public const CRM_FIELD_KEY = 'gym_lead_source';

	/**
	 * Jetpack CRM contact custom-field key for the free-text "Other" detail.
	 *
	 * @var string
	 */
	public const CRM_FIELD_OTHER = 'gym_lead_source_other';

	/**
	 * Free-trial CPT (`hp_trial_lead`) post-meta key for the lead source.
	 *
	 * @var string
	 */
	public const TRIAL_CPT_META_KEY = 'gym_lead_source';

	/**
	 * Free-trial CPT post-meta key for the free-text "Other" detail.
	 *
	 * @var string
	 */
	public const TRIAL_CPT_META_OTHER = 'gym_lead_source_other';

	/**
	 * Sentinel slug indicating the staff/prospect chose "Other".
	 *
	 * @var string
	 */
	public const SOURCE_OTHER = 'other';

	/**
	 * Canonical option list. Order is rendering order in selects.
	 *
	 * Slugs are stable identifiers; do not rename without a migration.
	 * Labels follow brand-guide voice — "Walk-in" (not "Drop-in"),
	 * "Referral" (not "Friend referral"), etc.
	 *
	 * @var array<int, array{slug: string, label: string}>
	 */
	public const OPTIONS = array(
		array(
			'slug'  => 'google',
			'label' => 'Google',
		),
		array(
			'slug'  => 'walk_in',
			'label' => 'Walk-in',
		),
		array(
			'slug'  => 'referral',
			'label' => 'Referral',
		),
		array(
			'slug'  => 'facebook',
			'label' => 'Facebook',
		),
		array(
			'slug'  => 'instagram',
			'label' => 'Instagram',
		),
		array(
			'slug'  => self::SOURCE_OTHER,
			'label' => 'Other',
		),
	);

	/**
	 * Returns the filterable list of lead-source options.
	 *
	 * Each option is `array{slug: string, label: string}`. Plugins/themes can
	 * extend, reorder, or relabel via the `gym_core_lead_source_options` filter.
	 *
	 * @return array<int, array{slug: string, label: string}>
	 */
	public static function get_options(): array {
		/**
		 * Filters the lead-source option list.
		 *
		 * Each option is `array{slug: string, label: string}`. Slugs are stable
		 * identifiers; renaming a slug after data has been captured will orphan
		 * historical rows in the report.
		 *
		 * @since 4.1.0
		 *
		 * @param array<int, array{slug: string, label: string}> $options Default options.
		 */
		$options = apply_filters( 'gym_core_lead_sources', self::OPTIONS );

		// Defensive: drop malformed entries so downstream code can rely on shape.
		$clean = array();
		foreach ( (array) $options as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$slug  = isset( $opt['slug'] ) ? sanitize_key( (string) $opt['slug'] ) : '';
			$label = isset( $opt['label'] ) ? (string) $opt['label'] : '';
			if ( '' === $slug || '' === $label ) {
				continue;
			}
			$clean[] = array(
				'slug'  => $slug,
				'label' => $label,
			);
		}

		return $clean;
	}

	/**
	 * Returns the slug=>label map for convenience (admin reports, CSV headers).
	 *
	 * @return array<string, string>
	 */
	public static function get_choices(): array {
		$choices = array();
		foreach ( self::get_options() as $opt ) {
			$choices[ $opt['slug'] ] = $opt['label'];
		}
		return $choices;
	}

	/**
	 * Returns the human-readable label for a stored source slug.
	 *
	 * Falls back to a title-cased version of the slug when the slug is unknown
	 * (e.g. legacy data or a removed custom option), so reports never show a raw
	 * machine slug to staff.
	 *
	 * @param string $slug Stored source slug.
	 * @return string Human-readable label.
	 */
	public static function label_for( string $slug ): string {
		$choices = self::get_choices();
		if ( isset( $choices[ $slug ] ) ) {
			return $choices[ $slug ];
		}

		if ( '' === $slug ) {
			return __( 'Unknown', 'gym-core' );
		}

		return ucwords( str_replace( array( '_', '-' ), ' ', $slug ) );
	}

	/**
	 * Returns true when `$slug` is a registered option.
	 *
	 * @param string $slug Candidate slug.
	 * @return bool
	 */
	public static function is_valid_source( string $slug ): bool {
		return isset( self::get_choices()[ $slug ] );
	}

	/**
	 * Validates a submitted lead-source payload.
	 *
	 * Returns either a normalised pair `array{source: string, other: string}`
	 * or a `WP_Error` describing the validation failure. Callers persist only
	 * after a successful return.
	 *
	 * Rules:
	 * - `source` is required and MUST be a known slug.
	 * - When `source === 'other'` the `other` free-text MUST be non-empty
	 *   after sanitisation.
	 *
	 * @param string $source Raw source slug from request.
	 * @param string $other  Raw "Other" free-text from request.
	 * @return array{source: string, other: string}|\WP_Error
	 */
	public static function validate( string $source, string $other = '' ) {
		$source = sanitize_key( $source );
		$other  = sanitize_text_field( $other );

		if ( '' === $source ) {
			return new \WP_Error(
				'gym_lead_source_required',
				__( 'Please tell us how you heard about Haanpaa Martial Arts.', 'gym-core' ),
				array(
					'status' => 422,
					'field'  => 'lead_source',
				)
			);
		}

		if ( ! self::is_valid_source( $source ) ) {
			return new \WP_Error(
				'gym_lead_source_invalid',
				__( 'Please choose a valid option for how you heard about us.', 'gym-core' ),
				array(
					'status' => 422,
					'field'  => 'lead_source',
				)
			);
		}

		if ( self::SOURCE_OTHER === $source && '' === $other ) {
			return new \WP_Error(
				'gym_lead_source_other_required',
				__( 'Please add a quick note describing how you heard about us.', 'gym-core' ),
				array(
					'status' => 422,
					'field'  => 'lead_source_other',
				)
			);
		}

		// Truncate "Other" to a sane max length to keep order notes tidy.
		if ( strlen( $other ) > 200 ) {
			$other = substr( $other, 0, 200 );
		}

		return array(
			'source' => $source,
			'other'  => self::SOURCE_OTHER === $source ? $other : '',
		);
	}

	/**
	 * Persists the validated lead source to a WooCommerce order (HPOS-safe).
	 *
	 * Caller is responsible for calling `$order->save()` (or relying on the
	 * subsequent `OrderBuilder` save). We `update_meta_data()` only.
	 *
	 * @param \WC_Order $order  Order to annotate.
	 * @param string    $source Validated source slug.
	 * @param string    $other  Validated "Other" free-text (empty when source != other).
	 * @return void
	 */
	public static function persist_to_order( \WC_Order $order, string $source, string $other = '' ): void {
		$order->update_meta_data( self::ORDER_META_KEY, $source );
		if ( '' !== $other ) {
			$order->update_meta_data( self::ORDER_META_OTHER, $other );
		}
	}

	/**
	 * Carries the lead source from an order over to the user profile.
	 *
	 * Called when an intake order creates (or matches) a customer; this is the
	 * "trial → member conversion" carryover requirement from the plan §F:
	 * the eventual member must keep `_gym_lead_source` on their WP profile.
	 *
	 * The user-meta values are written only when the user does not already
	 * carry a captured source — first-touch wins, so a returning customer's
	 * original source isn't overwritten by a later order.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $source  Validated source slug.
	 * @param string $other   Validated "Other" free-text (may be empty).
	 * @return void
	 */
	public static function persist_to_user( int $user_id, string $source, string $other = '' ): void {
		if ( $user_id <= 0 || '' === $source ) {
			return;
		}

		$existing = (string) get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( '' !== $existing ) {
			return;
		}

		update_user_meta( $user_id, self::USER_META_KEY, $source );
		if ( '' !== $other ) {
			update_user_meta( $user_id, self::USER_META_OTHER, $other );
		}
	}

	/**
	 * Returns CRM tags representing the lead source.
	 *
	 * Used by the kiosk lead endpoint and by Jetpack_CRM for free-trial submissions.
	 * Format: `lead-source: <slug>` so the CRM tag list groups cleanly.
	 *
	 * @param string $source Validated source slug.
	 * @return array<int, string>
	 */
	public static function crm_tags_for( string $source ): array {
		if ( '' === $source ) {
			return array();
		}
		return array( 'lead-source: ' . $source );
	}

	/**
	 * Returns a localised CRM note line summarising the lead source.
	 *
	 * @param string $source Validated source slug.
	 * @param string $other  Validated "Other" free-text (may be empty).
	 * @return string
	 */
	public static function crm_note_line( string $source, string $other = '' ): string {
		$label = self::label_for( $source );
		if ( self::SOURCE_OTHER === $source && '' !== $other ) {
			return sprintf(
				/* translators: %s: free-text "Other" detail. */
				__( 'Lead source: Other — %s', 'gym-core' ),
				$other
			);
		}
		return sprintf(
			/* translators: %s: lead source label, e.g. Google. */
			__( 'Lead source: %s', 'gym-core' ),
			$label
		);
	}
}
