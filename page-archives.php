<?php /* Template Name: 文章归档 */ get_header(); while (have_posts()): the_post(); ?>
<main class="site-main"><section class="page-hero"><h1 class="page-title"><?php the_title(); ?></h1></section>
    <?php if (trim(get_the_content())): ?><article class="content-card"><div class="article-body"><?php the_content(); ?></div></article><?php endif; ?>
    <?php $posts = get_posts(['numberposts' => -1, 'post_type' => 'post']); $groups = []; foreach ($posts as $post) $groups[get_the_date('Y', $post)][] = $post; ?>
    <?php if ($groups): ?><div class="archive-list"><?php foreach ($groups as $year => $items): ?><section class="archive-year-card"><div class="archive-year-header"><h2 class="archive-year-title"><?php echo esc_html($year); ?></h2><span class="archive-year-count"><?php echo count($items); ?> 篇文章</span></div><div class="archive-items"><?php foreach ($items as $item): ?><article class="archive-item"><time><?php echo esc_html(get_the_date('m-d', $item)); ?></time><a href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></article><?php endforeach; ?></div></section><?php endforeach; ?></div>
    <?php else: ?><section class="empty-state"><p>还没有可归档的文章。</p></section><?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
