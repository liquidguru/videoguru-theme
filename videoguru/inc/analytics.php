<?php
/**
 * Full edition only: a Customizer box for an analytics snippet (Google Analytics, Plausible, Cloudflare, Matomo ...).
 * (The WordPress.org edition leaves this file out: analytics belong in plugins there.)
 */
if (!defined('ABSPATH')) exit;

add_filter('videoguru_defaults', function ($d) {
    return $d + ['tracking_code' => '', 'skip_admin_tracking' => true];
});

add_action('customize_register', function (WP_Customize_Manager $wp) {
    $d = videoguru_defaults();
    $bool = fn($v) => (bool) $v;
    $add = function (string $id, string $section, string $label, string $type, $sanitize, array $extra = []) use ($wp, $d) {
        $wp->add_setting($id, ['default' => $d[$id] ?? '', 'sanitize_callback' => $sanitize]);
        $wp->add_control($id, array_merge(['label' => $label, 'section' => $section, 'type' => $type], $extra));
    };
    $wp->add_section('videoguru_analytics', ['title' => __('Analytics', 'videoguru'), 'priority' => 45,
        'description' => __('Paste the tracking snippet from Google Analytics, Plausible, Cloudflare Web Analytics, Matomo or similar. It is added to every page. If your visitors need to give consent first, that is up to you (use a consent plugin or your analytics tool\'s own setting).', 'videoguru')]);
    // Only people allowed to post raw code (not on multisite) can save a snippet; for everyone else it is blanked.
    $add('tracking_code', 'videoguru_analytics', __('Tracking snippet', 'videoguru'), 'textarea', fn($v) => current_user_can('unfiltered_html') ? (string) $v : '',
        ['input_attrs' => ['rows' => 8, 'placeholder' => '<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX"></script>', 'spellcheck' => 'false']]);
    $add('skip_admin_tracking', 'videoguru_analytics', __('Don\'t track logged-in administrators', 'videoguru'), 'checkbox', $bool);
});

// The snippet goes in the page head, but not in the Customizer preview and (by default) not for logged-in administrators.
add_action('wp_head', function () {
    $code = trim((string) videoguru_mod('tracking_code'));
    if ($code === '' || is_customize_preview()) return;
    if (videoguru_mod('skip_admin_tracking') && current_user_can('manage_options')) return;
    echo "\n" . $code . "\n"; // raw on purpose: saved only by users allowed unfiltered HTML
}, 99);
