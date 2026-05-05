<?php
/**
 * Title: Haanpaa · Lead Capture Form
 * Slug: haanpaa/lead-capture-form
 * Description: Reusable lead-capture form (name, phone, email, lead source) with required source-of-discovery field. Submits to /haanpaa/v1/trial.
 * Categories: haanpaa-form
 * Keywords: lead capture, form, signup, intake, lead source
 * Viewport Width: 1440
 *
 * Voice and copy follow the Haanpaa brand guide §8 — academy, journey, join our
 * community. Validation styling uses #C62828 per brand-guide §3 (Error).
 *
 * The form uses the `haanpaa/trial` Interactivity API store (defined in
 * haanpaa-site-kit). When `state.source === 'other'`, the free-text "Tell us
 * more" field is required. Server-side validation also runs in the REST
 * handler (Haanpaa\Jetpack_CRM::handle_trial).
 */

// Lead-source options stay in sync with Gym_Core\Sales\LeadSourceField. We
// don't class_exists() that file from a site-kit pattern so the source list is
// duplicated here intentionally — keep these slug/label pairs identical.
$lead_sources = array(
	array( 'slug' => 'google',    'label' => 'Google' ),
	array( 'slug' => 'walk_in',   'label' => 'Walk-in' ),
	array( 'slug' => 'referral',  'label' => 'Referral' ),
	array( 'slug' => 'facebook',  'label' => 'Facebook' ),
	array( 'slug' => 'instagram', 'label' => 'Instagram' ),
	array( 'slug' => 'other',     'label' => 'Other' ),
);
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(48px,6vw,80px)","bottom":"clamp(48px,6vw,80px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#ffffff"}},"layout":{"type":"constrained","contentSize":"720px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#ffffff;padding-top:clamp(48px,6vw,80px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(48px,6vw,80px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div class="hp-lead-capture" data-wp-interactive="haanpaa/trial">

    <h2 style="font-family:'Barlow Condensed','Arial Narrow',sans-serif;font-size:clamp(28px,4vw,40px);font-weight:700;text-transform:uppercase;letter-spacing:0.02em;line-height:1.1;margin:0 0 16px;color:#000">Join our community.</h2>
    <p style="font-size:16px;color:#75787B;margin:0 0 32px;line-height:1.6">All ages and levels welcome. Tell us a bit about yourself and a coach will reach out.</p>

    <form data-wp-on--submit="actions.submit" style="display:grid;gap:20px" novalidate>
      <input type="text" name="company" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true" />

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <label style="display:grid;gap:8px">
          <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#000;font-weight:500">Your name <span style="color:#C62828" aria-hidden="true">*</span></span>
          <input name="name" required placeholder="Alex Garcia" style="padding:14px 16px;border:1px solid #E5E5E7;font-size:16px;border-radius:4px;background:#fff;width:100%;box-sizing:border-box" />
        </label>
        <label style="display:grid;gap:8px">
          <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#000;font-weight:500">Phone <span style="color:#C62828" aria-hidden="true">*</span></span>
          <input name="phone" required type="tel" placeholder="(815) 000-0000" style="padding:14px 16px;border:1px solid #E5E5E7;font-size:16px;border-radius:4px;background:#fff;width:100%;box-sizing:border-box" />
        </label>
      </div>

      <label style="display:grid;gap:8px">
        <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#000;font-weight:500">Email <span style="font-size:12px;text-transform:none;letter-spacing:0;color:#75787B">(optional)</span></span>
        <input name="email" type="email" placeholder="alex@example.com" style="padding:14px 16px;border:1px solid #E5E5E7;font-size:16px;border-radius:4px;background:#fff;width:100%;box-sizing:border-box" />
      </label>

      <!-- Lead source — required at intake (Plan §F) -->
      <label style="display:grid;gap:8px">
        <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#000;font-weight:500">How did you hear about us? <span style="color:#C62828" aria-hidden="true">*</span></span>
        <select name="lead_source" required aria-required="true" data-wp-on--change="actions.pickSource" style="padding:14px 16px;border:1px solid #E5E5E7;font-size:16px;border-radius:4px;background:#fff;width:100%;box-sizing:border-box;font-family:inherit">
          <option value="">Choose one…</option>
          <?php foreach ( $lead_sources as $src ) : ?>
            <option value="<?php echo esc_attr( $src['slug'] ); ?>"><?php echo esc_html( $src['label'] ); ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label data-wp-bind--hidden="!state.isSourceOther" style="display:grid;gap:8px">
        <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#000;font-weight:500">Tell us more <span style="color:#C62828" aria-hidden="true">*</span></span>
        <input name="lead_source_other" type="text" maxlength="200" placeholder="A specific person, podcast, billboard…" style="padding:14px 16px;border:1px solid #E5E5E7;font-size:16px;border-radius:4px;background:#fff;width:100%;box-sizing:border-box" />
      </label>

      <div data-wp-bind--hidden="!state.error" role="alert" style="color:#C62828;font-size:14px;font-weight:500" data-wp-text="state.error"></div>

      <div style="margin-top:8px">
        <button type="submit" data-wp-bind--disabled="state.submitting" style="background:#0032A0;color:#fff;border:0;padding:16px 32px;font-family:'Barlow Condensed','Arial Narrow',sans-serif;font-size:1rem;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;cursor:pointer;border-radius:4px;min-height:44px"><span data-wp-text="state.submitting ? 'Sending…' : 'Send my info →'">Send my info →</span></button>
      </div>
    </form>

  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
