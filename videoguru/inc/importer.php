<?php
/**
 * Appearance → Import videos: turn a list of links, a YouTube channel/playlist or a Vimeo channel/group/showcase/folder/account
 * into one post per video. Existing videos are skipped, so it is safe to run again.
 *
 * Posts are made from: the video's link as an Embed block, its description, and its original publish date.
 * (This is the one part of the theme that creates content. WordPress.org's theme directory doesn't allow that;
 * delete this file and its require in functions.php for a directory build.)
 */
if (!defined('ABSPATH')) exit;

/* ---------- Finding videos ---------- */

/** Parse one URL into a video: ['provider','id','hash','url'] or null. */
function vg_import_parse_url(string $u): ?array {
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
function vg_import_existing(string $provider, string $id): int {
    global $wpdb;
    $host = $provider === 'vimeo' ? 'vimeo' : 'youtu';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish','draft','pending','future','private')
         AND post_content LIKE %s AND post_content LIKE %s LIMIT 1",
        '%' . $wpdb->esc_like($id) . '%', '%' . $wpdb->esc_like($host) . '%'));
}

/** GET a URL and decode JSON. Errors never include the key/token. */
function vg_import_json(string $url, array $headers = [], string $what = 'the service') {
    $res = wp_remote_get($url, ['timeout' => 15, 'headers' => $headers + ['Accept' => 'application/json'], 'user-agent' => 'VideoGuru WordPress theme; ' . home_url('/')]);
    if (is_wp_error($res)) return new WP_Error('vg_http', sprintf(/* translators: %s: service name */ __('Could not reach %s.', 'videoguru'), $what));
    $code = (int) wp_remote_retrieve_response_code($res);
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        $msg = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? $data['developer_message'] ?? '') : '';
        if (is_array($msg)) $msg = '';
        return new WP_Error('vg_api', sprintf(/* translators: 1: service, 2: HTTP status, 3: message */ __('%1$s said no (HTTP %2$d). %3$s', 'videoguru'), $what, $code, wp_strip_all_tags((string) $msg)));
    }
    return $data;
}

/** Items for pasted links: titles and descriptions from the public oEmbed endpoints (no key needed). */
function vg_import_from_links(string $text, int $max = 40, array &$bad = []): array {
    $items = [];
    $seen = [];
    foreach (preg_split('~[\r\n]+~', $text) as $line) {
        foreach (preg_split('~\s+~', trim($line)) as $tok) {
            $v = vg_import_parse_url($tok);
            if (!$v || isset($seen[$v['provider'] . $v['id']])) continue;
            $seen[$v['provider'] . $v['id']] = true;
            if (count($items) >= $max) break 2;
            $title = $desc = '';
            if ($v['provider'] === 'vimeo') {
                $d = vg_import_json('https://vimeo.com/api/oembed.json?url=' . rawurlencode($v['url']), [], 'Vimeo');
                if (is_array($d)) { $title = (string) ($d['title'] ?? ''); $desc = (string) ($d['description'] ?? ''); }
            } else {
                $d = vg_import_json('https://www.youtube.com/oembed?format=json&url=' . rawurlencode($v['url']), [], 'YouTube');
                if (is_array($d)) $title = (string) ($d['title'] ?? '');
            }
            if ($title === '') { $bad[] = sprintf(/* translators: %s: video address */ __('Skipped %s: the video site would not say what it is (private, removed or not embeddable?).', 'videoguru'), $v['url']); continue; }
            $items[] = $v + ['title' => $title, 'description' => $desc, 'date' => gmdate('Y-m-d H:i:s')];
        }
    }
    return $items;
}

/** Make an item from raw pieces. */
function vg_import_item(string $provider, string $id, string $title, string $desc, string $date, string $hash = ''): array {
    $ts = strtotime($date) ?: time();
    return ['provider' => $provider, 'id' => $id, 'hash' => $hash, 'title' => $title, 'description' => $desc, 'date' => gmdate('Y-m-d H:i:s', $ts),
        'url' => $provider === 'vimeo' ? 'https://vimeo.com/' . $id . ($hash ? '/' . $hash : '') : 'https://www.youtube.com/watch?v=' . $id];
}

