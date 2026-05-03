<?php
/**
 * Title: Haanpaa · Lead Capture
 * Slug: haanpaa/lead-capture
 * Description: Homepage lead-capture strip — blue background, "Walk in. That's the hard part." headline, inline form that submits to Jetpack CRM via /wp-json/haanpaa/v1/trial.
 * Categories: haanpaa-form
 * Keywords: lead, capture, free trial, form, cta
 * Viewport Width: 1440
 */

$programs = [
	'bjj'  => 'Brazilian Jiu-Jitsu',
	'kick' => 'Fitness Kickboxing',
	'kids' => 'Kids Jiu-Jitsu (Ages 5–12)',
];
$nonce = wp_create_nonce( 'haanpaa_trial' );
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#1A2DC4"}},"textColor":"paper","layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-paper-color has-text-color has-background" style="background-color:#1A2DC4;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <p class="hp-eyebrow-mono" style="color:rgba(255,255,255,0.65)">THE HARDEST PART IS STARTING</p>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start;margin-top:32px">

    <div>
      <h2 style="font-size:clamp(36px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05;color:#fff;margin:0 0 24px">Walk in.<br>That's the hard part.</h2>
      <p style="font-size:18px;line-height:1.6;color:rgba(255,255,255,0.8);margin:0;max-width:38ch">Your first week is free. No card, no contract, no tour — just show up. We'll take care of everything else.</p>
    </div>

    <div data-wp-interactive="haanpaa/trial">

      <form data-wp-on--submit="actions.submit" style="display:grid;gap:16px">
        <input type="text" name="company" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <label style="display:grid;gap:6px">
            <span style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.65)">Name</span>
            <input name="name" required placeholder="Your name" style="padding:14px 16px;border:1px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.12);color:#fff;font-size:15px;border-radius:2px;font-family:inherit" />
          </label>
          <label style="display:grid;gap:6px">
            <span style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.65)">Phone</span>
            <input name="phone" required type="tel" placeholder="(815) 000-0000" style="padding:14px 16px;border:1px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.12);color:#fff;font-size:15px;border-radius:2px;font-family:inherit" />
          </label>
        </div>

        <label style="display:grid;gap:6px">
          <span style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.65)">Program interest</span>
          <select name="program" style="padding:14px 16px;border:1px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.12);color:#fff;font-size:15px;border-radius:2px;font-family:inherit;appearance:none">
            <option value="">Select a program…</option>
            <?php foreach ( $programs as $id => $label ) : ?>
            <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <div data-wp-bind--hidden="!state.error" style="color:#FFD0D0;font-size:14px;padding:12px 16px;background:rgba(255,255,255,0.1);border-radius:2px" data-wp-text="state.error"></div>

        <button type="submit" data-wp-bind--disabled="state.submitting" style="background:#F6F4EE;color:#1A2DC4;border:0;padding:18px 32px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px;font-family:inherit;text-align:left;display:flex;align-items:center;justify-content:space-between;gap:16px">
          <span data-wp-text="state.submitting ? 'Sending…' : 'Book my free week'">Book my free week</span>
          <span aria-hidden="true" data-wp-bind--hidden="state.submitting">→</span>
        </button>
      </form>

      <div data-wp-bind--hidden="!state.isStep4" style="padding:32px 0;color:#fff">
        <div style="font-size:40px;margin-bottom:16px">✓</div>
        <p style="font-size:24px;font-weight:600;margin:0 0 12px">You're on the list.</p>
        <p style="font-size:16px;color:rgba(255,255,255,0.8);margin:0">We'll text you within one business day. Just show up.</p>
      </div>

    </div>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
