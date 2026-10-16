<?php
/**
 * The search box in the bar (get_search_form() loads this file).
 */
$videoguru_hint = videoguru_mod('search_placeholder') ?: __('Search videos…', 'videoguru');
?>
<form class="search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
  <input id="q" type="search" name="s" placeholder="<?php echo esc_attr($videoguru_hint); ?>" aria-label="<?php esc_attr_e('Search videos', 'videoguru'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
</form>
