<?php /* Template Name: 说说 */ get_header(); while (have_posts()): the_post(); ?>
<main class="site-main"><section class="page-hero"><h1 class="page-title"><?php the_title(); ?></h1></section>
    <?php if (trim(get_the_content())): ?><article class="content-card"><div class="article-body"><?php if (function_exists('timellow_cached_content')) timellow_cached_content(); else the_content(); ?></div></article><?php endif; ?>
    <?php $moments = get_posts(['post_type' => 'timellow_moment', 'numberposts' => -1]); $reaction_states = $moments && function_exists('timellow_plugin_reaction_states') ? timellow_plugin_reaction_states(wp_list_pluck($moments, 'ID')) : []; if ($moments): ?><section class="moments-list" aria-label="说说列表"><?php foreach ($moments as $moment): $GLOBALS['post'] = $moment; setup_postdata($moment); ?><article class="moment-item" id="moment-<?php echo (int) $moment->ID; ?>"><div class="moment-main">
        <div class="moment-content"><?php the_content(); ?></div>
        <?php if (has_post_thumbnail($moment)): ?><div class="moment-media-grid is-single"><a class="moment-media-item" href="<?php echo esc_url(get_the_post_thumbnail_url($moment, 'full')); ?>" target="_blank" rel="noopener"><?php echo get_the_post_thumbnail($moment, 'large', ['loading' => 'lazy']); ?></a></div><?php endif; ?>
        <?php timellow_moment_reactions($moment->ID, $reaction_states[$moment->ID] ?? []); ?>
        <footer class="moment-meta"><span class="moment-meta-left"><time class="moment-published" datetime="<?php echo esc_attr(get_the_date(DATE_W3C, $moment)); ?>"><?php echo esc_html(get_the_date('Y-m-d H:i', $moment)); ?></time><?php if ($location = get_post_meta($moment->ID, 'location', true)): ?><span class="moment-location"><span class="moment-location-text"><?php echo esc_html($location); ?></span></span><?php endif; ?></span><?php if ($source = get_post_meta($moment->ID, 'source', true)): ?><span class="moment-source"><?php echo esc_html($source); ?></span><?php endif; ?></footer>
    </div></article><?php endforeach; wp_reset_postdata(); ?></section>
    <?php else: ?><section class="empty-state"><p>暂时还没有说说。</p></section><?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
