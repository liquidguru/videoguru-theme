<?php
/**
 * The video wall: what loads on it, the cards, filter chips, related videos and the Random button.
 */
if (!defined('ABSPATH')) exit;

// Wall pages: home, category, tag, search, author and date archives.
add_action('pre_get_posts', function (WP_Query $q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (!($q->is_home() || $q->is_category() || $q->is_tag() || $q->is_search() || $q->is_author() || $q->is_date())) return;
    $q->set('posts_per_page', (int) vg_mod('per_page'));
    $q->set('ignore_sticky_posts', true);
    $q->set('vg_wall', (bool) vg_mod('only_videos'));
    if ($q->is_home()) {
        $order = vg_mod('home_order');
        if ($order === 'oldest') { $q->set('orderby', 'date'); $q->set('order', 'ASC'); }
        elseif ($order === 'title_asc' || $order === 'title_desc') { $q->set('orderby', 'title'); $q->set('order', $order === 'title_asc' ? 'ASC' : 'DESC'); }
    }
});

// Queries flagged 'vg_wall' only return posts that contain a Vimeo or YouTube link (so text-only posts don't leave blank cards).
add_filter('posts_where', function ($where, WP_Query $q) {
    if (!$q->get('vg_wall')) return $where;
    global $wpdb;
    $like = array_map(fn($s) => $wpdb->posts . '.post_content LIKE ' . "'%" . $wpdb->esc_like($s) . "%'", ['vimeo.com', 'youtube.com', 'youtube-nocookie.com', 'youtu.be', '[vimeo']);
    return $where . ' AND (' . implode(' OR ', $like) . ')';
}, 10, 2);

// /?vg_random=1 jumps to a random video (used by the Random button on pages that aren't the wall).
add_action('template_redirect', function () {
    if (!isset($_GET['vg_random']) || !(is_home() || is_front_page())) return;
    $q = new WP_Query(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'orderby' => 'rand', 'fields' => 'ids',
        'no_found_rows' => true, 'vg_wall' => (bool) vg_mod('only_videos'), 'post__not_in' => [absint($_GET['from'] ?? 0)]]);
    nocache_headers();
    wp_safe_redirect($q->posts ? get_permalink($q->posts[0]) : home_url('/'), 302);
    exit;
});

/** Category slugs the owner chose to hide from chips and video pages (Customizer → Video wall). */
function vg_hidden_cats(): array {
    static $h = null;
    if ($h === null) $h = array_filter(array_map('sanitize_title', preg_split('~[\s,]+~', (string) vg_mod('hide_cats'))));
    return $h;
}

/** The categories a video is filed under, ignoring "Uncategorized" and any the owner hid. [id => WP_Term] */
function vg_cats(int $post_id): array {
    $default = (int) get_option('default_category');
    $hide = vg_hidden_cats();
    $out = [];
    foreach (get_the_category($post_id) as $t) if ((int) $t->term_id !== $default && !in_array($t->slug, $hide, true)) $out[$t->term_id] = $t;
    return $out;
}

/**
 * The category each filter chip stands for, for one video: [slug => name].
 * Chip level 1 = top-level categories, 2 = their children, 3 = the next level down. A video in a shallower
 * category than the chosen level is left out of the chips (it still shows under "All").
 */
function vg_groups(int $post_id): array {
    static $cache = [];
    if (isset($cache[$post_id])) return $cache[$post_id];
    $level = max(1, min(3, (int) vg_mod('chip_level')));
    $hide = vg_hidden_cats();
    $default = (int) get_option('default_category');
    $out = [];
    foreach (get_the_category($post_id) as $t) {
        if ((int) $t->term_id === $default) continue;
        $chain = array_reverse(get_ancestors($t->term_id, 'category'));   // root … parent
        $chain[] = $t->term_id;
        if (count($chain) < $level) continue;
        $node = get_term($chain[$level - 1], 'category');
        if ($node && !is_wp_error($node) && !in_array($node->slug, $hide, true)) $out[$node->slug] = $node->name;
    }
    return $cache[$post_id] = $out;
}

/** Up to $n related videos: same category first, then the newest. */
function vg_related(int $post_id, int $n = 4): array {
    $ids = [$post_id];
    $add = function (array $args) use (&$ids, $n) {
        $need = $n - (count($ids) - 1);
        if ($need <= 0) return;
        $q = new WP_Query($args + ['post_type' => 'post', 'post_status' => 'publish', 'post__not_in' => $ids, 'posts_per_page' => $need, 'fields' => 'ids', 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'vg_wall' => (bool) vg_mod('only_videos')]);
        $ids = array_merge($ids, $q->posts);
    };
    $cats = array_keys(vg_cats($post_id));
    if ($cats) $add(['category__in' => $cats]);
    $add([]);
    return array_slice($ids, 1);
}

/** One thumbnail card. */
function vg_card($post = null): void {
    static $n = 0;
    $first = !is_singular() && $n++ < 4; // above-the-fold posters load now, the rest lazily
    $post = get_post($post);
    $v = vg_video_info($post);
    [$title, $sub] = vg_title_parts($post);
    $groups = vg_groups($post->ID);
    $terms = array_merge(wp_list_pluck(vg_cats($post->ID), 'name'), wp_get_post_tags($post->ID, ['fields' => 'names']));
    $search = strtolower(html_entity_decode($title . ' ' . $sub . ' ' . implode(' ', $terms), ENT_QUOTES, 'UTF-8'));
    $dur = vg_mod('show_duration') ? vg_fmt_duration(vg_duration($post)) : '';
    $attrs = ' data-groups="' . esc_attr(implode(' ', array_keys($groups))) . '" data-search="' . esc_attr($search) . '"'
        . ' data-date="' . (int) get_post_time('U', true, $post) . '" data-title="' . esc_attr(strtolower(html_entity_decode($title, ENT_QUOTES, 'UTF-8'))) . '"';
    if ($v['provider'] === 'vimeo') $attrs .= ' data-vimeo="' . esc_attr($v['id']) . '"' . ($v['hash'] ? ' data-h="' . esc_attr($v['hash']) . '"' : '');
    if ($v['provider'] === 'youtube') $attrs .= ' data-youtube="' . esc_attr($v['id']) . '"';
    ?>
    <a class="card" href="<?php echo esc_url(get_permalink($post)); ?>"<?php echo $attrs; // escaped above ?>>
        <?php
        $alt = $title . ($sub !== '' ? ' (' . $sub . ')' : '');
        $load = ['loading' => $first ? 'eager' : 'lazy', 'fetchpriority' => $first ? 'high' : 'auto', 'alt' => $alt, 'sizes' => '(min-width: 1000px) 25vw, (min-width: 600px) 33vw, 100vw'];
        if (has_post_thumbnail($post)) {
            echo get_the_post_thumbnail($post, 'vg-card', $load);
        } elseif (($poster = vg_poster_url($v)) !== '') {
            printf('<img src="%s" alt="%s" width="640" height="360" loading="%s"%s decoding="async">', esc_url($poster), esc_attr($alt), $first ? 'eager' : 'lazy', $first ? ' fetchpriority="high"' : '');
        }
        if ($dur) echo '<span class="dur">' . esc_html($dur) . '</span>';
        ?>
        <span class="meta"><span class="name"><?php echo esc_html($title); ?></span><?php if ($sub !== '') echo '<em class="sub">' . esc_html($sub) . '</em>'; ?></span>
    </a>
    <?php
}
