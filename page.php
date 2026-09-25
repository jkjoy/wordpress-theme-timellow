<?php get_header(); while (have_posts()): the_post(); ?>
<main class="site-main"><section class="page-hero"><h1 class="page-title"><?php the_title(); ?></h1></section>
    <article <?php post_class('content-card'); ?>><div class="article-body"><?php the_content(); wp_link_pages(); ?></div></article>
    <?php if (comments_open() || get_comments_number()) comments_template(); ?>
</main>
<?php endwhile; get_footer(); ?>