/* ---------- YouTube ---------- */

/** Work out what a pasted YouTube address points at: ['playlist' => id] or ['channel' => UC…] or WP_Error. */
function vg_yt_target(string $in) {
    $in = trim($in);
    if (preg_match('~[?&]list=([A-Za-z0-9_-]{10,})~', $in, $m)) return ['playlist' => $m[1]];
    if (preg_match('~^(UC[A-Za-z0-9_-]{22})$~', $in, $m) || preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $in, $m)) return ['channel' => $m[1]];
    if (preg_match('~youtube\.com/(@[A-Za-z0-9._-]+|user/[A-Za-z0-9._-]+|c/[A-Za-z0-9._-]+)~', $in, $m) || preg_match('~^(@[A-Za-z0-9._-]+)$~', $in, $m)) {
        $res = wp_remote_get('https://www.youtube.com/' . ltrim($m[1], '/'), ['timeout' => 15, 'headers' => ['Cookie' => 'CONSENT=YES+1', 'Accept-Language' => 'en'], 'user-agent' => 'Mozilla/5.0 (compatible; VideoGuru)']);
        $html = is_wp_error($res) ? '' : (string) wp_remote_retrieve_body($res);
        if (preg_match('~"(?:channelId|externalId)":"(UC[A-Za-z0-9_-]{22})"~', $html, $c) || preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $html, $c)) return ['channel' => $c[1]];
        return new WP_Error('vg_yt', __('Could not find that channel. Try the address that contains /channel/UC… (open the channel, then About → Share channel → Copy channel ID).', 'videoguru'));
    }
    // A video address from the channel: YouTube's oEmbed says whose channel it is.
    if (($v = vg_import_parse_url($in)) && $v['provider'] === 'youtube') {
        $d = vg_import_json('https://www.youtube.com/oembed?format=json&url=' . rawurlencode($v['url']), [], 'YouTube');
        if (is_array($d) && !empty($d['author_url']) && $d['author_url'] !== $in) return vg_yt_target((string) $d['author_url']);
    }
    // A bare channel name such as liquidguru is taken as the @handle.
    if (preg_match('~^[A-Za-z0-9._-]{3,}$~', $in)) return vg_yt_target('@' . $in);
    return new WP_Error('vg_yt', __('That doesn\'t look like a YouTube channel or playlist address. Use the channel\'s @handle or /channel/UC… address, a playlist address, or any one of its video addresses.', 'videoguru'));
}

/** Videos from a channel or playlist. With an API key: everything (up to $max). Without: the newest 15 from the public feed. */
function vg_yt_list(string $input, string $key = '', int $max = 300) {
    $t = vg_yt_target($input);
    if (is_wp_error($t)) return $t;
    $items = [];
    if ($key !== '') {
        $pl = $t['playlist'] ?? 'UU' . substr($t['channel'], 2);   // a channel's uploads playlist
        $token = '';
        for ($page = 0; $page < 20 && count($items) < $max; $page++) {
            $url = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet,contentDetails&maxResults=50&playlistId=' . rawurlencode($pl) . '&key=' . rawurlencode($key) . ($token ? '&pageToken=' . rawurlencode($token) : '');
            $d = vg_import_json($url, [], 'YouTube');
            if (is_wp_error($d)) return $d;
            foreach ($d['items'] ?? [] as $it) {
                $s = $it['snippet'] ?? [];
                $id = $s['resourceId']['videoId'] ?? ($it['contentDetails']['videoId'] ?? '');
                $title = (string) ($s['title'] ?? '');
                if (!$id || in_array($title, ['Private video', 'Deleted video'], true)) continue;
                $items[] = vg_import_item('youtube', $id, $title, (string) ($s['description'] ?? ''), (string) ($it['contentDetails']['videoPublishedAt'] ?? $s['publishedAt'] ?? ''));
            }
            $token = (string) ($d['nextPageToken'] ?? '');
            if ($token === '') break;
        }
        return $items;
    }
    if (!function_exists('simplexml_load_string')) return new WP_Error('vg_yt', __('This server can\'t read YouTube feeds (the PHP SimpleXML extension is missing). Add a YouTube API key instead.', 'videoguru'));
    $feed = 'https://www.youtube.com/feeds/videos.xml?' . (isset($t['playlist']) ? 'playlist_id=' . rawurlencode($t['playlist']) : 'channel_id=' . rawurlencode($t['channel']));
    $res = wp_remote_get($feed, ['timeout' => 15, 'user-agent' => 'VideoGuru WordPress theme']);
    if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) return new WP_Error('vg_yt', __('YouTube\'s public feed for that address could not be read.', 'videoguru'));
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string((string) wp_remote_retrieve_body($res));
    if (!$xml) return new WP_Error('vg_yt', __('YouTube sent a feed that could not be understood.', 'videoguru'));
    foreach ($xml->entry as $e) {
        $yt = $e->children('http://www.youtube.com/xml/schemas/2015');
        $media = $e->children('http://search.yahoo.com/mrss/');
        $id = (string) $yt->videoId;
        if (!$id) continue;
        $items[] = vg_import_item('youtube', $id, (string) $e->title, (string) ($media->group->description ?? ''), (string) $e->published);
    }
    return $items;
}

