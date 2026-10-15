<?php
/**
 * Plain pages (About, Contact, ...).
 */
get_header();
get_template_part('template-parts/bar');
while (have_posts()) : the_post(); ?>
<main id="main" class="watch page">
  <h1><?php the_title(); ?></h1>
  <div class="story"><?php the_content(); ?></div>
  <?php if (comments_open()) comments_template(); ?>
</main>
<?php endwhile;
get_footer();
