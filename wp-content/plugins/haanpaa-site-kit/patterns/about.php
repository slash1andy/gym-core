<?php
/**
 * Title: Haanpaa · About
 * Slug: haanpaa/about
 * Description: About page — hero, gym story, four coaching principles, coach grid, and CTA.
 * Categories: haanpaa-page
 * Keywords: about, coaches, instructors, story, philosophy, team
 * Viewport Width: 1440
 */

$role_labels = [
	'gym_head_coach' => 'Owner & Head Coach',
	'gym_coach'      => 'Instructor',
];

$all_coaches = [];
$users       = get_users( [
	'role__in' => [ 'gym_head_coach', 'gym_coach' ],
	'number'   => 6,
	'orderby'  => 'meta_value',
	'meta_key' => 'last_name',
	'order'    => 'ASC',
] );

foreach ( $users as $user ) {
	$role = 'Instructor';
	foreach ( array_keys( $role_labels ) as $r ) {
		if ( in_array( $r, (array) $user->roles, true ) ) {
			$role = $role_labels[ $r ];
			break;
		}
	}
	$all_coaches[] = [
		'name'  => $user->display_name,
		'title' => $role,
		'belt'  => get_user_meta( $user->ID, 'gym_belt', true ) ?: '',
		'bio'   => $user->description ?: '',
	];
}

if ( empty( $all_coaches ) ) {
	$all_coaches = [
		[
			'name'  => 'Darby Haanpaa',
			'title' => 'Owner & Head Coach',
			'belt'  => 'Brazilian Jiu-Jitsu · Team Curran',
			'bio'   => 'Darby came up under Pat Curran and the Curran family in Crystal Lake — one of the most respected MMA lineages in the Midwest. He brought that pedigree home to Rockford and built a school where the technique is real, the room is welcoming, and the standard is high.',
		],
		[
			'name'  => 'Amanda Haanpaa',
			'title' => 'Co-owner',
			'belt'  => 'Kids Program Director',
			'bio'   => 'Amanda runs the business side and the kids program. Together with Darby, they\'ve raised generations of students from white belts to coaches — many of whom you\'ll meet on the mat.',
		],
		[
			'name'  => '[Coach name]',
			'title' => '[Title]',
			'belt'  => '[Rank · lineage]',
			'bio'   => '[Bio paragraph — short, personal, mentions years training, why they coach. Replace with real copy before launch.]',
		],
		[
			'name'  => '[Coach name]',
			'title' => '[Title]',
			'belt'  => '[Rank · lineage]',
			'bio'   => '[Bio paragraph — short, personal, mentions years training, why they coach. Replace with real copy before launch.]',
		],
	];
}

$featured   = array_slice( $all_coaches, 0, 2 );
$supporting = array_slice( $all_coaches, 2 );

$principles = [
	[
		'n' => '01',
		't' => 'Technique over intensity',
		'c' => 'Hard work matters, but real progress is built on careful technique. We coach details. We slow things down. You leave class better, not just tired.',
	],
	[
		'n' => '02',
		't' => 'No ego on the mat',
		'c' => 'We do not have a fight-gym culture. Beginners are protected. Advanced students help. The room is calm and the people are kind.',
	],
	[
		'n' => '03',
		't' => 'Show up, not show off',
		'c' => 'Consistency builds black belts. We celebrate the student who came twice a week for two years more than we celebrate the natural athlete.',
	],
	[
		'n' => '04',
		't' => 'Family first',
		'c' => 'Many of our students train with their kids, their spouse, or their best friend. We design schedules and pricing to make that possible.',
	],
];
?>