/* ---------- Vimeo ---------- */

/** Which Vimeo API list does a pasted address mean? Returns ['path' => '/…/videos'] or WP_Error. */
function vg_vimeo_target(string $in) {
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
    return new WP_Error('vg_vimeo', __('That doesn\'t look like a Vimeo channel, group, showcase, folder or account address.', 'videoguru'));
}

/** Videos from a Vimeo list. Needs a personal access token (with the "public" and, for your own account, "private" scopes). */
function vg_vimeo_list(string $input, string $token, int $max = 300) {
    if ($token === '') return new WP_Error('vg_vimeo', __('Vimeo needs an access token before it will list videos. Add one below.', 'videoguru'));
    $t = vg_vimeo_target($input);
    if (is_wp_error($t)) return $t;
    $items = [];
    $fields = 'uri,name,description,created_time,link,privacy.view';
    for ($page = 1; $page <= 20 && count($items) < $max; $page++) {
        $d = vg_import_json('https://api.vimeo.com' . $t['path'] . '?per_page=50&page=' . $page . '&sort=date&direction=desc&fields=' . rawurlencode($fields),
            ['Authorization' => 'bearer ' . $token, 'Accept' => 'application/vnd.vimeo.*+json;version=3.4'], 'Vimeo');
        if (is_wp_error($d)) return $d;
        foreach ($d['data'] ?? [] as $v) {
            if (!preg_match('~/videos/(\d+)~', (string) ($v['uri'] ?? ''), $m)) continue;
            $parsed = vg_import_parse_url((string) ($v['link'] ?? ''));
            $items[] = vg_import_item('vimeo', $m[1], (string) ($v['name'] ?? ''), (string) ($v['description'] ?? ''), (string) ($v['created_time'] ?? ''), $parsed['hash'] ?? '');
        }
        if (empty($d['paging']['next'])) break;
    }
    return $items;
}

/* ---------- Making posts ---------- */

