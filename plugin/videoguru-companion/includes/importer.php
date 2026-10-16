<?php
/**
 * Importer library: turn pasted links, a YouTube channel/playlist or a Vimeo channel/group/showcase/folder/account into one
 * post per video. Videos already on the site are skipped, so it is safe to run again. The Tools page is in admin.php.
 */
if (!defined('ABSPATH')) exit;

/* ---------- Finding videos ---------- */

/** Parse one URL into a video: ['provider','id','hash','url'] or null. */
function vgcompanion_import_parse_url(string $u): ?array {
    $u = trim($u);
    if ($u === '') return null;
    if (preg_match('~vimeo\.com/(?:video/|channels/[^/\s]+/|groups/[^/\s]+/videos/|album/\d+/video/|showcase/\d+/video/)?(\d{5,})(?:/([0-9a-f]{8,}))?(?:\?(?:[^\s]*?&)?h=([0-9a-f]{8,}))?~i', $u, $m)) {
        $hash = ($m[2] ?? '') ?: ($m[3] ?? '');
        return ['provider' => 'vimeo', 'id' => $m[1], 'hash' => $hash, 'url' => 'https://vimeo.com/' . $m[1] . ($hash ? '/' . $hash : '')];
    }
    if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^\s]*?&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $u, $m)) {
        return ['provider' => 'youtube', 'id' => $m[1], 'hash' => '', 'url' => 'https://www.youtube.com/watch?v=' . $m[1]];
    }
    return null;
}

/** The post (any status) that already holds this video, or 0. */
function vgcompanion_import_existing(string $provider, string $id): int {
    global $wpdb;
    $host = $provider === 'vimeo' ? 'vimeo' : 'youtu';
    // A LIKE search of post content has no core API equivalent. It runs once per video on an admin screen, so it isn't cached.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish','draft','pending','future','private')
         AND post_content LIKE %s AND post_content LIKE %s LIMIT 1",
        '%' . $wpdb->esc_like($id) . '%', '%' . $wpdb->esc_like($host) . '%'));
}

/** GET a URL and decode JSON. Errors never include the key/token. */
function vgcompanion_import_json(string $url, array $headers = [], string $what = 'the service') {
    $res = wp_remote_get($url, ['timeout' => 15, 'headers' => $headers + ['Accept' => 'application/json'], 'user-agent' => 'VideoGuru Companion WordPress plugin; ' . home_url('/')]);
    if (is_wp_error($res)) return new WP_Error('vgcompanion_http', sprintf(/* translators: %s: service name */ __('Could not reach %s.', 'videoguru-companion'), $what));
    $code = (int) wp_remote_retrieve_response_code($res);
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        $msg = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? $data['developer_message'] ?? '') : '';
        if (is_array($msg)) $msg = '';
        return new WP_Error('vgcompanion_api', sprintf(/* translators: 1: service, 2: HTTP status, 3: message */ __('%1$s said no (HTTP %2$d). %3$s', 'videoguru-companion'), $what, $code, wp_strip_all_tags((string) $msg)));
    }
    return $data;
}

/** Items for pasted links: titles and descriptions from the public oEmbed endpoints (no key needed). */
function vgcompanion_import_from_links(string $text, int $max = 40, array &$bad = []): array {
    $items = [];
    $seen = [];
    foreach (preg_split('~[\r\n]+~', $text) as $line) {
        foreach (preg_split('~\s+~', trim($line)) as $tok) {
            $v = vgcompanion_import_parse_url($tok);
            if (!$v || isset($seen[$v['provider'] . $v['id']])) continue;
            $seen[$v['provider'] . $v['id']] = true;
            if (count($items) >= $max) break 2;
            $title = $desc = '';
            if ($v['provider'] === 'vimeo') {
                $d = vgcompanion_import_json('https://vimeo.com/api/oembed.json?url=' . rawurlencode($v['url']), [], 'Vimeo');
                if (is_array($d)) { $title = (string) ($d['title'] ?? ''); $desc = (string) ($d['description'] ?? ''); }
            } else {
                $d = vgcompanion_import_json('https://www.youtube.com/oembed?format=json&url=' . rawurlencode($v['url']), [], 'YouTube');
                if (is_array($d)) $title = (string) ($d['title'] ?? '');
            }
            if ($title === '') { $bad[] = sprintf(/* translators: %s: video address */ __('Skipped %s: the video site would not say what it is (private, removed or not embeddable?).', 'videoguru-companion'), $v['url']); continue; }
            $items[] = $v + ['title' => $title, 'description' => $desc, 'date' => gmdate('Y-m-d H:i:s')];
        }
    }
    return $items;
}

