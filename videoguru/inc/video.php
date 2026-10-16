<?php
/**
 * Finding a post's video (Vimeo or YouTube), its poster and duration.
 *
 * A post is one video: paste a Vimeo or YouTube link (or the embed block) anywhere in the post.
 * Nothing needs setting up. Vimeo posters/durations are fetched from Vimeo's public oEmbed endpoint
 * and cached; YouTube posters come straight from YouTube's image server.
 */
if (!defined('ABSPATH')) exit;

/**
 * The main video in a post: ['provider' => 'vimeo'|'youtube'|'', 'id' => '', 'hash' => ''].
 * The earliest Vimeo or YouTube link/embed in the content wins.
 */
function vg_video_info($post = null): array {
    static $cache = [];
    $post = get_post($post);
    if (!$post) return ['provider' => '', 'id' => '', 'hash' => ''];
    $key = $post->ID . ':' . $post->post_modified_gmt;
    if (isset($cache[$key])) return $cache[$key];

    $c = (string) $post->post_content;
    $found = [];
    $vimeo = '~(?:player\.)?vimeo\.com/(?:video/|channels/[^/\s"\'<]+/|groups/[^/\s"\'<]+/videos/|album/\d+/video/)?(\d{5,})(?:/([0-9a-f]{8,}))?(?:\?(?:[^"\s<]*?&(?:amp;)?)?h=([0-9a-f]{8,}))?~i';
    if (preg_match($vimeo, $c, $m, PREG_OFFSET_CAPTURE)) {
        $found[$m[0][1]] = ['provider' => 'vimeo', 'id' => $m[1][0], 'hash' => ($m[2][0] ?? '') ?: ($m[3][0] ?? '')];
    }
    if (preg_match('~\[vimeo[^\]]*?(\d{5,})~i', $c, $m, PREG_OFFSET_CAPTURE)) {
        $found[$m[0][1]] = ['provider' => 'vimeo', 'id' => $m[1][0], 'hash' => ''];
    }
    $yt = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^"\s<]*?&(?:amp;)?)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i';
    if (preg_match($yt, $c, $m, PREG_OFFSET_CAPTURE)) {
        $found[$m[0][1]] = ['provider' => 'youtube', 'id' => $m[1][0], 'hash' => ''];
    }
    if ($found) { ksort($found); $info = reset($found); }
    else $info = ['provider' => '', 'id' => '', 'hash' => ''];
    return $cache[$key] = $info;
}

/**
 * [main title, subtitle]. A title written with a line break ("Common name<br>Latin name") shows the second part as a
 * subtitle; any other tags are dropped, so markup in a title never shows up as text.
 */
function vg_title_parts($post = null): array {
    $parts = array_values(array_filter(array_map(function ($p) {
        return trim(wp_strip_all_tags($p));
    }, preg_split('~<br\s*/?>~i', (string) get_the_title($post))), function ($p) { return $p !== ''; }));
    return [$parts[0] ?? '', isset($parts[1]) ? implode(' – ', array_slice($parts, 1)) : ''];
}

/** The whole title on one line, for page titles, alt text and structured data. */
function vg_title_line($post = null): string {
    [$name, $sub] = vg_title_parts($post);
    return $sub !== '' ? $name . ' – ' . $sub : $name;
}

add_filter('document_title_parts', function ($p) {
    if (is_singular('post')) $p['title'] = html_entity_decode(vg_title_line(), ENT_QUOTES, 'UTF-8');
    return $p;
});

/** Remove the main video's own embed from the body text (the theme draws the player itself, above the title). */
function vg_strip_main_video(string $c, array $v): string {
    if (!$v['id']) return $c;
    $id = preg_quote($v['id'], '~');
    $done = false;
    // Embed blocks (Vimeo / YouTube / generic) that point at this video.
    $c = preg_replace_callback('~<!--\s*wp:(?:core-)?embed\b.*?<!--\s*/wp:(?:core-)?embed\s*-->~is', function ($m) use ($id, &$done) {
        if (!$done && preg_match('~' . $id . '~', $m[0])) { $done = true; return ''; }
        return $m[0];
    }, $c);
    // A raw iframe for it.
    if (!$done) {
        $c = preg_replace_callback('~<iframe[^>]*>.*?</iframe>~is', function ($m) use ($id, &$done) {
            if (!$done && preg_match('~' . $id . '~', $m[0])) { $done = true; return ''; }
            return $m[0];
        }, $c);
    }
    // A bare URL on its own line, or a [vimeo] shortcode.
    if (!$done) $c = preg_replace('~^[ \t]*(?:<p>)?[ \t]*https?://[^\s<"]*' . $id . '[^\s<"]*[ \t]*(?:</p>)?[ \t]*$~im', '', $c, 1, $n);
    $c = preg_replace('~\[vimeo[^\]]*' . $id . '[^\]]*\]~i', '', $c, 1);
    return $c;
}

