<?php
/**
 * Title: Haanpaa · Trust Bar
 * Slug: haanpaa/trust-bar
 * Description: Inline strip of credentials and affiliations, separated by stars.
 * Categories: haanpaa-section
 * Keywords: trust, credentials, affiliations
 * Viewport Width: 1440
 */
$items = [
    'Pedro Sauer Association',
    'Royler Gracie Lineage',
    'IBJJF Affiliate',
    'Team Curran Affiliate',
    '22 years coaching',
    'Black-belt instructors',
];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"40px","bottom":"40px","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"border":{"top":{"color":"rgba(10,10,10,0.1)","width":"1px"},"bottom":{"color":"rgba(10,10,10,0.1)","width":"1px"}}},"layout":{"type":"constrained","contentSize":"1440px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull" style="border-top-color:rgba(10,10,10,0.1);border-top-width:1px;border-bottom-color:rgba(10,10,10,0.1);border-bottom-width:1px;padding-top:40px;padding-right:clamp(24px,4vw,80px);padding-bottom:40px;padding-left:clamp(24px,4vw,80px)">
  <!-- wp:html -->
  <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center;justify-content:center;font-family:'Menlo',monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#4A4A48">
    <?php foreach ( $items as $i => $item ) : ?>
      <span><?php echo esc_html( $item ); ?></span>
      <?php if ( $i < count( $items ) - 1 ) : ?><span style="color:#1A2DC4">★</span><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
