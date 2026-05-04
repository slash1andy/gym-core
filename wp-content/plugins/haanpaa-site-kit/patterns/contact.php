<?php
/**
 * Title: Haanpaa · Contact
 * Slug: haanpaa/contact
 * Description: Contact form with location selector, Rockford and Beloit details, and confirmation. Submits via Jetpack Forms.
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
  <!-- /wp:html -->

  <?php
  $to = esc_attr( get_option( 'admin_email' ) );
  echo '<!-- wp:jetpack/contact-form {"to":"' . $to . '","subject":"New message from Haanpaa Martial Arts website","submitButtonText":"Send message →","style":{"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"}}}} -->' . "\n";
  ?>
  <!-- wp:jetpack/field-select {"label":"Which location?","options":["Rockford HQ","Beloit"],"required":false} /-->
  <!-- wp:jetpack/field-name {"label":"Your name","required":true,"placeholder":"Alex Garcia"} /-->
  <!-- wp:jetpack/field-email {"label":"Email","required":true,"placeholder":"alex@example.com"} /-->
  <!-- wp:jetpack/field-telephone {"label":"Phone (optional)","required":false,"placeholder":"(815) 000-0000"} /-->
  <!-- wp:jetpack/field-textarea {"label":"Message","required":true,"placeholder":"What are you curious about?","rows":5} /-->
  <!-- /wp:jetpack/contact-form -->

  <!-- wp:html -->
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
