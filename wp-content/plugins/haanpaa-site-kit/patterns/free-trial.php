<?php
/**
 * Title: Haanpaa · Free Trial Wizard
 * Slug: haanpaa/free-trial
 * Description: 3-step booking flow (program → time → contact) with confirmation. Submits to Jetpack CRM via REST.
 * Categories: haanpaa-form
 * Keywords: free trial, booking, lead capture, form
 * Viewport Width: 1440
 */

$bullets = [
	'Loaner gi &amp; gloves provided',
	'Beginners welcome — most start here',
	'Same-day text confirmation',
	'Cancel anytime, no upsell',
];

$programs = [
	[ 'id' => 'bjj',    'name' => 'Brazilian Jiu-Jitsu', 'desc' => 'Adults 13+ · gentle start, technical for life' ],
	[ 'id' => 'kick',   'name' => 'Fitness Kickboxing',   'desc' => 'All levels · Muay Thai technique at fitness pace' ],
	[ 'id' => 'kids',   'name' => 'Kids Jiu-Jitsu',       'desc' => 'Ages 5–12 · focus, courtesy, confidence' ],
	[ 'id' => 'unsure', 'name' => "I'm not sure yet",     'desc' => "We'll help you pick after a quick chat" ],
];

$trial_times = [
	'bjj'    => [ 'Mon 6:30p · BJJ Fundamentals', 'Wed 6:30p · BJJ Fundamentals', 'Fri 12:00p · Open Mat', 'Sat 10:30a · BJJ Fundamentals' ],
	'kick'   => [ 'Mon 12:00p · Fitness Kickboxing', 'Tue 6:00a · Fitness Kickboxing', 'Wed 12:00p · Fitness Kickboxing', 'Thu 6:00a · Fitness Kickboxing' ],
	'kids'   => [ 'Mon 4:30p · Tigers (5–8)', 'Mon 5:30p · Juniors (9–12)', 'Wed 4:30p · Tigers (5–8)', 'Sat 9:00a · Family Open Mat' ],
	'unsure' => [ 'Mon 6:30p · BJJ Fundamentals', 'Wed 12:00p · Fitness Kickboxing', 'Sat 10:30a · BJJ Fundamentals' ],
];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(64px,8vw,120px)","bottom":"0","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#EFEBE1"}},"layout":{"type":"constrained","contentSize":"1200px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#EFEBE1;padding-top:clamp(64px,8vw,120px);padding-right:clamp(24px,4vw,80px);padding-bottom:0;padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:48px;align-items:end;padding-bottom:clamp(40px,5vw,72px)">
    <h1 style="font-size:clamp(40px,6vw,80px);font-weight:600;letter-spacing:-0.03em;line-height:1.02;margin:0">Your first class<br /><em style="font-style:italic;font-weight:500;color:#1A2DC4">is on us.</em></h1>
    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:12px;color:#181816">
      <?php foreach ( $bullets as $b ) : ?>
      <li style="display:flex;gap:12px;align-items:center;font-size:16px"><span style="color:#1A2DC4;font-size:16px">✓</span> <?php echo $b; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(64px,8vw,100px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#ffffff"}},"layout":{"type":"constrained","contentSize":"880px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#ffffff;padding-top:clamp(64px,8vw,100px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div data-wp-interactive="haanpaa/trial">

    <div class="hp-step-bar">
      <div data-wp-bind--data-active="state.isStep1" data-wp-bind--data-done="state.step > 1"><span class="hp-step-bullet">1</span><span>Pick a program</span></div>
      <div data-wp-bind--data-active="state.isStep2" data-wp-bind--data-done="state.step > 2"><span class="hp-step-bullet">2</span><span>Pick a class time</span></div>
      <div data-wp-bind--data-active="state.isStep3" data-wp-bind--data-done="state.step > 3"><span class="hp-step-bullet">3</span><span>Your details</span></div>
      <div data-wp-bind--data-active="state.isStep4"><span class="hp-step-bullet">✓</span><span>Confirmed</span></div>
    </div>

    <!-- STEP 1 -->
    <div data-wp-bind--hidden="!state.isStep1">
      <h2 style="font-size:clamp(28px,4vw,48px);font-weight:600;letter-spacing:-0.02em;margin:0 0 40px">Which class are you curious about?</h2>
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
      <div style="margin-top:40px;display:flex;justify-content:flex-end">
        <button type="button" data-wp-on--click="actions.next" style="background:#1A2DC4;color:#fff;border:0;padding:18px 40px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px;font-family:inherit">Continue →</button>
      </div>
    </div>

    <!-- STEP 2 -->
    <div data-wp-bind--hidden="!state.isStep2">
      <h2 style="font-size:clamp(28px,4vw,48px);font-weight:600;letter-spacing:-0.02em;margin:0 0 24px">Pick a class time.</h2>

      <div style="margin-bottom:24px">
        <div style="font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48;margin-bottom:12px">Location</div>
        <div style="display:flex;gap:0;border:1px solid rgba(10,10,10,0.18);border-radius:2px;width:fit-content">
          <button type="button" data-wp-context='{"location":"rockford"}' data-wp-on--click="actions.pickLoc" data-wp-bind--aria-pressed="state.locSelected" style="padding:12px 24px;border:none;background:#0A0A0A;color:#F6F4EE;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">Rockford</button>
          <button type="button" data-wp-context='{"location":"beloit"}' data-wp-on--click="actions.pickLoc" data-wp-bind--aria-pressed="state.locSelected" style="padding:12px 24px;border:none;background:transparent;color:#181816;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">Beloit</button>
        </div>
      </div>

      <?php foreach ( $trial_times as $prog_id => $prog_times ) : ?>
      <div data-wp-context='<?php echo wp_json_encode( [ 'prog' => $prog_id ] ); ?>' data-wp-bind--hidden="!state.isCurrentProgram" style="display:grid;gap:12px">
        <?php foreach ( $prog_times as $t ) : ?>
        <button class="hp-trial-option" type="button" data-wp-context='<?php echo wp_json_encode( [ 'time' => $t ] ); ?>' data-wp-on--click="actions.pickTime" data-wp-bind--aria-pressed="state.timeSelected">
          <span class="hp-trial-radio"></span>
          <span style="font-size:16px;font-weight:500"><?php echo esc_html( $t ); ?></span>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>

      <p style="margin-top:20px;font-size:13px;color:#4A4A48">
        Don't see a time that works? <a href="tel:815-451-3001" style="color:#1A2DC4;font-weight:600">Call us at (815) 451-3001</a> and we'll find something.
      </p>
      <div style="margin-top:32px;display:flex;justify-content:space-between">
        <button type="button" data-wp-on--click="actions.back" style="background:transparent;color:#0A0A0A;border:1px solid rgba(10,10,10,0.18);padding:18px 32px;font-size:15px;cursor:pointer;border-radius:2px;font-family:inherit">← Back</button>
        <button type="button" data-wp-on--click="actions.next" style="background:#1A2DC4;color:#fff;border:0;padding:18px 40px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px;font-family:inherit">Continue →</button>
      </div>
    </div>

    <!-- STEP 3 -->
    <div data-wp-bind--hidden="!state.isStep3">
      <h2 style="font-size:clamp(28px,4vw,48px);font-weight:600;letter-spacing:-0.02em;margin:0 0 40px">Last bit. Where do we text the confirmation?</h2>
      <form data-wp-on--submit="actions.submit" style="display:grid;gap:20px">
        <input type="text" name="company" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true" />
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <label style="display:grid;gap:8px">
            <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Your name</span>
            <input name="name" required placeholder="Alex Garcia" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff;width:100%;box-sizing:border-box" />
          </label>
          <label style="display:grid;gap:8px">
            <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Phone</span>
            <input name="phone" required type="tel" placeholder="(815) 000-0000" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff;width:100%;box-sizing:border-box" />
          </label>
        </div>
        <label style="display:grid;gap:8px">
          <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Email <span style="font-size:12px;text-transform:none;letter-spacing:0;color:#9A9A98">(optional)</span></span>
          <input name="email" type="email" placeholder="alex@example.com" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff;width:100%;box-sizing:border-box" />
        </label>
        <label style="display:grid;gap:8px">
          <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Anything we should know? <span style="font-size:12px;text-transform:none;letter-spacing:0;color:#9A9A98">(injuries, nerves, kid info)</span></span>
          <textarea name="notes" rows="4" placeholder="Optional. We'll keep it private." style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff;font-family:inherit;resize:vertical;width:100%;box-sizing:border-box"></textarea>
        </label>

        <div data-wp-bind--hidden="!state.error" style="color:#B00020;font-size:14px" data-wp-text="state.error"></div>

        <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center">
          <button type="button" data-wp-on--click="actions.back" style="background:transparent;color:#0A0A0A;border:1px solid rgba(10,10,10,0.18);padding:18px 32px;font-size:15px;cursor:pointer;border-radius:2px;font-family:inherit">← Back</button>
          <button type="submit" data-wp-bind--disabled="state.submitting" style="background:#1A2DC4;color:#fff;border:0;padding:18px 40px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px;font-family:inherit"><span data-wp-text="state.submitting ? 'Sending…' : 'Confirm my class →'">Confirm my class →</span></button>
        </div>
      </form>
    </div>

    <!-- STEP 4 — confirmation -->
    <div data-wp-bind--hidden="!state.isStep4" style="text-align:center;padding:48px 0">
      <div style="width:80px;height:80px;border-radius:50%;background:#1A2DC4;color:#fff;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 32px">✓</div>
      <h2 style="font-size:clamp(32px,5vw,56px);font-weight:600;letter-spacing:-0.02em;margin:0 0 24px">You're in.</h2>
      <p style="font-size:18px;color:#4A4A48;max-width:48ch;margin:0 auto 40px">A coach will text you to confirm your spot and answer any questions before class. No card on file. Just show up.</p>
      <a href="/" style="color:#1A2DC4;text-decoration:none;border-bottom:1px solid currentColor;padding-bottom:2px">← Back to home</a>
    </div>

  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
