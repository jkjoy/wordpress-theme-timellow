<?php /* Template Name: 全部标签 */ get_header(); while (have_posts()): the_post(); ?>
<main class="site-main"><section class="page-hero"><h1 class="page-title"><?php the_title(); ?></h1></section>
    <?php if (trim(get_the_content())): ?><article class="content-card"><div class="article-body"><?php if (function_exists('timellow_cached_content')) timellow_cached_content(); else the_content(); ?></div></article><?php endif; ?>
    <?php $tags = get_tags(['hide_empty' => true]); if ($tags): $max = max(array_map(static fn($tag) => $tag->count, $tags)); ?><article class="content-card"><div class="tag-cloud"><?php foreach ($tags as $tag): ?><a class="tag-chip" href="<?php echo esc_url(get_tag_link($tag)); ?>" style="--tag-scale:<?php echo esc_attr(round($tag->count / $max, 2)); ?>"><span>#</span><span><?php echo esc_html($tag->name); ?></span><span><?php echo (int) $tag->count; ?></span></a><?php endforeach; ?></div></article>
    <?php else: ?><section class="empty-state"><p>暂时还没有标签。</p></section><?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
