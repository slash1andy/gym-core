<?php
/**
 * Title: Haanpaa · Values
 * Slug: haanpaa/values
 * Description: Three-up principles grid with bordered columns.
 * Categories: haanpaa-section
 * Keywords: values, principles, philosophy
 * Viewport Width: 1440
 */
$values = [
    [ 'n' => '01', 'h' => 'Everyone starts as a beginner',   'p' => 'No experience, no shame. Our beginner classes run nightly and the room is full of people who walked in nervous a week ago.' ],
    [ 'n' => '02', 'h' => 'Family-built, family-run',        'p' => 'Darby and Amanda Haanpaa run the school as a family. The room takes care of you.' ],
    [ 'n' => '03', 'h' => 'Real lineage, taught patiently',  'p' => 'Gracie Brazilian Jiu-Jitsu and traditional Muay Thai. Real curriculum, no flash. You leave each class better than you came in.' ],
];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull" style="padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">
  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:1fr 2.2fr;gap:64px;margin-bottom:80px;align-items:start">
    <p class="hp-eyebrow">What we believe</p>
    <h2 style="font-size:clamp(28px,3.5vw,48px);font-weight:600;letter-spacing:-0.03em;line-height:1.1;margin:0">We are not a fight gym. We are a martial arts school<span style="color:#9A9A98"> — for everyone in your family, regardless of where you are starting.</span></h2>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-top:1px solid rgba(10,10,10,0.15)">
    <?php foreach ( $values as $i => $v ) : ?>
    <div style="padding:36px <?php echo $i < 2 ? '32px' : '0'; ?> 32px <?php echo $i > 0 ? '32px' : '0'; ?>;<?php echo $i < 2 ? 'border-right:1px solid rgba(10,10,10,0.1);' : ''; ?>">
      <p class="hp-meta" style="color:#1A2DC4;margin:0 0 24px"><?php echo esc_html( $v['n'] ); ?></p>
      <h3 style="font-size:22px;font-weight:600;letter-spacing:-0.01em;margin:0 0 14px;line-height:1.2"><?php echo esc_html( $v['h'] ); ?></h3>
      <p style="font-size:15px;line-height:1.6;color:#4A4A48;margin:0"><?php echo esc_html( $v['p'] ); ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
