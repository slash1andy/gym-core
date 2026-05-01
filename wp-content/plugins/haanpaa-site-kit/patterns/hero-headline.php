<?php
/**
 * Title: Haanpaa · Hero (Headline + CTA + Location selector + Live class)
 * Slug: haanpaa/hero-headline
 * Description: Full-bleed homepage hero — two-column grid with bold Fraunces headline, CTAs, live location selector wired to gym_class CPT, and a photo placeholder with a live-class overlay card.
 * Categories: haanpaa-hero
 * Keywords: hero, header, banner
 * Viewport Width: 1440
 */

/**
 * Returns the next upcoming active gym_class for a given location slug.
 * Tries today's remaining classes first; falls back to the earliest class
 * in the schedule (any day) if nothing is left today.
 *
 * @param string $location_slug gym_location term slug ('rockford', 'beloit').
 * @return array{title:string,time_display:string,kind:string,day_label:string}|null
 */
if ( ! function_exists( 'haanpaa_hero_next_class' ) ) {
	function haanpaa_hero_next_class( string $location_slug ): ?array {
		if ( ! post_type_exists( 'gym_class' ) ) {
			return null;
		}

		$now_ts   = current_time( 'timestamp' );
		$today    = strtolower( date( 'l', $now_ts ) ); // e.g. "thursday"
		$now_time = date( 'H:i', $now_ts );             // e.g. "18:15"

		$base_tax = [
			[
				'taxonomy' => 'gym_location',
				'field'    => 'slug',
				'terms'    => $location_slug,
			],
		];

		// First: today's remaining classes at this location.
		$q = new WP_Query( [
			'post_type'      => 'gym_class',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_gym_class_start_time',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'tax_query'      => $base_tax,
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_gym_class_status',      'value' => 'active' ],
				[ 'key' => '_gym_class_day_of_week', 'value' => $today   ],
				[ 'key' => '_gym_class_start_time',  'value' => $now_time, 'compare' => '>=' ],
			],
		] );

		// Fallback: earliest active class for this location (any day/time).
		if ( ! $q->have_posts() ) {
			$q = new WP_Query( [
				'post_type'      => 'gym_class',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_gym_class_start_time',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'tax_query'      => $base_tax,
				'meta_query'     => [
					[ 'key' => '_gym_class_status', 'value' => 'active' ],
				],
			] );
		}

		if ( ! $q->have_posts() ) {
			return null;
		}

		$q->the_post();
		$post_id  = get_the_ID();
		$start    = get_post_meta( $post_id, '_gym_class_start_time', true );
		$ts       = $start ? strtotime( '2000-01-01 ' . $start ) : false;
		$display  = $ts ? date( 'g:i A', $ts ) : $start;
		$day_name = get_post_meta( $post_id, '_gym_class_day_of_week', true );

		$kind     = 'bjj';
		$kind_map = [
			'bjj'            => 'bjj',
			'muay-thai'      => 'kick',
			'muay_thai'      => 'kick',
			'kickboxing'     => 'kick',
			'kids'           => 'kids',
			'kids-jiu-jitsu' => 'kids',
		];
		$prog_terms = get_the_terms( $post_id, 'gym_program' );
		if ( $prog_terms && ! is_wp_error( $prog_terms ) ) {
			$kind = $kind_map[ $prog_terms[0]->slug ] ?? 'bjj';
		}

		$title = get_the_title();
		wp_reset_postdata();

		return [
			'title'        => $title,
			'time_display' => $display,
			'kind'         => $kind,
			'day_label'    => ucfirst( $day_name ?: $today ),
		];
	}
}

/**
 * Returns display metadata for a gym_location term.
 *
 * @param string $slug Term slug ('rockford', 'beloit').
 * @return array{city:string,city_state_zip:string,phone:string}
 */
if ( ! function_exists( 'haanpaa_hero_location_meta' ) ) {
	function haanpaa_hero_location_meta( string $slug ): array {
		$defaults = [ 'city' => ucfirst( $slug ), 'city_state_zip' => '', 'phone' => '' ];
		if ( ! taxonomy_exists( 'gym_location' ) ) {
			return $defaults;
		}
		$term = get_term_by( 'slug', $slug, 'gym_location' );
		if ( ! $term || is_wp_error( $term ) ) {
			return $defaults;
		}
		$id = $term->term_id;
		return [
			'city'           => $term->name,
			'city_state_zip' => (string) ( get_term_meta( $id, '_gym_city_state_zip', true ) ?: '' ),
			'phone'          => (string) ( get_term_meta( $id, '_gym_phone', true ) ?: '' ),
		];
	}
}

// ── Gather runtime data ───────────────────────────────────────────────────────

$loc_slugs = [ 'rockford', 'beloit' ];
$today_dow = strtolower( date( 'l', current_time( 'timestamp' ) ) );