/** Make an item from raw pieces. */
function vgcompanion_import_item(string $provider, string $id, string $title, string $desc, string $date, string $hash = ''): array {
    $ts = strtotime($date) ?: time();
    return ['provider' => $provider, 'id' => $id, 'hash' => $hash, 'title' => $title, 'description' => $desc, 'date' => gmdate('Y-m-d H:i:s', $ts),
        'url' => $provider === 'vimeo' ? 'https://vimeo.com/' . $id . ($hash ? '/' . $hash : '') : 'https://www.youtube.com/watch?v=' . $id];
}

/* ---------- YouTube ---------- */

/** Work out what a pasted YouTube address points at: ['playlist' => id] or ['channel' => UC…] or WP_Error. */
function vgcompanion_yt_target(string $in) {
    $in = trim($in);
    if (preg_match('~[?&]list=([A-Za-z0-9_-]{10,})~', $in, $m)) return ['playlist' => $m[1]];
    if (preg_match('~^(UC[A-Za-z0-9_-]{22})$~', $in, $m) || preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $in, $m)) return ['channel' => $m[1]];
    if (preg_match('~youtube\.com/(@[A-Za-z0-9._-]+|user/[A-Za-z0-9._-]+|c/[A-Za-z0-9._-]+)~', $in, $m) || preg_match('~^(@[A-Za-z0-9._-]+)$~', $in, $m)) {
        $res = wp_remote_get('https://www.youtube.com/' . ltrim($m[1], '/'), ['timeout' => 15, 'headers' => ['Cookie' => 'CONSENT=YES+1', 'Accept-Language' => 'en'], 'user-agent' => 'Mozilla/5.0 (compatible; VideoGuru)']);
        $html = is_wp_error($res) ? '' : (string) wp_remote_retrieve_body($res);
        if (preg_match('~"(?:channelId|externalId)":"(UC[A-Za-z0-9_-]{22})"~', $html, $c) || preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $html, $c)) return ['channel' => $c[1]];
        return new WP_Error('vgcompanion_yt', __('Could not find that channel. Try the address that contains /channel/UC… (open the channel, then About → Share channel → Copy channel ID).', 'videoguru-companion'));
    }
    // A video address from the channel: YouTube's oEmbed says whose channel it is.
    if (($v = vgcompanion_import_parse_url($in)) && $v['provider'] === 'youtube') {
        $d = vgcompanion_import_json('https://www.youtube.com/oembed?format=json&url=' . rawurlencode($v['url']), [], 'YouTube');
        if (is_array($d) && !empty($d['author_url']) && $d['author_url'] !== $in) return vgcompanion_yt_target((string) $d['author_url']);
    }
    // A bare channel name such as liquidguru is taken as the @handle.
    if (preg_match('~^[A-Za-z0-9._-]{3,}$~', $in)) return vgcompanion_yt_target('@' . $in);
    return new WP_Error('vgcompanion_yt', __('That doesn\'t look like a YouTube channel or playlist address. Use the channel\'s @handle or /channel/UC… address, a playlist address, or any one of its video addresses.', 'videoguru-companion'));
}

