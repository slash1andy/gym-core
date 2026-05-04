<?php
/**
 * Title: Haanpaa · Contact
 * Slug: haanpaa/contact
 * Description: Contact form with location selector, Rockford and Beloit details, and confirmation. Design reference — Jetpack Forms blocks used in live plugin.
 * Categories: haanpaa-form
 * Keywords: contact, form, message, location
 * Viewport Width: 1440
 */
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(64px,8vw,120px)","bottom":"0","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#EFEBE1"}},"layout":{"type":"constrained","contentSize":"1200px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#EFEBE1;padding-top:clamp(64px,8vw,120px);padding-right:clamp(24px,4vw,80px);padding-bottom:0;padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:48px;align-items:end;padding-bottom:clamp(40px,5vw,72px)">
    <h1 style="font-size:clamp(40px,6vw,80px);font-weight:600;letter-spacing:-0.03em;line-height:1.02;margin:0">Get in touch.<br /><em style="font-style:italic;font-weight:500;color:#1A2DC4">We respond fast.</em></h1>
    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:12px;color:#181816">
      <li style="display:flex;gap:12px;align-items:center;font-size:16px"><span style="color:#1A2DC4;font-size:16px">✓</span> Same-day response</li>
      <li style="display:flex;gap:12px;align-items:center;font-size:16px"><span style="color:#1A2DC4;font-size:16px">✓</span> Two locations to choose from</li>
      <li style="display:flex;gap:12px;align-items:center;font-size:16px"><span style="color:#1A2DC4;font-size:16px">✓</span> No sales pitch — just answers</li>
      <li style="display:flex;gap:12px;align-items:center;font-size:16px"><span style="color:#1A2DC4;font-size:16px">✓</span> A coach makes first contact</li>
    </ul>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(64px,8vw,100px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#ffffff"}},"layout":{"type":"constrained","contentSize":"880px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#ffffff;padding-top:clamp(64px,8vw,100px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <h2 style="font-size:clamp(28px,4vw,48px);font-weight:600;letter-spacing:-0.02em;margin:0 0 40px">Send us a message.</h2>

  <!-- Design reference form (static HTML). Live plugin uses Jetpack Forms blocks. -->
  <form style="display:grid;gap:20px">
    <!-- Honeypot -->
    <input type="text" name="company" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0" aria-hidden="true" />

    <div>
      <p style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48;margin:0 0 12px">Location</p>
      <div style="display:flex;gap:0;border:1px solid rgba(10,10,10,0.18);border-radius:2px;width:fit-content">
        <button type="button" style="padding:12px 24px;border:none;background:#0A0A0A;color:#F6F4EE;font-size:14px;font-weight:600;cursor:pointer">Rockford</button>
        <button type="button" style="padding:12px 24px;border:none;background:transparent;color:#181816;font-size:14px;font-weight:600;cursor:pointer">Beloit</button>
      </div>
    </div>

    <label style="display:grid;gap:8px">
      <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Your name</span>
      <input name="name" required placeholder="Alex Garcia" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff;width:100%;box-sizing:border-box" />
    </label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <label style="display:grid;gap:8px">
        <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Email</span>
        <input name="email" required type="email" placeholder="alex@example.com" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff" />
      </label>
      <label style="display:grid;gap:8px">
        <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Phone <span style="text-transform:none;font-size:12px">(optional)</span></span>
        <input name="phone" type="tel" placeholder="(815) 000-0000" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff" />
      </label>
    </div>
    <label style="display:grid;gap:8px">
      <span style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48">Message</span>
      <textarea name="notes" required rows="5" placeholder="What are you curious about?" style="padding:16px;border:1px solid rgba(10,10,10,0.18);font-size:16px;border-radius:2px;background:#fff;font-family:inherit;resize:vertical"></textarea>
    </label>
    <div style="margin-top:16px;display:flex;justify-content:flex-end">
      <button type="submit" style="background:#1A2DC4;color:#fff;border:0;padding:18px 40px;font-size:15px;font-weight:600;cursor:pointer;border-radius:2px">Send message →</button>
    </div>
  </form>

  <div style="margin-top:48px;padding-top:40px;border-top:1px solid rgba(10,10,10,0.1)">
    <p style="font-size:13px;letter-spacing:0.06em;text-transform:uppercase;color:#4A4A48;margin:0 0 16px">Or reach us directly</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <div style="padding:24px;background:#EFEBE1;border-left:3px solid #1A2DC4">
        <p style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#4A4A48;margin:0 0 8px">★ Rockford HQ</p>
        <p style="margin:0 0 4px;font-size:15px;color:#181816">4911 26th Avenue, Rockford, IL 61109</p>
        <a href="tel:815-451-3001" style="font-family:Menlo,monospace;font-size:14px;color:#1A2DC4;font-weight:600">815-451-3001</a>
      </div>
      <div style="padding:24px;background:#F6F4EE;border-left:3px solid rgba(10,10,10,0.18)">
        <p style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#4A4A48;margin:0 0 8px">Beloit satellite</p>
        <p style="margin:0 0 4px;font-size:15px;color:#181816">610 4th St, Beloit, WI 53511</p>
        <a href="tel:608-795-3608" style="font-family:Menlo,monospace;font-size:14px;color:#1A2DC4;font-weight:600">608-795-3608</a>
      </div>
    </div>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
