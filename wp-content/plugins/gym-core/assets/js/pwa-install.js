/**
 * HMA PWA install-prompt handler.
 *
 * Shows a subtle install banner at the bottom of member portal pages
 * after the user has visited twice (page-view count stored in localStorage).
 *
 * Vanilla JS only — no jQuery.
 *
 * @since 5.0.0
 */

( function () {
	'use strict';

	var STORAGE_KEY  = 'hma_pwa_views';
	var BANNER_ID    = 'hma-pwa-banner';
	var THRESHOLD    = 2;

	/** @type {Event|null} */
	var deferredPrompt = null;

	// Capture the browser install prompt before it fires.
	window.addEventListener( 'beforeinstallprompt', function ( e ) {
		e.preventDefault();
		deferredPrompt = e;
		maybeShowBanner();
	} );

	// Track page views.
	var views = parseInt( localStorage.getItem( STORAGE_KEY ) || '0', 10 );
	views += 1;
	localStorage.setItem( STORAGE_KEY, String( views ) );

	/**
	 * Shows the install banner if conditions are met.
	 *
	 * Conditions:
	 *   - deferredPrompt is set (browser supports PWA install)
	 *   - user has reached the page-view threshold
	 *   - banner not already shown in this session
	 */
	function maybeShowBanner() {
		if ( ! deferredPrompt ) return;
		if ( views < THRESHOLD ) return;
		if ( document.getElementById( BANNER_ID ) ) return;

		var banner = buildBanner();
		document.body.appendChild( banner );

		// Slide in after next paint.
		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				banner.style.transform = 'translateY(0)';
			} );
		} );
	}

	/**
	 * Builds and returns the banner DOM element.
	 *
	 * @returns {HTMLElement}
	 */
	function buildBanner() {
		var banner = document.createElement( 'div' );
		banner.id = BANNER_ID;
		banner.setAttribute( 'role', 'complementary' );
		banner.setAttribute( 'aria-label', 'Install app prompt' );

		// Inline styles — keeps the banner self-contained, no theme CSS required.
		Object.assign( banner.style, {
			position:        'fixed',
			bottom:          '0',
			left:            '0',
			right:           '0',
			zIndex:          '99999',
			display:         'flex',
			alignItems:      'center',
			justifyContent:  'space-between',
			backgroundColor: '#0032A0',
			color:           '#ffffff',
			padding:         '0.875rem 1rem',
			fontFamily:      '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
			fontSize:        '0.9rem',
			transform:       'translateY(100%)',
			transition:      'transform 0.3s ease',
			boxShadow:       '0 -2px 8px rgba(0,0,0,0.4)',
		} );

		// Message text.
		var msg = document.createElement( 'span' );
		msg.textContent = 'Add Haanpaa MA to your home screen';
		Object.assign( msg.style, {
			flex:       '1',
			marginRight: '0.75rem',
			lineHeight:  '1.4',
		} );

		// Dismiss button.
		var dismiss = document.createElement( 'button' );
		dismiss.textContent = 'Dismiss';
		styleSecondaryButton( dismiss );
		dismiss.addEventListener( 'click', function () {
			removeBanner( banner );
		} );

		// Install button.
		var install = document.createElement( 'button' );
		install.textContent = 'Install';
		stylePrimaryButton( install );
		install.addEventListener( 'click', function () {
			if ( ! deferredPrompt ) return;
			deferredPrompt.prompt();
			deferredPrompt.userChoice.then( function ( choice ) {
				console.log( '[HMA PWA] Install outcome:', choice.outcome );
				deferredPrompt = null;
				removeBanner( banner );
			} );
		} );

		banner.appendChild( msg );
		banner.appendChild( dismiss );
		banner.appendChild( install );

		return banner;
	}

	/**
	 * Slides the banner out and removes it from the DOM.
	 *
	 * @param {HTMLElement} banner
	 */
	function removeBanner( banner ) {
		banner.style.transform = 'translateY(100%)';
		setTimeout( function () {
			if ( banner.parentNode ) {
				banner.parentNode.removeChild( banner );
			}
		}, 320 );
	}

	/**
	 * Applies shared button base styles.
	 *
	 * @param {HTMLButtonElement} btn
	 */
	function styleBaseButton( btn ) {
		Object.assign( btn.style, {
			border:        'none',
			cursor:        'pointer',
			fontFamily:    'inherit',
			fontSize:      '0.875rem',
			fontWeight:    '600',
			padding:       '0.5rem 0.875rem',
			borderRadius:  '4px',
			whiteSpace:    'nowrap',
			letterSpacing: '0.02em',
		} );
	}

	/**
	 * @param {HTMLButtonElement} btn
	 */
	function styleSecondaryButton( btn ) {
		styleBaseButton( btn );
		Object.assign( btn.style, {
			backgroundColor: 'transparent',
			color:           'rgba(255,255,255,0.8)',
			marginRight:     '0.5rem',
		} );
	}

	/**
	 * @param {HTMLButtonElement} btn
	 */
	function stylePrimaryButton( btn ) {
		styleBaseButton( btn );
		Object.assign( btn.style, {
			backgroundColor: '#ffffff',
			color:           '#0032A0',
		} );
	}
}() );
