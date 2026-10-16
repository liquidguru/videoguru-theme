<?php
/**
 * Appearance → Customize: colour theme, logo/tagline, the video wall, footer and social links.
 */
if (!defined('ABSPATH')) exit;

/** Colour themes: [label, background, accent]. */
function videoguru_presets(): array {
    return [
        'midnight' => [__('Midnight (dark blue)', 'videoguru'), '#040316', '#5cc8e0'],
        'ocean'    => [__('Deep ocean (teal)', 'videoguru'),    '#03141a', '#2dd4bf'],
        'charcoal' => [__('Charcoal (amber)', 'videoguru'),     '#131313', '#f5a524'],
        'forest'   => [__('Forest (green)', 'videoguru'),       '#07140d', '#7ddc8a'],
        'plum'     => [__('Plum (violet)', 'videoguru'),        '#14061c', '#e879f9'],
        'paper'    => [__('Paper (light)', 'videoguru'),        '#f6f5f1', '#0b6e8a'],
        'custom'   => [__('Custom colours', 'videoguru'),       '', ''],
    ];
}

/** Thumbnail shapes: slug => label. */
function videoguru_shapes(): array {
    return [
        'rounded' => __('Rounded corners', 'videoguru'),
        'soft'    => __('Very rounded corners', 'videoguru'),
        'sharp'   => __('Square corners', 'videoguru'),
        'cut'     => __('Cut corners', 'videoguru'),
        'square'  => __('Square tile', 'videoguru'),
        'circle'  => __('Circle', 'videoguru'),
        'oval'    => __('Oval', 'videoguru'),
        'wavy'    => __('Wavy edges', 'videoguru'),
    ];
}

function videoguru_defaults(): array {
    $full = videoguru_is_full();   // the WordPress.org edition keeps every third-party request switched off until the owner opts in
    return apply_filters('videoguru_defaults', [
        'preset' => 'midnight', 'bg' => '#040316', 'accent' => '#5cc8e0',
        'card_size' => 'medium', 'thumb_shape' => 'rounded', 'per_page' => 200, 'home_order' => 'newest',
        'chip_level' => 1, 'hide_cats' => '', 'only_videos' => true, 'show_sort' => true, 'hover_preview' => $full, 'remote_posters' => $full,
        'show_random' => true, 'show_search' => true, 'show_duration' => true, 'show_tagline' => true,
        'random_label' => '', 'search_placeholder' => '',
        'home_intro' => '', 'home_description' => '', 'footer_text' => '', 'show_rss' => true,
        'social_youtube' => '', 'social_vimeo' => '', 'social_instagram' => '', 'social_facebook' => '', 'social_x' => '', 'social_tiktok' => '', 'social_linkedin' => '',
    ]);
}

function videoguru_mod(string $key) {
    $d = videoguru_defaults();
    return get_theme_mod($key, $d[$key] ?? '');
}

/* ---------- colour maths ---------- */

function videoguru_hex_rgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
function videoguru_rgb_hex(array $c): string { return sprintf('#%02x%02x%02x', ...array_map(fn($v) => max(0, min(255, (int) round($v))), $c)); }
function videoguru_mix(string $a, string $b, float $t): string {
    $x = videoguru_hex_rgb($a); $y = videoguru_hex_rgb($b);
    return videoguru_rgb_hex([$x[0] + ($y[0] - $x[0]) * $t, $x[1] + ($y[1] - $x[1]) * $t, $x[2] + ($y[2] - $x[2]) * $t]);
}
function videoguru_lum(string $hex): float {
    $l = array_map(function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); }, videoguru_hex_rgb($hex));
    return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

/** The full set of colours for the chosen theme, all derived from a background and an accent. */
function videoguru_palette(): array {
    $presets = videoguru_presets();
    $p = videoguru_mod('preset');
    if (!isset($presets[$p])) $p = 'midnight';
    if ($p === 'custom') {
        $bg = sanitize_hex_color(videoguru_mod('bg')) ?: '#040316';
        $accent = sanitize_hex_color(videoguru_mod('accent')) ?: '#5cc8e0';
    } else { $bg = $presets[$p][1]; $accent = $presets[$p][2]; }
    $dark = videoguru_lum($bg) < 0.4;
    $fg = $dark ? '#eef0ff' : '#10122a';
    return [
        'dark' => $dark, 'bg' => $bg, 'accent' => $accent,
        'surface' => videoguru_mix($bg, $fg, $dark ? .06 : .05),
        'line' => videoguru_mix($bg, $fg, .14),
        'muted' => videoguru_mix($bg, $fg, $dark ? .56 : .68),
        'body' => videoguru_mix($bg, $fg, .84),
        'text' => $fg,
        'foot' => $dark ? videoguru_mix($bg, '#000000', .3) : videoguru_mix($bg, '#000000', .04),
        // Whichever of near-black or white reads better on the accent colour.
        'on_accent' => (videoguru_lum($accent) + .05) / (videoguru_lum('#06101a') + .05) >= 1.05 / (videoguru_lum($accent) + .05) ? '#06101a' : '#ffffff',
    ];
}