/** Videos from a channel or playlist. With an API key: everything (up to $max). Without: the newest 15 from the public feed. */
function vgcompanion_yt_list(string $input, string $key = '', int $max = 300) {
    $t = vgcompanion_yt_target($input);
    if (is_wp_error($t)) return $t;
    $items = [];
    if ($key !== '') {
        $pl = $t['playlist'] ?? 'UU' . substr($t['channel'], 2);   // a channel's uploads playlist
        $token = '';
        for ($page = 0; $page < 20 && count($items) < $max; $page++) {
            $url = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet,contentDetails&maxResults=50&playlistId=' . rawurlencode($pl) . '&key=' . rawurlencode($key) . ($token ? '&pageToken=' . rawurlencode($token) : '');
            $d = vgcompanion_import_json($url, [], 'YouTube');
            if (is_wp_error($d)) return $d;
            foreach ($d['items'] ?? [] as $it) {
                $s = $it['snippet'] ?? [];
                $id = $s['resourceId']['videoId'] ?? ($it['contentDetails']['videoId'] ?? '');
                $title = (string) ($s['title'] ?? '');
                if (!$id || in_array($title, ['Private video', 'Deleted video'], true)) continue;
                $items[] = vgcompanion_import_item('youtube', $id, $title, (string) ($s['description'] ?? ''), (string) ($it['contentDetails']['videoPublishedAt'] ?? $s['publishedAt'] ?? ''));
            }
            $token = (string) ($d['nextPageToken'] ?? '');
            if ($token === '') break;
        }
        return $items;
    }
    if (!function_exists('simplexml_load_string')) return new WP_Error('vgcompanion_yt', __('This server can\'t read YouTube feeds (the PHP SimpleXML extension is missing). Add a YouTube API key instead.', 'videoguru-companion'));
    $feed = 'https://www.youtube.com/feeds/videos.xml?' . (isset($t['playlist']) ? 'playlist_id=' . rawurlencode($t['playlist']) : 'channel_id=' . rawurlencode($t['channel']));
    $res = wp_remote_get($feed, ['timeout' => 15, 'user-agent' => 'VideoGuru Companion WordPress plugin']);
    if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) return new WP_Error('vgcompanion_yt', __('YouTube\'s public feed for that address could not be read.', 'videoguru-companion'));
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string((string) wp_remote_retrieve_body($res));
    if (!$xml) return new WP_Error('vgcompanion_yt', __('YouTube sent a feed that could not be understood.', 'videoguru-companion'));
    foreach ($xml->entry as $e) {
        $yt = $e->children('http://www.youtube.com/xml/schemas/2015');
        $media = $e->children('http://search.yahoo.com/mrss/');
        $id = (string) $yt->videoId;
        if (!$id) continue;
        $items[] = vgcompanion_import_item('youtube', $id, (string) $e->title, (string) ($media->group->description ?? ''), (string) $e->published);
    }
    return $items;
}

/* ---------- Vimeo ---------- */

/** Which Vimeo API list does a pasted address mean? Returns ['path' => '/…/videos'] or WP_Error. */
function vgcompanion_vimeo_target(string $in) {
    $in = trim($in);
    $p = preg_replace('~^https?://(?:www\.)?vimeo\.com/?~i', '', $in);
    $p = trim(strtok($p, '?#'), '/');
    if ($p === '' || strtolower($p) === 'me') return ['path' => '/me/videos'];
    if (preg_match('~^channels/([^/]+)~i', $p, $m)) return ['path' => '/channels/' . rawurlencode($m[1]) . '/videos'];
    if (preg_match('~^groups/([^/]+)~i', $p, $m)) return ['path' => '/groups/' . rawurlencode($m[1]) . '/videos'];
    if (preg_match('~^(?:showcase|album)/(\d+)~i', $p, $m)) return ['path' => '/albums/' . $m[1] . '/videos'];
    if (preg_match('~^user/(\d+)/folder/(\d+)~i', $p, $m) || preg_match('~^users/(\d+)/projects/(\d+)~i', $p, $m)) return ['path' => '/users/' . $m[1] . '/projects/' . $m[2] . '/videos'];
    if (preg_match('~^(?:users?/)?(\d+)$~', $p, $m) || preg_match('~^user(\d+)$~i', $p, $m)) return ['path' => '/users/' . $m[1] . '/videos'];
    if (preg_match('~^([A-Za-z0-9_.-]+)$~', $p, $m)) return ['path' => '/users/' . rawurlencode($m[1]) . '/videos'];
    return new WP_Error('vgcompanion_vimeo', __('That doesn\'t look like a Vimeo channel, group, showcase, folder or account address.', 'videoguru-companion'));
}

