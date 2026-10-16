<?php
/**
 * Template tags: the markup behind the wall, the single video page, the bars and the footer.
 * (Kept in functions so the template files stay small and every variable is local and prefixed.)
 */
if (!defined('ABSPATH')) exit;

/** What the wall (home, category, tag, search, author, date) needs to know about the current query. */
function videoguru_wall_context(): array {
    global $wp_query;
    $posts = (array) $wp_query->posts;
    $counts = [];
    $names = [];
    foreach ($posts as $post) {
        foreach (videoguru_groups($post->ID) as $slug => $name) {
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
            $names[$slug] = $name;
        }
    }
    arsort($counts);
    return [
        'posts'   => $posts,
        'total'   => (int) $wp_query->found_posts,
        'whole'   => (int) $wp_query->max_num_pages <= 1,   // the whole wall is on this page: filter and search instantly in the browser
        'is_home' => is_home() || is_front_page(),
        'counts'  => $counts,
        'names'   => $names,
    ];
}

/** The sticky bar on the wall: filter chips, Sort menu, Random button and search. */
function videoguru_wall_bar(array $ctx): void {
    $random = videoguru_mod('random_label') ?: __('Random video', 'videoguru');
    ?>
<div class="bar">
  <div class="chips" id="chips" role="group" aria-label="<?php esc_attr_e('Filter videos', 'videoguru'); ?>">
    <?php if ($ctx['is_home'] && $ctx['whole'] && $ctx['counts']) : ?>
      <button class="chip" type="button" data-g="all" aria-pressed="true"><?php esc_html_e('All', 'videoguru'); ?><small><?php echo (int) count($ctx['posts']); ?></small></button>
      <?php foreach ($ctx['counts'] as $slug => $n) : ?>
        <button class="chip" type="button" data-g="<?php echo esc_attr($slug); ?>" aria-pressed="false"><?php echo esc_html($ctx['names'][$slug]); ?><small><?php echo (int) $n; ?></small></button>
      <?php endforeach; ?>
    <?php elseif ($ctx['is_home'] && !$ctx['whole']) : // too many videos for one page: the chips become links to category pages ?>
      <?php foreach (get_categories(['parent' => 0, 'hide_empty' => true, 'exclude' => [(int) get_option('default_category')], 'orderby' => 'count', 'order' => 'DESC']) as $cat) : ?>
        <a class="chip" href="<?php echo esc_url(get_category_link($cat)); ?>"><?php echo esc_html($cat->name); ?><small><?php echo (int) $cat->count; ?></small></a>
      <?php endforeach; ?>
    <?php elseif (!$ctx['is_home']) : ?>
      <a class="chip" href="<?php echo esc_url(home_url('/')); ?>">← <?php esc_html_e('All videos', 'videoguru'); ?></a>
      <?php foreach ((is_category() ? get_categories(['parent' => get_queried_object_id(), 'hide_empty' => true]) : []) as $cat) : ?>
        <a class="chip" href="<?php echo esc_url(get_category_link($cat)); ?>"><?php echo esc_html($cat->name); ?><small><?php echo (int) $cat->count; ?></small></a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php if ($ctx['is_home'] && $ctx['whole'] && videoguru_mod('show_sort') && count($ctx['posts']) > 1) : ?>
    <label class="sort"><span class="sr-only"><?php esc_html_e('Sort videos', 'videoguru'); ?></span>
      <select id="sort" hidden>
        <?php foreach (['newest' => __('Newest first', 'videoguru'), 'oldest' => __('Oldest first', 'videoguru'), 'title_asc' => __('Title A–Z', 'videoguru'), 'title_desc' => __('Title Z–A', 'videoguru'), 'random' => __('Random', 'videoguru')] as $value => $label) : ?>
          <option value="<?php echo esc_attr($value); ?>"<?php selected(videoguru_mod('home_order'), $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <?php if (videoguru_mod('show_random')) : ?><button class="rand" id="rand" type="button">🎲 <?php echo esc_html($random); ?></button><?php endif; ?>
  <?php if (videoguru_mod('show_search')) get_search_form(); ?>
</div>
    <?php
}

/** The page heading on the wall (hidden on the home page, where the site title already says it). */
function videoguru_wall_heading(bool $is_home): void {
    if ($is_home) {
        echo '<h1 class="sr-only">' . esc_html(get_bloginfo('name'));
        if (get_bloginfo('description')) echo ' – ' . esc_html(get_bloginfo('description'));
        echo '</h1>';
    } elseif (is_category() || is_tag()) {
        echo '<h1 class="archive-title">' . esc_html(single_term_title('', false)) . '</h1>';
    } elseif (is_search()) {
        /* translators: %s: the words the visitor searched for */
        echo '<h1 class="archive-title">' . esc_html(sprintf(__('Search: %s', 'videoguru'), get_search_query(false))) . '</h1>';
    } else {
        echo '<h1 class="archive-title">' . wp_kses_post(get_the_archive_title()) . '</h1>';
    }
}

/** "34 videos" */
function videoguru_count_label(int $total): string {
    /* translators: %s: number of videos */
    return sprintf(_n('%s video', '%s videos', $total, 'videoguru'), number_format_i18n($total));
}

/** Slim bar for pages that aren't the wall: back link, Random button, search. */
function videoguru_slim_bar(): void {
    $random = videoguru_mod('random_label') ?: __('Random video', 'videoguru');
    ?>
<div class="bar">
  <div class="chips">
    <a class="chip" href="<?php echo esc_url(home_url('/')); ?>">← <?php esc_html_e('All videos', 'videoguru'); ?></a>
  </div>
  <?php if (videoguru_mod('show_random')) : ?>
    <a class="rand" href="<?php echo esc_url(add_query_arg(['videoguru_random' => 1, 'from' => is_singular('post') ? get_the_ID() : 0], home_url('/'))); ?>" rel="nofollow">🎲 <?php echo esc_html($random); ?></a>
  <?php endif; ?>
  <?php if (videoguru_mod('show_search')) get_search_form(); ?>
</div>
    <?php
}

/** A single video's player, title, details and text. Call inside the loop. */
function videoguru_single_article(): void {
    [$name, $sub] = videoguru_title_parts();
    $src = videoguru_player_src(videoguru_video_info());
    $dur = videoguru_fmt_duration(videoguru_duration());
    ?>
    <?php if ($src) : ?>
      <div class="player"><iframe src="<?php echo esc_url($src); ?>" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen title="<?php echo esc_attr($name); ?>"></iframe></div>
    <?php endif; ?>

    <h1><?php echo esc_html($name); ?></h1>
    <?php if ($sub !== '') : ?><p class="sub"><?php echo esc_html($sub); ?></p><?php endif; ?>

    <ul class="facts">
      <li><?php echo esc_html(get_the_date()); ?></li>
      <?php if ($dur) : ?><li><?php echo esc_html($dur); ?></li><?php endif; ?>
      <?php foreach (videoguru_cats(get_the_ID()) as $cat) : ?><li><a href="<?php echo esc_url(get_category_link($cat)); ?>"><?php echo esc_html($cat->name); ?></a></li><?php endforeach; ?>
      <?php foreach (get_the_tags() ?: [] as $tag) : ?><li><a href="<?php echo esc_url(get_tag_link($tag)); ?>">#<?php echo esc_html($tag->name); ?></a></li><?php endforeach; ?>
    </ul>

    <div class="story">
      <?php
      the_content();
      wp_link_pages(['before' => '<p class="page-links">' . esc_html__('Pages:', 'videoguru'), 'after' => '</p>']);
      ?>
    </div>
    <?php
}

/** Under a video: related videos, comments and previous/next. Call inside the loop. */
function videoguru_single_footer(): void {
    $related = videoguru_related(get_the_ID(), 4);
    $prev = get_adjacent_post(false, '', true);
    $next = get_adjacent_post(false, '', false);
    ?>
    <?php if ($related) : ?>
      <h3><?php esc_html_e('More like this', 'videoguru'); ?></h3>
      <div class="grid"><?php foreach ($related as $related_id) videoguru_card($related_id); ?></div>
    <?php endif; ?>

    <?php if (comments_open() || get_comments_number()) comments_template(); ?>

    <nav class="pn" aria-label="<?php esc_attr_e('Older and newer videos', 'videoguru'); ?>">
      <span><?php if ($prev) : ?><a href="<?php echo esc_url(get_permalink($prev)); ?>" rel="prev">← <?php echo esc_html(videoguru_title_parts($prev)[0]); ?></a><?php endif; ?></span>
      <span><?php if ($next) : ?><a href="<?php echo esc_url(get_permalink($next)); ?>" rel="next"><?php echo esc_html(videoguru_title_parts($next)[0]); ?> →</a><?php endif; ?></span>
    </nav>
    <?php
}

/** The footer: optional widget columns, footer text, footer menu, social links. */
function videoguru_footer_content(): void {
    $social = videoguru_social_links();
    ?>
<footer class="foot">
  <?php if (is_active_sidebar('footer-1') || is_active_sidebar('footer-2') || is_active_sidebar('footer-3')) : ?>
    <div class="foot-cols">
      <?php foreach ([1, 2, 3] as $column) if (is_active_sidebar('footer-' . $column)) dynamic_sidebar('footer-' . $column); ?>
    </div>
  <?php endif; ?>
  <div class="foot-base">
    <span><?php echo wp_kses_post(videoguru_mod('footer_text') ?: '© ' . gmdate('Y') . ' ' . get_bloginfo('name')); ?></span>
    <?php wp_nav_menu(['theme_location' => 'footer', 'container' => 'nav', 'container_class' => 'foot-links', 'container_aria_label' => __('Pages', 'videoguru'), 'items_wrap' => '%3$s', 'depth' => 1, 'fallback_cb' => false, 'walker' => new Videoguru_Walker()]); ?>
    <?php if ($social || videoguru_mod('show_rss')) : ?>
      <nav class="social" aria-label="<?php esc_attr_e('Elsewhere', 'videoguru'); ?>">
        <?php foreach ($social as $label => $url) : ?><a href="<?php echo esc_url($url); ?>" rel="noopener me"><?php echo esc_html($label); ?></a><?php endforeach; ?>
        <?php if (videoguru_mod('show_rss')) : ?><a href="<?php echo esc_url(get_feed_link()); ?>">RSS</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</footer>
<button type="button" id="to-top" class="to-top" aria-label="<?php esc_attr_e('Back to top', 'videoguru'); ?>" hidden>
  <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M12 19V6M6 12l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>
    <?php
}
