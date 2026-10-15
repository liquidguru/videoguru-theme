<?php
/**
 * Appearance → Customize: colour theme, logo/tagline, the video wall, footer and social links.
 */
if (!defined('ABSPATH')) exit;

/** Colour themes: [label, background, accent]. */
function vg_presets(): array {
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

function vg_defaults(): array {
    return [
        'preset' => 'midnight', 'bg' => '#040316', 'accent' => '#5cc8e0',
        'card_size' => 'medium', 'per_page' => 200, 'home_order' => 'newest',
        'only_videos' => true, 'hover_preview' => true, 'show_random' => true, 'show_search' => true, 'show_duration' => true, 'show_tagline' => true,
        'random_label' => '', 'search_placeholder' => '',
        'home_intro' => '', 'home_description' => '', 'footer_text' => '', 'show_rss' => true,
        'social_youtube' => '', 'social_vimeo' => '', 'social_instagram' => '', 'social_facebook' => '', 'social_x' => '', 'social_tiktok' => '', 'social_linkedin' => '',
    ];
}

function vg_mod(string $key) {
    $d = vg_defaults();
    return get_theme_mod($key, $d[$key] ?? '');
}

/* ---------- colour maths ---------- */

function vg_hex_rgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
function vg_rgb_hex(array $c): string { return sprintf('#%02x%02x%02x', ...array_map(fn($v) => max(0, min(255, (int) round($v))), $c)); }
function vg_mix(string $a, string $b, float $t): string {
    $x = vg_hex_rgb($a); $y = vg_hex_rgb($b);
    return vg_rgb_hex([$x[0] + ($y[0] - $x[0]) * $t, $x[1] + ($y[1] - $x[1]) * $t, $x[2] + ($y[2] - $x[2]) * $t]);
}
function vg_lum(string $hex): float {
    $l = array_map(function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); }, vg_hex_rgb($hex));
    return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

/** The full set of colours for the chosen theme, all derived from a background and an accent. */
function vg_palette(): array {
    $presets = vg_presets();
    $p = vg_mod('preset');
    if (!isset($presets[$p])) $p = 'midnight';
    if ($p === 'custom') {
        $bg = sanitize_hex_color(vg_mod('bg')) ?: '#040316';
        $accent = sanitize_hex_color(vg_mod('accent')) ?: '#5cc8e0';
    } else { $bg = $presets[$p][1]; $accent = $presets[$p][2]; }
    $dark = vg_lum($bg) < 0.4;
    $fg = $dark ? '#eef0ff' : '#10122a';
    return [
        'dark' => $dark, 'bg' => $bg, 'accent' => $accent,
        'surface' => vg_mix($bg, $fg, $dark ? .06 : .05),
        'line' => vg_mix($bg, $fg, .14),
        'muted' => vg_mix($bg, $fg, $dark ? .56 : .68),
        'body' => vg_mix($bg, $fg, .84),
        'text' => $fg,
        'foot' => $dark ? vg_mix($bg, '#000000', .3) : vg_mix($bg, '#000000', .04),
        // Whichever of near-black or white reads better on the accent colour.
        'on_accent' => (vg_lum($accent) + .05) / (vg_lum('#06101a') + .05) >= 1.05 / (vg_lum($accent) + .05) ? '#06101a' : '#ffffff',
    ];
}

/** Palette + card size as CSS variables, appended to the theme stylesheet. */
add_action('wp_enqueue_scripts', function () {
    $c = vg_palette();
    $sizes = ['small' => 240, 'medium' => 300, 'large' => 380];
    $min = $sizes[vg_mod('card_size')] ?? 300;
    $css = ':root{--bg:' . $c['bg'] . ';--surface:' . $c['surface'] . ';--line:' . $c['line'] . ';--text:' . $c['text'] . ';--body:' . $c['body'] . ';--muted:' . $c['muted'] .
        ';--accent:' . $c['accent'] . ';--on-accent:' . $c['on_accent'] . ';--foot:' . $c['foot'] . ';--card-min:' . $min . 'px;color-scheme:' . ($c['dark'] ? 'dark' : 'light') . '}';
    wp_add_inline_style('videoguru-main', $css);
}, 20);

add_action('wp_head', function () {
    $c = vg_palette();
    echo '<meta name="color-scheme" content="' . ($c['dark'] ? 'dark' : 'light') . '">' . "\n";
    echo '<meta name="theme-color" content="' . esc_attr($c['bg']) . '">' . "\n";
}, 2);

/* ---------- Customizer controls ---------- */

