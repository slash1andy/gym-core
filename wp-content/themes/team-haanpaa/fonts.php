<?php
if ( ! function_exists( 'team_haanpaa_fonts' ) ) {
	function team_haanpaa_fonts() {
		wp_enqueue_style(
			'team-haanpaa-fonts',
			'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap',
			array(),
			null
		);
	}
}
add_action( 'enqueue_block_assets', 'team_haanpaa_fonts' );
