<?php /* Template Name: 全部分类 */ get_header(); while (have_posts()): the_post(); ?>
<main class="site-main"><section class="page-hero"><h1 class="page-title"><?php the_title(); ?></h1></section>
    <?php if (trim(get_the_content())): ?><article class="content-card"><div class="article-body"><?php the_content(); ?></div></article><?php endif; ?>
    <?php $categories = get_categories(['hide_empty' => true]); if ($categories): ?><div class="taxonomy-grid"><?php foreach ($categories as $category): ?><article class="taxonomy-card"><h2 class="taxonomy-card-title"><a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a></h2><p><?php echo esc_html($category->description ?: '这个分类下收纳了相关主题文章。'); ?></p><div class="taxonomy-card-meta"><?php echo (int) $category->count; ?> 篇文章</div></article><?php endforeach; ?></div>
    <?php else: ?><section class="empty-state"><p>暂时还没有分类。</p></section><?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