/** Palette + card size as CSS variables, appended to the theme stylesheet. */
add_action('wp_enqueue_scripts', function () {
    $c = videoguru_palette();
    $sizes = ['small' => 240, 'medium' => 300, 'large' => 380];
    $min = $sizes[videoguru_mod('card_size')] ?? 300;
    $css = ':root{--bg:' . $c['bg'] . ';--surface:' . $c['surface'] . ';--line:' . $c['line'] . ';--text:' . $c['text'] . ';--body:' . $c['body'] . ';--muted:' . $c['muted'] .
        ';--accent:' . $c['accent'] . ';--on-accent:' . $c['on_accent'] . ';--foot:' . $c['foot'] . ';--card-min:' . $min . 'px;color-scheme:' . ($c['dark'] ? 'dark' : 'light') . '}';
    wp_add_inline_style('videoguru-main', $css);
}, 20);

add_action('wp_head', function () {
    $c = videoguru_palette();
    echo '<meta name="color-scheme" content="' . esc_attr($c['dark'] ? 'dark' : 'light') . '">' . "\n";
    echo '<meta name="theme-color" content="' . esc_attr($c['bg']) . '">' . "\n";
}, 2);

/* ---------- Customizer controls ---------- */

add_action('customize_register', function (WP_Customize_Manager $wp) {
    $d = videoguru_defaults();
    $add = function (string $id, string $section, string $label, string $type, $sanitize, array $extra = []) use ($wp, $d) {
        $wp->add_setting($id, ['default' => $d[$id] ?? '', 'sanitize_callback' => $sanitize]);
        $wp->add_control($id, array_merge(['label' => $label, 'section' => $section, 'type' => $type], $extra));
    };
    $bool = fn($v) => (bool) $v;
    $txt = 'sanitize_text_field';

    // Colours
    $wp->add_section('videoguru_colors', ['title' => __('Colours', 'videoguru'), 'priority' => 30]);
    $choices = array_map(fn($p) => $p[0], videoguru_presets());
    $add('preset', 'videoguru_colors', __('Colour theme', 'videoguru'), 'select', fn($v) => isset($choices[$v]) ? $v : 'midnight', ['choices' => $choices]);
    $wp->add_setting('bg', ['default' => $d['bg'], 'sanitize_callback' => 'sanitize_hex_color']);
    $wp->add_control(new WP_Customize_Color_Control($wp, 'bg', ['label' => __('Background (Custom only)', 'videoguru'), 'section' => 'videoguru_colors', 'active_callback' => fn() => videoguru_mod('preset') === 'custom']));
    $wp->add_setting('accent', ['default' => $d['accent'], 'sanitize_callback' => 'sanitize_hex_color']);
    $wp->add_control(new WP_Customize_Color_Control($wp, 'accent', ['label' => __('Accent (Custom only)', 'videoguru'), 'section' => 'videoguru_colors', 'active_callback' => fn() => videoguru_mod('preset') === 'custom']));

    // Logo and tagline live in the built-in "Site Identity" section.
    $add('show_tagline', 'title_tagline', __('Show the tagline in the header', 'videoguru'), 'checkbox', $bool);

    // The video wall
    $wp->add_section('videoguru_wall', ['title' => __('Video wall', 'videoguru'), 'priority' => 35]);
    $add('card_size', 'videoguru_wall', __('Thumbnail size', 'videoguru'), 'select', fn($v) => in_array($v, ['small', 'medium', 'large'], true) ? $v : 'medium',
        ['choices' => ['small' => __('Small', 'videoguru'), 'medium' => __('Medium', 'videoguru'), 'large' => __('Large', 'videoguru')]]);
    $add('thumb_shape', 'videoguru_wall', __('Thumbnail shape', 'videoguru'), 'select', fn($v) => isset(videoguru_shapes()[$v]) ? $v : 'rounded', ['choices' => videoguru_shapes()]);
    $add('home_order', 'videoguru_wall', __('Order on the home page', 'videoguru'), 'select', fn($v) => in_array($v, ['newest', 'oldest', 'title_asc', 'title_desc', 'random'], true) ? $v : 'newest',
        ['choices' => ['newest' => __('Newest first', 'videoguru'), 'oldest' => __('Oldest first', 'videoguru'), 'title_asc' => __('Title A–Z', 'videoguru'),
            'title_desc' => __('Title Z–A', 'videoguru'), 'random' => __('Random on every visit', 'videoguru')]]);
    $add('chip_level', 'videoguru_wall', __('Filter chips are made from', 'videoguru'), 'select', fn($v) => in_array((int) $v, [1, 2, 3], true) ? (int) $v : 1,
        ['choices' => [1 => __('Top-level categories', 'videoguru'), 2 => __('Second-level categories (children of the top level)', 'videoguru'), 3 => __('Third-level categories', 'videoguru')],
         'description' => __('Pick the level where your real groups sit, e.g. Fish / Crustaceans / Cephalopods.', 'videoguru')]);
    $add('hide_cats', 'videoguru_wall', __('Hide these categories (slugs, separated by commas)', 'videoguru'), 'text', 'sanitize_text_field',
        ['description' => __('Left out of the filter chips and the category tags on video pages, e.g. archive, featured, all-videos.', 'videoguru'), 'input_attrs' => ['placeholder' => 'archive, featured']]);
    $add('show_sort', 'videoguru_wall', __('Show a Sort menu so visitors can reorder the wall', 'videoguru'), 'checkbox', $bool);
    $add('per_page', 'videoguru_wall', __('Videos per page', 'videoguru'), 'number', fn($v) => max(12, min(1000, (int) $v)), ['input_attrs' => ['min' => 12, 'max' => 1000],
        'description' => __('Up to this many videos load on one screen, with instant filtering and search. More than this and the wall pages, and search goes to the server.', 'videoguru')]);
    $add('only_videos', 'videoguru_wall', __('Only list posts that contain a Vimeo or YouTube link', 'videoguru'), 'checkbox', $bool);
    $add('hover_preview', 'videoguru_wall', __('Play a muted preview when the mouse rests on a thumbnail', 'videoguru'), 'checkbox', $bool,
        ['description' => __('Loads the video from Vimeo or YouTube when a visitor rests the mouse on a thumbnail, so their browser contacts that service.', 'videoguru')]);
    $add('remote_posters', 'videoguru_wall', __('Use YouTube thumbnails for posts without a featured image', 'videoguru'), 'checkbox', $bool,
        ['description' => __("Loads the thumbnail from YouTube's servers, so visitors' browsers contact YouTube. Off by default: set a featured image on each post instead.", 'videoguru')]);
    if (videoguru_is_full()) {
        $add('show_duration', 'videoguru_wall', __('Show video length on thumbnails (Vimeo)', 'videoguru'), 'checkbox', $bool);
    }
    $add('show_search', 'videoguru_wall', __('Show the search box', 'videoguru'), 'checkbox', $bool);
    $add('show_random', 'videoguru_wall', __('Show the Random video button', 'videoguru'), 'checkbox', $bool);
    $add('random_label', 'videoguru_wall', __('Random button label', 'videoguru'), 'text', $txt, ['input_attrs' => ['placeholder' => __('Random video', 'videoguru')]]);
    $add('search_placeholder', 'videoguru_wall', __('Search box hint', 'videoguru'), 'text', $txt, ['input_attrs' => ['placeholder' => __('Search videos…', 'videoguru')]]);
    $add('home_intro', 'videoguru_wall', __('Intro text under the wall (optional)', 'videoguru'), 'textarea', 'wp_kses_post');
    if (videoguru_is_full()) {
        $add('home_description', 'videoguru_wall', __('Home page description for Google (optional)', 'videoguru'), 'textarea', 'sanitize_textarea_field',
            ['description' => __('About 150 characters. If empty, the tagline is used.', 'videoguru')]);
    }

    // Footer and social
    $wp->add_section('videoguru_footer', ['title' => __('Footer and social links', 'videoguru'), 'priority' => 40]);
    $add('footer_text', 'videoguru_footer', __('Footer text', 'videoguru'), 'text', 'wp_kses_post', ['input_attrs' => ['placeholder' => '© ' . gmdate('Y') . ' ' . get_bloginfo('name')]]);
    foreach (['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'] as $k => $label) {
        $add('social_' . $k, 'videoguru_footer', $label . ' ' . __('link', 'videoguru'), 'url', 'esc_url_raw');
    }
    $add('show_rss', 'videoguru_footer', __('Show an RSS link', 'videoguru'), 'checkbox', $bool);
});

// The shape is a class on the page body; the stylesheet does the rest.
add_filter('body_class', function ($classes) {
    $shape = videoguru_mod('thumb_shape');
    $classes[] = 'videoguru-shape-' . (isset(videoguru_shapes()[$shape]) ? $shape : 'rounded');
    return $classes;
});
