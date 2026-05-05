/**
 * Gym Core — funnel tracker (vanilla JS, no jQuery).
 *
 * Beacons CRO funnel events (page-view, free-trial-page-view, form-start,
 * form-submit) to gym/v1/funnel-event. A first-party cookie persists the
 * session id across navigation so we can stitch a multi-page funnel.
 *
 * Server-side `confirmation` event is recorded in PHP via
 * haanpaa/trial_submitted action (see FunnelLogger::on_trial_submitted).
 *
 * Lead-source carry: reads gym_core_lead_sources cookie if present; tolerates
 * absence (Phase 1 §F may not be merged when this ships).
 *
 * @package Gym_Core
 * @since   5.0.0
 */
( function () {
	'use strict';

	if ( typeof window === 'undefined' || ! window.gymFunnel ) {
		return;
	}

	var SESSION_COOKIE   = 'gym_funnel_session';
	var LEAD_SRC_COOKIE  = 'gym_core_lead_sources';
	var SESSION_MAX_AGE  = 60 * 60 * 24 * 30; // 30 days
	var ENDPOINT         = window.gymFunnel.endpoint;

	/**
	 * Generate an RFC4122 v4 UUID using crypto if available, else a fallback.
	 *
	 * @return {string}
	 */
	function uuidv4() {
		if ( window.crypto && window.crypto.getRandomValues ) {
			var bytes = new Uint8Array( 16 );
			window.crypto.getRandomValues( bytes );
			bytes[ 6 ] = ( bytes[ 6 ] & 0x0f ) | 0x40;
			bytes[ 8 ] = ( bytes[ 8 ] & 0x3f ) | 0x80;
			var hex = [];
			for ( var i = 0; i < 16; i++ ) {
				hex.push( ( bytes[ i ] + 0x100 ).toString( 16 ).slice( 1 ) );
			}
			return (
				hex[ 0 ] + hex[ 1 ] + hex[ 2 ] + hex[ 3 ] + '-' +
				hex[ 4 ] + hex[ 5 ] + '-' +
				hex[ 6 ] + hex[ 7 ] + '-' +
				hex[ 8 ] + hex[ 9 ] + '-' +
				hex[ 10 ] + hex[ 11 ] + hex[ 12 ] + hex[ 13 ] + hex[ 14 ] + hex[ 15 ]
			);
		}
		// Fallback — not cryptographically strong but unique enough.
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = Math.random() * 16 | 0;
			var v = c === 'x' ? r : ( r & 0x3 | 0x8 );
			return v.toString( 16 );
		} );
	}

	/**
	 * Read a cookie by name. Returns empty string if missing.
	 *
	 * @param {string} name
	 * @return {string}
	 */
	function getCookie( name ) {
		var match = document.cookie.match( new RegExp( '(^| )' + name + '=([^;]+)' ) );
		return match ? decodeURIComponent( match[ 2 ] ) : '';
	}

	/**
	 * Set a first-party cookie. SameSite=Lax. No domain restriction so it
	 * works across subdomains the merchant might use (locations.example.com).
	 *
	 * @param {string} name
	 * @param {string} value
	 * @param {number} maxAge Seconds.
	 */
	function setCookie( name, value, maxAge ) {
		document.cookie =
			name + '=' + encodeURIComponent( value ) +
			'; max-age=' + maxAge +
			'; path=/' +
			'; SameSite=Lax' +
			( window.location.protocol === 'https:' ? '; Secure' : '' );
	}

	/**
	 * Get-or-create the persistent session id.
	 *
	 * @return {string}
	 */
	function getSessionId() {
		var sid = getCookie( SESSION_COOKIE );
		if ( ! sid ) {
			sid = uuidv4();
			setCookie( SESSION_COOKIE, sid, SESSION_MAX_AGE );
		}
		return sid;
	}

	/**
	 * Send one funnel event. Uses sendBeacon when available so the page can
	 * unload cleanly; falls back to fetch with keepalive.
	 *
	 * @param {string} event
	 * @param {Object} extras Optional metadata.
	 */
	function send( event, extras ) {
		if ( ! ENDPOINT ) {
			return;
		}

		var payload = {
			event:       event,
			session_id:  getSessionId(),
			page_url:    window.location.href,
			lead_source: getCookie( LEAD_SRC_COOKIE ),
			metadata:    extras || {},
		};

		var body = JSON.stringify( payload );

		try {
			if ( navigator.sendBeacon ) {
				var blob = new Blob( [ body ], { type: 'application/json' } );
				if ( navigator.sendBeacon( ENDPOINT, blob ) ) {
					return;
				}
			}
		} catch ( e ) {
			// Fall through to fetch.
		}

		try {
			window.fetch( ENDPOINT, {
				method:    'POST',
				headers:   { 'Content-Type': 'application/json' },
				body:      body,
				keepalive: true,
			} );
		} catch ( e ) {
			// Best-effort. Telemetry must never break the page.
		}
	}

	/**
	 * Record the page-view event. On the free-trial page, also fire
	 * free-trial-page-view (a pre-funnel intent signal distinct from a generic
	 * marketing-page view).
	 */
	function recordPageView() {
		send( 'page-view', { title: document.title } );

		var path = window.location.pathname || '';
		if ( /\/free-trial(\/|$)/i.test( path ) ) {
			send( 'free-trial-page-view', {} );
		}
	}

	/**
	 * Wire form-start on first interaction with the trial wizard, form-submit
	 * on the wizard's submit button. The wizard markup is rendered by
	 * haanpaa-site-kit/patterns/free-trial.php and the wp-interactivity
	 * scope is `haanpaa/trial`.
	 */
	function wireTrialWizard() {
		var wizard = document.querySelector( '[data-wp-interactive="haanpaa/trial"]' );
		if ( ! wizard ) {
			return;
		}

		var started = false;
		var startEvents = [ 'pointerdown', 'keydown', 'change' ];
		var onStart = function () {
			if ( started ) {
				return;
			}
			started = true;
			send( 'form-start', {} );
			startEvents.forEach( function ( ev ) {
				wizard.removeEventListener( ev, onStart, true );
			} );
		};
		startEvents.forEach( function ( ev ) {
			wizard.addEventListener( ev, onStart, true );
		} );

		// form-submit — fires on the wizard's submit form (step 3). The
		// server-side `confirmation` event lives in FunnelLogger::on_trial_submitted.
		var submitForm = wizard.querySelector( 'form[data-wp-on--submit]' );
		if ( submitForm ) {
			submitForm.addEventListener( 'submit', function () {
				send( 'form-submit', {
					program:  ( submitForm.querySelector( '[name="program"]' ) || {} ).value || '',
					location: ( submitForm.querySelector( '[name="location"]' ) || {} ).value || '',
				} );
			}, { once: true } );
		}
	}

	function init() {
		recordPageView();
		wireTrialWizard();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
