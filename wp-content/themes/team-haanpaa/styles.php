<?php

/**
 * Theme styles and editor styles setup.
 * It enqueues the main stylesheet in the front-end and in the editor.
**/

add_action( 'after_setup_theme', function () {
	add_editor_style( 'style.css' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'team-haanpaa-style', get_stylesheet_uri(), [], wp_get_theme()->get( 'Version' ) );
} );