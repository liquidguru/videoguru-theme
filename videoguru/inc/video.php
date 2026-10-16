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
function videoguru_video_info($post = null): array {
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
function videoguru_title_parts($post = null): array {
    $parts = array_values(array_filter(array_map(function ($p) {
        return trim(wp_strip_all_tags($p));
    }, preg_split('~<br\s*/?>~i', (string) get_the_title($post))), function ($p) { return $p !== ''; }));
    return [$parts[0] ?? '', isset($parts[1]) ? implode(' – ', array_slice($parts, 1)) : ''];
}

/** The whole title on one line, for page titles, alt text and structured data. */
function videoguru_title_line($post = null): string {
    [$name, $sub] = videoguru_title_parts($post);
    return $sub !== '' ? $name . ' – ' . $sub : $name;
}

add_filter('document_title_parts', function ($p) {
    if (is_singular('post')) $p['title'] = html_entity_decode(videoguru_title_line(), ENT_QUOTES, 'UTF-8');
    return $p;
});

/** Remove the main video's own embed from the body text (the theme draws the player itself, above the title). */
function videoguru_strip_main_video(string $c, array $v): string {
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
    return videoguru_strip_main_video($c, videoguru_video_info());
}, 5);

/**
 * Poster image URL for a video with no featured image ('' if none). YouTube's own image server is used only when the site
 * owner has switched on "Use video posters from YouTube" (it loads images from a third party); other sources can add theirs
 * through the 'videoguru_poster_url' filter.
 */
function videoguru_poster_url(array $v): string {
    $url = '';
    if ($v['provider'] === 'youtube' && videoguru_mod('remote_posters')) {
        $url = 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg';
    }
    return (string) apply_filters('videoguru_poster_url', $url, $v);
}

/** Video length in seconds (0 if unknown); the full edition fills this in for Vimeo through the 'videoguru_duration' filter. */
function videoguru_duration($post = null): int {
    return (int) apply_filters('videoguru_duration', 0, videoguru_video_info($post));
}


function videoguru_fmt_duration(int $s): string {
    if ($s <= 0) return '';
    return $s >= 3600 ? floor($s / 3600) . ':' . str_pad((string) floor($s % 3600 / 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT)
                      : floor($s / 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
}

/** The player iframe URL for the single-video page. */
function videoguru_player_src(array $v): string {
    if ($v['provider'] === 'vimeo') return 'https://player.vimeo.com/video/' . $v['id'] . '?title=0&byline=0&portrait=0&dnt=1' . ($v['hash'] ? '&h=' . $v['hash'] : '');
    if ($v['provider'] === 'youtube') return 'https://www.youtube-nocookie.com/embed/' . $v['id'] . '?rel=0';
    return '';
}