add_action('customize_register', function (WP_Customize_Manager $wp) {
    $d = vg_defaults();
    $add = function (string $id, string $section, string $label, string $type, $sanitize, array $extra = []) use ($wp, $d) {
        $wp->add_setting($id, ['default' => $d[$id] ?? '', 'sanitize_callback' => $sanitize]);
        $wp->add_control($id, array_merge(['label' => $label, 'section' => $section, 'type' => $type], $extra));
    };
    $bool = fn($v) => (bool) $v;
    $txt = 'sanitize_text_field';

    // Colours
    $wp->add_section('vg_colors', ['title' => __('Colours', 'videoguru'), 'priority' => 30]);
    $choices = array_map(fn($p) => $p[0], vg_presets());
    $add('preset', 'vg_colors', __('Colour theme', 'videoguru'), 'select', fn($v) => isset($choices[$v]) ? $v : 'midnight', ['choices' => $choices]);
    $wp->add_setting('bg', ['default' => $d['bg'], 'sanitize_callback' => 'sanitize_hex_color']);
    $wp->add_control(new WP_Customize_Color_Control($wp, 'bg', ['label' => __('Background (Custom only)', 'videoguru'), 'section' => 'vg_colors', 'active_callback' => fn() => vg_mod('preset') === 'custom']));
    $wp->add_setting('accent', ['default' => $d['accent'], 'sanitize_callback' => 'sanitize_hex_color']);
    $wp->add_control(new WP_Customize_Color_Control($wp, 'accent', ['label' => __('Accent (Custom only)', 'videoguru'), 'section' => 'vg_colors', 'active_callback' => fn() => vg_mod('preset') === 'custom']));

    // Logo and tagline live in the built-in "Site Identity" section.
    $add('show_tagline', 'title_tagline', __('Show the tagline in the header', 'videoguru'), 'checkbox', $bool);

    // The video wall
    $wp->add_section('vg_wall', ['title' => __('Video wall', 'videoguru'), 'priority' => 35]);
    $add('card_size', 'vg_wall', __('Thumbnail size', 'videoguru'), 'select', fn($v) => in_array($v, ['small', 'medium', 'large'], true) ? $v : 'medium',
        ['choices' => ['small' => __('Small', 'videoguru'), 'medium' => __('Medium', 'videoguru'), 'large' => __('Large', 'videoguru')]]);
    $add('home_order', 'vg_wall', __('Order on the home page', 'videoguru'), 'select', fn($v) => in_array($v, ['newest', 'oldest', 'random'], true) ? $v : 'newest',
        ['choices' => ['newest' => __('Newest first', 'videoguru'), 'oldest' => __('Oldest first', 'videoguru'), 'random' => __('Random on every visit', 'videoguru')]]);
    $add('per_page', 'vg_wall', __('Videos per page', 'videoguru'), 'number', fn($v) => max(12, min(1000, (int) $v)), ['input_attrs' => ['min' => 12, 'max' => 1000],
        'description' => __('Up to this many videos load on one screen, with instant filtering and search. More than this and the wall pages, and search goes to the server.', 'videoguru')]);
    $add('only_videos', 'vg_wall', __('Only list posts that contain a Vimeo or YouTube link', 'videoguru'), 'checkbox', $bool);
    $add('hover_preview', 'vg_wall', __('Play a muted preview when the mouse rests on a thumbnail', 'videoguru'), 'checkbox', $bool);
    $add('show_duration', 'vg_wall', __('Show video length on thumbnails (Vimeo)', 'videoguru'), 'checkbox', $bool);
    $add('show_search', 'vg_wall', __('Show the search box', 'videoguru'), 'checkbox', $bool);
    $add('show_random', 'vg_wall', __('Show the Random video button', 'videoguru'), 'checkbox', $bool);
    $add('random_label', 'vg_wall', __('Random button label', 'videoguru'), 'text', $txt, ['input_attrs' => ['placeholder' => __('Random video', 'videoguru')]]);
    $add('search_placeholder', 'vg_wall', __('Search box hint', 'videoguru'), 'text', $txt, ['input_attrs' => ['placeholder' => __('Search videos…', 'videoguru')]]);
    $add('home_intro', 'vg_wall', __('Intro text under the wall (optional)', 'videoguru'), 'textarea', 'wp_kses_post');
    $add('home_description', 'vg_wall', __('Home page description for Google (optional)', 'videoguru'), 'textarea', 'sanitize_textarea_field',
        ['description' => __('About 150 characters. If empty, the tagline is used.', 'videoguru')]);

    // Footer and social
    $wp->add_section('vg_footer', ['title' => __('Footer and social links', 'videoguru'), 'priority' => 40]);
    $add('footer_text', 'vg_footer', __('Footer text', 'videoguru'), 'text', 'wp_kses_post', ['input_attrs' => ['placeholder' => '© ' . gmdate('Y') . ' ' . get_bloginfo('name')]]);
    foreach (['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'] as $k => $label) {
        $add('social_' . $k, 'vg_footer', $label . ' ' . __('link', 'videoguru'), 'url', 'esc_url_raw');
    }
    $add('show_rss', 'vg_footer', __('Show an RSS link', 'videoguru'), 'checkbox', $bool);
});
