<?php
/**
 * Search-engine basics: description, canonical, social previews and video structured data.
 * Skipped automatically when a dedicated SEO plugin is active (it does these better).
 */
if (!defined('ABSPATH')) exit;

function vg_seo_plugin_active(): bool {
    return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('SEOPRESS_VERSION') || defined('AIOSEO_VERSION');
}

/** A plain-text excerpt of a post body: no shortcodes, embeds, links or tags; cut at a word boundary. */
function vg_plain_excerpt($post = null, int $len = 155): string {
    $post = get_post($post);
    $c = $post && has_excerpt($post) ? (string) $post->post_excerpt : (string) get_post_field('post_content', $post);
    $c = preg_replace('~<!--.*?-->~s', ' ', $c);
    $c = preg_replace('~<iframe.*?</iframe>~is', ' ', $c);
    $c = preg_replace('~\[[^\]]+\]~', ' ', $c);
    $c = preg_replace('~https?://\S+~', ' ', $c);
    $c = html_entity_decode(wp_strip_all_tags($c), ENT_QUOTES, 'UTF-8');
    $c = trim(preg_replace('~\s+~u', ' ', $c));
    if (mb_strlen($c) <= $len) return $c;
    $cut = mb_substr($c, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: $len), " ,.;:-–") . '…';
}

function vg_meta_description(): string {
    if (is_singular('post')) return vg_plain_excerpt() ?: wp_strip_all_tags(get_the_title()) . ' – ' . get_bloginfo('name');
    if (is_front_page() || is_home()) {
        return trim((string) vg_mod('home_description')) ?: trim(wp_strip_all_tags((string) vg_mod('home_intro'))) ?: get_bloginfo('description');
    }
    if (is_category() || is_tag()) {
        $t = get_queried_object();
        return trim(wp_strip_all_tags(term_description())) ?: sprintf(/* translators: 1: category, 2: site name */ __('Videos about %1$s on %2$s.', 'videoguru'), $t->name, get_bloginfo('name'));
    }
    if (is_page()) return vg_plain_excerpt() ?: get_bloginfo('description');
    return '';
}

/** Poster for social cards and structured data. */
function vg_social_image(): string {
    if (is_singular() && has_post_thumbnail()) return (string) get_the_post_thumbnail_url(null, 'large');
    if (is_singular('post')) return vg_poster_url(vg_video_info());
    $logo = get_custom_logo() ? wp_get_attachment_image_url((int) get_theme_mod('custom_logo'), 'full') : '';
    return (string) $logo;
}

// Search result pages are thin; keep them out of the index.
add_filter('wp_robots', function ($r) {
    if (is_search()) { $r['noindex'] = true; $r['follow'] = true; }
    return $r;
});

add_action('wp_head', function () {
    if (vg_seo_plugin_active() || is_404() || is_search()) return;
    $desc = vg_meta_description();
    $title = wp_get_document_title();
    $url = is_singular() ? get_permalink() : ((is_front_page() || is_home()) ? home_url('/') : ((is_category() || is_tag()) ? get_term_link(get_queried_object()) : ''));
    if ($desc) echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    if (!is_singular() && $url) echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n"; // WordPress adds its own for single pages
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    echo '<meta property="og:type" content="' . (is_singular('post') ? 'video.other' : 'website') . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr(html_entity_decode($title, ENT_QUOTES, 'UTF-8')) . '">' . "\n";
    if ($desc) echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    if ($url) echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    if ($img = vg_social_image()) echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}, 5);

// Structured data so Google can show the video as a rich result.
add_action('wp_head', function () {
    if (!is_singular('post')) return;
    $v = vg_video_info();
    $thumb = vg_social_image();
    if (!$v['provider'] || !$thumb) return;
    $dur = vg_duration();
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => wp_strip_all_tags(get_the_title()),
        'description' => vg_meta_description(),
        'thumbnailUrl' => [$thumb],
        'uploadDate' => get_the_date('c'),
        'embedUrl' => $v['provider'] === 'vimeo' ? 'https://player.vimeo.com/video/' . $v['id'] : 'https://www.youtube.com/embed/' . $v['id'],
        'author' => ['@type' => 'Person', 'name' => get_the_author(), 'url' => get_author_posts_url((int) get_the_author_meta('ID'))],
    ];
    if ($dur > 0) $data['duration'] = 'PT' . intdiv($dur, 60) . 'M' . ($dur % 60) . 'S';
    echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}, 6);
