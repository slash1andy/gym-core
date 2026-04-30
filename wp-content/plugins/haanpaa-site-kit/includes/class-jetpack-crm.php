<?php
/**
 * Jetpack CRM form submission handler for the Free Trial flow.
 *
 * The free-trial pattern POSTs (via Interactivity API fetch) to
 * /wp-json/haanpaa/v1/trial. We forward the payload to Jetpack CRM
 * using its public API helpers if available, otherwise we store as a
 * lead via the standard zeroBSCRM_addUpdateContact() function.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Jetpack_CRM {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_route' ] );
    }

    public static function register_route() {
        register_rest_route( 'haanpaa/v1', '/trial', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_trial' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'name'     => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
                'phone'    => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
                'email'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_email' ],
                'program'  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'time'     => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'location' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
                'notes'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ],
                'nonce'    => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    public static function handle_trial( \WP_REST_Request $req ) {
        // Verify nonce (issued by the pattern via wp_create_nonce)
        if ( ! wp_verify_nonce( $req['nonce'], 'haanpaa_trial' ) ) {
            return new \WP_Error( 'bad_nonce', 'Invalid request.', [ 'status' => 403 ] );
        }

        // Honeypot — patterns include a hidden "company" field; bail if filled.
        if ( ! empty( $req->get_param( 'company' ) ) ) {
            return [ 'ok' => true ]; // Silent succeed for bots.
        }

        $payload = [
            'fname'  => $req['name'],
            'phone'  => $req['phone'],
            'email'  => $req['email'] ?? '',
            'status' => 'Lead',
            'notes'  => sprintf(
                "Program: %s\nClass time: %s\nLocation: %s\n\n%s",
                $req['program'] ?? '—',
                $req['time'] ?? '—',
                $req['location'] ?? '—',
                $req['notes'] ?? ''
            ),
            'tags'   => [ 'free-trial', 'website', $req['program'] ?? 'unknown' ],
        ];

        // Prefer Jetpack CRM API
        if ( function_exists( 'zeroBSCRM_addUpdateContact' ) ) {
            $contact_id = \zeroBSCRM_addUpdateContact( [
                'data' => $payload,
            ] );
            do_action( 'haanpaa/trial_submitted', $contact_id, $payload );
            return [ 'ok' => true, 'contact_id' => $contact_id ];
        }

        // Fallback: store as private CPT for manual export
        $post_id = wp_insert_post( [
            'post_type'   => 'hp_trial_lead',
            'post_status' => 'private',
            'post_title'  => $req['name'] . ' — ' . current_time( 'Y-m-d H:i' ),
            'post_content'=> $payload['notes'],
            'meta_input'  => $payload,
        ] );
        do_action( 'haanpaa/trial_submitted', $post_id, $payload );
        return [ 'ok' => true, 'fallback_post_id' => $post_id ];
    }
}