<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(64px,8vw,120px)","bottom":"clamp(64px,8vw,100px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#EFEBE1"}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#EFEBE1;padding-top:clamp(64px,8vw,120px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(64px,8vw,100px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:64px;align-items:end">
    <h1 style="font-size:clamp(40px,6vw,88px);font-weight:600;letter-spacing:-0.03em;line-height:1.02;margin:0">A school built<br />by a family,<br /><em style="font-style:italic;font-weight:500;color:#1A2DC4">for families.</em></h1>
    <p style="font-size:clamp(17px,1.5vw,22px);line-height:1.5;color:#181816;max-width:460px;margin:0">Darby and Amanda Haanpaa opened the doors with a simple idea — make real martial arts available to people who never thought they belonged in a gym. Twenty years later, that's still the room you walk into.</p>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#ffffff"}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#ffffff;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:96px;align-items:start">
    <div>
      <p style="font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#4A4A48;margin:0 0 24px;font-weight:500">The story</p>
      <h2 style="font-size:clamp(28px,3.5vw,52px);font-weight:600;letter-spacing:-0.03em;line-height:1.1;margin:0">From Team Curran<br /><em style="font-style:italic;font-weight:500;color:#9A9A98">to your neighborhood.</em></h2>
    </div>
    <div style="display:grid;gap:24px">
      <p style="font-size:clamp(16px,1.3vw,19px);line-height:1.65;color:#181816;margin:0">Darby came up under Pat Curran and the Curran family in Crystal Lake — one of the most respected MMA lineages in the Midwest. He brought that pedigree home to Rockford and built a school where the technique is real, the room is welcoming, and the standard is high.</p>
      <p style="font-size:clamp(16px,1.3vw,19px);line-height:1.65;color:#4A4A48;margin:0">Amanda Haanpaa runs the business side and the kids program. Together they've raised generations of students from white belts to coaches — many of whom you'll meet on the mat.</p>
      <p style="font-size:clamp(16px,1.3vw,19px);line-height:1.65;color:#4A4A48;margin:0">We're still a small, family-run school. We know our students by name. We'll know yours.</p>
    </div>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#0A0A0A"}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#0A0A0A;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <p style="font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#9A9A98;margin:0 0 24px;font-weight:500">How we coach</p>
  <h2 style="font-size:clamp(32px,4.5vw,72px);font-weight:600;letter-spacing:-0.03em;line-height:1.05;color:#F6F4EE;margin:0 0 80px;max-width:920px">Four principles<br /><em style="font-style:italic;font-weight:500;color:#1A2DC4">we never compromise.</em></h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid rgba(255,255,255,0.12)">
    <?php foreach ( $principles as $i => $p ) : ?>
    <div style="padding:40px <?php echo $i % 2 === 0 ? '40px 40px 0' : '0 40px 40px 40px'; ?>;<?php echo $i % 2 === 0 ? 'border-right:1px solid rgba(255,255,255,0.12);' : ''; ?><?php echo $i < 2 ? 'border-bottom:1px solid rgba(255,255,255,0.12);' : ''; ?>">
      <p style="font-size:12px;letter-spacing:0.06em;color:#1A2DC4;margin:0 0 16px;font-weight:500;text-transform:uppercase"><?php echo esc_html( $p['n'] ); ?></p>
      <h3 style="font-size:clamp(20px,1.8vw,28px);font-weight:600;letter-spacing:-0.02em;color:#F6F4EE;margin:0 0 16px"><?php echo esc_html( $p['t'] ); ?></h3>
      <p style="font-size:15px;line-height:1.65;color:#9A9A98;margin:0;max-width:460px"><?php echo esc_html( $p['c'] ); ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","id":"coaches","style":{"spacing":{"padding":{"top":"clamp(80px,10vw,140px)","bottom":"clamp(80px,10vw,140px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#ffffff"}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" id="coaches" style="background-color:#ffffff;padding-top:clamp(80px,10vw,140px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(80px,10vw,140px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:64px;margin-bottom:64px;align-items:end">
    <p style="font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:#4A4A48;margin:0;font-weight:500">The team</p>
    <h2 style="font-size:clamp(28px,3.5vw,52px);font-weight:600;letter-spacing:-0.03em;line-height:1.1;margin:0">Coaches you'll see every week.</h2>
  </div>

  <?php if ( ! empty( $featured ) ) : ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:64px">
    <?php foreach ( $featured as $i => $c ) : ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <div class="hp-photo" style="aspect-ratio:4/5;background:#EFEBE1;display:flex;align-items:flex-end;padding:16px">[<?php echo esc_html( strtoupper( $c['name'] ) ); ?>]</div>
      <div style="display:flex;flex-direction:column;justify-content:flex-end;padding-bottom:8px">
        <p style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#1A2DC4;margin:0 0 12px;font-weight:500"><?php echo esc_html( $c['title'] ); ?></p>
        <h3 style="font-size:clamp(20px,1.8vw,28px);font-weight:600;letter-spacing:-0.02em;margin:0 0 8px"><?php echo esc_html( $c['name'] ); ?></h3>
        <?php if ( $c['belt'] ) : ?>
        <p style="font-size:13px;color:#4A4A48;margin:0 0 16px"><?php echo esc_html( $c['belt'] ); ?></p>
        <?php endif; ?>
        <p style="font-size:14px;line-height:1.6;color:#4A4A48;margin:0"><?php echo esc_html( $c['bio'] ); ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ( ! empty( $supporting ) ) : ?>
  <div style="display:grid;grid-template-columns:repeat(<?php echo min( 4, count( $supporting ) ); ?>,1fr);gap:24px">
    <?php foreach ( $supporting as $i => $c ) : ?>
    <div style="display:flex;flex-direction:column;gap:14px">
      <div class="hp-photo" style="aspect-ratio:3/4;background:#EFEBE1;display:flex;align-items:flex-end;padding:12px">[<?php echo esc_html( strtoupper( $c['name'] ) ); ?>]</div>
      <div>
        <h4 style="font-size:18px;font-weight:600;letter-spacing:-0.01em;margin:0 0 4px"><?php echo esc_html( $c['name'] ); ?></h4>
        <p style="font-size:13px;color:#4A4A48;margin:0 0 8px"><?php echo esc_html( $c['title'] ); ?></p>
        <?php if ( $c['belt'] ) : ?>
        <p style="font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#1A2DC4;font-weight:500;margin:0"><?php echo esc_html( $c['belt'] ); ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"clamp(72px,9vw,128px)","bottom":"clamp(72px,9vw,128px)","left":"clamp(24px,4vw,80px)","right":"clamp(24px,4vw,80px)"}},"color":{"background":"#1A2DC4"}},"layout":{"type":"constrained","contentSize":"1280px"},"templateLock":"all"} -->
<section class="wp-block-group alignfull has-background" style="background-color:#1A2DC4;padding-top:clamp(72px,9vw,128px);padding-right:clamp(24px,4vw,80px);padding-bottom:clamp(72px,9vw,128px);padding-left:clamp(24px,4vw,80px)">

  <!-- wp:html -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center">
    <h2 style="font-size:clamp(28px,3.5vw,56px);font-weight:600;letter-spacing:-0.03em;line-height:1.1;color:#ffffff;margin:0">Come meet the team in person.</h2>
    <div>
      <p style="font-size:clamp(16px,1.3vw,20px);line-height:1.6;color:rgba(255,255,255,0.85);margin:0 0 28px">Your free trial includes a sit-down with the head coach. No pressure, no upsell. Just a chance to ask questions and see if we're the right room for you.</p>
      <a href="<?php echo esc_url( home_url( '/free-trial/' ) ); ?>" style="display:inline-flex;align-items:center;gap:8px;background:#ffffff;color:#1A2DC4;font-size:15px;font-weight:700;letter-spacing:-0.01em;padding:16px 28px;text-decoration:none">Book your free trial →</a>
    </div>
  </div>
  <!-- /wp:html -->

</section>
<!-- /wp:group -->
