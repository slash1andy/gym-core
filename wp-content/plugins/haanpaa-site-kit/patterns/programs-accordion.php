<?php
/**
 * Title: Haanpaa · Programs Accordion
 * Slug: haanpaa/programs-accordion
 * Description: Single-open accordion of the core programs. Sourced from the gym_program taxonomy.
 * Categories: haanpaa-section
 * Keywords: programs, accordion, classes
 * Viewport Width: 1440
 */

// Map gym_program term slugs to dot/color identifiers used by the Interactivity API store.
$slug_to_id = [
	'bjj'            => 'bjj',
	'adult-bjj'      => 'bjj',
	'muay-thai'      => 'kick',
	'muay_thai'      => 'kick',
	'kickboxing'     => 'kick',
	'kids'           => 'kids',
	'kids-bjj'       => 'kids',
	'kids-jiu-jitsu' => 'kids',
];

// Map slugs to display names so slug-style term names in the DB render as proper titles.
$slug_to_name = [
	'bjj'            => 'Brazilian Jiu-Jitsu',
	'adult-bjj'      => 'Brazilian Jiu-Jitsu',
	'kickboxing'     => 'Kickboxing',
	'muay-thai'      => 'Kickboxing',
	'muay_thai'      => 'Kickboxing',
	'kids'           => 'Kids Jiu-Jitsu',
	'kids-bjj'       => 'Kids Jiu-Jitsu',
	'kids-jiu-jitsu' => 'Kids Jiu-Jitsu',
];

$rows = [];
if ( taxonomy_exists( 'gym_program' ) ) {
	$terms = get_terms( [
		'taxonomy'   => 'gym_program',
		'hide_empty' => false,
		'orderby'    => 'term_order',
		'order'      => 'ASC',
	] );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $i => $term ) {
			$id = $slug_to_id[ $term->slug ] ?? $term->slug;

			// Count active classes for this program.
			$class_count = (int) ( new WP_Query( [
				'post_type'      => 'gym_class',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [ [ 'key' => '_gym_class_status', 'value' => 'active' ] ],
				'tax_query'      => [ [ 'taxonomy' => 'gym_program', 'field' => 'term_id', 'terms' => $term->term_id ] ],
			] ) )->found_posts;

			$term_link = get_term_link( $term );

			$rows[] = [
				'id'       => $id,
				'num'      => sprintf( '%02d', $i + 1 ),
				'name'     => $slug_to_name[ $term->slug ] ?? $term->name,
				'desc'     => wp_strip_all_tags( $term->description ),
				'long'     => $term->description ?: 'Details coming soon.',
				'sessions' => $class_count ? $class_count . ' / week' : '',
				'open'     => $i === 0,
				'link'     => ! is_wp_error( $term_link ) ? $term_link : '#',
			];
		}
	}
}

// Fallback when no gym_program terms exist.
if ( empty( $rows ) ) {
	$rows = [
		[ 'id' => 'bjj',  'num' => '01', 'name' => 'Brazilian Jiu-Jitsu',  'desc' => 'Jeff Curran lineage. Gi &amp; no-gi.',         'long' => 'The art of submission grappling. We coach the IBJJF curriculum with a fundamentals-first ethos — drilling positions, escapes, and the small-game details that win matches. Beginners get their own dedicated track.', 'sessions' => '', 'open' => true,  'link' => '#' ],
		[ 'id' => 'kick', 'num' => '02', 'name' => 'Kickboxing',           'desc' => 'Striking, conditioning, sparring on Fridays.',                   'long' => 'Stand-up striking with shins, knees, elbows, and clinch. Optional sparring twice a week. Fitness kickboxing track for students who want the work without the hits.', 'sessions' => '', 'open' => false, 'link' => '#' ],
		[ 'id' => 'kids', 'num' => '03', 'name' => 'Kids Jiu-Jitsu',      'desc' => 'Anti-bullying curriculum, character development.',               'long' => 'Confidence, discipline, and real grappling skill — without aggression. Two age groups (5–8 and 9–13) keep classes paced and safe.', 'sessions' => '', 'open' => false, 'link' => '#' ],
	];
}
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#F6F4EE"}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#F6F4EE;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:paragraph {"className":"hp-eyebrow-mono","style":{"text":{"textAlign":"center"}}} --><p class="hp-eyebrow-mono" style="text-align:center">01 / PROGRAMS</p><!-- /wp:paragraph -->
  <!-- wp:heading {"level":2,"textAlign":"center","style":{"typography":{"fontSize":"clamp(40px,5vw,72px)","lineHeight":"1.05","letterSpacing":"-0.03em","fontWeight":"600"}}} -->
  <h2 class="wp-block-heading has-text-align-center" style="font-size:clamp(40px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05">Three Programs. One Mat. No Ego.</h2>
  <!-- /wp:heading -->

  <!-- wp:html -->
  <div data-wp-interactive="haanpaa/programs" style="margin-top:80px">
    <?php foreach ( $rows as $r ) : ?>
    <div class="hp-acc-row" data-wp-context='<?php echo wp_json_encode( [ 'id' => $r['id'] ] ); ?>'>
      <button class="hp-acc-head" data-wp-on--click="actions.toggle" data-wp-bind--aria-expanded="state.isOpen">
        <span class="hp-meta"><?php echo esc_html( $r['num'] ); ?></span>
        <span style="font-size:32px;font-weight:600;letter-spacing:-0.02em"><?php echo esc_html( $r['name'] ); ?></span>
        <span style="color:#4A4A48;font-size:15px"><?php echo esc_html( $r['desc'] ); ?></span>
        <?php if ( $r['sessions'] ) : ?>
        <span class="hp-meta"><?php echo esc_html( $r['sessions'] ); ?></span>
        <?php else : ?>
        <span></span>
        <?php endif; ?>
        <span style="text-align:right;font-size:24px;color:#1A2DC4" data-wp-text="state.isOpen ? '−' : '+'"><?php echo $r['open'] ? '−' : '+'; ?></span>
      </button>
      <div class="hp-acc-body" data-wp-bind--hidden="!state.isOpen"<?php echo empty( $r['open'] ) ? ' hidden' : ''; ?>>
        <div>
          <p style="font-size:18px;line-height:1.6;color:#1F1F1D;max-width:56ch"><?php echo wp_kses_post( $r['long'] ); ?></p>
          <a href="<?php echo esc_url( $r['link'] ); ?>" style="display:inline-block;margin-top:32px;color:#1A2DC4;text-decoration:none;border-bottom:1px solid currentColor;padding-bottom:2px">See full program →</a>
        </div>
        <div class="hp-photo">[<?php echo esc_html( strtoupper( $r['name'] ) ); ?> photo]</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
