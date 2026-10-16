<?php /* Template Name: 友情链接 */ get_header(); while (have_posts()): the_post(); ?>
<main class="site-main"><section class="page-hero"><h1 class="page-title"><?php the_title(); ?></h1></section>
    <?php if (post_password_required()): ?>
        <article class="content-card"><div class="article-body"><?php the_content(); ?></div></article>
    <?php else:
        $raw = get_post_field('post_content', get_the_ID());
        $groups = timellow_bookmark_groups();
        $intro = preg_replace('/<!--\s*timellow-links-start\s*-->.*?<!--\s*timellow-links-end\s*-->/is', '', $raw);
    ?>
    <?php if (trim(wp_strip_all_tags($intro))): ?><article class="content-card"><div class="article-body"><?php if (function_exists('timellow_cached_content')) timellow_cached_content($intro); else echo apply_filters('the_content', $intro); ?></div></article><?php endif; ?>
    <?php if ($groups): foreach ($groups as $group => $items): ?><section class="links-group"><h2 class="links-group-title"><span><?php echo esc_html($group); ?></span><span class="links-group-count"><?php echo count($items); ?> 个站点</span></h2><div class="links-grid">
        <?php foreach ($items as $item): ?><article class="link-card"><div class="link-avatar"><?php if ($item['image']): ?><img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name']); ?>" loading="lazy"><?php else: ?><span class="link-avatar-fallback"><?php echo esc_html(timellow_first_character($item['name'])); ?></span><?php endif; ?></div><div class="link-main"><div class="link-heading"><a class="link-name" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html($item['name']); ?></a><span class="link-host"><?php echo esc_html($item['host']); ?></span></div><?php if ($item['description']): ?><p class="link-desc"><?php echo esc_html($item['description']); ?></p><?php endif; ?></div></article><?php endforeach; ?>
    </div></section><?php endforeach; else: ?><section class="empty-state"><p>暂时还没有友情链接。</p></section><?php endif; ?>
    <?php if (comments_open() || get_comments_number()) comments_template(); ?>
    <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
