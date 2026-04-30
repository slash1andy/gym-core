<?php
/**
 * Title: Haanpaa · Reviews
 * Slug: haanpaa/reviews
 * Description: Three-up student testimonial cards.
 * Categories: haanpaa-section
 * Keywords: reviews, testimonials, students
 * Viewport Width: 1440
 */
$reviews = [];
if ( post_type_exists( 'hp_testimonial' ) ) {
    $q = new WP_Query( [ 'post_type' => 'hp_testimonial', 'posts_per_page' => 3 ] );
    while ( $q->have_posts() ) { $q->the_post();
        $reviews[] = [
            'quote'  => wp_strip_all_tags( get_the_content() ),
            'author' => get_the_title(),
            'meta'   => get_post_meta( get_the_ID(), 'hp_context', true ),
        ];
    }
    wp_reset_postdata();
}
if ( empty( $reviews ) ) {
    $reviews = [
        [ 'quote'=>'I walked in expecting to feel out of place. Three classes in, I was rolling with people who\'d been training for years and they made me feel like I belonged.', 'author'=>'[Student name]', 'meta'=>'Adult BJJ · 6 months in' ],
        [ 'quote'=>'My daughter was the quiet kid. Two months at Haanpaa and she\'s the one helping new kids tie their belts.', 'author'=>'[Parent name]', 'meta'=>'Kids program parent' ],
        [ 'quote'=>'The Brazilian Jiu-Jitsu program is second to none, and Muay Thai is incredible.', 'author'=>'[Student name]', 'meta'=>'Adult · multi-program' ],
    ];
}
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#F6F4EE"}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#F6F4EE;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">
  <!-- wp:html -->
  <p class="hp-eyebrow-mono">05 / FROM STUDENTS</p>
  <h2 style="font-size:clamp(40px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05;margin:16px 0 64px;max-width:18ch">"The hardest part is simply, getting there."</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:32px">
    <?php foreach ( $reviews as $i => $r ) : ?>
    <figure style="background:#fff;padding:40px;border:1px solid rgba(10,10,10,0.08);margin:0">
      <p class="hp-meta" style="color:#9A9A98">0<?php echo $i + 1; ?></p>
      <blockquote style="font-size:20px;line-height:1.5;margin:24px 0;font-weight:500;letter-spacing:-0.01em">"<?php echo esc_html( $r['quote'] ); ?>"</blockquote>
      <figcaption style="border-top:1px solid rgba(10,10,10,0.08);padding-top:20px">
        <div style="font-weight:600;font-size:15px"><?php echo esc_html( $r['author'] ); ?></div>
        <div class="hp-meta" style="margin-top:4px"><?php echo esc_html( $r['meta'] ); ?></div>
      </figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
