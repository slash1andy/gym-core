<?php
/**
 * Title: Haanpaa · Locations
 * Slug: haanpaa/locations
 * Description: Two-up location cards with address, phone, hours, and map placeholder. Sourced from gym_location taxonomy term meta.
 * Categories: haanpaa-section
 * Keywords: locations, address, map
 * Viewport Width: 1440
 */

$locations = [];
if ( taxonomy_exists( 'gym_location' ) ) {
	$terms = get_terms( [ 'taxonomy' => 'gym_location', 'hide_empty' => false ] );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$hours_raw = get_term_meta( $term->term_id, '_gym_hours_json', true );
			$hours_arr = $hours_raw ? json_decode( $hours_raw, true ) : [];

			// Build a human-readable hours string from structured JSON.
			$hours_display = '';
			if ( is_array( $hours_arr ) ) {
				$lines = [];
				foreach ( $hours_arr as $h ) {
					$day    = substr( $h['day'] ?? '', 0, 3 );
					$opens  = isset( $h['opens'] )  ? date( 'g A', strtotime( '2000-01-01 ' . $h['opens'] ) )  : '';
					$closes = isset( $h['closes'] ) ? date( 'g A', strtotime( '2000-01-01 ' . $h['closes'] ) ) : '';
					if ( $day && $opens && $closes ) {
						$lines[] = "$day · $opens – $closes";
					}
				}
				$hours_display = implode( "\n", $lines );
			}

			$is_primary = (bool) get_term_meta( $term->term_id, '_gym_is_primary', true );

			$locations[] = [
				'name'        => $term->name . ( $is_primary ? ' · HQ' : ' · Satellite' ),
				'address'     => get_term_meta( $term->term_id, '_gym_address', true ),
				'city_state'  => get_term_meta( $term->term_id, '_gym_city_state_zip', true ),
				'phone'       => get_term_meta( $term->term_id, '_gym_phone', true ),
				'hours'       => $hours_display,
				'is_primary'  => $is_primary,
			];
		}
		// Primary location first.
		usort( $locations, fn( $a, $b ) => (int) $b['is_primary'] - (int) $a['is_primary'] );
	}
}

// Fallback.
if ( empty( $locations ) ) {
	$locations = [
		[ 'name' => 'Rockford · HQ',       'address' => '4911 26th Avenue',  'city_state' => 'Rockford, IL 61109', 'phone' => '(815) 451-3001', 'hours' => "Mon–Fri · 6 AM – 8 PM\nSat · 9 AM – 1 PM",         'is_primary' => true  ],
		[ 'name' => 'Beloit · Satellite',   'address' => '610 4th Street',    'city_state' => 'Beloit, WI 53511',   'phone' => '(815) 451-3001', 'hours' => "Mon · Wed · Fri · 5–7 PM\nSat · 10 AM",              'is_primary' => false ],
	];
}
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull" style="padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"24px","left":"80px"}}}} -->
  <div class="wp-block-columns">
    <!-- wp:column {"width":"30%"} -->
    <div class="wp-block-column" style="flex-basis:30%">
      <!-- wp:paragraph {"className":"hp-eyebrow-mono"} --><p class="hp-eyebrow-mono">06 / LOCATIONS</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
    <!-- wp:column {"width":"70%"} -->
    <div class="wp-block-column" style="flex-basis:70%">
      <!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(40px,5vw,72px)","lineHeight":"1.05","letterSpacing":"-0.03em","fontWeight":"600"}}} -->
      <h2 class="wp-block-heading" style="font-size:clamp(40px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05">Two locations. Same standard.</h2>
      <!-- /wp:heading -->
    </div>
    <!-- /wp:column -->
  </div>
  <!-- /wp:columns -->

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:48px;margin-top:80px">
    <?php foreach ( $locations as $loc ) : ?>
    <div>
      <div class="hp-photo" style="aspect-ratio:4/3;margin-bottom:40px">[<?php echo esc_html( strtoupper( $loc['name'] ) ); ?> photo]</div>
      <h3 style="font-size:28px;font-weight:600;letter-spacing:-0.02em;margin:0 0 24px"><?php echo esc_html( $loc['name'] ); ?></h3>
      <dl style="display:grid;gap:12px;font-size:15px;color:#4A4A48">
        <div>
          <dt class="hp-meta" style="margin-bottom:4px">Address</dt>
          <dd style="margin:0"><?php echo esc_html( $loc['address'] ); ?><br><?php echo esc_html( $loc['city_state'] ); ?></dd>
        </div>
        <div>
          <dt class="hp-meta" style="margin-bottom:4px">Phone</dt>
          <dd style="margin:0"><a href="tel:<?php echo esc_attr( preg_replace( '/\D/', '', $loc['phone'] ) ); ?>" style="color:inherit;text-decoration:none"><?php echo esc_html( $loc['phone'] ); ?></a></dd>
        </div>
        <?php if ( $loc['hours'] ) : ?>
        <div>
          <dt class="hp-meta" style="margin-bottom:4px">Hours</dt>
          <dd style="margin:0;white-space:pre-line"><?php echo esc_html( $loc['hours'] ); ?></dd>
        </div>
        <?php endif; ?>
      </dl>
      <a href="/free-trial" style="display:inline-block;margin-top:32px;padding:14px 28px;background:#1A2DC4;color:#F6F4EE;text-decoration:none;font-weight:500;font-size:15px;border-radius:2px">Book a free trial →</a>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
