<?php
/**
 * Pattern registration. Patterns live as PHP files in /patterns/*.php
 * with a header comment block (Title, Slug, Categories, Block Types).
 * WordPress auto-discovers them when this directory is registered.
 *
 * @package Haanpaa
 */

namespace Haanpaa;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Patterns {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_categories' ] );
        add_action( 'init', [ __CLASS__, 'register_patterns' ], 11 );
    }

    public static function register_categories() {
        if ( ! function_exists( 'register_block_pattern_category' ) ) { return; }
        register_block_pattern_category( 'haanpaa-hero',     [ 'label' => __( 'Haanpaa · Hero', 'haanpaa' ) ] );
        register_block_pattern_category( 'haanpaa-section',  [ 'label' => __( 'Haanpaa · Section', 'haanpaa' ) ] );
        register_block_pattern_category( 'haanpaa-cta',      [ 'label' => __( 'Haanpaa · CTA', 'haanpaa' ) ] );
        register_block_pattern_category( 'haanpaa-form',     [ 'label' => __( 'Haanpaa · Form', 'haanpaa' ) ] );
    }

    /**
     * Loop /patterns/*.php; require each file's header to register it.
     */
    public static function register_patterns() {
        if ( ! function_exists( 'register_block_pattern' ) ) { return; }

        $dir = HAANPAA_PLUGIN_DIR . 'patterns/';
        if ( ! is_dir( $dir ) ) { return; }

        foreach ( glob( $dir . '*.php' ) as $file ) {
            $data = get_file_data( $file, [
                'title'        => 'Title',
                'slug'         => 'Slug',
                'description'  => 'Description',
                'categories'   => 'Categories',
                'keywords'     => 'Keywords',
                'block_types'  => 'Block Types',
                'viewport'     => 'Viewport Width',
                'inserter'     => 'Inserter',
            ] );
            if ( empty( $data['slug'] ) ) { continue; }

            ob_start();
            include $file;
            $content = ob_get_clean();

            $args = [
                'title'       => $data['title'] ?: $data['slug'],
                'description' => $data['description'],
                'content'     => $content,
                'categories'  => array_filter( array_map( 'trim', explode( ',', $data['categories'] ) ) ),
                'keywords'    => array_filter( array_map( 'trim', explode( ',', $data['keywords'] ) ) ),
                'inserter'    => 'no' !== strtolower( $data['inserter'] ),
            ];
            if ( $data['block_types'] ) {
                $args['blockTypes'] = array_filter( array_map( 'trim', explode( ',', $data['block_types'] ) ) );
            }
            if ( $data['viewport'] ) {
                $args['viewportWidth'] = (int) $data['viewport'];
            }

            register_block_pattern( $data['slug'], $args );
        }
    }
}
