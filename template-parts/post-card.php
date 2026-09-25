<?php
$category = get_the_category();
$cover = timellow_cover();
?>
<article <?php post_class('post-card'); ?> data-post-cid="<?php the_ID(); ?>" itemscope itemtype="https://schema.org/BlogPosting">
    <a class="post-thumb-link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if ($cover): ?><img class="post-thumb" src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" decoding="async"><?php else: ?><span class="post-thumb-placeholder"><?php echo esc_html(timellow_first_character(get_the_title())); ?></span><?php endif; ?>
    </a>
    <div class="post-body">
        <h2 class="post-title" itemprop="headline"><?php if (is_sticky()): ?><span class="post-sticky-badge">置顶</span><?php endif; ?><a href="<?php the_permalink(); ?>" itemprop="url"><?php the_title(); ?></a></h2>
        <div class="post-meta"><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>" itemprop="datePublished"><?php echo esc_html(get_the_date('Y-m-d')); ?></time><?php if ($category): ?><span class="meta-separator"></span><a href="<?php echo esc_url(get_category_link($category[0])); ?>"><?php echo esc_html($category[0]->name); ?></a><?php endif; ?></div>
        <p class="post-excerpt" itemprop="description"><?php echo esc_html(timellow_excerpt()); ?></p>
    </div>
</article>