/** Videos from a Vimeo list. Needs a personal access token (with the "public" and, for your own account, "private" scopes). */
function vgcompanion_vimeo_list(string $input, string $token, int $max = 300) {
    if ($token === '') return new WP_Error('vgcompanion_vimeo', __('Vimeo needs an access token before it will list videos. Add one below.', 'videoguru-companion'));
    $t = vgcompanion_vimeo_target($input);
    if (is_wp_error($t)) return $t;
    $items = [];
    $fields = 'uri,name,description,created_time,link,privacy.view';
    for ($page = 1; $page <= 20 && count($items) < $max; $page++) {
        $d = vgcompanion_import_json('https://api.vimeo.com' . $t['path'] . '?per_page=50&page=' . $page . '&sort=date&direction=desc&fields=' . rawurlencode($fields),
            ['Authorization' => 'bearer ' . $token, 'Accept' => 'application/vnd.vimeo.*+json;version=3.4'], 'Vimeo');
        if (is_wp_error($d)) return $d;
        foreach ($d['data'] ?? [] as $v) {
            if (!preg_match('~/videos/(\d+)~', (string) ($v['uri'] ?? ''), $m)) continue;
            $parsed = vgcompanion_import_parse_url((string) ($v['link'] ?? ''));
            $items[] = vgcompanion_import_item('vimeo', $m[1], (string) ($v['name'] ?? ''), (string) ($v['description'] ?? ''), (string) ($v['created_time'] ?? ''), $parsed['hash'] ?? '');
        }
        if (empty($d['paging']['next'])) break;
    }
    return $items;
}

/* ---------- Making posts ---------- */

/** Create the post for one item. Returns the post ID, or 0 if it already exists, or a WP_Error. */
function vgcompanion_import_create(array $it, array $o) {
    if (vgcompanion_import_existing($it['provider'], $it['id'])) return 0;
    $url = esc_url_raw($it['url']);
    $slug = $it['provider'] === 'vimeo' ? 'vimeo' : 'youtube';
    $content = '<!-- wp:embed {"url":"' . $url . '","type":"video","providerNameSlug":"' . $slug . '","responsive":true} -->' . "\n"
        . '<figure class="wp-block-embed is-type-video is-provider-' . $slug . ' wp-block-embed-' . $slug . '"><div class="wp-block-embed__wrapper">' . "\n" . $url . "\n" . '</div></figure>' . "\n"
        . '<!-- /wp:embed -->';
    $desc = trim(wp_strip_all_tags((string) $it['description']));
    if ($desc !== '') {
        foreach (preg_split('~\n\s*\n~', $desc) as $para) {
            $content .= "\n\n<!-- wp:paragraph -->\n<p>" . nl2br(esc_html(trim($para))) . "</p>\n<!-- /wp:paragraph -->";
        }
    }
    $gmt = $it['date'] ?: gmdate('Y-m-d H:i:s');
    $id = wp_insert_post([
        'post_type' => 'post', 'post_status' => $o['status'], 'post_title' => sanitize_text_field($it['title']),
        'post_content' => $content, 'post_date_gmt' => $gmt, 'post_date' => get_date_from_gmt($gmt),
        'post_category' => $o['cat'] ? [$o['cat']] : [],
    ], true);
    return $id;
}

/** Create many, within a time budget. Returns ['made' => n, 'skipped' => n, 'failed' => [..], 'left' => n]. */
function vgcompanion_import_run(array $items, array $o, int $seconds = 25): array {
    $r = ['made' => 0, 'skipped' => 0, 'failed' => [], 'left' => 0];
    $start = microtime(true);
    foreach ($items as $i => $it) {
        if (microtime(true) - $start > $seconds) { $r['left'] = count($items) - $i; break; }
        $id = vgcompanion_import_create($it, $o);
        if (is_wp_error($id)) $r['failed'][] = $it['title'] . ': ' . $id->get_error_message();
        elseif ($id === 0) $r['skipped']++;
        else $r['made']++;
    }
    return $r;
}

