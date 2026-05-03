<?php
/**
 * Title: Haanpaa · Schedule Week
 * Slug: haanpaa/schedule-week
 * Description: Full-week class schedule displayed as a 7-column grid with location toggle and program filter chips.
 * Categories: haanpaa-section
 * Keywords: schedule, classes, week, times
 * Viewport Width: 1440
 */

if ( ! function_exists( 'haanpaa_day_to_label' ) ) {
	/**
	 * Map gym_class day-of-week strings (lowercase, full name) to 3-char display labels.
	 *
	 * @param string $day Full lowercase day name from _gym_class_day_of_week meta.
	 * @return string 3-char label (Mon, Tue, …) or empty string.
	 */
	function haanpaa_day_to_label( string $day ): string {
		$map = [
			'monday'    => 'Mon',
			'tuesday'   => 'Tue',
			'wednesday' => 'Wed',
			'thursday'  => 'Thu',
			'friday'    => 'Fri',
			'saturday'  => 'Sat',
			'sunday'    => 'Sun',
		];
		return $map[ strtolower( trim( $day ) ) ] ?? '';
	}
}

if ( ! function_exists( 'haanpaa_program_to_kind' ) ) {
	/**
	 * Map gym_program taxonomy slugs to the dot-color kind keys used by patterns.css.
	 *
	 * @param string $slug Taxonomy term slug.
	 * @return string Kind key: 'bjj', 'kick', or 'kids'.
	 */
	function haanpaa_program_to_kind( string $slug ): string {
		$map = [
			'bjj'            => 'bjj',
			'muay-thai'      => 'kick',
			'muay_thai'      => 'kick',
			'kickboxing'     => 'kick',
			'kids'           => 'kids',
			'kids-jiu-jitsu' => 'kids',
		];
		return $map[ strtolower( $slug ) ] ?? 'bjj';
	}
}

