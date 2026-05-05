/**
 * Haanpaa Interactivity API — accordion, schedule, free-trial wizard, FAQ.
 * Loaded as a module; namespaces match the stores registered in PHP.
 */
import { store, getContext } from '@wordpress/interactivity';

// Programs accordion (single-open)
store( 'haanpaa/programs', {
  state: {
    get isOpen() {
      const ctx = getContext();
      return store( 'haanpaa/programs' ).state.openId === ctx.id;
    },
    openId: 'bjj',
  },
  actions: {
    toggle() {
      const ctx = getContext();
      const s = store( 'haanpaa/programs' ).state;
      s.openId = s.openId === ctx.id ? null : ctx.id;
    },
  },
} );

// Schedule grid — day tabs + filters
store( 'haanpaa/schedule', {
  state: {
    day: 'Mon',
    location: 'rockford',
    filter: 'all',
    get dayActive() { return getContext().day === store( 'haanpaa/schedule' ).state.day; },
    get filterActive() { return getContext().filter === store( 'haanpaa/schedule' ).state.filter; },
    get locActive() { return getContext().location === store( 'haanpaa/schedule' ).state.location; },
    get rowVisible() {
      const c = getContext();
      const s = store( 'haanpaa/schedule' ).state;
      if ( c.day !== s.day ) return false;
      if ( s.filter !== 'all' && c.kind !== s.filter ) return false;
      if ( s.location === 'beloit' && ! [ 'bjj', 'kids' ].includes( c.kind ) ) return false;
      return true;
    },
    // Week grid: no day filter — only kind and location.
    get cardVisible() {
      const c = getContext();
      const s = store( 'haanpaa/schedule' ).state;
      if ( s.filter !== 'all' && c.kind !== s.filter ) return false;
      if ( s.location === 'beloit' && ! [ 'bjj', 'kids' ].includes( c.kind ) ) return false;
      return true;
    },
  },
  actions: {
    setDay()    { store( 'haanpaa/schedule' ).state.day      = getContext().day; },
    setFilter() { store( 'haanpaa/schedule' ).state.filter   = getContext().filter; },
    setLoc()    { store( 'haanpaa/schedule' ).state.location = getContext().location; },
  },
} );

// Free-trial wizard
store( 'haanpaa/trial', {
  state: {
    step: 1, program: '', time: '', location: 'rockford',
    source: '', sourceOther: '',
    submitting: false, done: false, error: '',
    get isStep1() { return store( 'haanpaa/trial' ).state.step === 1; },
    get isStep2() { return store( 'haanpaa/trial' ).state.step === 2; },
    get isStep3() { return store( 'haanpaa/trial' ).state.step === 3; },
    get isStep4() { return store( 'haanpaa/trial' ).state.step === 4; },
    get programSelected()   { return getContext().id === store( 'haanpaa/trial' ).state.program; },
    get timeSelected()      { return getContext().time === store( 'haanpaa/trial' ).state.time; },
    get locSelected()       { return getContext().location === store( 'haanpaa/trial' ).state.location; },
    get isCurrentProgram()  { return getContext().prog === store( 'haanpaa/trial' ).state.program; },
    get isSourceOther()     { return store( 'haanpaa/trial' ).state.source === 'other'; },
  },
  actions: {
    pickProgram() { store( 'haanpaa/trial' ).state.program = getContext().id; },
    pickTime()    { store( 'haanpaa/trial' ).state.time    = getContext().time; },
    pickLoc()     { store( 'haanpaa/trial' ).state.location= getContext().location; },
    pickSource( e ) {
      const s = store( 'haanpaa/trial' ).state;
      const value = e && e.target ? e.target.value : '';
      s.source = value || '';
      if ( s.source !== 'other' ) {
        s.sourceOther = '';
      }
      s.error = '';
    },
    next() {
      const s = store( 'haanpaa/trial' ).state;
      if ( s.step === 1 && ! s.program ) return;
      if ( s.step === 2 && ! s.time ) return;
      s.step = Math.min( 4, s.step + 1 );
    },
    back() {
      const s = store( 'haanpaa/trial' ).state;
      s.step = Math.max( 1, s.step - 1 );
    },
    *submit( e ) {
      e.preventDefault();
      const s = store( 'haanpaa/trial' ).state;
      const form = e.target;
      const data = Object.fromEntries( new FormData( form ).entries() );
      data.program = s.program; data.time = s.time; data.location = s.location;
      data.nonce = window.haanpaaTrial.nonce;

      // Required: lead source. Match server validation in Jetpack_CRM::handle_trial.
      if ( ! data.lead_source ) {
        s.error = 'Please tell us how you heard about Haanpaa Martial Arts.';
        return;
      }
      if ( data.lead_source === 'other' && ! ( data.lead_source_other && String( data.lead_source_other ).trim() ) ) {
        s.error = 'Please add a quick note describing how you heard about us.';
        return;
      }

      s.submitting = true; s.error = '';
      try {
        const res = yield fetch( window.haanpaaTrial.endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.haanpaaTrial.restNonce },
          body: JSON.stringify( data ),
        } );
        const json = yield res.json();
        if ( ! res.ok || ! json.ok ) {
          // Surface server-side validation messages when present.
          if ( json && json.message ) {
            s.error = String( json.message );
            return;
          }
          throw new Error( 'Submission failed' );
        }
        s.step = 4; s.done = true;
      } catch ( err ) {
        s.error = 'Something went wrong. Please call us at ' + ( window.haanpaaTrial.phone || '' );
      } finally {
        s.submitting = false;
      }
    },
  },
} );

// FAQ accordion (single-open by index)
store( 'haanpaa/faq', {
  state: {
    open: 0,
    get isOpen() { return getContext().idx === store( 'haanpaa/faq' ).state.open; },
  },
  actions: {
    toggle() {
      const s = store( 'haanpaa/faq' ).state;
      const i = getContext().idx;
      s.open = s.open === i ? -1 : i;
    },
  },
} );
