<?php
/**
 * The video wall: home, category/tag/author archives and search results.
 */
get_header();

$videoguru_ctx = videoguru_wall_context();
videoguru_wall_bar($videoguru_ctx);
?>

<main id="main" class="sheet">
  <?php videoguru_wall_heading($videoguru_ctx['is_home']); ?>
  <p class="count" id="count" aria-live="polite"><?php echo esc_html(videoguru_count_label($videoguru_ctx['total'])); ?></p>
  <div class="grid" id="grid" data-all="<?php echo $videoguru_ctx['whole'] ? '1' : '0'; ?>" data-order="<?php echo esc_attr($videoguru_ctx['is_home'] ? videoguru_mod('home_order') : 'newest'); ?>" data-hover="<?php echo videoguru_mod('hover_preview') ? '1' : '0'; ?>">
    <?php foreach ($videoguru_ctx['posts'] as $videoguru_post) videoguru_card($videoguru_post); ?>
  </div>
  <p class="empty" id="empty" hidden><?php esc_html_e('No videos match that search.', 'videoguru'); ?></p>
  <?php if (!$videoguru_ctx['posts']) : ?><p class="empty"><?php esc_html_e('Nothing here yet.', 'videoguru'); ?></p><?php endif; ?>
  <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => '←', 'next_text' => '→']); ?>
  <?php if ($videoguru_ctx['is_home'] && !is_paged() && trim((string) videoguru_mod('home_intro')) !== '') : ?>
    <div class="intro"><?php echo wp_kses_post(wpautop(trim((string) videoguru_mod('home_intro')))); ?></div>
  <?php endif; ?>
</main>
<?php get_footer();
