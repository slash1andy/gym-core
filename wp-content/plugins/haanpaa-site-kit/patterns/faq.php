<?php
/**
 * Title: Haanpaa · FAQ
 * Slug: haanpaa/faq
 * Description: Single-open accordion of common questions.
 * Categories: haanpaa-section
 * Keywords: faq, questions, accordion
 * Viewport Width: 1440
 */
$faqs = [
    [ 'q' => "I've never trained. Will I be lost?",          'a' => "Our fundamentals classes are built for first-timers. You'll be drilling with other beginners on day one and rolling lightly within a week or two." ],
    [ 'q' => "What do I wear / what do I bring?",            'a' => "Athletic clothes for your first class — t-shirt and shorts (no zippers or pockets) work fine. We'll loan you a gi if you want to try BJJ. Bring water." ],
    [ 'q' => "How does the free week work?",                 'a' => "Seven calendar days, all programs, no card on file. Take as many classes as you want. If it fits, we talk membership at the end of the week." ],
    [ 'q' => "Is there a long-term contract?",               'a' => "No. Memberships are month-to-month. Cancel by emailing us. No fees, no friction." ],
    [ 'q' => "How old does my kid need to be?",              'a' => "Our youngest mat shoes are size 10. Realistically: 5+ for the kids program, with two age groups (5–8 and 9–13)." ],
    [ 'q' => "Can I just do kickboxing — no jiu-jitsu?",     'a' => "Absolutely. Many students train one program. Many train two. Your call." ],
];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#0A0A0A"}},"textColor":"paper","layout":{"type":"constrained","contentSize":"880px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-paper-color has-text-color has-background" style="background-color:#0A0A0A;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">
  <!-- wp:html -->
  <h2 style="font-size:clamp(40px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05;margin:16px 0 64px">Everything you're about to ask.</h2>
  <div data-wp-interactive="haanpaa/faq">
    <?php foreach ( $faqs as $i => $f ) : ?>
    <div class="hp-faq-row" data-wp-context='<?php echo wp_json_encode( [ 'idx' => $i ] ); ?>'>
      <button class="hp-faq-head" data-wp-on--click="actions.toggle" data-wp-bind--aria-expanded="state.isOpen">
        <span style="font-size:20px;font-weight:500;letter-spacing:-0.01em"><?php echo esc_html( $f['q'] ); ?></span>
        <span style="color:#1A2DC4;font-size:24px" data-wp-text="state.isOpen ? '−' : '+'"><?php echo $i === 0 ? '−' : '+'; ?></span>
      </button>
      <div class="hp-faq-body" data-wp-bind--hidden="!state.isOpen"<?php echo $i === 0 ? '' : ' hidden'; ?> style="padding:0 0 28px 0;color:#C9C7C1;font-size:16px;line-height:1.6;max-width:60ch">
        <?php echo esc_html( $f['a'] ); ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
