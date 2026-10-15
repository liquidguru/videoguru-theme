<?php
if (post_password_required()) return;
?>
<section class="comments" id="comments">
  <?php if (have_comments()) : ?>
    <h3><?php printf(esc_html(_n('%s comment', '%s comments', get_comments_number(), 'videoguru')), number_format_i18n(get_comments_number())); ?></h3>
    <?php wp_list_comments(['style' => 'div', 'avatar_size' => 0, 'callback' => function ($c, $args, $depth) { ?>
      <div id="comment-<?php comment_ID(); ?>" <?php comment_class($depth > 1 ? 'comment reply' : 'comment'); ?>>
        <b><?php comment_author(); ?></b><small><?php echo esc_html(get_comment_date('', $c)); ?></small>
        <?php comment_text(); ?>
    <?php }, 'end-callback' => function () { echo '</div>'; }]); ?>
  <?php endif; ?>
  <?php comment_form(['title_reply' => esc_html__('Leave a comment', 'videoguru'), 'class_submit' => 'rand', 'comment_notes_before' => '', 'logged_in_as' => '', 'comment_notes_after' => '']); ?>
</section>
