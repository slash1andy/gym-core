/**
 * Promotion Eligibility Dashboard — AJAX handlers.
 *
 * Handles inline recommend and promote actions from the list table.
 *
 * @package Gym_Core
 * @since   2.1.0
 */

/* global jQuery, gymPromotion */
( function ( $ ) {
	'use strict';

	/**
	 * Sends an AJAX action for a single member row.
	 *
	 * @param {HTMLElement} button  The clicked button element.
	 * @param {string}      action  WordPress AJAX action name.
	 */
	function handleAction( button, action ) {
		var $btn = $( button );
		var $row = $btn.closest( 'tr' );

		if ( $btn.prop( 'disabled' ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( gymPromotion.i18n.processing );

		$.post( gymPromotion.ajaxUrl, {
			action:  action,
			user_id: $btn.data( 'user-id' ),
			program: $btn.data( 'program' ),
			_wpnonce: gymPromotion.nonce
		} )
			.done( function ( response ) {
				if ( response.success ) {
					$row.css( 'background-color', '#e7f5e7' );
					$btn.text( response.data.message );

					// If this was a recommend action, update the recommendation column.
					if ( 'gym_recommend_student' === action ) {
						$row.find( '.column-recommendation' ).html(
							'<span class="dashicons dashicons-yes-alt" style="color:#46b450;" aria-label="' +
							gymPromotion.i18n.recommended + '"></span>'
						);
					}

					// If promoted, fade the row after a short delay.
					if ( 'gym_promote_student' === action ) {
						setTimeout( function () {
							$row.fadeOut( 400, function () {
								$( this ).remove();
							} );
						}, 1500 );
					}
				} else {
					$btn.prop( 'disabled', false ).text( $btn.data( 'original-text' ) );
					/* translators: %s: error message */
					window.alert( response.data && response.data.message ? response.data.message : gymPromotion.i18n.error );
				}
			} )
			.fail( function () {
				$btn.prop( 'disabled', false ).text( $btn.data( 'original-text' ) );
				window.alert( gymPromotion.i18n.error );
			} );
	}

	$( document ).ready( function () {
		// Store original button text for reset on error.
		$( '.gym-recommend-btn, .gym-promote-btn' ).each( function () {
			$( this ).data( 'original-text', $( this ).text() );
		} );

		// Recommend button handler.
		$( document ).on( 'click', '.gym-recommend-btn', function ( e ) {
			e.preventDefault();
			handleAction( this, 'gym_recommend_student' );
		} );

		// Promote button handler.
		$( document ).on( 'click', '.gym-promote-btn', function ( e ) {
			e.preventDefault();
			handleAction( this, 'gym_promote_student' );
		} );
	} );
} )( jQuery );
