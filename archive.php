<?php get_header(); [$heading, $description] = timellow_archive_heading(); ?>
<main class="site-main">
    <section class="page-hero"><h1 class="page-title"><?php echo esc_html(wp_strip_all_tags($heading)); ?></h1><?php if ($description): ?><p class="page-description"><?php echo esc_html(wp_strip_all_tags($description)); ?></p><?php endif; ?></section>
    <?php if (have_posts()): ?><div class="post-list" data-post-list>
        <?php while (have_posts()): the_post(); get_template_part('template-parts/post-card'); endwhile; ?>
    </div><?php timellow_pagination(); ?>
    <?php else: ?><section class="empty-state"><p>这个归档下暂时没有内容。</p></section><?php endif; ?>
</main>
<?php get_footer(); ?>