// Canonical day order for the week grid columns.
$day_order = [ 'Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4, 'Sat' => 5, 'Sun' => 6 ];
$days      = array_keys( $day_order );

// Color map indexed by kind.
$kind_colors = [
	'bjj'  => '#1A2DC4',
	'kick' => '#B26200',
	'kids' => '#2B8A5F',
];

// -----------------------------------------------------------------------
// Build schedule from DB, keyed by day label.
// -----------------------------------------------------------------------
$by_day = array_fill_keys( $days, [] );
$has_db = false;

if ( post_type_exists( 'gym_class' ) ) {
	$q = new WP_Query( [
		'post_type'      => 'gym_class',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => [
			[
				'key'     => '_gym_class_status',
				'value'   => 'active',
				'compare' => '=',
			],
		],
	] );

	while ( $q->have_posts() ) {
		$q->the_post();
		$id = get_the_ID();

		$raw_day = get_post_meta( $id, '_gym_class_day_of_week', true );
		$day     = haanpaa_day_to_label( (string) $raw_day );
		if ( ! $day ) { continue; }

		$start_time   = get_post_meta( $id, '_gym_class_start_time', true );
		$display_time = '';
		if ( $start_time ) {
			$ts = strtotime( '2000-01-01 ' . $start_time );
			$display_time = $ts ? date( 'g:i A', $ts ) : $start_time;
		}

		$kind       = 'bjj';
		$prog_terms = get_the_terms( $id, 'gym_program' );
		if ( $prog_terms && ! is_wp_error( $prog_terms ) ) {
			$kind = haanpaa_program_to_kind( $prog_terms[0]->slug );
		}

		$location = 'rockford';
		$loc_terms = get_the_terms( $id, 'gym_location' );
		if ( $loc_terms && ! is_wp_error( $loc_terms ) ) {
			$location = $loc_terms[0]->slug;
		}

		$who = get_post_meta( $id, '_gym_class_instructor', true );

		if ( isset( $by_day[ $day ] ) ) {
			$by_day[ $day ][] = [
				'time'     => $display_time,
				'name'     => get_the_title(),
				'kind'     => $kind,
				'who'      => (string) $who,
				'location' => $location,
			];
			$has_db = true;
		}
	}
	wp_reset_postdata();

	// Sort each day's classes by display time.
	if ( $has_db ) {
		foreach ( $by_day as $d => $classes ) {
			usort( $by_day[ $d ], function( $a, $b ) {
				return strcmp( $a['time'], $b['time'] );
			} );
		}
	}
}

// -----------------------------------------------------------------------
// Fallback hardcoded schedule if no DB classes found.
// -----------------------------------------------------------------------
if ( ! $has_db ) {
	$fallback = [
		'Mon' => [
			[ 'time' => '6:00 AM',  'name' => 'Open Mat',            'kind' => 'bjj',  'who' => 'All levels' ],
			[ 'time' => '12:00 PM', 'name' => 'Fitness Kickboxing',  'kind' => 'kick', 'who' => 'All levels' ],
			[ 'time' => '4:30 PM',  'name' => 'Kids Jiu-Jitsu',      'kind' => 'kids', 'who' => 'Ages 5–8' ],
			[ 'time' => '5:30 PM',  'name' => 'Kids Jiu-Jitsu',      'kind' => 'kids', 'who' => 'Ages 9–12' ],
			[ 'time' => '6:30 PM',  'name' => 'BJJ Fundamentals',    'kind' => 'bjj',  'who' => 'Beginners' ],
			[ 'time' => '7:30 PM',  'name' => 'BJJ Advanced',        'kind' => 'bjj',  'who' => 'Blue belt+' ],
		],
		'Tue' => [
			[ 'time' => '6:00 AM',  'name' => 'Fitness Kickboxing',  'kind' => 'kick', 'who' => 'All levels' ],
			[ 'time' => '12:00 PM', 'name' => 'BJJ All Levels',      'kind' => 'bjj',  'who' => 'All levels' ],
			[ 'time' => '4:30 PM',  'name' => 'Kids Jiu-Jitsu',      'kind' => 'kids', 'who' => 'Ages 5–8' ],
		],
		'Wed' => [
			[ 'time' => '6:00 AM',  'name' => 'Open Mat',            'kind' => 'bjj',  'who' => 'All levels' ],
			[ 'time' => '12:00 PM', 'name' => 'Fitness Kickboxing',  'kind' => 'kick', 'who' => 'All levels' ],
			[ 'time' => '4:30 PM',  'name' => 'Kids Jiu-Jitsu',      'kind' => 'kids', 'who' => 'Ages 5–8' ],
			[ 'time' => '5:30 PM',  'name' => 'Kids Jiu-Jitsu',      'kind' => 'kids', 'who' => 'Ages 9–12' ],
			[ 'time' => '6:30 PM',  'name' => 'BJJ Fundamentals',    'kind' => 'bjj',  'who' => 'Beginners' ],
			[ 'time' => '7:30 PM',  'name' => 'No-Gi BJJ',           'kind' => 'bjj',  'who' => 'All levels' ],
		],
		'Thu' => [
			[ 'time' => '6:00 AM',  'name' => 'Fitness Kickboxing',  'kind' => 'kick', 'who' => 'All levels' ],
			[ 'time' => '12:00 PM', 'name' => 'BJJ All Levels',      'kind' => 'bjj',  'who' => 'All levels' ],
			[ 'time' => '4:30 PM',  'name' => 'Kids Jiu-Jitsu',      'kind' => 'kids', 'who' => 'Ages 9–12' ],
		],
		'Fri' => [
			[ 'time' => '6:00 AM',  'name' => 'Fitness Kickboxing',  'kind' => 'kick', 'who' => 'All levels' ],
			[ 'time' => '12:00 PM', 'name' => 'Open Mat',            'kind' => 'bjj',  'who' => 'All levels' ],
			[ 'time' => '4:30 PM',  'name' => 'Kids Jiu-Jitsu',      'kind' => 'kids', 'who' => 'Ages 5–12' ],
			[ 'time' => '6:00 PM',  'name' => 'BJJ Sparring',        'kind' => 'bjj',  'who' => 'Blue belt+' ],
		],
		'Sat' => [
			[ 'time' => '9:00 AM',  'name' => 'Family Open Mat',     'kind' => 'kids', 'who' => 'All ages' ],
			[ 'time' => '10:30 AM', 'name' => 'BJJ Fundamentals',    'kind' => 'bjj',  'who' => 'Beginners' ],
		],
		'Sun' => [],
	];

	foreach ( $fallback as $day => $classes ) {
		$by_day[ $day ] = array_map( function( $c ) {
			return array_merge( [ 'location' => 'rockford' ], $c );
		}, $classes );
	}
}
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(32px,4vw,48px)","bottom":"clamp(48px,6vw,80px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull" style="padding-top:clamp(32px,4vw,48px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(48px,6vw,80px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div data-wp-interactive="haanpaa/schedule">

    <!-- Controls: location toggle + program filter chips -->
    <div class="hp-sch-week-controls" style="margin-bottom:40px">
      <div class="hp-seg" role="tablist" aria-label="Location">
        <?php foreach ( [ 'rockford' => 'Rockford', 'beloit' => 'Beloit' ] as $loc_key => $loc_label ) : ?>
        <button
          data-wp-context='<?php echo wp_json_encode( [ 'location' => $loc_key ] ); ?>'
          data-wp-on--click="actions.setLoc"
          data-wp-bind--aria-pressed="state.locActive"
          <?php echo 'rockford' === $loc_key ? ' aria-pressed="true"' : ''; ?>
        ><?php echo esc_html( $loc_label ); ?></button>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ( [ 'all' => 'All programs', 'bjj' => 'Jiu-Jitsu', 'kick' => 'Kickboxing', 'kids' => 'Kids' ] as $filter_key => $filter_label ) : ?>
        <button
          class="hp-chip"
          data-wp-context='<?php echo wp_json_encode( [ 'filter' => $filter_key ] ); ?>'
          data-wp-on--click="actions.setFilter"
          data-wp-bind--aria-pressed="state.filterActive"
          <?php echo 'all' === $filter_key ? ' aria-pressed="true"' : ''; ?>
        ><?php echo esc_html( $filter_label ); ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Week grid: 7 columns, one per day -->
    <div class="hp-sch-week" role="grid" aria-label="Weekly class schedule">
      <?php foreach ( $days as $day ) : ?>
      <div class="hp-sch-week-col" role="rowgroup">

        <!-- Column header -->
        <div class="hp-sch-week-col-head">
          <span class="hp-sch-week-col-day hp-eyebrow-mono"><?php echo esc_html( $day ); ?></span>
          <span class="hp-sch-week-col-count hp-meta"><?php echo count( $by_day[ $day ] ); ?> classes</span>
        </div>

        <!-- Class cards -->
        <?php if ( empty( $by_day[ $day ] ) ) : ?>
        <div class="hp-sch-week-empty" aria-label="No classes scheduled">&mdash;</div>
        <?php else : ?>
        <?php foreach ( $by_day[ $day ] as $card ) :
          $color = $kind_colors[ $card['kind'] ] ?? '#1A2DC4';
          $ctx   = wp_json_encode( [ 'kind' => $card['kind'], 'loc' => $card['loc'] ?? 'rockford' ] );
        ?>
        <div
          class="hp-sch-week-card"
          role="row"
          style="border-left-color:<?php echo esc_attr( $color ); ?>"
          data-wp-interactive="haanpaa/schedule"
          data-wp-context='<?php echo $ctx; ?>'
          data-wp-bind--hidden="!state.cardVisible"
        >
          <span class="hp-sch-week-card-time hp-meta"><?php echo esc_html( $card['time'] ); ?></span>
          <span class="hp-sch-week-card-name hp-body-lg"><?php echo esc_html( $card['name'] ); ?></span>
          <span class="hp-sch-week-card-kind">
            <span class="hp-sch-week-card-dot" style="background:<?php echo esc_attr( $color ); ?>"></span>
            <span class="hp-sch-week-card-kind-label hp-meta"><?php
              $kind_labels = [ 'bjj' => 'BJJ', 'kick' => 'Kickboxing', 'kids' => 'Kids' ];
              echo esc_html( $kind_labels[ $card['kind'] ] ?? $card['kind'] );
            ?></span>
          </span>
          <?php if ( $card['who'] ) : ?>
          <span class="hp-sch-week-card-who hp-meta"><?php echo esc_html( $card['who'] ); ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

      </div><!-- /.hp-sch-week-col -->
      <?php endforeach; ?>
    </div><!-- /.hp-sch-week -->

  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
