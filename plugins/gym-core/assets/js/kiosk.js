/**
 * Kiosk Check-In App
 *
 * Touch-optimized check-in flow for the tablet kiosk at each gym location.
 * Flow: Search member → Select class → Check in → Success (auto-reset).
 *
 * Uses the gym/v1 REST API:
 *   GET  /gym/v1/schedule?location={slug}  — today's classes
 *   POST /gym/v1/check-in                  — record check-in
 *
 * Member search uses the WordPress REST API:
 *   GET  /wp/v2/users?search={query}&roles=customer,subscriber
 *
 * @package Gym_Core
 * @since   1.3.0
 */

( function () {
	'use strict';

	var config = window.gymKiosk || {};
	var resetTimer = null;
	var selectedMember = null;

	// DOM references.
	var screens = {
		search:  document.getElementById( 'kiosk-search' ),
		classes: document.getElementById( 'kiosk-classes' ),
		success: document.getElementById( 'kiosk-success' ),
		error:   document.getElementById( 'kiosk-error' ),
	};
	var searchInput   = document.getElementById( 'kiosk-search-input' );
	var resultsBox    = document.getElementById( 'kiosk-results' );
	var classList     = document.getElementById( 'kiosk-class-list' );
	var memberNameEl  = document.getElementById( 'kiosk-member-name' );
	var welcomeMsg    = document.getElementById( 'kiosk-welcome-msg' );
	var rankDisplay   = document.getElementById( 'kiosk-rank-display' );
	var errorMsg      = document.getElementById( 'kiosk-error-msg' );
	var loadingEl     = document.getElementById( 'kiosk-loading' );
	var backBtn       = document.getElementById( 'kiosk-back' );
	var retryBtn      = document.getElementById( 'kiosk-retry' );

	// -----------------------------------------------------------------------
	// Screen management
	// -----------------------------------------------------------------------

	function showScreen( name ) {
		Object.keys( screens ).forEach( function ( key ) {
			screens[ key ].classList.toggle( 'active', key === name );
		} );
	}

	function showLoading() {
		loadingEl.classList.add( 'active' );
	}

	function hideLoading() {
		loadingEl.classList.remove( 'active' );
	}

	function resetToSearch() {
		clearTimeout( resetTimer );
		selectedMember = null;
		searchInput.value = '';
		resultsBox.innerHTML = '';
		classList.innerHTML = '';
		showScreen( 'search' );
		searchInput.focus();
	}

	function scheduleReset() {
		clearTimeout( resetTimer );
		resetTimer = setTimeout( resetToSearch, ( config.timeout || 10 ) * 1000 );
	}

	// -----------------------------------------------------------------------
	// API helpers
	// -----------------------------------------------------------------------

	function apiFetch( endpoint, options ) {
		var url = config.restUrl + endpoint;
		var opts = Object.assign( {
			headers: {
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json',
			},
		}, options || {} );

		return fetch( url, opts ).then( function ( response ) {
			return response.json().then( function ( data ) {
				return { status: response.status, body: data };
			} );
		} );
	}

	function wpApiFetch( endpoint ) {
		var url = config.restUrl.replace( 'gym/v1/', '' ) + endpoint;
		return fetch( url, {
			headers: { 'X-WP-Nonce': config.nonce },
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	// -----------------------------------------------------------------------
	// Member search
	// -----------------------------------------------------------------------

	var searchDebounce = null;

	function handleSearchInput() {
		clearTimeout( searchDebounce );
		var query = searchInput.value.trim();

		if ( query.length < 2 ) {
			resultsBox.innerHTML = '';
			return;
		}

		searchDebounce = setTimeout( function () {
			searchMembers( query );
		}, 300 );
	}

	function searchMembers( query ) {
		wpApiFetch( 'wp/v2/users?search=' + encodeURIComponent( query ) + '&per_page=10&context=view' )
			.then( function ( users ) {
				renderSearchResults( users );
			} )
			.catch( function () {
				resultsBox.innerHTML = '<div class="kiosk-no-results">' +
					escapeHtml( config.strings.error || 'Search failed' ) + '</div>';
			} );
	}

	function renderSearchResults( users ) {
		if ( ! users || users.length === 0 ) {
			resultsBox.innerHTML = '<div class="kiosk-no-results">' +
				escapeHtml( config.strings.noResults || 'No members found' ) + '</div>';
			return;
		}

		var html = '';
		users.forEach( function ( user ) {
			html += '<button type="button" class="kiosk-result-item" ' +
				'data-user-id="' + user.id + '" ' +
				'data-user-name="' + escapeAttr( user.name ) + '" ' +
				'role="option">' +
				'<span class="kiosk-result-name">' + escapeHtml( user.name ) + '</span>' +
				'</button>';
		} );

		resultsBox.innerHTML = html;
	}

	function handleMemberSelect( event ) {
		var item = event.target.closest( '.kiosk-result-item' );
		if ( ! item ) {
			return;
		}

		selectedMember = {
			id:   parseInt( item.dataset.userId, 10 ),
			name: item.dataset.userName,
		};

		memberNameEl.textContent = selectedMember.name;
		loadTodaysClasses();
	}

	// -----------------------------------------------------------------------
	// Class selection
	// -----------------------------------------------------------------------

	function loadTodaysClasses() {
		showLoading();

		apiFetch( 'schedule?location=' + encodeURIComponent( config.location ) )
			.then( function ( result ) {
				hideLoading();

				if ( result.status !== 200 || ! result.body.success ) {
					showError( 'Could not load class schedule.' );
					return;
				}

				var today = new Date().toLocaleDateString( 'en-CA' ); // YYYY-MM-DD
				var todayClasses = [];

				result.body.data.forEach( function ( day ) {
					if ( day.date === today ) {
						todayClasses = day.classes;
					}
				} );

				if ( todayClasses.length === 0 ) {
					// No classes today — check in to "Open Mat" (class_id = 0).
					performCheckIn( 0 );
					return;
				}

				renderClassList( todayClasses );
				showScreen( 'classes' );
			} )
			.catch( function () {
				hideLoading();
				showError( 'Could not load class schedule.' );
			} );
	}

	function renderClassList( classes ) {
		var html = '';
		classes.forEach( function ( cls ) {
			html += '<button type="button" class="kiosk-class-item" ' +
				'data-class-id="' + cls.id + '" role="option">' +
				'<span class="kiosk-class-name">' + escapeHtml( cls.name ) + '</span>' +
				'<span class="kiosk-class-time">' + escapeHtml( cls.start_time + ' – ' + cls.end_time ) + '</span>' +
				'</button>';
		} );

		classList.innerHTML = html;
	}

	function handleClassSelect( event ) {
		var item = event.target.closest( '.kiosk-class-item' );
		if ( ! item ) {
			return;
		}

		var classId = parseInt( item.dataset.classId, 10 );
		performCheckIn( classId );
	}

	// -----------------------------------------------------------------------
	// Check-in
	// -----------------------------------------------------------------------

	function performCheckIn( classId ) {
		showLoading();

		apiFetch( 'check-in', {
			method: 'POST',
			body: JSON.stringify( {
				user_id:  selectedMember.id,
				class_id: classId,
				method:   'name_search',
				location: config.location,
			} ),
		} )
			.then( function ( result ) {
				hideLoading();

				if ( result.status === 201 && result.body.success ) {
					showSuccess( result.body.data );
				} else {
					var msg = result.body.message || result.body.data?.message || 'Check-in failed.';
					showError( msg );
				}
			} )
			.catch( function () {
				hideLoading();
				showError( 'Network error. Please try again.' );
			} );
	}

	// -----------------------------------------------------------------------
	// Success / Error screens
	// -----------------------------------------------------------------------

	function showSuccess( data ) {
		welcomeMsg.textContent = ( config.strings.welcomeBack || 'Welcome back,' ) +
			' ' + ( data.user ? data.user.name : selectedMember.name ) + '!';

		// Fetch rank for display.
		apiFetch( 'members/' + selectedMember.id + '/rank' )
			.then( function ( result ) {
				if ( result.status === 200 && result.body.success && result.body.data.length > 0 ) {
					var ranks = result.body.data.map( function ( r ) {
						return r.belt + ( r.stripes > 0 ? ' (' + r.stripes + ' stripes)' : '' ) +
							' — ' + r.program;
					} );
					rankDisplay.textContent = ranks.join( ' | ' );
				} else {
					rankDisplay.textContent = '';
				}
			} )
			.catch( function () {
				rankDisplay.textContent = '';
			} );

		showScreen( 'success' );
		scheduleReset();
	}

	function showError( message ) {
		errorMsg.textContent = message;
		showScreen( 'error' );
		scheduleReset();
	}

	// -----------------------------------------------------------------------
	// Utility
	// -----------------------------------------------------------------------

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	function escapeAttr( str ) {
		return str.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	// -----------------------------------------------------------------------
	// Event binding
	// -----------------------------------------------------------------------

	function init() {
		if ( ! searchInput ) {
			return;
		}

		searchInput.addEventListener( 'input', handleSearchInput );
		resultsBox.addEventListener( 'click', handleMemberSelect );
		classList.addEventListener( 'click', handleClassSelect );
		backBtn.addEventListener( 'click', resetToSearch );
		retryBtn.addEventListener( 'click', resetToSearch );

		// Auto-focus the search input.
		searchInput.focus();

		// Reset on any touch after success/error (in addition to the timer).
		document.addEventListener( 'touchstart', function () {
			var successActive = screens.success.classList.contains( 'active' );
			var errorActive = screens.error.classList.contains( 'active' );
			if ( successActive || errorActive ) {
				resetToSearch();
			}
		} );
	}

	// Start when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
