<?php
/**
 * Title: Haanpaa · Free Trial Wizard
 * Slug: haanpaa/free-trial
 * Description: 3-step booking flow (program → time → contact) with confirmation. Submits to Jetpack CRM via REST.
 * Categories: haanpaa-form
 * Keywords: free trial, booking, lead capture, form
 * Viewport Width: 1440
 */
$programs = [
    [ 'id' => 'bjj',  'name' => 'Brazilian Jiu-Jitsu', 'desc' => 'Adults · all levels' ],
    [ 'id' => 'kick', 'name' => 'Muay Thai Kickboxing', 'desc' => 'Adults · all levels' ],
    [ 'id' => 'kids', 'name' => 'Kids Jiu-Jitsu',       'desc' => 'Ages 5–13' ],
];
$times = [ 'Weekday mornings (6 AM)', 'Weekday lunch (12 PM)', 'Weekday evenings (5–7 PM)', 'Saturday open mat' ];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(64px,8vw,120px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#F6F4EE"}},"layout":{"type":"constrained","contentSize":"880px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#F6F4EE;padding-top:clamp(64px,8vw,120px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div data-wp-interactive="haanpaa/trial">

    <p class="hp-eyebrow-mono">FREE TRIAL · 7 DAYS · NO CARD</p>
    <h1 style="font-size:clamp(40px,6vw,80px);font-weight:600;letter-spacing:-0.03em;line-height:1.02;margin:16px 0 48px">Pick a program. Pick a time. Walk in Monday.</h1>

    <div class="hp-step-bar">
      <div data-wp-bind--data-active="state.isStep1" data-wp-bind--data-done="state.step > 1"><span class="hp-step-bullet">1</span><span>Program</span></div>
      <div data-wp-bind--data-active="state.isStep2" data-wp-bind--data-done="state.step > 2"><span class="hp-step-bullet">2</span><span>When</span></div>
      <div data-wp-bind--data-active="state.isStep3" data-wp-bind--data-done="state.step > 3"><span class="hp-step-bullet">3</span><span>You</span></div>
      <div data-wp-bind--data-active="state.isStep4"><span class="hp-step-bullet">✓</span><span>Confirmed</span></div>
    </div>

    <!-- STEP 1 -->
    <div data-wp-bind--hidden="!state.isStep1">
      <h2 style="font-size:24px;font-weight:600;margin:0 0 24px">Which program are you most curious about?</h2>
      <div style="display:grid;gap:12px">
        <?php foreach ( $programs as $p ) : ?>
        <button class="hp-trial-option" type="button" data-wp-context='<?php echo wp_json_encode( [ 'id' => $p['id'] ] ); ?>' data-wp-on--click="actions.pickProgram" data-wp-bind--aria-pressed="state.programSelected">
          <span class="hp-trial-radio"></span>
          <span style="flex:1">
            <span style="display:block;font-size:18px;font-weight:600"><?php echo esc_html( $p['name'] ); ?></span>
            <span style="display:block;font-size:14px;color:#4A4A48;margin-top:4px"><?php echo esc_html( $p['desc'] ); ?></span>
          </span>
        </button>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:32px;display:flex;justify-content:flex-end">
        <button type="button" data-wp-on--click="actions.next" style="background:#1A2DC4;color:#fff;border:0;padding:18px 40px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px">Continue →</button>
      </div>
    </div>

    <!-- STEP 2 -->
    <div data-wp-bind--hidden="!state.isStep2">
      <h2 style="font-size:24px;font-weight:600;margin:0 0 24px">When do you want to train?</h2>
      <p style="color:#4A4A48;margin:0 0 24px">Pick the slot that fits your week. We'll suggest the best class.</p>
      <div style="display:grid;gap:12px">
        <?php foreach ( $times as $t ) : ?>
        <button class="hp-trial-option" type="button" data-wp-context='<?php echo wp_json_encode( [ 'time' => $t ] ); ?>' data-wp-on--click="actions.pickTime" data-wp-bind--aria-pressed="state.timeSelected">
          <span class="hp-trial-radio"></span>
          <span style="font-size:18px;font-weight:500"><?php echo esc_html( $t ); ?></span>
        </button>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:32px;display:flex;justify-content:space-between">
        <button type="button" data-wp-on--click="actions.back" style="background:transparent;color:#0A0A0A;border:1px solid rgba(10,10,10,0.18);padding:18px 32px;font-size:15px;cursor:pointer;border-radius:2px">← Back</button>
        <button type="button" data-wp-on--click="actions.next" style="background:#1A2DC4;color:#fff;border:0;padding:18px 40px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px">Continue →</button>
      </div>
    </div>

    <!-- STEP 3 -->
    <div data-wp-bind--hidden="!state.isStep3">
      <h2 style="font-size:24px;font-weight:600;margin:0 0 24px">Last step. We text you class details.</h2>
      <form data-wp-on--submit="actions.submit" style="display:grid;gap:20px">
        <input type="text" name="company" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true" />
        <label style="display:grid;gap:8px"><span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Name</span>
          <input name="name" required style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff" /></label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <label style="display:grid;gap:8px"><span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Phone</span>
            <input name="phone" required type="tel" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff" /></label>
          <label style="display:grid;gap:8px"><span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Email</span>
            <input name="email" required type="email" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff" /></label>
        </div>
        <label style="display:grid;gap:8px"><span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Anything we should know? (Optional)</span>
          <textarea name="notes" rows="3" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff;font-family:inherit"></textarea></label>

        <div data-wp-bind--hidden="!state.error" style="color:#B00020;font-size:14px" data-wp-text="state.error"></div>

        <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center">
          <button type="button" data-wp-on--click="actions.back" style="background:transparent;color:#0A0A0A;border:1px solid rgba(10,10,10,0.18);padding:18px 32px;font-size:15px;cursor:pointer;border-radius:2px">← Back</button>
          <button type="submit" data-wp-bind--disabled="state.submitting" style="background:#1A2DC4;color:#fff;border:0;padding:18px 40px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px"><span data-wp-text="state.submitting ? 'Sending…' : 'Book my free week →'">Book my free week →</span></button>
        </div>
      </form>
    </div>

    <!-- STEP 4 — confirmation -->
    <div data-wp-bind--hidden="!state.isStep4" style="text-align:center;padding:48px 0">
      <div style="width:80px;height:80px;border-radius:50%;background:#2B8A5F;color:#fff;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 32px">✓</div>
      <h2 style="font-size:36px;font-weight:600;letter-spacing:-0.02em;margin:0 0 16px">You're on the list.</h2>
      <p style="font-size:18px;color:#4A4A48;max-width:48ch;margin:0 auto 32px">We'll text you within one business day with your start date and a quick what-to-bring note. No card on file. No tour required — just show up.</p>
      <a href="/" style="color:#1A2DC4;text-decoration:none;border-bottom:1px solid currentColor;padding-bottom:2px">← Back to home</a>
    </div>

  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