add_filter('the_content', function ($c) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $c;
    return vg_strip_main_video($c, vg_video_info());
}, 5);

/* ---------- Vimeo poster / duration: fetched once, cached, never on a visitor's page load ---------- */

/** Fetch from Vimeo's oEmbed endpoint. Returns ['thumb' => url, 'duration' => seconds] or ['fail' => 1]. */
function vg_fetch_vimeo(string $id, string $hash = ''): array {
    $video = 'https://vimeo.com/' . $id . ($hash ? '/' . $hash : '');
    $res = wp_remote_get('https://vimeo.com/api/oembed.json?width=640&url=' . rawurlencode($video), ['timeout' => 8, 'user-agent' => 'VideoGuru WordPress theme; ' . home_url('/')]);
    $data = !is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200 ? json_decode(wp_remote_retrieve_body($res), true) : null;
    if (!is_array($data) || empty($data['thumbnail_url'])) {
        $out = ['fail' => 1];
        set_transient('vg_vm_' . $id, $out, 6 * HOUR_IN_SECONDS);
        return $out;
    }
    $out = ['thumb' => esc_url_raw($data['thumbnail_url']), 'duration' => (int) ($data['duration'] ?? 0)];
    set_transient('vg_vm_' . $id, $out, 60 * DAY_IN_SECONDS);
    return $out;
}

/**
 * Cached Vimeo data. With $fetch = false (page views) an uncached video is queued and fetched in the background by WP-Cron,
 * so a wall of 200 new videos never makes a visitor wait.
 */
function vg_vimeo_data(string $id, string $hash = '', bool $fetch = false): array {
    $d = get_transient('vg_vm_' . $id);
    if (is_array($d)) return $d;
    if ($fetch) return vg_fetch_vimeo($id, $hash);
    $GLOBALS['vg_pending'][$id] = $hash;
    return [];
}

add_action('shutdown', function () {
    $new = $GLOBALS['vg_pending'] ?? [];
    if (!$new) return;
    $all = (array) get_option('vg_pending', []) + $new;
    update_option('vg_pending', $all, false);
    if (!wp_next_scheduled('vg_warm_vimeo')) wp_schedule_single_event(time() + 5, 'vg_warm_vimeo');
});

add_action('vg_warm_vimeo', function () {
    $queue = (array) get_option('vg_pending', []);
    $start = microtime(true);
    foreach ($queue as $id => $hash) {
        if (microtime(true) - $start > 20) break;
        if (get_transient('vg_vm_' . $id) === false) vg_fetch_vimeo((string) $id, (string) $hash);
        unset($queue[$id]);
    }
    update_option('vg_pending', $queue, false);
    if ($queue) wp_schedule_single_event(time() + 30, 'vg_warm_vimeo');
});

// Saving a post: fetch its Vimeo poster straight away so it is ready when the post is first seen.
add_action('save_post_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || $post->post_status !== 'publish') return;
    $v = vg_video_info($post);
    if ($v['provider'] === 'vimeo') { delete_transient('vg_vm_' . $v['id']); vg_vimeo_data($v['id'], $v['hash'], true); }
}, 10, 2);

/** Poster image URL for a video with no featured image (empty if not known yet). */
function vg_poster_url(array $v): string {
    if ($v['provider'] === 'youtube') return 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg';
    if ($v['provider'] === 'vimeo') { $d = vg_vimeo_data($v['id'], $v['hash']); return $d['thumb'] ?? ''; }
    return '';
}

function vg_duration($post = null): int {
    $v = vg_video_info($post);
    if ($v['provider'] !== 'vimeo') return 0;
    $d = vg_vimeo_data($v['id'], $v['hash']);
    return (int) ($d['duration'] ?? 0);
}

function vg_fmt_duration(int $s): string {
    if ($s <= 0) return '';
    return $s >= 3600 ? floor($s / 3600) . ':' . str_pad((string) floor($s % 3600 / 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT)
                      : floor($s / 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
}

/** The player iframe URL for the single-video page. */
function vg_player_src(array $v): string {
    if ($v['provider'] === 'vimeo') return 'https://player.vimeo.com/video/' . $v['id'] . '?title=0&byline=0&portrait=0&dnt=1' . ($v['hash'] ? '&h=' . $v['hash'] : '');
    if ($v['provider'] === 'youtube') return 'https://www.youtube-nocookie.com/embed/' . $v['id'] . '?rel=0';
    return '';
}
