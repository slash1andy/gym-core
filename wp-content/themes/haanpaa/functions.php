<?php
/**
 * Haanpaa block theme bootstrap.
 *
 * @package Haanpaa
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/theme-assets-rewrite.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'html5', [ 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ] );
	add_editor_style( 'assets/editor.css' );
	add_theme_support( 'woocommerce' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'haanpaa-style', get_stylesheet_uri(), [], wp_get_theme()->get( 'Version' ) );
	wp_enqueue_style( 'haanpaa-globals', get_template_directory_uri() . '/assets/globals.css', [], wp_get_theme()->get( 'Version' ) );

	// Fraunces — display serif used in the hero headline and trust bar.
	wp_enqueue_style(
		'haanpaa-fraunces',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,500;1,9..144,600;1,9..144,700&display=swap',
		[],
		null
	);
} );

add_action( 'admin_notices', function () {
	if ( ! is_admin() || class_exists( 'Haanpaa\\CPTs' ) ) { return; }
	echo '<div class="notice notice-warning"><p><strong>Haanpaa theme:</strong> activate the <em>Haanpaa Martial Arts — Site Kit</em> plugin for patterns, custom post types, and the free-trial form.</p></div>';
} );
