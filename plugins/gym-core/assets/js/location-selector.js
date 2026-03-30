/**
 * Gym Location Selector
 *
 * Handles location switching from the frontend banner. On selection, fires an
 * AJAX request to update the server-side cookie (and user meta for logged-in
 * users), then reloads the page so product grids reflect the new location filter.
 *
 * Data is passed from PHP via wp_localize_script under window.gymLocation:
 *   ajaxUrl  {string} — wp-admin/admin-ajax.php URL
 *   nonce    {string} — gym_location_nonce value
 *   current  {string} — currently active location slug
 *   i18n     {object} — translated strings
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
}() );
