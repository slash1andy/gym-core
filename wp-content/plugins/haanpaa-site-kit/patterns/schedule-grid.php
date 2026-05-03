<?php
/**
 * Title: Haanpaa · Schedule Grid
 * Slug: haanpaa/schedule-grid
 * Description: Day-tabbed class schedule with location toggle and program filter chips. Pulls live from the gym_class CPT.
 * Categories: haanpaa-section
 * Keywords: schedule, classes, times
 * Viewport Width: 1440
 */

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

/**
 * Map gym_program taxonomy slugs to the dot-color kind keys used by patterns.css.
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

$rows = [];
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

		$raw_day  = get_post_meta( $id, '_gym_class_day_of_week', true );
		$day      = haanpaa_day_to_label( (string) $raw_day );
		if ( ! $day ) { continue; } // Skip classes without a valid day.

		$start_time = get_post_meta( $id, '_gym_class_start_time', true ); // H:i 24hr
		$display_time = '';
		if ( $start_time ) {
			$ts = strtotime( '2000-01-01 ' . $start_time );
			$display_time = $ts ? date( 'g:i A', $ts ) : $start_time;
		}
		$end_time_raw = get_post_meta( $id, '_gym_class_end_time', true );
		$display_end  = '';
		if ( $end_time_raw ) {
			$ts = strtotime( '2000-01-01 ' . $end_time_raw );
			$display_end = $ts ? date( 'g:i A', $ts ) : $end_time_raw;
		}
		$sort_time = $start_time ?: '00:00'; // raw H:i, lexically sortable

		// Resolve program kind from gym_program taxonomy.
		$kind = 'bjj';
		$prog_terms = get_the_terms( $id, 'gym_program' );
		if ( $prog_terms && ! is_wp_error( $prog_terms ) ) {
			$kind = haanpaa_program_to_kind( $prog_terms[0]->slug );
		}

		// Resolve location slug.
		$location = 'rockford';
		$loc_terms = get_the_terms( $id, 'gym_location' );
		if ( $loc_terms && ! is_wp_error( $loc_terms ) ) {
			$location = $loc_terms[0]->slug;
		}

		// Resolve instructor display name.
		$coach = '';
		$instructor_id = (int) get_post_meta( $id, '_gym_class_instructor', true );
		if ( $instructor_id ) {
			$user  = get_userdata( $instructor_id );
			$coach = $user ? $user->display_name : '';
		}

		$rows[] = [
			'day'       => $day,
			'sort_time' => $sort_time,
			'time'      => $display_time,
			'end_time'  => $display_end,
			'name'      => get_the_title(),
			'kind'      => $kind,
			'location'  => $location,
			'coach'     => $coach,
		];
	}
	wp_reset_postdata();

	// Sort by day order, then time.
	$day_order = [ 'Mon' => 0, 'Tue' => 1, 'Wed' => 2, 'Thu' => 3, 'Fri' => 4, 'Sat' => 5, 'Sun' => 6 ];
	usort( $rows, function( $a, $b ) use ( $day_order ) {
		$da = $day_order[ $a['day'] ] ?? 9;
		$db = $day_order[ $b['day'] ] ?? 9;
		if ( $da !== $db ) { return $da - $db; }
		return strcmp( $a['sort_time'], $b['sort_time'] );
	} );
}

// Fallback placeholder schedule.
if ( empty( $rows ) ) {
	$rows = [
		[ 'day' => 'Mon', 'sort_time' => '06:00', 'time' => '6:00 AM',  'end_time' => '7:00 AM',  'name' => 'BJJ Fundamentals',     'kind' => 'bjj',  'location' => 'rockford', 'coach' => 'Darby' ],
		[ 'day' => 'Mon', 'sort_time' => '12:00', 'time' => '12:00 PM', 'end_time' => '',          'name' => 'Open Mat',              'kind' => 'bjj',  'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Mon', 'sort_time' => '17:00', 'time' => '5:00 PM',  'end_time' => '6:00 PM',  'name' => 'Kids Jiu-Jitsu (5–8)',  'kind' => 'kids', 'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Mon', 'sort_time' => '18:00', 'time' => '6:00 PM',  'end_time' => '7:00 PM',  'name' => 'Kids Jiu-Jitsu (9–13)', 'kind' => 'kids', 'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Mon', 'sort_time' => '19:00', 'time' => '7:00 PM',  'end_time' => '8:30 PM',  'name' => 'BJJ All Levels',        'kind' => 'bjj',  'location' => 'rockford', 'coach' => 'Darby' ],
		[ 'day' => 'Tue', 'sort_time' => '06:00', 'time' => '6:00 AM',  'end_time' => '7:00 AM',  'name' => 'Muay Thai',             'kind' => 'kick', 'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Tue', 'sort_time' => '19:00', 'time' => '7:00 PM',  'end_time' => '8:00 PM',  'name' => 'Muay Thai',             'kind' => 'kick', 'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Wed', 'sort_time' => '06:00', 'time' => '6:00 AM',  'end_time' => '7:00 AM',  'name' => 'BJJ Fundamentals',      'kind' => 'bjj',  'location' => 'rockford', 'coach' => 'Darby' ],
		[ 'day' => 'Wed', 'sort_time' => '17:00', 'time' => '5:00 PM',  'end_time' => '6:00 PM',  'name' => 'Kids Jiu-Jitsu (5–8)',  'kind' => 'kids', 'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Wed', 'sort_time' => '19:00', 'time' => '7:00 PM',  'end_time' => '8:30 PM',  'name' => 'No-Gi Jiu-Jitsu',      'kind' => 'bjj',  'location' => 'rockford', 'coach' => 'Darby' ],
		[ 'day' => 'Thu', 'sort_time' => '19:00', 'time' => '7:00 PM',  'end_time' => '8:00 PM',  'name' => 'Muay Thai Sparring',    'kind' => 'kick', 'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Fri', 'sort_time' => '06:00', 'time' => '6:00 AM',  'end_time' => '',          'name' => 'Open Mat',              'kind' => 'bjj',  'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Fri', 'sort_time' => '18:00', 'time' => '6:00 PM',  'end_time' => '7:30 PM',  'name' => 'BJJ All Levels',        'kind' => 'bjj',  'location' => 'rockford', 'coach' => 'Darby' ],
		[ 'day' => 'Sat', 'sort_time' => '10:00', 'time' => '10:00 AM', 'end_time' => '11:00 AM', 'name' => 'Kids Open Mat',         'kind' => 'kids', 'location' => 'rockford', 'coach' => '' ],
		[ 'day' => 'Sat', 'sort_time' => '11:30', 'time' => '11:30 AM', 'end_time' => '1:00 PM',  'name' => 'Adult Open Mat',        'kind' => 'bjj',  'location' => 'rockford', 'coach' => '' ],
	];
}

$days  = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
$kinds = [
	'all'  => 'All',
	'bjj'  => 'BJJ',
	'kick' => 'Kickboxing',
	'kids' => 'Kids',
];
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull" style="padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div data-wp-interactive="haanpaa/schedule">
    <div style="display:flex;justify-content:space-between;align-items:end;flex-wrap:wrap;gap:32px;margin-bottom:48px">
      <div>
        <p class="hp-eyebrow-mono">02 / SCHEDULE</p>
        <h2 style="font-size:clamp(40px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05;margin:16px 0 0">This week on the mats.</h2>
      </div>
      <div class="hp-seg" role="tablist" aria-label="Location">
        <?php foreach ( [ 'rockford' => 'Rockford', 'beloit' => 'Beloit' ] as $k => $label ) : ?>
        <button data-wp-context='<?php echo wp_json_encode( [ 'location' => $k ] ); ?>' data-wp-on--click="actions.setLoc" data-wp-bind--aria-pressed="state.locActive"<?php echo $k === 'rockford' ? ' aria-pressed="true"' : ''; ?>><?php echo esc_html( $label ); ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:32px">
      <?php foreach ( $kinds as $k => $label ) : ?>
      <button class="hp-chip" data-wp-context='<?php echo wp_json_encode( [ 'filter' => $k ] ); ?>' data-wp-on--click="actions.setFilter" data-wp-bind--aria-pressed="state.filterActive"<?php echo $k === 'all' ? ' aria-pressed="true"' : ''; ?>><?php echo esc_html( $label ); ?></button>
      <?php endforeach; ?>
    </div>

    <div class="hp-sch-tabs" role="tablist" aria-label="Day">
      <?php foreach ( $days as $d ) : ?>
      <button class="hp-sch-tab" role="tab" data-wp-context='<?php echo wp_json_encode( [ 'day' => $d ] ); ?>' data-wp-on--click="actions.setDay" data-wp-bind--aria-selected="state.dayActive"<?php echo $d === 'Mon' ? ' aria-selected="true"' : ''; ?>>
        <span style="font-size:22px;font-weight:600;letter-spacing:-0.01em"><?php echo esc_html( $d ); ?></span>
      </button>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:24px">
      <?php foreach ( $rows as $row ) : ?>
      <div class="hp-sch-row" data-wp-context='<?php echo wp_json_encode( [ 'day' => $row['day'], 'kind' => $row['kind'], 'location' => $row['location'] ] ); ?>' data-wp-bind--hidden="!state.rowVisible"<?php echo $row['day'] !== 'Mon' ? ' hidden' : ''; ?>>
        <span class="hp-meta" style="font-family:'Menlo',monospace;font-size:14px"><?php echo esc_html( $row['time'] ); if ( $row['end_time'] ) { echo ' – ' . esc_html( $row['end_time'] ); } ?></span>
        <span style="font-size:18px;font-weight:500"><span class="hp-sch-dot kind-<?php echo esc_attr( $row['kind'] ); ?>"></span><?php echo esc_html( $row['name'] ); ?></span>
        <?php if ( $row['coach'] ) : ?>
        <span class="hp-meta"><?php echo esc_html( $row['coach'] ); ?></span>
        <?php else : ?>
        <span></span>
        <?php endif; ?>
        <a href="/free-trial" style="text-align:right;color:#1A2DC4;font-size:13px;text-decoration:none">Drop in →</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
