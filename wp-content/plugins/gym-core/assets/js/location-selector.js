/**
 * Gym Location Selector
 *
 * Handles location switching from the frontend banner and auto-detects the
 * visitor's nearest location on first visit.
 *
 * Detection cascade (first-time visitors only):
 *   1. localStorage cache from a previous detection (24h TTL)
 *   2. IP geolocation via geojs.io (silent, no prompt, 4s timeout)
 *   3. Browser Geolocation API (shows permission prompt, 5s timeout)
 *   4. No action — visitor picks manually from the banner
 *
 * Any manual selection via the banner overrides auto-detection permanently.
 *
 * Data is passed from PHP via wp_localize_script under window.gymLocation:
 *   ajaxUrl    {string}  — wp-admin/admin-ajax.php URL
 *   nonce      {string}  — gym_location_nonce value
 *   current    {string}  — currently active location slug (empty if unset)
 *   locations  {object}  — { slug: { lat, lng } } coordinates for distance calc
 *   i18n       {object}  — translated strings
 */

/* global gymLocation */
( function () {
	'use strict';

	var selector = document.getElementById( 'gym-location-selector' );

	if ( ! selector ) {
		return;
	}

	var settings = window.gymLocation || {};

	/**
	 * Updates the selector UI to reflect the newly active location.
	 *
	 * @param {string} slug - The newly active location slug.
	 */
	function updateUI( slug ) {
		var buttons = selector.querySelectorAll( '.gym-location-selector__button' );

		buttons.forEach( function ( btn ) {
			var isActive = btn.dataset.location === slug;
			btn.classList.toggle( 'gym-location-selector__button--active', isActive );
			btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
		} );

		selector.dataset.currentLocation = slug;
		selector.classList.remove( 'gym-location-selector--no-selection' );
	}

	/**
	 * Re-enables all buttons and restores their text content.
	 *
	 * @param {NodeList} buttons        - All location buttons.
	 * @param {Array}    originalLabels - Saved label for each button.
	 */
	function restoreButtons( buttons, originalLabels ) {
		buttons.forEach( function ( btn, i ) {
			btn.disabled     = false;
			btn.textContent  = originalLabels[ i ];
		} );
	}

	/**
	 * Sends the AJAX request to switch the active location.
	 *
	 * @param {string} slug   - The location slug to switch to.
	 * @param {HTMLElement} clickedButton - The button the user activated.
	 */
	function switchLocation( slug, clickedButton ) {
		var buttons = selector.querySelectorAll( '.gym-location-selector__button' );
		var originalLabels = [];

		// Save labels and disable all buttons while the request is in flight.
		buttons.forEach( function ( btn ) {
			originalLabels.push( btn.textContent );
			btn.disabled = true;
		} );

		clickedButton.textContent = settings.i18n
			? settings.i18n.switching
			: 'Switching\u2026';

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', settings.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

		xhr.onload = function () {
			if ( xhr.status >= 200 && xhr.status < 300 ) {
				try {
					var response = JSON.parse( xhr.responseText );
					if ( response.success ) {
						updateUI( slug );
						// Reload so product grids pick up the new location filter.
						window.location.reload();
						return;
					}
				} catch ( e ) {
					// JSON parse error — fall through to error handling below.
				}
			}

			restoreButtons( buttons, originalLabels );
			// eslint-disable-next-line no-alert
			window.alert(
				settings.i18n
					? settings.i18n.error
					: 'Could not switch location. Please try again.'
			);
		};

		xhr.onerror = function () {
			restoreButtons( buttons, originalLabels );
			// eslint-disable-next-line no-alert
			window.alert(
				settings.i18n
					? settings.i18n.error
					: 'Could not switch location. Please try again.'
			);
		};

		xhr.send(
			'action=gym_set_location' +
			'&nonce=' + encodeURIComponent( settings.nonce || '' ) +
			'&location=' + encodeURIComponent( slug )
		);
	}

	// Delegate click events on location buttons.
	selector.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.gym-location-selector__button' );

		if ( ! button || button.disabled ) {
			return;
		}

		var slug = button.dataset.location;

		// Skip if the user clicked the already-active location.
		if ( slug === selector.dataset.currentLocation ) {
			return;
		}

		switchLocation( slug, button );
	} );

	// -----------------------------------------------------------------------
	// Location auto-detection for first-time visitors.
	//
	// Cascade (each step is silent — no user prompt until step 3):
	//   1. localStorage cache from a previous detection
	//   2. IP-based geolocation via free API (no prompt)
	//   3. Browser Geolocation API (shows permission prompt)
	//   4. Do nothing — visitor picks manually
	//
	// Once detected, the result is cached in localStorage for 24 hours
	// so repeat visits skip the lookup. Any manual selection via the
	// banner buttons overrides the auto-detection permanently (cookie).
	// -----------------------------------------------------------------------

	var GEO_CACHE_KEY = 'gym_detected_location';
	var GEO_CACHE_TTL = 86400000; // 24 hours in ms.

	/**
	 * Haversine distance between two lat/lng points (km).
	 */
	function haversineDistance( lat1, lng1, lat2, lng2 ) {
		var R = 6371;
		var dLat = ( lat2 - lat1 ) * Math.PI / 180;
		var dLng = ( lng2 - lng1 ) * Math.PI / 180;
		var a = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) +
			Math.cos( lat1 * Math.PI / 180 ) * Math.cos( lat2 * Math.PI / 180 ) *
			Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );
		return R * 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
	}

	/**
	 * Returns the closest location slug for a given coordinate pair.
	 */
	function findClosestLocation( lat, lng ) {
		var locations = settings.locations || {};
		var closest = null;
		var minDist = Infinity;

		Object.keys( locations ).forEach( function ( slug ) {
			var loc = locations[ slug ];
			var dist = haversineDistance( lat, lng, loc.lat, loc.lng );
			if ( dist < minDist ) {
				minDist = dist;
				closest = slug;
			}
		} );

		return closest;
	}

	/**
	 * Applies a detected location: updates the server cookie and reloads.
	 */
	function applyDetectedLocation( slug ) {
		// Cache in localStorage so we don't re-detect on next page load.
		try {
			localStorage.setItem( GEO_CACHE_KEY, JSON.stringify( {
				slug: slug,
				ts: Date.now(),
			} ) );
		} catch ( e ) {
			// Storage full or unavailable — continue without caching.
		}

		var btn = selector.querySelector(
			'.gym-location-selector__button[data-location="' + slug + '"]'
		);

		if ( btn ) {
			switchLocation( slug, btn );
		}
	}

	/**
	 * Step 3: Browser Geolocation API (requires permission prompt).
	 * Only called if IP geolocation failed.
	 */
	function detectViaBrowser() {
		if ( ! navigator.geolocation ) {
			return;
		}

		navigator.geolocation.getCurrentPosition(
			function ( position ) {
				var closest = findClosestLocation(
					position.coords.latitude,
					position.coords.longitude
				);

				if ( closest ) {
					applyDetectedLocation( closest );
				}
			},
			function () {
				// Denied or error — visitor picks manually.
			},
			{
				timeout: 5000,
				maximumAge: 300000,
				enableHighAccuracy: false,
			}
		);
	}

	/**
	 * Step 2: IP-based geolocation via free API (no prompt).
	 * Falls back to browser geolocation on failure.
	 */
	function detectViaIP() {
		// Use geojs.io — free, no API key, returns lat/lng from IP.
		var controller = new AbortController();
		var timeoutId = setTimeout( function () {
			controller.abort();
		}, 4000 );

		fetch( 'https://get.geojs.io/v1/ip/geo.json', {
			signal: controller.signal,
		} )
			.then( function ( response ) {
				clearTimeout( timeoutId );
				return response.json();
			} )
			.then( function ( data ) {
				var lat = parseFloat( data.latitude );
				var lng = parseFloat( data.longitude );

				if ( isNaN( lat ) || isNaN( lng ) ) {
					detectViaBrowser();
					return;
				}

				var closest = findClosestLocation( lat, lng );

				if ( closest ) {
					applyDetectedLocation( closest );
				} else {
					detectViaBrowser();
				}
			} )
			.catch( function () {
				clearTimeout( timeoutId );
				// IP lookup failed — fall back to browser geolocation.
				detectViaBrowser();
			} );
	}

	/**
	 * Entry point: runs auto-detection only when no location is set.
	 */
	function detectLocation() {
		if ( settings.current || ! settings.locations ) {
			return;
		}

		// Step 1: Check localStorage cache from a previous detection.
		try {
			var cached = localStorage.getItem( GEO_CACHE_KEY );
			if ( cached ) {
				var parsed = JSON.parse( cached );
				if ( parsed.slug && parsed.ts && ( Date.now() - parsed.ts ) < GEO_CACHE_TTL ) {
					applyDetectedLocation( parsed.slug );
					return;
				}
				// Expired — remove stale cache.
				localStorage.removeItem( GEO_CACHE_KEY );
			}
		} catch ( e ) {
			// localStorage unavailable — continue to network detection.
		}

		// Step 2: Try IP geolocation (silent, no prompt).
		detectViaIP();
	}

	detectLocation();
}() );
