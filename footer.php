    </div>
    <?php timellow_render_social(); ?>
    <footer class="site-footer"><p>&copy; <?php echo esc_html(wp_date('Y')); ?> <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
        <?php if ($icp = get_theme_mod('icp_record')): ?><span class="footer-divider">·</span><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener"><?php echo esc_html($icp); ?></a><?php endif; ?>
        <span class="footer-divider">·</span>由 <a href="https://wordpress.org/" target="_blank" rel="noopener">WordPress</a> 驱动
        <span class="footer-divider">·</span>Theme <a href="https://www.timellow.com/" target="_blank" rel="noopener">Timellow</a>
        <?php if ($note = get_theme_mod('footer_note')): ?><span class="footer-divider">·</span><?php echo esc_html($note); ?><?php endif; ?>
    </p></footer>
    <nav class="floating-actions" aria-label="快捷操作">
        <button class="floating-action floating-action-top" type="button" data-back-to-top hidden aria-label="返回顶部" title="返回顶部"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg></button>
        <?php if (is_singular() && current_user_can('edit_post', get_queried_object_id())): ?><a class="floating-action" href="<?php echo esc_url(get_edit_post_link()); ?>" aria-label="编辑当前内容" title="编辑当前内容"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></a><?php endif; ?>
        <?php if (is_user_logged_in()): ?><a class="floating-action" href="<?php echo esc_url(admin_url()); ?>" aria-label="进入后台" title="进入后台"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4m-5 5 5-5-5-5m5 5H3"/></svg></a><?php endif; ?>
    </nav>
</div></div>
<?php wp_footer(); ?>
</body></html>