/** Create the post for one item. Returns the post ID, or 0 if it already exists, or a WP_Error. */
function vg_import_create(array $it, array $o) {
    if (vg_import_existing($it['provider'], $it['id'])) return 0;
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
function vg_import_run(array $items, array $o, int $seconds = 25): array {
    $r = ['made' => 0, 'skipped' => 0, 'failed' => [], 'left' => 0];
    $start = microtime(true);
    foreach ($items as $i => $it) {
        if (microtime(true) - $start > $seconds) { $r['left'] = count($items) - $i; break; }
        $id = vg_import_create($it, $o);
        if (is_wp_error($id)) $r['failed'][] = $it['title'] . ': ' . $id->get_error_message();
        elseif ($id === 0) $r['skipped']++;
        else $r['made']++;
    }
    return $r;
}

/* ---------- The admin page ---------- */

add_action('admin_menu', function () {
    add_theme_page(__('Import videos', 'videoguru'), __('Import videos', 'videoguru'), 'manage_options', 'videoguru-import', 'vg_import_page');
});

function vg_import_page(): void {
    if (!current_user_can('manage_options')) return;
    $tab = isset($_GET['tab']) && in_array($_GET['tab'], ['paste', 'youtube', 'vimeo'], true) ? $_GET['tab'] : 'paste';
    $base = admin_url('themes.php?page=videoguru-import');
    $notice = '';
    $review = null;
    $result = null;
    $uid = get_current_user_id();

    if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['vg_action'])) {
        check_admin_referer('vg_import');
        $act = sanitize_key($_POST['vg_action']);
        $opts = [
            'status' => ($_POST['status'] ?? 'publish') === 'draft' ? 'draft' : 'publish',
            'cat' => 0,
        ];
        $new = trim(sanitize_text_field(wp_unslash($_POST['new_cat'] ?? '')));
        if ($new !== '') { require_once ABSPATH . 'wp-admin/includes/taxonomy.php'; $opts['cat'] = (int) wp_create_category($new); }
        elseif (!empty($_POST['cat'])) $opts['cat'] = absint($_POST['cat']);

        if ($act === 'save_yt_key') { update_option('vg_yt_key', sanitize_text_field(wp_unslash($_POST['yt_key'] ?? '')), false); $notice = __('YouTube key saved.', 'videoguru'); }
        elseif ($act === 'save_vimeo_token') { update_option('vg_vimeo_token', sanitize_text_field(wp_unslash($_POST['vimeo_token'] ?? '')), false); $notice = __('Vimeo token saved.', 'videoguru'); }
        elseif ($act === 'paste') {
            $bad = [];
            $items = vg_import_from_links(wp_unslash($_POST['links'] ?? ''), 40, $bad);
            $result = $items ? vg_import_run($items, $opts) : ['made' => 0, 'skipped' => 0, 'failed' => [], 'left' => 0];
            $result['failed'] = array_merge($bad, $result['failed']);
            if (!$items && !$bad) $result['failed'][] = __('No YouTube or Vimeo links found in that text.', 'videoguru');
        } elseif ($act === 'list_youtube' || $act === 'list_vimeo') {
            $items = $act === 'list_youtube'
                ? vg_yt_list(wp_unslash($_POST['source'] ?? ''), (string) get_option('vg_yt_key', ''))
                : vg_vimeo_list(wp_unslash($_POST['source'] ?? ''), (string) get_option('vg_vimeo_token', ''));
            if (is_wp_error($items)) $notice = $items->get_error_message();
            elseif (!$items) $notice = __('No videos were found at that address.', 'videoguru');
            else { set_transient('vg_import_' . $uid, $items, HOUR_IN_SECONDS); $review = $items; }
        } elseif ($act === 'import_selected') {
            $all = get_transient('vg_import_' . $uid);
            $pick = array_map('sanitize_text_field', (array) wp_unslash($_POST['pick'] ?? []));
            $sel = array_values(array_filter((array) $all, fn($it) => in_array($it['provider'] . ':' . $it['id'], $pick, true)));
            $result = $sel ? vg_import_run($sel, $opts) : ['made' => 0, 'skipped' => 0, 'failed' => [__('Nothing was selected.', 'videoguru')], 'left' => 0];
            if ($result['left']) $review = $all; // keep the list on screen so the rest can be imported with another click
        }
    }

    $yt_key = (string) get_option('vg_yt_key', '');
    $vm_tok = (string) get_option('vg_vimeo_token', '');
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Import videos', 'videoguru'); ?></h1>
      <p><?php esc_html_e('Each video becomes one post, with its link, description and original date. Videos you already have are skipped, so running this again is safe.', 'videoguru'); ?></p>
      <h2 class="nav-tab-wrapper">
        <?php foreach (['paste' => __('Paste links', 'videoguru'), 'youtube' => 'YouTube', 'vimeo' => 'Vimeo'] as $k => $label) : ?>
          <a class="nav-tab<?php echo $tab === $k ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab', $k, $base)); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </h2>

      <?php if ($notice) : ?><div class="notice notice-info"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
      <?php if ($result) : ?>
        <div class="notice <?php echo $result['failed'] ? 'notice-warning' : 'notice-success'; ?>"><p>
          <?php echo esc_html(sprintf(/* translators: 1: created, 2: skipped */ __('Imported %1$d new videos, skipped %2$d already on the site.', 'videoguru'), $result['made'], $result['skipped'])); ?>
          <?php if ($result['left']) echo esc_html(sprintf(__('%d more are still waiting: click Import again.', 'videoguru'), $result['left'])); ?>
        </p><?php foreach ($result['failed'] as $f) echo '<p>' . esc_html($f) . '</p>'; ?></div>
      <?php endif; ?>

      <?php if ($review !== null) : vg_import_review($review, $tab); else : ?>
        <form method="post" action="<?php echo esc_url(add_query_arg('tab', $tab, $base)); ?>">
          <?php wp_nonce_field('vg_import'); ?>
          <?php if ($tab === 'paste') : ?>
            <input type="hidden" name="vg_action" value="paste">
            <p><label for="links"><strong><?php esc_html_e('Video links, one per line (YouTube or Vimeo)', 'videoguru'); ?></strong></label></p>
            <textarea id="links" name="links" rows="10" class="large-text code" placeholder="https://vimeo.com/123456789&#10;https://www.youtube.com/watch?v=XXXXXXXXXXX"></textarea>
            <p class="description"><?php esc_html_e('Up to 40 at a time. Titles and descriptions are fetched from the video sites. No key needed.', 'videoguru'); ?></p>
            <?php vg_import_options(); submit_button(__('Import these videos', 'videoguru')); ?>
          <?php elseif ($tab === 'youtube') : ?>
            <input type="hidden" name="vg_action" value="list_youtube">
            <p><label for="source"><strong><?php esc_html_e('YouTube channel or playlist address', 'videoguru'); ?></strong></label></p>
            <input id="source" name="source" type="text" class="large-text" placeholder="https://www.youtube.com/@yourchannel   or   https://www.youtube.com/playlist?list=PL…">
            <p class="description"><?php echo $yt_key ? esc_html__('An API key is saved, so every video can be listed.', 'videoguru') : esc_html__('Without an API key YouTube only shares the newest 15 videos. Add a key below to list them all.', 'videoguru'); ?></p>
            <?php submit_button(__('Find videos', 'videoguru'), 'primary', 'submit', false); ?>
          <?php else : ?>
            <input type="hidden" name="vg_action" value="list_vimeo">
            <p><label for="source"><strong><?php esc_html_e('Vimeo address', 'videoguru'); ?></strong></label></p>
            <input id="source" name="source" type="text" class="large-text" placeholder="https://vimeo.com/channels/yourchannel   |   /groups/…   |   /showcase/123456   |   /user12345   |   or leave empty for your own videos">
            <p class="description"><?php echo $vm_tok ? esc_html__('An access token is saved.', 'videoguru') : esc_html__('Vimeo only lists videos for people with an access token. Add one below.', 'videoguru'); ?></p>
            <?php submit_button(__('Find videos', 'videoguru'), 'primary', 'submit', false); ?>
          <?php endif; ?>
        </form>

        <?php if ($tab === 'youtube') : ?>
          <hr><h2><?php esc_html_e('YouTube API key (optional)', 'videoguru'); ?></h2>
          <form method="post" action="<?php echo esc_url(add_query_arg('tab', 'youtube', $base)); ?>">
            <?php wp_nonce_field('vg_import'); ?><input type="hidden" name="vg_action" value="save_yt_key">
            <input name="yt_key" type="password" class="regular-text" autocomplete="off" value="<?php echo esc_attr($yt_key); ?>" placeholder="AIza…">
            <?php submit_button(__('Save key', 'videoguru'), 'secondary', 'submit', false); ?>
            <p class="description"><?php esc_html_e('Free: Google Cloud Console → create a project → enable "YouTube Data API v3" → Credentials → Create API key. Stored in this site\'s settings; only administrators can see this page.', 'videoguru'); ?></p>
          </form>
        <?php elseif ($tab === 'vimeo') : ?>
          <hr><h2><?php esc_html_e('Vimeo access token', 'videoguru'); ?></h2>
          <form method="post" action="<?php echo esc_url(add_query_arg('tab', 'vimeo', $base)); ?>">
            <?php wp_nonce_field('vg_import'); ?><input type="hidden" name="vg_action" value="save_vimeo_token">
            <input name="vimeo_token" type="password" class="regular-text" autocomplete="off" value="<?php echo esc_attr($vm_tok); ?>">
            <?php submit_button(__('Save token', 'videoguru'), 'secondary', 'submit', false); ?>
            <p class="description"><?php esc_html_e('Free: developer.vimeo.com/apps → create an app → Generate an access token with the "public" and "private" scopes. Private videos in your own account are listed too, but only public ones play for visitors. Stored in this site\'s settings; only administrators can see this page.', 'videoguru'); ?></p>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
}

