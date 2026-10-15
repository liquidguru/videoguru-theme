<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main"><?php esc_html_e('Skip to content', 'videoguru'); ?></a>
<header class="top">
  <?php if (has_custom_logo()) : the_custom_logo(); else : ?>
    <a class="site-title" href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php bloginfo('name'); ?></a>
  <?php endif; ?>
  <?php if (vg_mod('show_tagline') && get_bloginfo('description')) : ?><span class="tag"><?php bloginfo('description'); ?></span><?php endif; ?>
  <?php wp_nav_menu(['theme_location' => 'primary', 'container' => 'nav', 'container_class' => 'links', 'container_aria_label' => __('Site', 'videoguru'), 'items_wrap' => '%3$s', 'depth' => 1, 'fallback_cb' => false, 'walker' => new VG_Walker()]); ?>
</header>
