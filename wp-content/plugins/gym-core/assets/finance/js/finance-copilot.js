/**
 * Finance Copilot — admin page client.
 *
 * Vanilla JS (no jQuery, per gym-core conventions). Wires up the NLP query
 * box, AR aging table, recovery queue widget, and monthly close button to
 * the gym/v1/finance/* REST endpoints.
 *
 * @package Gym_Core
 */

( function () {
	'use strict';

	if ( typeof window === 'undefined' || typeof window.gymCoreFinanceCopilot === 'undefined' ) {
		return;
	}

	const config = window.gymCoreFinanceCopilot;
	const apiFetch = window.wp && window.wp.apiFetch ? window.wp.apiFetch : null;

	if ( ! apiFetch ) {
		return;
	}

	const dollars = ( amount, currency ) => {
		try {
			return new Intl.NumberFormat( 'en-US', {
				style: 'currency',
				currency: currency || 'USD',
			} ).format( Number( amount || 0 ) );
		} catch ( err ) {
			return '$' + Number( amount || 0 ).toFixed( 2 );
		}
	};

	const escapeHtml = ( str ) => {
		if ( str === null || str === undefined ) {
			return '';
		}
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	};

	const bucketLabel = ( key ) => {
		switch ( key ) {
			case '0_30':
				return '0-30 days';
			case '31_60':
				return '31-60 days';
			case '61_90':
				return '61-90 days';
			case '90_plus':
				return '90+ days';
			default:
				return key;
		}
	};

	/**
	 * AR aging — load and render.
	 */
	const renderArAging = async () => {
		const root = document.querySelector( '[data-role="ar-aging"]' );
		if ( ! root ) {
			return;
		}

		try {
			const response = await apiFetch( { path: config.endpoints.arAging } );
			const data = response && response.data ? response.data : null;

			if ( ! data || ! data.rows || data.rows.length === 0 ) {
				root.innerHTML = '<p class="gym-finance-copilot__empty">' + escapeHtml( config.i18n.noReceivables ) + '</p>';
				return;
			}

			const totalsHtml = [ '0_30', '31_60', '61_90', '90_plus' ]
				.map( ( bucket ) => {
					const totals = data.totals && data.totals[ bucket ] ? data.totals[ bucket ] : { count: 0, amount: 0 };
					return (
						'<div class="gym-finance-copilot__total gym-finance-copilot__total--' + bucket + '">' +
							'<div class="gym-finance-copilot__total-label">' + escapeHtml( bucketLabel( bucket ) ) + '</div>' +
							'<div class="gym-finance-copilot__total-value">' + escapeHtml( dollars( totals.amount, data.currency ) ) + '</div>' +
							'<div class="gym-finance-copilot__total-meta">' + escapeHtml( totals.count ) + ' open</div>' +
						'</div>'
					);
				} )
				.join( '' );

			const rowsHtml = data.rows
				.map(
					( row ) =>
						'<tr>' +
							'<td>' + escapeHtml( row.customer_name ) + '</td>' +
							'<td>' + escapeHtml( dollars( row.total, row.currency ) ) + '</td>' +
							'<td>' + escapeHtml( row.date_created ) + '</td>' +
							'<td><span class="gym-finance-copilot__bucket-tag gym-finance-copilot__bucket-tag--' + escapeHtml( row.bucket ) + '">' + escapeHtml( bucketLabel( row.bucket ) ) + '</span></td>' +
							'<td><button class="gym-finance-copilot__draft-btn" data-role="draft-outreach" data-order-id="' + escapeHtml( row.order_id ) + '">' + escapeHtml( config.i18n.draftOutreach ) + '</button></td>' +
						'</tr>'
				)
				.join( '' );

			root.innerHTML =
				'<div class="gym-finance-copilot__totals">' + totalsHtml + '</div>' +
				'<table>' +
					'<thead><tr>' +
						'<th>Customer</th>' +
						'<th>Amount</th>' +
						'<th>Issued</th>' +
						'<th>Bucket</th>' +
						'<th></th>' +
					'</tr></thead>' +
					'<tbody>' + rowsHtml + '</tbody>' +
				'</table>';

			root.querySelectorAll( '[data-role="draft-outreach"]' ).forEach( ( btn ) => {
				btn.addEventListener( 'click', handleDraftOutreach );
			} );
		} catch ( err ) {
			root.innerHTML = '<p class="gym-finance-copilot__feedback gym-finance-copilot__feedback--error">' + escapeHtml( ( err && err.message ) || 'Failed to load AR aging.' ) + '</p>';
		}
	};

	/**
	 * Recovery queue — load and render.
	 */
	const renderRecoveryQueue = async () => {
		const root = document.querySelector( '[data-role="recovery-queue"]' );
		if ( ! root ) {
			return;
		}

		try {
			const response = await apiFetch( { path: config.endpoints.recoveryQueue } );
			const data = response && response.data ? response.data : null;

			if ( ! data || ! data.rows || data.rows.length === 0 ) {
				root.innerHTML = '<p class="gym-finance-copilot__empty">' + escapeHtml( config.i18n.noRecovery ) + '</p>';
				return;
			}

			const rowsHtml = data.rows
				.map(
					( row ) =>
						'<tr>' +
							'<td>#' + escapeHtml( row.order_id ) + '</td>' +
							'<td>' + escapeHtml( row.customer_name ) + '</td>' +
							'<td>' + escapeHtml( dollars( row.amount, row.currency ) ) + '</td>' +
							'<td>' + escapeHtml( row.last_attempt || '—' ) + '</td>' +
							'<td>' + escapeHtml( row.next_attempt || '—' ) + '</td>' +
						'</tr>'
				)
				.join( '' );

			root.innerHTML =
				'<table>' +
					'<thead><tr>' +
						'<th>Order</th>' +
						'<th>Customer</th>' +
						'<th>Amount</th>' +
						'<th>Last attempt</th>' +
						'<th>Next attempt</th>' +
					'</tr></thead>' +
					'<tbody>' + rowsHtml + '</tbody>' +
				'</table>';
		} catch ( err ) {
			root.innerHTML = '<p class="gym-finance-copilot__feedback gym-finance-copilot__feedback--error">' + escapeHtml( ( err && err.message ) || 'Failed to load recovery queue.' ) + '</p>';
		}
	};

	/**
	 * Draft outreach button click handler.
	 */
	const handleDraftOutreach = async ( event ) => {
		const btn = event.currentTarget;
		const orderId = parseInt( btn.getAttribute( 'data-order-id' ), 10 );
		if ( ! orderId ) {
			return;
		}

		btn.disabled = true;
		const original = btn.textContent;
		btn.textContent = '…';

		try {
			await apiFetch( {
				path: config.endpoints.dunningDraft,
				method: 'POST',
				data: { order_id: orderId, tone: 'gentle' },
			} );

			btn.textContent = original;
			showFeedback( btn, config.i18n.queued, false );
		} catch ( err ) {
			btn.textContent = original;
			btn.disabled = false;
			showFeedback( btn, ( err && err.message ) || 'Failed to draft message.', true );
		}
	};

	const showFeedback = ( anchor, message, isError ) => {
		const cell = anchor.closest( 'td' );
		if ( ! cell ) {
			return;
		}
		const feedback = document.createElement( 'div' );
		feedback.className = 'gym-finance-copilot__feedback' + ( isError ? ' gym-finance-copilot__feedback--error' : '' );
		feedback.textContent = message;
		cell.appendChild( feedback );
	};

	/**
	 * Ask Pippin form handler.
	 *
	 * Calls the NLP endpoint when one is wired (handled by hma-ai-chat); when
	 * the endpoint isn't available, falls back to a deterministic structured
	 * answer derived from the AR aging data so Joy still gets useful output.
	 */
	const wireAskForm = () => {
		const form = document.querySelector( '[data-role="ask-form"]' );
		const input = document.querySelector( '[data-role="ask-input"]' );
		const answer = document.querySelector( '[data-role="ask-answer"]' );

		if ( ! form || ! input || ! answer ) {
			return;
		}

		input.placeholder = config.i18n.askPlaceholder;

		form.addEventListener( 'submit', async ( ev ) => {
			ev.preventDefault();
			const question = input.value.trim();
			if ( '' === question ) {
				return;
			}

			answer.innerHTML = '<p class="gym-finance-copilot__loading">' + escapeHtml( 'Thinking…' ) + '</p>';

			try {
				const response = await apiFetch( {
					path: config.endpoints.nlpQuery,
					method: 'POST',
					data: { question },
				} );

				renderAnswer( answer, response && response.data ? response.data : response );
			} catch ( err ) {
				// Fallback when the NLP endpoint isn't registered (hma-ai-chat absent).
				answer.innerHTML =
					'<div class="gym-finance-copilot__answer-narrative">' +
					escapeHtml(
						'NLP endpoint not available right now. Try the AR aging table or run the monthly close — or wire the hma-ai-chat plugin to enable Pippin.'
					) +
					'</div>';
			}
		} );
	};

	const renderAnswer = ( root, data ) => {
		if ( ! data ) {
			root.innerHTML = '<p class="gym-finance-copilot__empty">No answer.</p>';
			return;
		}

		const narrative = data.narrative || data.answer || '';
		const series = Array.isArray( data.series ) ? data.series : [];

		let html = '';
		if ( narrative ) {
			html += '<div class="gym-finance-copilot__answer-narrative">' + escapeHtml( narrative ) + '</div>';
		}

		if ( series.length > 0 ) {
			const max = series.reduce( ( acc, point ) => Math.max( acc, Number( point.value ) || 0 ), 0 );
			const barsHtml = series
				.map( ( point ) => {
					const height = max > 0 ? Math.round( ( ( Number( point.value ) || 0 ) / max ) * 100 ) : 0;
					return (
						'<div class="gym-finance-copilot__chart-col">' +
						'<div class="gym-finance-copilot__chart-bar" style="height:' + height + '%" title="' + escapeHtml( dollars( point.value, data.currency || 'USD' ) ) + '"></div>' +
						'<div class="gym-finance-copilot__chart-label">' + escapeHtml( point.label ) + '</div>' +
						'</div>'
					);
				} )
				.join( '' );

			html += '<div class="gym-finance-copilot__chart">' + barsHtml + '</div>';
		}

		root.innerHTML = html || '<p class="gym-finance-copilot__empty">No structured answer returned.</p>';
	};

	/**
	 * Monthly close — wire the run button.
	 */
	const wireCloseButton = () => {
		const btn = document.querySelector( '[data-role="close-run"]' );
		const monthInput = document.querySelector( '[data-role="close-month"]' );
		const result = document.querySelector( '[data-role="close-result"]' );

		if ( ! btn || ! monthInput || ! result ) {
			return;
		}

		btn.addEventListener( 'click', async () => {
			const month = monthInput.value;
			if ( ! month ) {
				return;
			}

			btn.disabled = true;
			result.innerHTML = '<p class="gym-finance-copilot__loading">Running…</p>';

			try {
				const response = await apiFetch( {
					path: config.endpoints.closeRun + '/' + encodeURIComponent( month ) + '/run',
					method: 'POST',
					data: { force: false },
				} );

				const payload = response && response.data ? response.data : response;
				renderCloseResult( result, payload );
			} catch ( err ) {
				result.innerHTML = '<p class="gym-finance-copilot__feedback gym-finance-copilot__feedback--error">' + escapeHtml( ( err && err.message ) || 'Close failed.' ) + '</p>';
			} finally {
				btn.disabled = false;
			}
		} );
	};

	const renderCloseResult = ( root, payload ) => {
		if ( ! payload || ! payload.steps ) {
			root.innerHTML = '<p class="gym-finance-copilot__empty">No close result.</p>';
			return;
		}

		let html = '';
		if ( payload.from_cache ) {
			html += '<p class="gym-finance-copilot__feedback">' + escapeHtml( 'Already closed for this month — re-running is safe.' ) + '</p>';
		}

		html += Object.keys( payload.steps )
			.map( ( stepKey ) => {
				const step = payload.steps[ stepKey ];
				const isError = step && step.status && step.status !== 'ok' && step.status !== 'skipped';
				return (
					'<div class="gym-finance-copilot__close-step' + ( isError ? ' gym-finance-copilot__close-step--error' : '' ) + '">' +
					'<strong>' + escapeHtml( stepKey ) + ':</strong> ' +
					escapeHtml( JSON.stringify( step ) ) +
					'</div>'
				);
			} )
			.join( '' );

		root.innerHTML = html;
	};

	const init = () => {
		renderArAging();
		renderRecoveryQueue();
		wireAskForm();
		wireCloseButton();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
