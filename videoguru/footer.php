<footer class="foot">
  <?php if (is_active_sidebar('footer-1') || is_active_sidebar('footer-2') || is_active_sidebar('footer-3')) : ?>
    <div class="foot-cols">
      <?php foreach ([1, 2, 3] as $n) if (is_active_sidebar('footer-' . $n)) dynamic_sidebar('footer-' . $n); ?>
    </div>
  <?php endif; ?>
  <div class="foot-base">
    <span><?php echo wp_kses_post(vg_mod('footer_text') ?: '© ' . gmdate('Y') . ' ' . get_bloginfo('name')); ?></span>
    <?php wp_nav_menu(['theme_location' => 'footer', 'container' => 'nav', 'container_class' => 'foot-links', 'container_aria_label' => __('Pages', 'videoguru'), 'items_wrap' => '%3$s', 'depth' => 1, 'fallback_cb' => false, 'walker' => new VG_Walker()]); ?>
    <?php $social = vg_social_links(); if ($social || vg_mod('show_rss')) : ?>
      <nav class="social" aria-label="<?php esc_attr_e('Elsewhere', 'videoguru'); ?>">
        <?php foreach ($social as $label => $url) : ?><a href="<?php echo esc_url($url); ?>" rel="noopener me"><?php echo esc_html($label); ?></a><?php endforeach; ?>
        <?php if (vg_mod('show_rss')) : ?><a href="<?php echo esc_url(get_feed_link()); ?>">RSS</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</footer>
<button type="button" id="to-top" class="to-top" aria-label="<?php esc_attr_e('Back to top', 'videoguru'); ?>" hidden>
  <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M12 19V6M6 12l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>
<?php wp_footer(); ?>
</body>
</html>
