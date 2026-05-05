<?php
/**
 * Jetpack CRM form submission handler for the Free Trial flow.
 *
 * The free-trial pattern POSTs (via Interactivity API fetch) to
 * /wp-json/haanpaa/v1/trial. We forward the payload to Jetpack CRM
 * using its public API helpers if available, otherwise we store as a
 * lead via the standard zeroBSCRM_addUpdateContact() function.
 *
 * Lead-source capture (Plan §F): the form requires a `lead_source` slug
 * (Google / Walk-in / Referral / Facebook / Instagram / Other). Validation
 * is delegated to {@see Gym_Core\Sales\LeadSourceField} when gym-core is
 * active; we keep a small inline fallback so the site-kit pattern still
 * works on a standalone install.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Jetpack_CRM {

    /**
     * Fallback option list when gym-core's LeadSourceField class is not loaded.
     * Kept in sync with Gym_Core\Sales\LeadSourceField::OPTIONS.
     *
     * @var array<int, string>
     */
    private const FALLBACK_SOURCES = [ 'google', 'walk_in', 'referral', 'facebook', 'instagram', 'other' ];

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_route' ] );
    }

    public static function register_route() {
        register_rest_route( 'haanpaa/v1', '/trial', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_trial' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'name'              => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
                'phone'             => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
                'email'             => [ 'required' => false, 'sanitize_callback' => 'sanitize_email' ],
                'program'           => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'time'              => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'location'          => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'notes'             => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
                'lead_source'       => [ 'required' => true,  'sanitize_callback' => 'sanitize_key' ],
                'lead_source_other' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'nonce'             => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    public static function handle_trial( \WP_REST_Request $req ) {
        // Verify nonce (issued by the pattern via wp_create_nonce)
        if ( ! wp_verify_nonce( $req['nonce'], 'haanpaa_trial' ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'message' => __( 'Invalid request.', 'haanpaa-site-kit' ) ], 403 );
        }

        // Honeypot — patterns include a hidden "company" field; bail if filled.
        if ( ! empty( $req->get_param( 'company' ) ) ) {
            return [ 'ok' => true ]; // Silent succeed for bots.
        }

        // Validate lead source (required per Plan §F). Prefer gym-core's
        // canonical validator when available so option lists stay in sync.
        $raw_source = (string) ( $req['lead_source'] ?? '' );
        $raw_other  = (string) ( $req['lead_source_other'] ?? '' );

        if ( class_exists( '\\Gym_Core\\Sales\\LeadSourceField' ) ) {
            $validated = \Gym_Core\Sales\LeadSourceField::validate( $raw_source, $raw_other );
            if ( is_wp_error( $validated ) ) {
                return new \WP_REST_Response(
                    [
                        'ok'      => false,
                        'code'    => $validated->get_error_code(),
                        'message' => $validated->get_error_message(),
                    ],
                    422
                );
            }
            $source = $validated['source'];
            $other  = $validated['other'];
        } else {
            $source = sanitize_key( $raw_source );
            $other  = sanitize_text_field( $raw_other );
            if ( '' === $source || ! in_array( $source, self::FALLBACK_SOURCES, true ) ) {
                return new \WP_REST_Response(
                    [
                        'ok'      => false,
                        'code'    => 'gym_lead_source_required',
                        'message' => __( 'Please tell us how you heard about Haanpaa Martial Arts.', 'haanpaa-site-kit' ),
                    ],
                    422
                );
            }
            if ( 'other' === $source && '' === $other ) {
                return new \WP_REST_Response(
                    [
                        'ok'      => false,
                        'code'    => 'gym_lead_source_other_required',
                        'message' => __( 'Please add a quick note describing how you heard about us.', 'haanpaa-site-kit' ),
                    ],
                    422
                );
            }
            if ( 'other' !== $source ) {
                $other = '';
            }
        }

        $source_note = 'other' === $source && '' !== $other
            ? sprintf( 'Lead source: Other — %s', $other )
            : sprintf( 'Lead source: %s', $source );

        $payload = [
            'fname'  => $req['name'],
            'phone'  => $req['phone'],
            'email'  => $req['email'] ?? '',
            'status' => 'Lead',
            'notes'  => sprintf(
                "Program: %s\nClass time: %s\nLocation: %s\n%s\n\n%s",
                $req['program'] ?? '—',
                $req['time'] ?? '—',
                $req['location'] ?? '—',
                $source_note,
                $req['notes'] ?? ''
            ),
            'tags'   => array_filter( [ 'free-trial', 'website', $req['program'] ?? 'unknown', 'lead-source: ' . $source ] ),
        ];

        // Prefer Jetpack CRM API
        if ( function_exists( 'zeroBSCRM_addUpdateContact' ) ) {
            $contact_id = \zeroBSCRM_addUpdateContact( [
                'data' => $payload,
            ] );

            // Persist lead source as a CRM contact custom field (Plan §F).
            if ( $contact_id && function_exists( 'zeroBSCRM_addUpdateContactMeta' ) ) {
                \zeroBSCRM_addUpdateContactMeta( (int) $contact_id, 'gym_lead_source', $source );
                if ( '' !== $other ) {
                    \zeroBSCRM_addUpdateContactMeta( (int) $contact_id, 'gym_lead_source_other', $other );
                }
            }

            do_action( 'haanpaa/trial_submitted', $contact_id, $payload );
            return [ 'ok' => true, 'contact_id' => $contact_id ];
        }

        // Fallback: store as private CPT for manual export. Lead source is
        // duplicated as discrete post meta so the Lead Sources report can read
        // it without parsing the notes blob.
        $meta = $payload;
        $meta['gym_lead_source'] = $source;
        if ( '' !== $other ) {
            $meta['gym_lead_source_other'] = $other;
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'hp_trial_lead',
            'post_status' => 'private',
            'post_title'  => $req['name'] . ' — ' . current_time( 'Y-m-d H:i' ),
            'post_content'=> $payload['notes'],
            'meta_input'  => $meta,
        ] );
        do_action( 'haanpaa/trial_submitted', $post_id, $payload );
        return [ 'ok' => true, 'fallback_post_id' => $post_id ];
    }
}
