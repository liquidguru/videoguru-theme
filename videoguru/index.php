<?php
/**
 * The video wall: home, category/tag/author archives and search results.
 */
get_header();

$posts = $GLOBALS['wp_query']->posts;
$pages = (int) $GLOBALS['wp_query']->max_num_pages;
$all = $pages <= 1;                       // the whole wall is on this page: filter and search instantly in the browser
$is_home = is_home() || is_front_page();
$counts = $names = [];
foreach ($posts as $p) foreach (vg_groups($p->ID) as $slug => $name) { $counts[$slug] = ($counts[$slug] ?? 0) + 1; $names[$slug] = $name; }
arsort($counts);
$children = is_category() ? get_categories(['parent' => get_queried_object_id(), 'hide_empty' => true]) : [];
$random = vg_mod('random_label') ?: __('Random video', 'videoguru');
$hint = vg_mod('search_placeholder') ?: __('Search videos…', 'videoguru');
$total = (int) $GLOBALS['wp_query']->found_posts;
?>
<div class="bar">
  <div class="chips" id="chips" role="group" aria-label="<?php esc_attr_e('Filter videos', 'videoguru'); ?>">
    <?php if ($is_home && $all && $counts) : ?>
      <button class="chip" type="button" data-g="all" aria-pressed="true"><?php esc_html_e('All', 'videoguru'); ?><small><?php echo count($posts); ?></small></button>
      <?php foreach ($counts as $slug => $n) : ?>
        <button class="chip" type="button" data-g="<?php echo esc_attr($slug); ?>" aria-pressed="false"><?php echo esc_html($names[$slug]); ?><small><?php echo (int) $n; ?></small></button>
      <?php endforeach; ?>
    <?php elseif ($is_home && !$all) : // too many videos for one page: the chips become links to category pages ?>
      <?php foreach (get_categories(['parent' => 0, 'hide_empty' => true, 'exclude' => [(int) get_option('default_category')], 'orderby' => 'count', 'order' => 'DESC']) as $c) : ?>
        <a class="chip" href="<?php echo esc_url(get_category_link($c)); ?>"><?php echo esc_html($c->name); ?><small><?php echo (int) $c->count; ?></small></a>
      <?php endforeach; ?>
    <?php elseif (!$is_home) : ?>
      <a class="chip" href="<?php echo esc_url(home_url('/')); ?>">← <?php esc_html_e('All videos', 'videoguru'); ?></a>
      <?php foreach ($children as $c) : ?>
        <a class="chip" href="<?php echo esc_url(get_category_link($c)); ?>"><?php echo esc_html($c->name); ?><small><?php echo (int) $c->count; ?></small></a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php if (vg_mod('show_random')) : ?><button class="rand" id="rand" type="button">🎲 <?php echo esc_html($random); ?></button><?php endif; ?>
  <?php if (vg_mod('show_search')) : ?>
    <form class="search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <input id="q" type="search" name="s" placeholder="<?php echo esc_attr($hint); ?>" aria-label="<?php esc_attr_e('Search videos', 'videoguru'); ?>" value="<?php echo is_search() ? esc_attr(get_search_query()) : ''; ?>">
    </form>
  <?php endif; ?>
</div>

<main id="main" class="sheet">
  <?php if ($is_home) : ?>
    <h1 class="sr-only"><?php bloginfo('name'); ?><?php if (get_bloginfo('description')) echo ' – ' . esc_html(get_bloginfo('description')); ?></h1>
  <?php elseif (is_category() || is_tag()) : ?>
    <h1 class="archive-title"><?php single_term_title(); ?></h1>
  <?php elseif (is_search()) : ?>
    <h1 class="archive-title"><?php printf(esc_html__('Search: %s', 'videoguru'), esc_html(get_search_query())); ?></h1>
  <?php else : ?>
    <h1 class="archive-title"><?php the_archive_title(); ?></h1>
  <?php endif; ?>
  <p class="count" id="count" aria-live="polite"><?php echo esc_html(sprintf(_n('%s video', '%s videos', $total, 'videoguru'), number_format_i18n($total))); ?></p>
  <div class="grid" id="grid" data-all="<?php echo $all ? '1' : '0'; ?>" data-order="<?php echo esc_attr($is_home ? vg_mod('home_order') : 'newest'); ?>" data-hover="<?php echo vg_mod('hover_preview') ? '1' : '0'; ?>">
    <?php foreach ($posts as $p) vg_card($p); ?>
  </div>
  <p class="empty" id="empty" hidden><?php esc_html_e('No videos match that search.', 'videoguru'); ?></p>
  <?php if (!$posts) : ?><p class="empty"><?php esc_html_e('Nothing here yet.', 'videoguru'); ?></p><?php endif; ?>
  <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => '←', 'next_text' => '→']); ?>
  <?php if ($is_home && !is_paged() && ($intro = trim((string) vg_mod('home_intro')))) : ?>
    <div class="intro"><?php echo wp_kses_post(wpautop($intro)); ?></div>
  <?php endif; ?>
</main>
<?php get_footer();
