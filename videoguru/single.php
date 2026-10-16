<?php
/**
 * A single video.
 */
get_header();
get_template_part('template-parts/bar');
while (have_posts()) :
    the_post();
    ?>
<main id="main" class="watch">
  <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <?php videoguru_single_article(); ?>
  </article>
  <?php videoguru_single_footer(); ?>
</main>
<?php
endwhile;
get_footer();
