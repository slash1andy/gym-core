<?php
/**
 * Title: Haanpaa · Trust Bar
 * Slug: haanpaa/trust-bar
 * Description: "As featured in" press bar with italic Fraunces outlet names and star separators.
 * Categories: haanpaa-section
 * Keywords: trust, press, featured, media
 * Viewport Width: 1440
 */
$outlets = [
	'The Rockford Register Star',
	'WTVO Good Day Stateline',
	'Rockford Buzz',
];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"28px","bottom":"28px","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"border":{"top":{"color":"rgba(10,10,10,0.1)","width":"1px"},"bottom":{"color":"rgba(10,10,10,0.1)","width":"1px"}}},"layout":{"type":"constrained","contentSize":"1440px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull" style="border-top-color:rgba(10,10,10,0.1);border-top-width:1px;border-bottom-color:rgba(10,10,10,0.1);border-bottom-width:1px;padding-top:28px;padding-right:clamp(24px,4vw,80px);padding-bottom:28px;padding-left:clamp(24px,4vw,80px)">
  <!-- wp:html -->
  <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:24px">
    <span class="hp-eyebrow">As featured in</span>
    <?php foreach ( $outlets as $i => $outlet ) : ?>
      <span style="font-family:'Fraunces',serif;font-style:italic;font-size:20px;font-weight:500;color:#181816"><?php echo esc_html( $outlet ); ?></span>
      <?php if ( $i < count( $outlets ) - 1 ) : ?>
        <span style="color:#1A2DC4;font-size:12px" aria-hidden="true">&#9733;</span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