$loc_data = [];
foreach ( $loc_slugs as $slug ) {
	$meta             = haanpaa_hero_location_meta( $slug );
	$meta['slug']     = $slug;
	$meta['next']     = haanpaa_hero_next_class( $slug );
	$loc_data[ $slug ] = $meta;
}
?>
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"paper","style":{"spacing":{"padding":{"top":"80px","bottom":"0","left":"0","right":"0"}}},"layout":{"type":"constrained","contentSize":"1440px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-paper-background-color has-background" style="background-color:#F6F4EE;padding-top:80px">

<!-- wp:html -->
<div style="padding:0 clamp(24px,4vw,80px)">
  <div class="hp-hero-grid">

    <!-- ── LEFT COLUMN ─────────────────────────────────────── -->
    <div>

      <div class="hp-eyebrow" style="margin-bottom:28px">Haanpaa Martial Arts</div>

      <h1 class="hp-hero-h1" style="font-family:'Fraunces',serif;font-size:clamp(80px,10vw,156px);line-height:0.86;letter-spacing:-0.04em;font-weight:700;margin-top:0;margin-bottom:56px">
        Train<br>like it<br><span style="color:#1A2DC4">matters.</span>
      </h1>

      <p style="max-width:480px;margin-bottom:36px;font-size:20px;line-height:1.5;color:#181816">
        A family-run martial arts school teaching Brazilian Jiu-Jitsu &#8212; for adults
        and kids &#8212; and Fitness Kickboxing. Beginners welcome; most of our members
        started exactly where you are now.
      </p>

      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <a href="/free-trial"
           style="display:inline-flex;align-items:center;gap:8px;padding:20px 36px;background:#1A2DC4;color:#F6F4EE;font-size:15px;font-weight:600;letter-spacing:0.04em;text-decoration:none;border-radius:2px;line-height:1;transition:background 160ms">
          Book your free trial &#x2192;
        </a>
        <a href="/schedule"
           style="display:inline-flex;align-items:center;padding:19px 36px;background:transparent;color:#0A0A0A;font-size:15px;font-weight:600;letter-spacing:0.04em;text-decoration:none;border-radius:2px;border:1px solid rgba(10,10,10,0.25);line-height:1;transition:border-color 160ms">
          See class schedule
        </a>
      </div>

      <!-- Location selector + affiliation strip -->
      <div style="margin-top:56px;display:flex;gap:48px;align-items:flex-start;flex-wrap:wrap">

        <div>
          <div class="hp-meta" style="margin-bottom:10px">Location</div>
          <div class="hp-seg" data-wp-interactive="haanpaa/schedule">
            <?php foreach ( $loc_slugs as $slug ) :
              $is_default = ( 'rockford' === $slug );
            ?>
            <button
              data-wp-context='{"location":"<?php echo esc_attr( $slug ); ?>"}'
              data-wp-on--click="actions.setLoc"
              data-wp-bind--aria-pressed="state.locActive"
              aria-pressed="<?php echo $is_default ? 'true' : 'false'; ?>"
            ><?php echo esc_html( ucfirst( $slug ) ); ?></button>
            <?php endforeach; ?>
          </div>
          <?php foreach ( $loc_data as $slug => $loc ) :
            $is_default = ( 'rockford' === $slug );
            $addr       = $loc['city_state_zip'] ?: ucfirst( $slug );
          ?>
          <div
            data-wp-interactive="haanpaa/schedule"
            data-wp-context='{"location":"<?php echo esc_attr( $slug ); ?>"}'
            data-wp-bind--hidden="!state.locActive"
            <?php echo $is_default ? '' : 'hidden'; ?>
            style="margin-top:6px;font-size:13px;color:#4A4A48"
          ><?php echo esc_html( $addr ); ?></div>
          <?php endforeach; ?>
        </div>

        <div>
          <div class="hp-meta" style="margin-bottom:10px">Affiliation</div>
          <div style="font-size:20px;font-weight:600;letter-spacing:-0.01em;color:#0A0A0A">Team Curran</div>
        </div>

      </div>
    </div><!-- /left -->

    <!-- ── RIGHT COLUMN ────────────────────────────────────── -->
    <div class="hp-photo-wrap">

      <div class="hp-photo hp-photo-bjj">
        <span style="position:relative;z-index:1">photo &#xB7; BJJ class on the mat</span>
      </div>

      <?php foreach ( $loc_slugs as $loc_slug ) :
        $cls        = $loc_data[ $loc_slug ]['next'];
        $is_default = ( 'rockford' === $loc_slug );
        $is_today   = $cls && ( strtolower( $cls['day_label'] ) === $today_dow );
        $live_label = $is_today ? '&#9679; Next &middot; Tonight' : '&#9679; Up Next';
      ?>
      <div class="hp-photo-overlay-card"
           data-wp-interactive="haanpaa/schedule"
           data-wp-context='{"location":"<?php echo esc_attr( $loc_slug ); ?>"}'
           data-wp-bind--hidden="!state.locActive"
           <?php echo $is_default ? '' : 'hidden'; ?>>
        <?php if ( $cls ) : ?>
          <div class="hp-eyebrow-mono" style="color:#1A2DC4"><?php echo $live_label; ?></div>
          <div style="margin-top:8px;font-size:16px;font-weight:600;letter-spacing:-0.01em;color:#0A0A0A">
            <?php echo esc_html( $cls['title'] ) . ' &middot; ' . esc_html( $cls['time_display'] ); ?>
          </div>
          <div style="margin-top:4px;font-size:13px;color:#4A4A48">
            Beginners welcome. Loaner gi available at the front desk.
          </div>
        <?php else : ?>
          <div class="hp-eyebrow-mono" style="color:#9A9A98">Schedule</div>
          <div style="margin-top:8px;font-size:16px;font-weight:600;color:#0A0A0A">
            <a href="/schedule" style="color:inherit;text-decoration:none">See today&#8217;s classes &#x2192;</a>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

    </div><!-- /right -->

  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
