<?php /* Template Name: 文章归档 */ get_header(); while (have_posts()): the_post(); ?>
<main class="site-main"><section class="page-hero"><h1 class="page-title"><?php the_title(); ?></h1></section>
    <?php if (post_password_required()): ?>
        <article class="content-card"><div class="article-body"><?php the_content(); ?></div></article>
    <?php else: ?>
    <?php if (trim(get_the_content())): ?><article class="content-card"><div class="article-body"><?php if (function_exists('timellow_cached_content')) timellow_cached_content(); else the_content(); ?></div></article><?php endif; ?>
    <?php
    $years = timellow_archive_years();
    $requested_year = absint($_GET['timellow_year'] ?? 0);
    $year = in_array($requested_year, $years, true) ? $requested_year : ($years[0] ?? 0);
    $archive_query = $year ? new WP_Query([
        'post_type' => 'post', 'post_status' => 'publish', 'year' => $year,
        'posts_per_page' => 50, 'paged' => timellow_list_page(),
        'orderby' => ['date' => 'DESC', 'ID' => 'DESC'], 'ignore_sticky_posts' => true,
        'update_post_meta_cache' => false, 'update_post_term_cache' => false,
    ]) : null;
    ?>
    <?php if ($years): ?><nav class="archive-years" aria-label="归档年份"><?php foreach ($years as $archive_year): ?><a class="tag-chip<?php echo $archive_year === $year ? ' is-current' : ''; ?>" href="<?php echo esc_url(add_query_arg('timellow_year', $archive_year, get_permalink())); ?>"<?php if ($archive_year === $year) echo ' aria-current="page"'; ?>><?php echo (int) $archive_year; ?></a><?php endforeach; ?></nav><?php endif; ?>
    <?php if ($archive_query && $archive_query->have_posts()): ?><div class="archive-list"><section class="archive-year-card"><div class="archive-year-header"><h2 class="archive-year-title"><?php echo (int) $year; ?></h2><span class="archive-year-count"><?php echo (int) $archive_query->found_posts; ?> 篇文章</span></div><div class="archive-items"><?php foreach ($archive_query->posts as $item): ?><article class="archive-item"><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C, $item)); ?>"><?php echo esc_html(get_the_date('m-d', $item)); ?></time><a href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></article><?php endforeach; ?></div></section></div>
    <?php timellow_list_pagination($archive_query, add_query_arg('timellow_year', $year, get_permalink())); ?>
    <?php else: ?><section class="empty-state"><p>还没有可归档的文章。</p></section><?php endif; ?>
    <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
