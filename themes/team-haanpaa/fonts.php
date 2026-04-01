<?php
if ( ! function_exists( 'team_haanpaa_fonts' ) ) {
	function team_haanpaa_fonts() {
		wp_enqueue_style(
			'team-haanpaa-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Playfair+Display:wght@700;900&display=swap',
			array(),
			null
		);
	}
}
add_action( 'enqueue_block_assets', 'team_haanpaa_fonts' );