<?php if (post_password_required()) return; ?>
<section id="comments" class="comments-section">
    <h2 class="comments-header">评论</h2>
    <?php if (have_comments()): ?><ol class="comment-list"><?php wp_list_comments(['style' => 'ol', 'callback' => 'timellow_comment', 'avatar_size' => 48, 'short_ping' => true]); ?></ol>
        <?php $pages = paginate_comments_links(['echo' => false, 'prev_text' => '上一页', 'next_text' => '下一页']); if ($pages): ?><div class="comments-nav"><div class="page-navigator"><?php echo wp_kses_post($pages); ?></div></div><?php endif; ?>
    <?php endif; ?>
    <?php if (comments_open()): ?>
        <?php
        $commenter = wp_get_current_commenter();
        $required = (bool) get_option('require_name_email');
        $required_attr = $required ? ' required' : '';
        $fields = [
            'author' => '<p class="comment-form-author"><label class="screen-reader-text" for="author">昵称</label><input class="comment-form-field" id="author" name="author" type="text" placeholder="昵称' . ($required ? ' *' : '') . '" value="' . esc_attr($commenter['comment_author']) . '"' . $required_attr . '></p>',
            'email' => '<p class="comment-form-email"><label class="screen-reader-text" for="email">邮箱</label><input class="comment-form-field" id="email" name="email" type="email" placeholder="邮箱' . ($required ? ' *' : '') . '" value="' . esc_attr($commenter['comment_author_email']) . '"' . $required_attr . '></p>',
            'url' => '<p class="comment-form-url"><label class="screen-reader-text" for="url">网站</label><input class="comment-form-field" id="url" name="url" type="url" placeholder="网站" value="' . esc_attr($commenter['comment_author_url']) . '"></p>',
        ];
        if (get_option('show_comments_cookies_opt_in')) {
            $fields['cookies'] = '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"><label for="wp-comment-cookies-consent">在此浏览器中保存我的昵称、邮箱和网站，以便下次评论。</label></p>';
        }
        comment_form([
            'class_container' => 'comment-form-card', 'title_reply' => '发表评论', 'title_reply_to' => '回复 %s',
            'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
            'title_reply_after' => '</h3>', 'cancel_reply_link' => '取消回复',
            'fields' => $fields,
            'comment_field' => '<p class="comment-form-comment"><label class="screen-reader-text" for="comment">评论内容</label><textarea id="comment" name="comment" placeholder="写下你的看法..." rows="7" required></textarea></p>',
            'label_submit' => '提交评论', 'class_submit' => 'button-link comment-submit-button',
            'comment_notes_before' => '', 'comment_notes_after' => '',
            'logged_in_as' => '<p class="logged-in-as">以 <a href="' . esc_url(admin_url('profile.php')) . '">' . esc_html(wp_get_current_user()->display_name) . '</a> 登录 <span class="footer-divider">·</span> <a href="' . esc_url(wp_logout_url(get_permalink())) . '">退出</a></p>',
        ]);
        ?>
    <?php else: ?><div class="notice-card"><p>评论已关闭。</p></div><?php endif; ?>
</section>
