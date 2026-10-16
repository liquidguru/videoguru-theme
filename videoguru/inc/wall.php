<?php
/**
 * The video wall: what loads on it, the cards, filter chips, related videos and the Random button.
 */
if (!defined('ABSPATH')) exit;

// Wall pages: home, category, tag, search, author and date archives.
add_action('pre_get_posts', function (WP_Query $q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (!($q->is_home() || $q->is_category() || $q->is_tag() || $q->is_search() || $q->is_author() || $q->is_date())) return;
    $q->set('posts_per_page', (int) videoguru_mod('per_page'));
    $q->set('ignore_sticky_posts', true);
    $q->set('videoguru_wall', (bool) videoguru_mod('only_videos'));
    if ($q->is_home()) {
        $order = videoguru_mod('home_order');
        if ($order === 'oldest') { $q->set('orderby', 'date'); $q->set('order', 'ASC'); }
        elseif ($order === 'title_asc' || $order === 'title_desc') { $q->set('orderby', 'title'); $q->set('order', $order === 'title_asc' ? 'ASC' : 'DESC'); }
    }
});

// Queries flagged 'videoguru_wall' only return posts that contain a Vimeo or YouTube link (so text-only posts don't leave blank cards).
add_filter('posts_where', function ($where, WP_Query $q) {
    if (!$q->get('videoguru_wall')) return $where;
    global $wpdb;
    $like = array_map(fn($s) => $wpdb->posts . '.post_content LIKE ' . "'%" . $wpdb->esc_like($s) . "%'", ['vimeo.com', 'youtube.com', 'youtube-nocookie.com', 'youtu.be', '[vimeo']);
    return $where . ' AND (' . implode(' OR ', $like) . ')';
}, 10, 2);

// /?videoguru_random=1 jumps to a random video (used by the Random button on pages that aren't the wall).
add_action('template_redirect', function () {
    // A read-only redirect to a random video: it changes nothing, so no nonce is needed.
    if (!isset($_GET['videoguru_random']) || !(is_home() || is_front_page())) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $q = new WP_Query(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'orderby' => 'rand', 'fields' => 'ids',
        'no_found_rows' => true, 'videoguru_wall' => (bool) videoguru_mod('only_videos'), 'post__not_in' => [absint(wp_unslash($_GET['from'] ?? 0))]]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    nocache_headers();
    wp_safe_redirect($q->posts ? get_permalink($q->posts[0]) : home_url('/'), 302);
    exit;
});

/** Category slugs the owner chose to hide from chips and video pages (Customizer → Video wall). */
function videoguru_hidden_cats(): array {
    static $h = null;
    if ($h === null) $h = array_filter(array_map('sanitize_title', preg_split('~[\s,]+~', (string) videoguru_mod('hide_cats'))));
    return $h;
}

/** The categories a video is filed under, ignoring "Uncategorized" and any the owner hid. [id => WP_Term] */
function videoguru_cats(int $post_id): array {
    $default = (int) get_option('default_category');
    $hide = videoguru_hidden_cats();
    $out = [];
    foreach (get_the_category($post_id) as $t) if ((int) $t->term_id !== $default && !in_array($t->slug, $hide, true)) $out[$t->term_id] = $t;
    return $out;
}

/**
 * The category each filter chip stands for, for one video: [slug => name].
 * Chip level 1 = top-level categories, 2 = their children, 3 = the next level down. A video in a shallower
 * category than the chosen level is left out of the chips (it still shows under "All").
 */
function videoguru_groups(int $post_id): array {
    static $cache = [];
    if (isset($cache[$post_id])) return $cache[$post_id];
    $level = max(1, min(3, (int) videoguru_mod('chip_level')));
    $hide = videoguru_hidden_cats();
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
function videoguru_related(int $post_id, int $n = 4): array {
    $ids = [$post_id];
    $add = function (array $args) use (&$ids, $n) {
        $need = $n - (count($ids) - 1);
        if ($need <= 0) return;
        $q = new WP_Query($args + ['post_type' => 'post', 'post_status' => 'publish', 'post__not_in' => $ids, 'posts_per_page' => $need, 'fields' => 'ids', 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'videoguru_wall' => (bool) videoguru_mod('only_videos')]);
        $ids = array_merge($ids, $q->posts);
    };
    $cats = array_keys(videoguru_cats($post_id));
    if ($cats) $add(['category__in' => $cats]);
    $add([]);
    return array_slice($ids, 1);
}

/** One thumbnail card. */
function videoguru_card($post = null): void {
    static $count = 0;
    $first = !is_singular() && $count++ < 4; // above-the-fold posters load now, the rest lazily
    $post = get_post($post);
    $v = videoguru_video_info($post);
    [$title, $sub] = videoguru_title_parts($post);
    $groups = videoguru_groups($post->ID);
    $terms = array_merge(wp_list_pluck(videoguru_cats($post->ID), 'name'), wp_get_post_tags($post->ID, ['fields' => 'names']));
    $search = strtolower(html_entity_decode($title . ' ' . $sub . ' ' . implode(' ', $terms), ENT_QUOTES, 'UTF-8'));
    $sort_title = strtolower(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));
    $dur = videoguru_mod('show_duration') ? videoguru_fmt_duration(videoguru_duration($post)) : '';
    $alt = $title . ($sub !== '' ? ' (' . $sub . ')' : '');
    $poster = has_post_thumbnail($post) ? '' : videoguru_poster_url($v);
    ?>
    <a class="card" href="<?php echo esc_url(get_permalink($post)); ?>" data-groups="<?php echo esc_attr(implode(' ', array_keys($groups))); ?>" data-search="<?php echo esc_attr($search); ?>" data-date="<?php echo (int) get_post_time('U', true, $post); ?>" data-title="<?php echo esc_attr($sort_title); ?>"<?php if ($v['provider'] === 'vimeo') : ?> data-vimeo="<?php echo esc_attr($v['id']); ?>"<?php if ($v['hash']) : ?> data-h="<?php echo esc_attr($v['hash']); ?>"<?php endif; ?><?php endif; ?><?php if ($v['provider'] === 'youtube') : ?> data-youtube="<?php echo esc_attr($v['id']); ?>"<?php endif; ?>>
        <?php if (has_post_thumbnail($post)) : ?>
            <?php echo get_the_post_thumbnail($post, 'videoguru-card', ['loading' => $first ? 'eager' : 'lazy', 'fetchpriority' => $first ? 'high' : 'auto', 'alt' => $alt, 'sizes' => '(min-width: 1000px) 25vw, (min-width: 600px) 33vw, 100vw']); ?>
        <?php elseif ($poster !== '') : ?>
            <img src="<?php echo esc_url($poster); ?>" alt="<?php echo esc_attr($alt); ?>" width="640" height="360" loading="<?php echo $first ? 'eager' : 'lazy'; ?>"<?php if ($first) : ?> fetchpriority="high"<?php endif; ?> decoding="async">
        <?php else : ?>
            <span class="ph" aria-hidden="true"><svg viewBox="0 0 24 24" width="44" height="44" focusable="false"><path d="M9 7v10l8-5z" fill="currentColor"/></svg></span>
        <?php endif; ?>
        <?php if ($dur) : ?><span class="dur"><?php echo esc_html($dur); ?></span><?php endif; ?>
        <span class="meta"><span class="name"><?php echo esc_html($title); ?></span><?php if ($sub !== '') : ?><em class="sub"><?php echo esc_html($sub); ?></em><?php endif; ?></span>
    </a>
    <?php
}
