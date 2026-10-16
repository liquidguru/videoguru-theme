<?php
/**
 * Full edition only: Vimeo posters and lengths, fetched from Vimeo's public oEmbed endpoint and cached.
 * (The WordPress.org edition leaves this file out: a theme there may not fetch remote data.)
 */
if (!defined('ABSPATH')) exit;

/* ---------- Vimeo poster / duration: fetched once, cached, never on a visitor's page load ---------- */

/** Fetch from Vimeo's oEmbed endpoint. Returns ['thumb' => url, 'duration' => seconds] or ['fail' => 1]. */
function videoguru_fetch_vimeo(string $id, string $hash = ''): array {
    $video = 'https://vimeo.com/' . $id . ($hash ? '/' . $hash : '');
    $res = wp_remote_get('https://vimeo.com/api/oembed.json?width=640&url=' . rawurlencode($video), ['timeout' => 8, 'user-agent' => 'VideoGuru WordPress theme; ' . home_url('/')]);
    $data = !is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200 ? json_decode(wp_remote_retrieve_body($res), true) : null;
    if (!is_array($data) || empty($data['thumbnail_url'])) {
        $out = ['fail' => 1];
        set_transient('videoguru_vm_' . $id, $out, 6 * HOUR_IN_SECONDS);
        return $out;
    }
    $out = ['thumb' => esc_url_raw($data['thumbnail_url']), 'duration' => (int) ($data['duration'] ?? 0)];
    set_transient('videoguru_vm_' . $id, $out, 60 * DAY_IN_SECONDS);
    return $out;
}

/**
 * Cached Vimeo data. With $fetch = false (page views) an uncached video is queued and fetched in the background by WP-Cron,
 * so a wall of 200 new videos never makes a visitor wait.
 */
function videoguru_vimeo_data(string $id, string $hash = '', bool $fetch = false): array {
    $d = get_transient('videoguru_vm_' . $id);
    if (is_array($d)) return $d;
    if ($fetch) return videoguru_fetch_vimeo($id, $hash);
    $GLOBALS['videoguru_pending'][$id] = $hash;
    return [];
}

add_action('shutdown', function () {
    $new = $GLOBALS['videoguru_pending'] ?? [];
    if (!$new) return;
    $all = (array) get_option('videoguru_pending', []) + $new;
    update_option('videoguru_pending', $all, false);
    if (!wp_next_scheduled('videoguru_warm_vimeo')) wp_schedule_single_event(time() + 5, 'videoguru_warm_vimeo');
});

add_action('videoguru_warm_vimeo', function () {
    $queue = (array) get_option('videoguru_pending', []);
    $start = microtime(true);
    foreach ($queue as $id => $hash) {
        if (microtime(true) - $start > 20) break;
        if (get_transient('videoguru_vm_' . $id) === false) videoguru_fetch_vimeo((string) $id, (string) $hash);
        unset($queue[$id]);
    }
    update_option('videoguru_pending', $queue, false);
    if ($queue) wp_schedule_single_event(time() + 30, 'videoguru_warm_vimeo');
});

// Saving a post: fetch its Vimeo poster straight away so it is ready when the post is first seen.
add_action('save_post_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || $post->post_status !== 'publish') return;
    $v = videoguru_video_info($post);
    if ($v['provider'] === 'vimeo') { delete_transient('videoguru_vm_' . $v['id']); videoguru_vimeo_data($v['id'], $v['hash'], true); }
}, 10, 2);

// Plug the Vimeo data into the theme's poster and length lookups.
add_filter('videoguru_poster_url', function ($url, $v) {
    if ($v['provider'] !== 'vimeo') return $url;
    $d = videoguru_vimeo_data($v['id'], $v['hash']);
    return $d['thumb'] ?? $url;
}, 10, 2);

add_filter('videoguru_duration', function ($seconds, $v) {
    if ($v['provider'] !== 'vimeo') return $seconds;
    $d = videoguru_vimeo_data($v['id'], $v['hash']);
    return (int) ($d['duration'] ?? 0);
}, 10, 2);
