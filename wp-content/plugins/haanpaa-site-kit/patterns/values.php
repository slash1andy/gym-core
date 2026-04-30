<?php
/**
 * Title: Haanpaa · Values
 * Slug: haanpaa/values
 * Description: Four-up principles grid with numbered headings.
 * Categories: haanpaa-section
 * Keywords: values, principles, philosophy
 * Viewport Width: 1440
 */
$values = [
    [ 'h' => 'Beginners come first.', 'p' => 'Our fundamentals track is its own program — not a side-room. New students drill with new students until the basics feel automatic.' ],
    [ 'h' => 'No ego on the mat.',    'p' => 'Every belt rolls with every belt. Black belts coach white belts. The room polices itself.' ],
    [ 'h' => 'Show up to grow up.',   'p' => 'The hardest part is getting through the door. After that, attendance and breath control do most of the work.' ],
    [ 'h' => 'Lineage matters.',      'p' => 'Pedro Sauer / Royler Gracie. We coach the curriculum we were taught — and we credit the line that taught us.' ],
];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull" style="padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">
  <!-- wp:html -->
  <p class="hp-eyebrow-mono">06 / WHAT WE BELIEVE</p>
  <h2 style="font-size:clamp(40px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05;margin:16px 0 80px;max-width:20ch">Four ideas that run the room.</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:48px 64px">
    <?php foreach ( $values as $i => $v ) : ?>
    <div>
      <p class="hp-meta" style="color:#1A2DC4;font-weight:600">0<?php echo $i + 1; ?></p>
      <h3 style="font-size:24px;font-weight:600;letter-spacing:-0.01em;margin:16px 0 16px;line-height:1.2"><?php echo esc_html( $v['h'] ); ?></h3>
      <p style="font-size:15px;line-height:1.6;color:#4A4A48;margin:0;max-width:32ch"><?php echo esc_html( $v['p'] ); ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
