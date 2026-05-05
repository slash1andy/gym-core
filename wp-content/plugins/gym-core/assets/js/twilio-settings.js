/**
 * Twilio settings: "Send test SMS" button handler.
 *
 * Vanilla JS, no jQuery. Posts to gym/v1/twilio/test-message and updates the
 * status span next to the button with a success or error message.
 *
 * @package Gym_Core
 * @since 4.2.0
 */

( function () {
	'use strict';

	/** @type {Window & { gymCoreTwilioSettings?: { restUrl: string, nonce: string, i18n: Record<string, string> } }} */
	var w = window;

	/**
	 * Initialises the click handler for the test-SMS button.
	 *
	 * @return {void}
	 */
	function init() {
		var config = w.gymCoreTwilioSettings;
		if ( ! config || ! config.restUrl ) {
			return;
		}

		var button = document.getElementById( 'gym-core-twilio-test-button' );
		var status = document.querySelector( '.gym-core-twilio-test-status' );

		if ( ! button || ! status ) {
			return;
		}

		button.addEventListener( 'click', function ( evt ) {
			evt.preventDefault();

			button.disabled = true;
			status.classList.remove( 'is-success', 'is-error' );
			status.textContent = config.i18n.sending || 'Sending...';

			fetch( config.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
					'Accept': 'application/json'
				},
				body: '{}'
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, status: response.status, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( result.ok && result.data && result.data.success ) {
						status.classList.add( 'is-success' );
						status.textContent = config.i18n.success || 'Test SMS sent.';
						return;
					}

					var msg = ( result.data && result.data.message )
						? result.data.message
						: ( config.i18n.genericError || 'Something went wrong.' );

					if ( result.data && result.data.code === 'no_phone_on_profile' ) {
						msg = config.i18n.noPhone || msg;
					}

					status.classList.add( 'is-error' );
					status.textContent = msg;
				} )
				.catch( function () {
					status.classList.add( 'is-error' );
					status.textContent = config.i18n.genericError || 'Something went wrong.';
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