/** Category and status choices shared by the import forms. */
function vg_import_options(): void {
    ?>
    <p>
      <label><?php esc_html_e('Put them in category:', 'videoguru'); ?>
        <?php wp_dropdown_categories(['show_option_none' => __('(none)', 'videoguru'), 'option_none_value' => 0, 'hide_empty' => 0, 'name' => 'cat', 'taxonomy' => 'category', 'hierarchical' => 1]); ?>
      </label>
      <label><?php esc_html_e('or a new one:', 'videoguru'); ?> <input type="text" name="new_cat" class="regular-text" style="width:12em"></label>
    </p>
    <p><label><?php esc_html_e('Publish as:', 'videoguru'); ?>
      <select name="status"><option value="publish"><?php esc_html_e('Published', 'videoguru'); ?></option><option value="draft"><?php esc_html_e('Drafts (I will review first)', 'videoguru'); ?></option></select></label></p>
    <?php
}

/** The "found N videos" list with tick boxes. */
function vg_import_review(array $items, string $tab): void {
    $new = 0;
    foreach ($items as $i => $it) { $items[$i]['dup'] = vg_import_existing($it['provider'], $it['id']); if (!$items[$i]['dup']) $new++; }
    ?>
    <form method="post" action="<?php echo esc_url(add_query_arg('tab', $tab, admin_url('themes.php?page=videoguru-import'))); ?>">
      <?php wp_nonce_field('vg_import'); ?><input type="hidden" name="vg_action" value="import_selected">
      <p><strong><?php echo esc_html(sprintf(/* translators: 1: found, 2: new */ __('Found %1$d videos, %2$d not on your site yet.', 'videoguru'), count($items), $new)); ?></strong></p>
      <?php vg_import_options(); ?>
      <table class="widefat striped" style="max-width:900px"><thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.vg-pick:not(:disabled)').forEach(function(c){c.checked=this.checked}.bind(this))" checked></td><th><?php esc_html_e('Title', 'videoguru'); ?></th><th><?php esc_html_e('Date', 'videoguru'); ?></th><th></th></tr></thead><tbody>
      <?php foreach ($items as $it) : ?>
        <tr>
          <th class="check-column"><input class="vg-pick" type="checkbox" name="pick[]" value="<?php echo esc_attr($it['provider'] . ':' . $it['id']); ?>" <?php echo $it['dup'] ? 'disabled' : 'checked'; ?>></th>
          <td><?php echo esc_html($it['title']); ?></td>
          <td><?php echo esc_html(substr($it['date'], 0, 10)); ?></td>
          <td><?php echo $it['dup'] ? esc_html__('already on site', 'videoguru') : ''; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php submit_button(__('Import selected videos', 'videoguru')); ?>
    </form>
    <?php
}
