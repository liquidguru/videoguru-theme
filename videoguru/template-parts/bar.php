<?php
/**
 * Slim bar for pages that aren't the wall: back link, Random button, search.
 * (The wall has its own bar with live filtering in index.php.)
 */
$random = vg_mod('random_label') ?: __('Random video', 'videoguru');
$hint = vg_mod('search_placeholder') ?: __('Search videos…', 'videoguru');
?>
<div class="bar">
  <div class="chips">
    <a class="chip" href="<?php echo esc_url(home_url('/')); ?>">← <?php esc_html_e('All videos', 'videoguru'); ?></a>
  </div>
  <?php if (vg_mod('show_random')) : ?>
    <a class="rand" href="<?php echo esc_url(add_query_arg(['vg_random' => 1, 'from' => is_singular('post') ? get_the_ID() : 0], home_url('/'))); ?>" rel="nofollow">🎲 <?php echo esc_html($random); ?></a>
  <?php endif; ?>
  <?php if (vg_mod('show_search')) : ?>
    <form class="search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <input type="search" name="s" placeholder="<?php echo esc_attr($hint); ?>" aria-label="<?php esc_attr_e('Search videos', 'videoguru'); ?>">
    </form>
  <?php endif; ?>
</div>
