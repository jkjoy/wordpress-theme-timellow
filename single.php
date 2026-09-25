<?php get_header(); while (have_posts()): the_post(); ?>
<main class="site-main">
    <article <?php post_class('content-card'); ?> itemscope itemtype="https://schema.org/BlogPosting">
        <header class="article-header"><h1 class="article-title" itemprop="headline"><?php the_title(); ?></h1>
            <div class="article-meta"><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>" itemprop="datePublished"><?php echo esc_html(get_the_date('Y-m-d')); ?></time>
                <?php foreach (get_the_category() as $category): ?><span class="meta-separator"></span><a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endforeach; ?>
                <span class="meta-separator"></span><a href="#comments"><?php comments_number('暂无评论', '1 条评论', '% 条评论'); ?></a>
            </div>
        </header>
        <div class="article-body" itemprop="articleBody"><?php if (function_exists('timellow_cached_content')) timellow_cached_content(); else the_content(); wp_link_pages(); ?></div>
        <?php $tags = get_the_tags(); if ($tags): ?><footer class="article-footer"><div class="tag-list"><?php foreach ($tags as $tag): ?><a class="tag-chip" href="<?php echo esc_url(get_tag_link($tag)); ?>"><span class="tag-hash">#</span><span><?php echo esc_html($tag->name); ?></span></a><?php endforeach; ?></div></footer><?php endif; ?>
    </article>
    <nav class="article-nav" aria-label="文章切换"><div class="article-nav-card"><span class="article-nav-label">上一篇</span><p class="article-nav-title"><?php if (get_previous_post()) previous_post_link('%link', '%title'); else echo '已经是第一篇了'; ?></p></div><div class="article-nav-card"><span class="article-nav-label">下一篇</span><p class="article-nav-title"><?php if (get_next_post()) next_post_link('%link', '%title'); else echo '已经是最后一篇了'; ?></p></div></nav>
    <?php if (comments_open() || get_comments_number()) comments_template(); ?>
</main>
<?php endwhile; get_footer(); ?>
