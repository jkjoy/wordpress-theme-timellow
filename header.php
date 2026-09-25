<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="color-scheme" content="light dark">
    <?php if (get_theme_mod('favicon_url')): ?><link rel="icon" href="<?php echo esc_url(get_theme_mod('favicon_url')); ?>"><?php endif; ?>
    <script>(function(){var theme='light';try{var saved=localStorage.getItem('timellow-theme');theme=saved==='light'||saved==='dark'?saved:(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')}catch(e){}document.documentElement.setAttribute('data-theme',theme)})();</script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-shell"><div class="container">
    <header class="site-header">
        <div class="header-inner">
            <div class="brand"><a class="brand-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>"><span class="brand-copy"><span class="brand-title"><?php bloginfo('name'); ?></span><span class="brand-description"><?php echo esc_html(timellow_subtitle()); ?></span></span></a></div>
            <div class="header-actions">
                <nav class="site-nav" aria-label="主导航"><?php timellow_navigation(); ?></nav>
                <div class="header-tools">
                    <button type="button" class="search-toggle" data-search-toggle aria-expanded="false" aria-controls="site-search-panel" title="搜索"><span class="screen-reader-text">打开搜索</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/></svg></button>
                    <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" title="切换深色模式"><span class="screen-reader-text" data-theme-label>切换深色模式</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle class="theme-icon-light" cx="12" cy="12" r="8.5"/><path class="theme-icon-dark" d="M12 3.5a8.5 8.5 0 0 0 0 17z"/><circle class="theme-icon-ring" cx="12" cy="12" r="8.5"/></svg></button>
                </div>
            </div>
        </div>
        <div class="search-panel" id="site-search-panel" data-search-panel hidden>
            <form class="search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>" role="search"><label for="timellow-search" class="screen-reader-text">搜索关键字</label><input id="timellow-search" type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="搜索文章、页面或关键词"><button type="submit">搜索</button></form>
        </div>
    </header>
    <div class="site-layout<?php echo timellow_sidebar_enabled() ? ' has-sidebar' : ''; ?>">
        <?php if (timellow_sidebar_enabled()) get_sidebar(); ?>
