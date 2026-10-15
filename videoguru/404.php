<?php get_header(); get_template_part('template-parts/bar'); ?>
<main id="main" class="watch page">
  <h1><?php esc_html_e('Nothing here', 'videoguru'); ?></h1>
  <div class="story">
    <p><?php esc_html_e('That page has gone missing. Try the search, or head back to all the videos.', 'videoguru'); ?></p>
    <p><a href="<?php echo esc_url(home_url('/')); ?>">← <?php esc_html_e('All videos', 'videoguru'); ?></a></p>
  </div>
</main>
<?php get_footer();
