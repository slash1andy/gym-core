/**
 * Sales Kiosk App
 *
 * Touch-optimized membership sales flow for tablet use by sales staff.
 * Flow: Select product -> Customize pricing -> Customer info -> Review -> Payment.
 *
 * Uses the gym/v1/sales REST API:
 *   GET  /gym/v1/sales/products       - List membership products
 *   POST /gym/v1/sales/calculate      - Dynamic pricing
 *   GET  /gym/v1/sales/customer       - Customer search
 *   POST /gym/v1/sales/order          - Create order
 *   POST /gym/v1/sales/lead           - Save lead
 *
 * @package Gym_Core
 * @since   4.0.0
 */

( function () {
	'use strict';

	var config = window.gymSalesKiosk || {};
	var resetTimer = null;

	// App state.
	var state = {
		products: [],
		selectedProduct: null,
		pricing: null,
		customer: {},
	};

	// DOM references.
	var screens = {
		products:  document.getElementById( 'sales-products' ),
		pricing:   document.getElementById( 'sales-pricing' ),
		customer:  document.getElementById( 'sales-customer' ),
		review:    document.getElementById( 'sales-review' ),
		success:   document.getElementById( 'sales-success' ),
		leadSaved: document.getElementById( 'sales-lead-saved' ),
		error:     document.getElementById( 'sales-error' ),
	};

	var productGrid      = document.getElementById( 'sales-product-grid' );
	var productNameEl    = document.getElementById( 'sales-product-name' );
	var downSlider       = document.getElementById( 'sales-down-slider' );
	var downDisplay      = document.getElementById( 'sales-down-display' );
	var priceDown        = document.getElementById( 'sales-price-down' );
	var priceRecurring   = document.getElementById( 'sales-price-recurring' );
	var priceTotal       = document.getElementById( 'sales-price-total' );
	var priceSavings     = document.getElementById( 'sales-price-savings' );
	var savingsRow       = document.getElementById( 'sales-savings-row' );
	var customerSearch   = document.getElementById( 'sales-customer-search-input' );
	var customerResults  = document.getElementById( 'sales-customer-results' );
	var customerForm     = document.getElementById( 'sales-customer-form' );
	var reviewProduct    = document.getElementById( 'review-product-name' );
	var reviewPricing    = document.getElementById( 'review-pricing' );
	var reviewCustomer   = document.getElementById( 'review-customer' );
	var successTitle     = document.getElementById( 'sales-success-title' );
	var successMsg       = document.getElementById( 'sales-success-msg' );
	var leadMsg          = document.getElementById( 'sales-lead-msg' );
	var errorMsg         = document.getElementById( 'sales-error-msg' );
	var loadingEl        = document.getElementById( 'sales-loading' );

	// -----------------------------------------------------------------------
	// Screen management
	// -----------------------------------------------------------------------

	function showScreen( name ) {
		Object.keys( screens ).forEach( function ( key ) {
			if ( screens[ key ] ) {
				screens[ key ].classList.toggle( 'active', key === name );
			}
		} );
	}

	function showLoading() {
		loadingEl.classList.add( 'active' );
	}

	function hideLoading() {
		loadingEl.classList.remove( 'active' );
	}

	function resetApp() {
		clearTimeout( resetTimer );
		state.selectedProduct = null;
		state.pricing = null;
		state.customer = {};
		customerSearch.value = '';
		customerResults.innerHTML = '';
		customerResults.classList.remove( 'active' );
		customerForm.reset();
		showScreen( 'products' );
	}

	function scheduleReset() {
		clearTimeout( resetTimer );
		resetTimer = setTimeout( resetApp, ( config.timeout || 10 ) * 1000 );
	}

	// -----------------------------------------------------------------------
	// API helpers
	// -----------------------------------------------------------------------

	function fetchWithTimeout( url, opts ) {
		var controller = new AbortController();
		var timeoutId = setTimeout( function () {
			controller.abort();
		}, 15000 );

		opts.signal = controller.signal;

		return fetch( url, opts )
			.then( function ( response ) {
				clearTimeout( timeoutId );
				return response;
			} )
			.catch( function ( err ) {
				clearTimeout( timeoutId );
				if ( err.name === 'AbortError' ) {
					throw new Error( 'Request timed out' );
				}
				throw err;
			} );
	}

	function apiFetch( endpoint, options ) {
		var url = config.restUrl + endpoint;
		var opts = Object.assign( {
			headers: {
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json',
			},
		}, options || {} );

		return fetchWithTimeout( url, opts ).then( function ( response ) {
			return response.json().then( function ( data ) {
				return { status: response.status, body: data };
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Formatting
	// -----------------------------------------------------------------------

	function formatCurrency( amount ) {
		return '$' + Number( amount ).toFixed( 2 );
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str || '' ) );
		return div.innerHTML;
	}

	function escapeAttr( str ) {
		return ( str || '' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	// -----------------------------------------------------------------------
	// Screen 1: Product Selection
	// -----------------------------------------------------------------------

	function loadProducts() {
		showLoading();
		var endpoint = 'sales/products';
		if ( config.location ) {
			endpoint += '?location=' + encodeURIComponent( config.location );
		}

		apiFetch( endpoint )
			.then( function ( result ) {
				hideLoading();
				if ( result.status === 200 && result.body.success ) {
					state.products = result.body.data;
					renderProductGrid( state.products );
				} else {
					showError( result.body.message || 'Failed to load products.' );
				}
			} )
			.catch( function ( err ) {
				hideLoading();
				showError( err.message || 'Failed to load products.' );
			} );
	}

	function renderProductGrid( products ) {
		if ( ! products || products.length === 0 ) {
			productGrid.innerHTML = '<div class="sales-no-products">' +
				escapeHtml( config.strings.noProducts ) + '</div>';
			return;
		}

		// Group by first category.
		var preferredOrder = [ 'Adult BJJ', 'Adult Kickboxing', 'Kids BJJ', 'Trials' ];
		var hiddenGroups = [ 'Other' ];
		var groups = {};
		products.forEach( function ( product ) {
			var category = product.categories && product.categories.length > 0
				? product.categories[0]
				: 'Other';
			if ( hiddenGroups.indexOf( category ) !== -1 ) {
				return;
			}
			if ( ! groups[ category ] ) {
				groups[ category ] = [];
			}
			groups[ category ].push( product );
		} );

		// Sort groups: preferred order first, then any remaining alphabetically.
		var groupOrder = preferredOrder.filter( function ( c ) { return groups[ c ]; } );
		Object.keys( groups ).forEach( function ( c ) {
			if ( groupOrder.indexOf( c ) === -1 ) {
				groupOrder.push( c );
			}
		} );

		var chevronSvg = '<span class="sales-accordion-chevron">' +
			'<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg>' +
			'</span>';

		var html = '';
		groupOrder.forEach( function ( category, index ) {
			var isFirst = index === 0;
			html += '<div class="sales-accordion-group' + ( isFirst ? ' open' : '' ) + '">';
			html += '<button type="button" class="sales-accordion-header" ' +
				'aria-expanded="' + ( isFirst ? 'true' : 'false' ) + '">' +
				escapeHtml( category ) + chevronSvg + '</button>';
			html += '<div class="sales-accordion-body">';
			html += '<div class="sales-accordion-products">';

			groups[ category ].forEach( function ( product ) {
				var pricing = product.pricing || {};
				var priceHtml;

				if ( pricing.configured ) {
					var period = product.billing_period === 'week' && product.billing_interval === 2
						? ( config.strings.perTwoWeeks || '/2wk' )
						: ( config.strings.perMonth || '/mo' );
					priceHtml = '<span class="sales-product-card__price">' +
						formatCurrency( product.subscription_price ) + '</span>' +
						'<span class="sales-product-card__period">' + escapeHtml( period ) + '</span>';
				} else {
					priceHtml = '<span class="sales-product-card__not-configured">Pricing not configured</span>';
				}

				html += '<button type="button" class="sales-product-card" data-product-id="' + product.id + '">' +
					'<p class="sales-product-card__category">' + escapeHtml( product.categories ? product.categories.join( ', ' ) : '' ) + '</p>' +
					'<h3 class="sales-product-card__name">' + escapeHtml( product.name ) + '</h3>' +
					'<div class="sales-product-card__pricing">' + priceHtml + '</div>' +
					'</button>';
			} );

			html += '</div></div></div>';
		} );

		productGrid.innerHTML = html;

		// Bind accordion toggle.
		productGrid.querySelectorAll( '.sales-accordion-header' ).forEach( function ( header ) {
			header.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				var group = header.closest( '.sales-accordion-group' );
				var isOpen = group.classList.contains( 'open' );
				group.classList.toggle( 'open', ! isOpen );
				header.setAttribute( 'aria-expanded', ! isOpen ? 'true' : 'false' );
			} );
		} );
	}

	function handleProductSelect( event ) {
		var card = event.target.closest( '.sales-product-card' );
		if ( ! card ) {
			return;
		}

		var productId = parseInt( card.dataset.productId, 10 );
		state.selectedProduct = state.products.find( function ( p ) {
			return p.id === productId;
		} );

		if ( ! state.selectedProduct ) {
			return;
		}

		var pricing = state.selectedProduct.pricing;

		// If pricing is not configured, skip slider and go to customer info.
		if ( ! pricing.configured ) {
			state.pricing = {
				down_payment:     0,
				recurring_payment: state.selectedProduct.subscription_price,
				effective_total:  state.selectedProduct.subscription_price,
				discount:         0,
				billing_label:    state.selectedProduct.billing_period === 'week' &&
					state.selectedProduct.billing_interval === 2
					? ( config.strings.biweekly || 'Every 2 Weeks' )
					: ( config.strings.monthly || 'Monthly' ),
			};
			showScreen( 'customer' );
			return;
		}

		// Set up pricing screen.
		productNameEl.textContent = state.selectedProduct.name;
		downSlider.min = Math.floor( pricing.min_down );
		downSlider.max = Math.floor( pricing.max_down );
		downSlider.value = Math.floor( pricing.min_down );

		// Trigger initial calculation.
		updatePricingDisplay();
		showScreen( 'pricing' );
	}

	// -----------------------------------------------------------------------
	// Screen 2: Pricing
	// -----------------------------------------------------------------------

	var calcDebounce = null;

	function updatePricingDisplay() {
		var downValue = parseInt( downSlider.value, 10 );
		downDisplay.textContent = formatCurrency( downValue );
		priceDown.textContent = formatCurrency( downValue );

		// Debounced server calculation.
		clearTimeout( calcDebounce );
		calcDebounce = setTimeout( function () {
			calculatePricing( downValue );
		}, 300 );

		// Optimistic client-side calculation for instant feedback.
		var pricing = state.selectedProduct.pricing;
		var range = pricing.max_down - pricing.min_down;
		var ratio = range > 0 ? ( downValue - pricing.min_down ) / range : 0;
		var discount = Math.round( pricing.max_discount * ratio * 100 ) / 100;
		var effectiveTotal = pricing.base_total - discount;
		var installments = state.selectedProduct.billing_period === 'week' &&
			state.selectedProduct.billing_interval === 2 ? 26 : 12;
		var recurring = Math.round( ( effectiveTotal - downValue ) / installments * 100 ) / 100;

		var period = installments === 26
			? ( config.strings.biweekly || 'Every 2 Weeks' )
			: ( config.strings.monthly || 'Monthly' );

		priceRecurring.textContent = formatCurrency( recurring ) + ' ' + period.toLowerCase();
		priceTotal.textContent = formatCurrency( effectiveTotal );

		if ( discount > 0 ) {
			priceSavings.textContent = formatCurrency( discount );
			savingsRow.style.display = '';
		} else {
			savingsRow.style.display = 'none';
		}
	}

	function calculatePricing( downPayment ) {
		apiFetch( 'sales/calculate', {
			method: 'POST',
			body: JSON.stringify( {
				product_id: state.selectedProduct.id,
				down_payment: downPayment,
			} ),
		} ).then( function ( result ) {
			if ( result.status === 200 && result.body.success ) {
				state.pricing = result.body.data;
				// Update display with server-validated values.
				priceDown.textContent = formatCurrency( state.pricing.down_payment );
				priceRecurring.textContent = formatCurrency( state.pricing.recurring_payment ) +
					' ' + state.pricing.billing_label;
				priceTotal.textContent = formatCurrency( state.pricing.effective_total );

				if ( state.pricing.discount > 0 ) {
					priceSavings.textContent = formatCurrency( state.pricing.discount );
					savingsRow.style.display = '';
				} else {
					savingsRow.style.display = 'none';
				}
			}
		} ).catch( function () {
			// Silent — optimistic display is still showing.
		} );
	}

	function buildFallbackPricing() {
		var product = state.selectedProduct;
		var downValue = downSlider ? parseInt( downSlider.value, 10 ) || 0 : 0;
		var subPrice = product ? parseFloat( product.subscription_price ) || 0 : 0;
		var billingLabel = product && product.billing_period === 'week' && product.billing_interval === 2
			? ( config.strings.biweekly || 'Every 2 Weeks' )
			: ( config.strings.monthly || 'Monthly' );

		return {
			down_payment:      downValue,
			recurring_payment: subPrice,
			effective_total:   subPrice + downValue,
			discount:          0,
			billing_label:     billingLabel,
		};
	}

	// -----------------------------------------------------------------------
	// Screen 3: Customer Info
	// -----------------------------------------------------------------------

	var searchDebounce = null;

	function handleCustomerSearch() {
		clearTimeout( searchDebounce );
		var query = customerSearch.value.trim();

		if ( query.length < 2 ) {
			customerResults.innerHTML = '';
			customerResults.classList.remove( 'active' );
			return;
		}

		searchDebounce = setTimeout( function () {
			apiFetch( 'sales/customer?search=' + encodeURIComponent( query ) )
				.then( function ( result ) {
					if ( result.status === 200 && result.body.success ) {
						renderCustomerResults( result.body.data );
					}
				} )
				.catch( function () {
					customerResults.innerHTML = '';
					customerResults.classList.remove( 'active' );
				} );
		}, 300 );
	}

	function renderCustomerResults( customers ) {
		if ( ! customers || customers.length === 0 ) {
			customerResults.innerHTML = '<div class="sales-customer-result" style="cursor:default;color:#6b7280;">' +
				escapeHtml( config.strings.noResults ) + '</div>';
			customerResults.classList.add( 'active' );
			return;
		}

		var html = '';
		customers.forEach( function ( c ) {
			html += '<button type="button" class="sales-customer-result" ' +
				'data-customer=\'' + escapeAttr( JSON.stringify( c ) ) + '\' role="option">' +
				'<div>' +
				'<span class="sales-customer-result__name">' +
				escapeHtml( c.first_name + ' ' + c.last_name ) + '</span>' +
				'<br><span class="sales-customer-result__email">' + escapeHtml( c.email ) + '</span>' +
				'</div>' +
				'<span class="sales-customer-result__source">' + escapeHtml( c.source ) + '</span>' +
				'</button>';
		} );

		customerResults.innerHTML = html;
		customerResults.classList.add( 'active' );
	}

	function handleCustomerSelect( event ) {
		var item = event.target.closest( '.sales-customer-result' );
		if ( ! item || ! item.dataset.customer ) {
			return;
		}

		var customer;
		try {
			customer = JSON.parse( item.dataset.customer );
		} catch ( e ) {
			return;
		}

		// Fill form fields.
		fillFormField( 'sales-first-name', customer.first_name );
		fillFormField( 'sales-last-name', customer.last_name );
		fillFormField( 'sales-email', customer.email );
		fillFormField( 'sales-phone', customer.phone );
		fillFormField( 'sales-address', customer.address_1 );
		fillFormField( 'sales-city', customer.city );
		fillFormField( 'sales-state', customer.state );
		fillFormField( 'sales-zip', customer.postcode );

		customerResults.innerHTML = '';
		customerResults.classList.remove( 'active' );
		customerSearch.value = '';
	}

	function fillFormField( id, value ) {
		var el = document.getElementById( id );
		if ( el && value ) {
			el.value = value;
		}
	}

	function getFormData() {
		return {
			first_name: ( document.getElementById( 'sales-first-name' ) || {} ).value || '',
			last_name:  ( document.getElementById( 'sales-last-name' ) || {} ).value || '',
			email:      ( document.getElementById( 'sales-email' ) || {} ).value || '',
			phone:      ( document.getElementById( 'sales-phone' ) || {} ).value || '',
			address_1:  ( document.getElementById( 'sales-address' ) || {} ).value || '',
			city:       ( document.getElementById( 'sales-city' ) || {} ).value || '',
			state:      ( document.getElementById( 'sales-state' ) || {} ).value || '',
			postcode:   ( document.getElementById( 'sales-zip' ) || {} ).value || '',
		};
	}

	function validateCustomerForm() {
		var data = getFormData();
		if ( ! data.first_name || ! data.last_name || ! data.email ) {
			return false;
		}
		return true;
	}

	// -----------------------------------------------------------------------
	// Screen 4: Review
	// -----------------------------------------------------------------------

	function populateReview() {
		var product = state.selectedProduct;
		var pricing = state.pricing || buildFallbackPricing();
		state.pricing = pricing;
		var customer = getFormData();
		state.customer = customer;

		reviewProduct.innerHTML = '<span class="review-highlight">' +
			escapeHtml( product.name ) + '</span>';

		var pricingHtml = '<p>Down Payment: <strong>' + formatCurrency( pricing.down_payment ) + '</strong></p>' +
			'<p>Recurring: <strong>' + formatCurrency( pricing.recurring_payment ) +
			' ' + escapeHtml( pricing.billing_label ) + '</strong></p>' +
			'<p>Total Contract: <strong>' + formatCurrency( pricing.effective_total ) + '</strong></p>';
		if ( pricing.discount > 0 ) {
			pricingHtml += '<p style="color:#10b981;">Savings: <strong>' +
				formatCurrency( pricing.discount ) + '</strong></p>';
		}
		reviewPricing.innerHTML = pricingHtml;

		reviewCustomer.innerHTML = '<p><strong>' +
			escapeHtml( customer.first_name + ' ' + customer.last_name ) + '</strong></p>' +
			'<p>' + escapeHtml( customer.email ) + '</p>' +
			( customer.phone ? '<p>' + escapeHtml( customer.phone ) + '</p>' : '' ) +
			( customer.address_1 ? '<p>' + escapeHtml( customer.address_1 ) +
				( customer.city ? ', ' + escapeHtml( customer.city ) : '' ) +
				( customer.state ? ', ' + escapeHtml( customer.state ) : '' ) +
				( customer.postcode ? ' ' + escapeHtml( customer.postcode ) : '' ) +
				'</p>' : '' );
	}

	// -----------------------------------------------------------------------
	// Order creation
	// -----------------------------------------------------------------------

	function processOrder() {
		showLoading();

		var payload = Object.assign( {}, state.customer, {
			product_id: state.selectedProduct.id,
			down_payment: state.pricing.down_payment,
			location: config.location,
		} );

		apiFetch( 'sales/order', {
			method: 'POST',
			body: JSON.stringify( payload ),
		} )
			.then( function ( result ) {
				hideLoading();
				if ( result.status === 201 && result.body.success ) {
					// Redirect to WooCommerce payment page.
					window.location.href = result.body.data.pay_url;
				} else {
					var msg = result.body.message || result.body.data?.message || 'Order creation failed.';
					showError( msg );
				}
			} )
			.catch( function ( err ) {
				hideLoading();
				showError( err.message || 'Network error. Please try again.' );
			} );
	}

	// -----------------------------------------------------------------------
	// Lead saving
	// -----------------------------------------------------------------------

	function saveLead() {
		var customer = getFormData();

		if ( ! customer.email && ! customer.phone ) {
			showError( 'At least an email or phone number is required to save a lead.' );
			return;
		}

		showLoading();

		// Get notes from textarea if present.
		var notesEl = document.getElementById( 'sales-lead-notes' );
		var notes = notesEl ? notesEl.value : '';

		apiFetch( 'sales/lead', {
			method: 'POST',
			body: JSON.stringify( {
				first_name: customer.first_name,
				last_name: customer.last_name,
				email: customer.email,
				phone: customer.phone,
				location: config.location,
				notes: notes,
			} ),
		} )
			.then( function ( result ) {
				hideLoading();
				if ( ( result.status === 200 || result.status === 201 ) && result.body.success ) {
					leadMsg.textContent = escapeHtml(
						( customer.first_name || '' ) + ' ' + ( customer.last_name || '' )
					).trim() + ' has been saved as a lead.';
					showScreen( 'leadSaved' );
					scheduleReset();
				} else {
					showError( result.body.message || 'Failed to save lead.' );
				}
			} )
			.catch( function ( err ) {
				hideLoading();
				showError( err.message || 'Network error. Please try again.' );
			} );
	}

	// -----------------------------------------------------------------------
	// Success / Error
	// -----------------------------------------------------------------------

	function showSuccess( orderId ) {
		successMsg.textContent = ( state.customer.first_name || '' ) + ' ' +
			( state.customer.last_name || '' ) + ' ' +
			( config.strings.enrolledIn || 'is now enrolled in' ) + ' ' +
			( state.selectedProduct ? state.selectedProduct.name : '' );
		showScreen( 'success' );
		scheduleReset();
	}

	function showError( message ) {
		errorMsg.textContent = message;
		showScreen( 'error' );
	}

	// -----------------------------------------------------------------------
	// Event binding
	// -----------------------------------------------------------------------

	function init() {
		if ( ! productGrid ) {
			return;
		}

		// Check for completed order return.
		var urlParams = new URLSearchParams( window.location.search );
		var completedOrder = urlParams.get( 'completed' );
		if ( completedOrder ) {
			showSuccess( completedOrder );
			// Clean URL.
			window.history.replaceState( {}, '', config.kioskUrl || window.location.pathname );
			return;
		}

		// Load products on start.
		loadProducts();

		// Product selection.
		productGrid.addEventListener( 'click', handleProductSelect );

		// Pricing slider.
		if ( downSlider ) {
			downSlider.addEventListener( 'input', updatePricingDisplay );
		}

		// Pricing navigation.
		document.getElementById( 'pricing-back' ).addEventListener( 'click', function () {
			showScreen( 'products' );
		} );
		document.getElementById( 'pricing-continue' ).addEventListener( 'click', function () {
			// Ensure we have server-validated pricing.
			if ( ! state.pricing ) {
				calculatePricing( parseInt( downSlider.value, 10 ) );
			}
			showScreen( 'customer' );
		} );

		// Customer search.
		customerSearch.addEventListener( 'input', handleCustomerSearch );
		customerResults.addEventListener( 'click', handleCustomerSelect );

		// Hide results when clicking outside.
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.sales-customer-search' ) ) {
				customerResults.classList.remove( 'active' );
			}
		} );

		// Customer navigation.
		document.getElementById( 'customer-back' ).addEventListener( 'click', function () {
			// If pricing was not configured (skipped slider), go back to products.
			if ( state.selectedProduct && ! state.selectedProduct.pricing.configured ) {
				showScreen( 'products' );
			} else {
				showScreen( 'pricing' );
			}
		} );
		document.getElementById( 'customer-save-lead' ).addEventListener( 'click', saveLead );
		document.getElementById( 'customer-continue' ).addEventListener( 'click', function () {
			if ( ! validateCustomerForm() ) {
				// Highlight required fields.
				var requiredFields = customerForm.querySelectorAll( '[required]' );
				requiredFields.forEach( function ( field ) {
					if ( ! field.value ) {
						field.style.borderColor = '#ef4444';
						field.addEventListener( 'input', function handler() {
							field.style.borderColor = '';
							field.removeEventListener( 'input', handler );
						} );
					}
				} );
				return;
			}
			populateReview();
			showScreen( 'review' );
		} );

		// Review navigation.
		document.getElementById( 'review-back' ).addEventListener( 'click', function () {
			showScreen( 'customer' );
		} );
		document.getElementById( 'review-process' ).addEventListener( 'click', processOrder );

		// Error retry.
		document.getElementById( 'sales-retry' ).addEventListener( 'click', resetApp );

		// Touch reset on success/lead-saved screens.
		document.addEventListener( 'touchstart', function () {
			if ( screens.success && screens.success.classList.contains( 'active' ) ) {
				resetApp();
			}
			if ( screens.leadSaved && screens.leadSaved.classList.contains( 'active' ) ) {
				resetApp();
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
