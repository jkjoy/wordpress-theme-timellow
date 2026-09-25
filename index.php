<?php get_header(); ?>
<main class="site-main">
    <?php if (have_posts()): ?><div class="post-list" data-post-list>
        <?php while (have_posts()): the_post(); get_template_part('template-parts/post-card'); endwhile; ?>
    </div><?php timellow_pagination(); ?>
    <?php else: ?><section class="empty-state"><p>这里还没有发布任何文章。</p></section><?php endif; ?>
</main>
<?php get_footer(); ?>
