<?php
/**
 * RealHomes Child Theme – functions.php
 *
 * Hides plugin fingerprints from the public HTML source so that tools like
 * WhatCMS / BuiltWith cannot enumerate installed plugins through:
 *   - <script> / <link> paths that contain /wp-content/plugins/<slug>/
 *   - ?ver= version query strings on enqueued assets
 *   - WordPress generator <meta> tag
 *   - WPML hreflang / language-meta tags in <head>
 *   - Borlabs Cookie script identifier comments
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// 1. Enqueue parent theme stylesheet
// ---------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'realhomes_child_enqueue_styles' );
function realhomes_child_enqueue_styles() {
    wp_enqueue_style(
        'realhomes-parent-style',
        get_template_directory_uri() . '/style.css'
    );
}

// ---------------------------------------------------------------------------
// 2. Remove WordPress version from <head> and RSS feeds
// ---------------------------------------------------------------------------
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// ---------------------------------------------------------------------------
// 3. Remove ?ver= query strings from all script and style URLs
//    (prevents version-based plugin/WP fingerprinting)
// ---------------------------------------------------------------------------
add_filter( 'style_loader_src',  'realhomes_child_remove_ver_query', 9999 );
add_filter( 'script_loader_src', 'realhomes_child_remove_ver_query', 9999 );
function realhomes_child_remove_ver_query( $src ) {
    return remove_query_arg( 'ver', $src );
}

// ---------------------------------------------------------------------------
// 4. Remove WPML-specific tags from <head>
//    (hreflang alternate links reveal sitepress-multilingual-cms)
// ---------------------------------------------------------------------------
add_action( 'init', 'realhomes_child_remove_wpml_head_items' );
function realhomes_child_remove_wpml_head_items() {
    // WPML adds hreflang links and a language meta via these hooks
    remove_action( 'wp_head', array( 'SitePress', 'meta_generator_tag' ) );

    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        global $sitepress;
        if ( is_object( $sitepress ) ) {
            remove_action( 'wp_head', array( $sitepress, 'meta_generator_tag' ) );
        }
    }
}

// ---------------------------------------------------------------------------
// 5. Output-buffer the full HTML response and scrub plugin paths
//    Affected plugins:
//      - sitepress-multilingual-cms  (WPML)
//      - image-watermark
//      - borlabs-cookie
//      - simple-weather
//      - disable-source-disabled-right-click-content-protection
//        (also known as: disabled-source-disabled-right-click-and-content-protection)
// ---------------------------------------------------------------------------
add_action( 'template_redirect', 'realhomes_child_start_output_buffer', 0 );
function realhomes_child_start_output_buffer() {
    ob_start( 'realhomes_child_clean_plugin_paths' );
}

/**
 * Callback for ob_start(): replace every occurrence of a plugin path inside
 * <script>, <link>, <style> and HTML-comment nodes with a neutral token so
 * that the plugin slug is no longer readable in View Source.
 *
 * @param string $html The complete page HTML.
 * @return string Cleaned HTML.
 */
function realhomes_child_clean_plugin_paths( $html ) {
    // Cache the plugins base path – it never changes within a request.
    static $plugins_base = null;
    if ( null === $plugins_base ) {
        $plugins_base = wp_make_link_relative( plugins_url() ); // e.g. /wp-content/plugins
    }

    // Replace all plugin slugs in a single preg_replace pass.
    $slug_alternation = implode( '|', array_map(
        'preg_quote',
        array(
            'sitepress-multilingual-cms',
            'wpml-string-translation',
            'wpml-translation-management',
            'image-watermark',
            'borlabs-cookie',
            'simple-weather',
            'disable-source-disabled-right-click-content-protection',
            'disabled-source-disabled-right-click-and-content-protection',
        )
    ) );
    $html = preg_replace(
        '#' . preg_quote( $plugins_base, '#' ) . '/(' . $slug_alternation . ')#',
        $plugins_base . '/assets',
        $html
    );

    // Remove Borlabs Cookie inline script comment block that names the plugin.
    $html = preg_replace(
        '#<!--\s*Borlabs Cookie.*?-->\s*.*?\s*<!--\s*/Borlabs Cookie.*?-->#si',
        '',
        $html
    );

    // Remove "Disabled Source" plugin inline JS comment header.
    $html = preg_replace(
        '#/\*\s*Disabled Source[^*]*\*/#si',
        '',
        $html
    );

    return $html;
}
