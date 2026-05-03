<?php
/**
 * Title: Haanpaa · Coaches Strip
 * Slug: haanpaa/coaches-strip
 * Description: Three-up coach cards with portrait placeholder, name, role, and bio. Sourced from WP users with gym_coach or gym_head_coach roles.
 * Categories: haanpaa-section
 * Keywords: coaches, instructors, team
 * Viewport Width: 1440
 */

$role_labels = [
	'gym_head_coach' => 'Head Instructor',
	'gym_coach'      => 'Instructor',
];

$coaches = [];
$users   = get_users( [
	'role__in' => [ 'gym_head_coach', 'gym_coach' ],
	'number'   => 3,
	'orderby'  => 'meta_value',
	'meta_key' => 'last_name',
	'order'    => 'ASC',
] );

foreach ( $users as $user ) {
	// Determine display role from the user's first matching role.
	$role = 'Instructor';
	foreach ( array_keys( $role_labels ) as $r ) {
		if ( in_array( $r, (array) $user->roles, true ) ) {
			$role = $role_labels[ $r ];
			break;
		}
	}

	$coaches[] = [
		'name' => $user->display_name,
		'role' => $role,
		'bio'  => $user->description ?: '',
	];
}

// Fallback placeholder coaches.
if ( empty( $coaches ) ) {
	$coaches = [
		[ 'name' => 'Darby Haanpaa', 'role' => 'Black Belt · Head Instructor',       'bio' => 'Founded the academy in 2003. Pedro Sauer / Royler Gracie lineage. Coaches the adult BJJ curriculum and competition team.' ],
		[ 'name' => 'Coach Lee',      'role' => 'Muay Thai · Striking Lead',          'bio' => 'Two decades on the pads. Built the kickboxing program from a Friday-night class into a six-day-a-week curriculum.' ],
		[ 'name' => 'Coach Mara',     'role' => 'Brown Belt · Kids Program Lead',     'bio' => "Runs the kids program. Specializes in beginner pedagogy." ],
	];
}
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#0A0A0A"}},"textColor":"paper","layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-paper-color has-text-color has-background" style="background-color:#0A0A0A;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:paragraph {"className":"hp-eyebrow-mono","style":{"color":{"text":"#9A9A98"}}} --><p class="hp-eyebrow-mono" style="color:#9A9A98">03 / COACHES</p><!-- /wp:paragraph -->
  <!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(40px,5vw,72px)","lineHeight":"1.05","letterSpacing":"-0.03em","fontWeight":"600"},"spacing":{"margin":{"top":"16px","bottom":"80px"}}}} -->
  <h2 class="wp-block-heading" style="margin-top:16px;margin-bottom:80px;font-size:clamp(40px,5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05">Continuously training.<br>Continuously coaching.</h2>
  <!-- /wp:heading -->

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:48px">
    <?php foreach ( $coaches as $i => $c ) : ?>
    <div>
      <div class="hp-photo" style="aspect-ratio:3/4">[<?php echo esc_html( strtoupper( $c['name'] ) ); ?>]</div>
      <p class="hp-meta" style="margin-top:24px;color:#9A9A98">0<?php echo $i + 1; ?></p>
      <h3 style="font-size:28px;font-weight:600;letter-spacing:-0.02em;margin:8px 0 4px"><?php echo esc_html( $c['name'] ); ?></h3>
      <p style="color:#1A2DC4;font-size:13px;letter-spacing:0.04em;text-transform:uppercase;font-weight:500;margin:0 0 16px"><?php echo esc_html( $c['role'] ); ?></p>
      <p style="color:#C9C7C1;font-size:15px;line-height:1.6;margin:0"><?php echo esc_html( $c['bio'] ); ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
