<?php
/**
 * Plain pages (About, Contact, ...).
 */
get_header();
get_template_part('template-parts/bar');
while (have_posts()) :
    the_post();
    ?>
<main id="main" class="watch page">
  <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <h1><?php the_title(); ?></h1>
    <div class="story">
      <?php
      the_content();
      wp_link_pages(['before' => '<p class="page-links">' . esc_html__('Pages:', 'videoguru'), 'after' => '</p>']);
      ?>
    </div>
  </article>
  <?php if (comments_open() || get_comments_number()) comments_template(); ?>
</main>
<?php
endwhile;
get_footer();
