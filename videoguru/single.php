<?php
/**
 * A single video.
 */
get_header();
get_template_part('template-parts/bar');
while (have_posts()) : the_post();
    $v = vg_video_info();
    $src = vg_player_src($v);
    $dur = vg_fmt_duration(vg_duration());
    $cats = vg_cats(get_the_ID());
    $related = vg_related(get_the_ID(), 4);
    $prev = get_adjacent_post(false, '', true);
    $next = get_adjacent_post(false, '', false);
    ?>
<main id="main" class="watch">
  <?php if ($src) : ?>
    <div class="player"><iframe src="<?php echo esc_url($src); ?>" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen title="<?php echo esc_attr(get_the_title()); ?>"></iframe></div>
  <?php endif; ?>

  <h1><?php the_title(); ?></h1>

  <ul class="facts">
    <li><?php echo esc_html(get_the_date()); ?></li>
    <?php if ($dur) : ?><li><?php echo esc_html($dur); ?></li><?php endif; ?>
    <?php foreach ($cats as $c) : ?><li><a href="<?php echo esc_url(get_category_link($c)); ?>"><?php echo esc_html($c->name); ?></a></li><?php endforeach; ?>
    <?php foreach (get_the_tags() ?: [] as $t) : ?><li><a href="<?php echo esc_url(get_tag_link($t)); ?>">#<?php echo esc_html($t->name); ?></a></li><?php endforeach; ?>
  </ul>

  <div class="story"><?php the_content(); ?></div>

  <?php if ($related) : ?>
    <h3><?php esc_html_e('More like this', 'videoguru'); ?></h3>
    <div class="grid"><?php foreach ($related as $rid) vg_card($rid); ?></div>
  <?php endif; ?>

  <?php if (comments_open() || get_comments_number()) comments_template(); ?>

  <nav class="pn" aria-label="<?php esc_attr_e('Older and newer videos', 'videoguru'); ?>">
    <span><?php if ($prev) : ?><a href="<?php echo esc_url(get_permalink($prev)); ?>" rel="prev">← <?php echo esc_html(get_the_title($prev)); ?></a><?php endif; ?></span>
    <span><?php if ($next) : ?><a href="<?php echo esc_url(get_permalink($next)); ?>" rel="next"><?php echo esc_html(get_the_title($next)); ?> →</a><?php endif; ?></span>
  </nav>
</main>
<?php endwhile;
get_footer();
