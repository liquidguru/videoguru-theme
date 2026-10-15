<?php
/**
 * VideoGuru theme functions.
 */
if (!defined('ABSPATH')) exit;

define('VG_VERSION', wp_get_theme()->get('Version'));

require get_theme_file_path('inc/video.php');
require get_theme_file_path('inc/customizer.php');
require get_theme_file_path('inc/wall.php');
require get_theme_file_path('inc/seo.php');
if (is_admin() || (defined('WP_CLI') && WP_CLI)) require get_theme_file_path('inc/importer.php'); // Appearance → Import videos

add_action('after_setup_theme', function () {
    load_theme_textdomain('videoguru', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'comment-list', 'comment-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 104, 'width' => 400, 'flex-height' => true, 'flex-width' => true, 'unlink-homepage-logo' => true]);
    add_image_size('vg-card', 640, 360, true);
    register_nav_menus(['primary' => __('Header menu', 'videoguru'), 'footer' => __('Footer menu', 'videoguru')]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('videoguru-main', get_theme_file_uri('assets/css/main.css'), [], VG_VERSION . '.' . filemtime(get_theme_file_path('assets/css/main.css')));
    wp_enqueue_script('videoguru-main', get_theme_file_uri('assets/js/main.js'), [], VG_VERSION . '.' . filemtime(get_theme_file_path('assets/js/main.js')), ['strategy' => 'defer', 'in_footer' => true]);
});

add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) { // the wall is all theme markup; skip WordPress's block styles there
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
    }
}, 100);

// Self-hosted font, preloaded; "js" class lets the CSS hide controls that need JavaScript.
add_action('wp_head', function () {
    printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url(get_theme_file_uri('assets/fonts/open-sans-normal.woff2')));
    echo '<script>document.documentElement.classList.add("js")</script>' . "\n";
}, 1);

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

/** Header menu items as plain links (no list markup). */
class VG_Walker extends Walker_Nav_Menu {
    function start_el(&$o, $item, $depth = 0, $args = null, $id = 0) {
        $cur = in_array('current-menu-item', $item->classes, true) ? ' aria-current="page"' : '';
        $o .= '<a href="' . esc_url($item->url) . '"' . $cur . '>' . esc_html($item->title) . '</a>';
    }
    function end_el(&$o, $item, $depth = 0, $args = null) {}
    function start_lvl(&$o, $depth = 0, $args = null) {}
    function end_lvl(&$o, $depth = 0, $args = null) {}
}

// Optional footer columns (Appearance → Widgets). Nothing shows until you add a widget.
add_action('widgets_init', function () {
    foreach ([1, 2, 3] as $n) {
        register_sidebar([
            'id' => 'footer-' . $n,
            /* translators: %d: column number */
            'name' => sprintf(__('Footer column %d', 'videoguru'), $n),
            'before_widget' => '<div class="foot-col %2$s">', 'after_widget' => '</div>',
            'before_title' => '<h3>', 'after_title' => '</h3>',
        ]);
    }
});

/** Links for the footer's social row, from the Customizer. */
function vg_social_links(): array {
    $names = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'];
    $out = [];
    foreach ($names as $k => $label) if ($u = vg_mod('social_' . $k)) $out[$label] = $u;
    return $out;
}
