<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus(['primary' => '主导航']);
}
add_action('after_setup_theme', 'timellow_setup');
add_filter('pre_option_link_manager_enabled', '__return_true');

function timellow_assets() {
    wp_enqueue_style('timellow', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
    wp_enqueue_script('timellow', get_template_directory_uri() . '/assets/theme.js', [], wp_get_theme()->get('Version'), true);
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
    $mode = get_theme_mod('font_mode', 'default');
    if ($mode === 'lxgw') {
        wp_add_inline_style('timellow', '@font-face{font-family:TimellowLXGW;src:url("' . esc_url(get_template_directory_uri() . '/assets/fonts/lxgw.woff2') . '") format("woff2");font-display:optional}body{--site-font-family:TimellowLXGW,serif}');
    } elseif ($mode === 'custom' && get_theme_mod('font_url')) {
        $name = sanitize_text_field(get_theme_mod('font_name', 'TimellowCustom'));
        $url = esc_url_raw(get_theme_mod('font_url'));
        if (preg_match('/\.(woff2?|ttf|otf)(\?.*)?$/i', $url)) {
            wp_add_inline_style('timellow', '@font-face{font-family:TimellowCustom;src:url("' . esc_url($url) . '");font-display:optional}body{--site-font-family:TimellowCustom,"' . esc_attr($name) . '",sans-serif}');
        }
    }
}
add_action('wp_enqueue_scripts', 'timellow_assets');

function timellow_preload_font($resources) {
    if (get_theme_mod('font_mode', 'default') === 'lxgw') {
        $resources[] = [
            'href' => get_template_directory_uri() . '/assets/fonts/lxgw.woff2',
            'as' => 'font',
            'type' => 'font/woff2',
            'crossorigin' => 'anonymous',
        ];
    }
    return $resources;
}
add_filter('wp_preload_resources', 'timellow_preload_font');

function timellow_customize($wp_customize) {
    $wp_customize->add_section('timellow_options', ['title' => 'Timellow 设置', 'priority' => 35]);
    $fields = [
        'subtitle' => ['站点副标题', 'text', 'Stay hungry. Stay foolish.'],
        'layout_mode' => ['页面布局', 'select', 'single', ['single' => '单栏', 'two_column' => '双栏']],
        'sidebar_profile_avatar' => ['自定义侧边栏头像 URL（留空使用 Gravatar）', 'url', ''],
        'sidebar_profile_bio' => ['侧边栏简介', 'textarea', ''],
        'moments_page_slug' => ['说说页面 Slug', 'text', 'moments'],
        'favicon_url' => ['Favicon URL', 'url', ''],
        'icp_record' => ['备案号', 'text', ''],
        'footer_note' => ['页脚补充文案', 'text', ''],
        'default_cover' => ['默认封面 URL', 'url', ''],
        'paging_mode' => ['列表翻页方式', 'select', 'pagination', ['pagination' => '页码', 'loadmore' => '加载更多']],
        'font_mode' => ['全站字体', 'select', 'default', ['default' => '默认', 'lxgw' => '霞鹜文楷', 'custom' => '自定义']],
        'font_name' => ['自定义字体名称', 'text', ''],
        'font_url' => ['自定义字体文件 URL', 'url', ''],
        'sns_mastodon' => ['Mastodon URL', 'url', ''],
        'sns_telegram' => ['Telegram URL', 'url', ''],
        'sns_x' => ['X URL', 'url', ''],
        'sns_instagram' => ['Instagram URL', 'url', ''],
        'sns_email' => ['邮箱地址', 'email', ''],
        'sns_sitemap' => ['网站地图 URL', 'url', ''],
        'analytics_code' => ['统计代码', 'textarea', ''],
    ];
    foreach ($fields as $id => $field) {
        [$label, $type, $default] = $field;
        $sanitize = in_array($type, ['url', 'email'], true) ? ($type === 'url' ? 'esc_url_raw' : 'sanitize_email') : 'sanitize_text_field';
        if ($type === 'textarea') {
            $sanitize = 'sanitize_textarea_field';
            if ($id === 'analytics_code') {
                $sanitize = static fn($value) => current_user_can('unfiltered_html') ? $value : wp_kses_post($value);
            }
        } elseif ($type === 'select') {
            $sanitize = static function ($value) use ($field, $default) { return isset($field[3][$value]) ? $value : $default; };
        }
        $wp_customize->add_setting($id, ['default' => $default, 'sanitize_callback' => $sanitize]);
        $args = ['label' => $label, 'section' => 'timellow_options', 'settings' => $id, 'type' => $type];
        if ($type === 'select') {
            $args['choices'] = $field[3];
        }
        $wp_customize->add_control($id, $args);
    }
    foreach (['profile' => '博主信息', 'latest_posts' => '最新文章', 'popular_posts' => '热门文章', 'latest_comments' => '最新评论', 'latest_moments' => '最新说说'] as $key => $label) {
        $id = 'sidebar_' . $key;
        $wp_customize->add_setting($id, ['default' => true, 'sanitize_callback' => 'wp_validate_boolean']);
        $wp_customize->add_control($id, ['label' => '侧栏：' . $label, 'section' => 'timellow_options', 'settings' => $id, 'type' => 'checkbox']);
    }
}
add_action('customize_register', 'timellow_customize');

function timellow_analytics() {
    $code = get_theme_mod('analytics_code');
    if ($code) echo "\n" . $code . "\n";
}
add_action('wp_head', 'timellow_analytics', 99);

function timellow_subtitle() {
    return trim((string) get_theme_mod('subtitle', 'Stay hungry. Stay foolish.')) ?: get_bloginfo('description');
}

function timellow_sidebar_enabled() {
    return get_theme_mod('layout_mode', 'single') === 'two_column';
}

function timellow_body_classes($classes) {
    $classes[] = timellow_sidebar_enabled() ? 'timellow-has-sidebar' : 'timellow-no-sidebar';
    return $classes;
}
add_filter('body_class', 'timellow_body_classes');
add_filter('use_widgets_block_editor', '__return_false');

function timellow_register_widgets() {
    register_sidebar([
        'name' => 'Timellow 侧边栏补充', 'id' => 'timellow-sidebar',
        'description' => '显示在主题内置侧边栏模块之后。',
        'before_widget' => '<section id="%1$s" class="sidebar-widget %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h2 class="sidebar-widget-title">',
        'after_title' => '</h2>',
    ]);
}
add_action('widgets_init', 'timellow_register_widgets');

function timellow_moments_type() {
    register_post_type('timellow_moment', [
        'labels' => ['name' => '说说', 'singular_name' => '说说', 'add_new_item' => '添加说说', 'edit_item' => '编辑说说'],
        'public' => true, 'show_in_rest' => true, 'has_archive' => false,
        'menu_icon' => 'dashicons-format-status',
        'supports' => ['title', 'editor', 'thumbnail', 'author', 'revisions', 'custom-fields'],
        'rewrite' => ['slug' => 'moment'],
    ]);
}
add_action('init', 'timellow_moments_type');

function timellow_moments_page() {
    $slug = sanitize_title(get_theme_mod('moments_page_slug', 'moments'));
    if ($slug && ($page = get_page_by_path($slug))) return $page;
    foreach (get_pages(['meta_key' => '_wp_page_template', 'meta_value' => 'page-moments.php']) as $page) return $page;
    return get_page_by_path('moments') ?: get_page_by_path('shuoshuo');
}

function timellow_meta_boxes() {
    add_meta_box('timellow-cover', 'Timellow 封面', 'timellow_cover_meta_box', 'post', 'side');
    add_meta_box('timellow-moment-meta', '说说信息', 'timellow_moment_meta_box', 'timellow_moment', 'normal');
}
add_action('add_meta_boxes', 'timellow_meta_boxes');

function timellow_cover_meta_box($post) {
    wp_nonce_field('timellow_save_meta', 'timellow_meta_nonce');
    echo '<p><label for="timellow-cover-url">封面图片 URL</label></p><input id="timellow-cover-url" type="url" name="timellow_cover_url" value="' . esc_attr(get_post_meta($post->ID, 'cover', true)) . '" style="width:100%"><p>特色图片优先于此地址。</p>';
}

function timellow_moment_meta_box($post) {
    wp_nonce_field('timellow_save_meta', 'timellow_meta_nonce');
    foreach (['location' => '地点', 'source' => '来源'] as $key => $label) {
        echo '<p><label for="timellow-' . esc_attr($key) . '">' . esc_html($label) . '</label><br><input id="timellow-' . esc_attr($key) . '" type="text" name="timellow_' . esc_attr($key) . '" value="' . esc_attr(get_post_meta($post->ID, $key, true)) . '" style="width:100%"></p>';
    }
}

function timellow_save_meta($post_id) {
    if (!isset($_POST['timellow_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['timellow_meta_nonce'])), 'timellow_save_meta') || !current_user_can('edit_post', $post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    foreach (['timellow_cover_url' => ['cover', 'esc_url_raw'], 'timellow_location' => ['location', 'sanitize_text_field'], 'timellow_source' => ['source', 'sanitize_text_field']] as $field => [$meta, $sanitize]) {
        if (isset($_POST[$field])) update_post_meta($post_id, $meta, $sanitize(wp_unslash($_POST[$field])));
    }
}
add_action('save_post', 'timellow_save_meta');

function timellow_comment_form_fields($fields) {
    $comment = $fields['comment'] ?? null;
    $cookies = $fields['cookies'] ?? null;
    unset($fields['comment'], $fields['cookies']);
    if ($comment !== null) $fields['comment'] = $comment;
    if ($cookies !== null) $fields['cookies'] = $cookies;
    return $fields;
}
add_filter('comment_form_fields', 'timellow_comment_form_fields');

function timellow_cover($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, 'large');
    }
    $cover = get_post_meta($post_id, 'cover', true);
    if ($cover) {
        return esc_url_raw($cover);
    }
    $content = get_post_field('post_content', $post_id);
    if (preg_match('/<img[^>]+src=["\']([^"\']+)/i', $content, $matches)) {
        return esc_url_raw($matches[1]);
    }
    $default = get_theme_mod('default_cover');
    if ($default) {
        return esc_url_raw($default);
    }
    return get_template_directory_uri() . '/assets/cover/' . (($post_id % 4) + 1) . '.jpg';
}

function timellow_first_character($text) {
    return mb_substr(trim(wp_strip_all_tags($text)), 0, 1);
}

function timellow_excerpt($length = 96) {
    $text = get_the_excerpt();
    return wp_trim_words(wp_strip_all_tags(strip_shortcodes($text)), $length / 3, '…');
}

function timellow_archive_heading() {
    if (is_search()) {
        return ['搜索结果', '关键词：' . get_search_query()];
    }
    if (is_category()) {
        return [single_cat_title('', false), category_description()];
    }
    if (is_tag()) {
        return [single_tag_title('', false), tag_description()];
    }
    if (is_author()) {
        return [get_the_author_meta('display_name', get_queried_object_id()), '作者的文章'];
    }
    return [get_the_archive_title(), get_the_archive_description()];
}

function timellow_pagination() {
    global $wp_query;
    if ($wp_query->max_num_pages < 2) {
        return;
    }
    if (get_theme_mod('paging_mode', 'pagination') === 'loadmore') {
        $next = get_next_posts_page_link();
        if ($next) {
            echo '<nav class="load-more" data-load-more><a class="load-more-btn" data-load-more-trigger data-next-url="' . esc_url($next) . '" href="' . esc_url($next) . '" data-default-text="加载更多" data-loading-text="正在加载…" data-error-text="重试加载"><span data-load-more-label>加载更多</span><span aria-hidden="true">↓</span></a></nav>';
        }
        return;
    }
    $current = max(1, get_query_var('paged'));
    echo '<nav class="pagination" aria-label="文章分页">';
    if ($current > 1) {
        echo '<a class="page-link" href="' . esc_url(get_previous_posts_page_link()) . '">上一页</a>';
    }
    echo '<span class="page-status">' . esc_html($current . ' / ' . $wp_query->max_num_pages) . '</span>';
    if ($current < $wp_query->max_num_pages) {
        echo '<a class="page-link" href="' . esc_url(get_next_posts_page_link()) . '">下一页</a>';
    }
    echo '</nav>';
}

function timellow_comment($comment, $args, $depth) {
    ?>
    <li id="li-comment-<?php comment_ID(); ?>" <?php comment_class('comment-item'); ?>>
        <article id="comment-<?php comment_ID(); ?>" class="comment-card"><div class="comment-row">
            <div class="comment-avatar"><?php echo get_avatar($comment, 48); ?></div>
            <div class="comment-main"><div class="comment-header"><div class="comment-meta-block">
                <span class="comment-author"><?php comment_author_link(); ?></span>
                <time class="comment-date" datetime="<?php echo esc_attr(get_comment_date(DATE_W3C)); ?>"><?php echo esc_html(get_comment_date('Y-m-d H:i')); ?></time>
            </div><div class="comment-actions"><?php comment_reply_link(array_merge($args, ['depth' => $depth, 'max_depth' => $args['max_depth'], 'reply_text' => '回复'])); ?></div></div>
            <div class="comment-content"><?php if ($comment->comment_approved === '0') echo '<p>评论正在等待审核。</p>'; comment_text(); ?></div>
            </div></div></article>
    <?php
}

function timellow_bookmark_groups() {
    $groups = [];
    $categories = get_terms(['taxonomy' => 'link_category', 'hide_empty' => true, 'orderby' => 'name']);
    if (is_wp_error($categories)) return $groups;
    foreach ($categories as $category) {
        $bookmarks = get_bookmarks(['category' => $category->term_id, 'orderby' => 'name', 'hide_invisible' => 1]);
        if (!$bookmarks) continue;
        $groups[$category->name] = array_map(static function ($bookmark) {
            return [
                'name' => $bookmark->link_name,
                'url' => $bookmark->link_url,
                'description' => trim((string) $bookmark->link_description),
                'image' => trim((string) $bookmark->link_image),
                'host' => wp_parse_url($bookmark->link_url, PHP_URL_HOST) ?: '',
            ];
        }, $bookmarks);
    }
    return $groups;
}

function timellow_parse_legacy_links($text) {
    $groups = [];
    $source = $text;
    if (preg_match('/<!--\s*timellow-links-start\s*-->(.*?)<!--\s*timellow-links-end\s*-->/is', $text, $match)) {
        $source = $match[1];
    }
    $group = '友情链接';
    foreach (preg_split('/\R/u', $source) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (str_starts_with($line, '#')) {
            $group = trim(ltrim($line, '# ')) ?: '友情链接';
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 2 || !filter_var($parts[1], FILTER_VALIDATE_URL)) continue;
        $groups[$group][] = [
            'name' => $parts[0], 'url' => $parts[1],
            'description' => $parts[2] ?? '', 'image' => $parts[3] ?? '',
            'host' => wp_parse_url($parts[1], PHP_URL_HOST),
        ];
    }
    return $groups;
}

function timellow_import_legacy_links($page) {
    if (!preg_match('/<!--\s*timellow-links-start\s*-->.*?<!--\s*timellow-links-end\s*-->/is', $page->post_content, $match)) return;
    $groups = timellow_parse_legacy_links($match[0]);
    if (!$groups) return;
    require_once ABSPATH . 'wp-admin/includes/bookmark.php';
    foreach ($groups as $group => $items) {
        $term = term_exists($group, 'link_category');
        if (!$term) $term = wp_insert_term($group, 'link_category');
        if (is_wp_error($term)) return;
        $category_id = (int) (is_array($term) ? $term['term_id'] : $term);
        $existing = array_column(get_bookmarks(['category' => $category_id, 'hide_invisible' => 0]), 'link_url');
        foreach ($items as $item) {
            if (in_array($item['url'], $existing, true)) continue;
            $result = wp_insert_link([
                'link_name' => $item['name'],
                'link_url' => $item['url'],
                'link_description' => $item['description'],
                'link_image' => $item['image'],
                'link_category' => [$category_id],
            ], true);
            if (is_wp_error($result) || !$result) return;
            $existing[] = $item['url'];
        }
    }
    update_post_meta($page->ID, '_timellow_legacy_links_backup', $match[0]);
    wp_update_post(['ID' => $page->ID, 'post_content' => trim(str_replace($match[0], '', $page->post_content))]);
}

function timellow_migrate_legacy_links() {
    if (!current_user_can('manage_links')) return;
    $pages = [];
    $page = get_page_by_path('links');
    if ($page) $pages[$page->ID] = $page;
    foreach (get_pages(['meta_key' => '_wp_page_template', 'meta_value' => 'page-links.php']) as $page) {
        $pages[$page->ID] = $page;
    }
    foreach ($pages as $page) timellow_import_legacy_links($page);
}
add_action('admin_init', 'timellow_migrate_legacy_links');

function timellow_social_links() {
    $links = [];
    foreach (['mastodon' => 'Mastodon', 'telegram' => 'Telegram', 'x' => 'X', 'instagram' => 'Instagram'] as $key => $label) {
        $url = get_theme_mod('sns_' . $key);
        if ($url) $links[] = [$key, $label, $url];
    }
    if ($email = get_theme_mod('sns_email')) $links[] = ['email', 'Email', 'mailto:' . $email];
    $links[] = ['rss', 'RSS', get_bloginfo('rss2_url')];
    $links[] = ['sitemap', '网站地图', get_theme_mod('sns_sitemap') ?: home_url('/wp-sitemap.xml')];
    return $links;
}

function timellow_social_icon($key) {
    $paths = [
        'mastodon' => '<path d="M4 18V9a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v5c0 5-7 6-12 4"/><path d="M8 13V9a2 2 0 0 1 4 0v4-4a2 2 0 0 1 4 0v4"/>',
        'telegram' => '<path d="m21 4-3 16-6-4-3 4v-6L3 12z"/><path d="m9 14 12-10"/>',
        'x' => '<path d="M4 3h4l12 18h-4zM20 3 4 21"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".7"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 6 9 7 9-7"/>',
        'rss' => '<circle cx="5" cy="19" r="1"/><path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/>',
        'sitemap' => '<rect x="9" y="3" width="6" height="5"/><rect x="3" y="16" width="6" height="5"/><rect x="15" y="16" width="6" height="5"/><path d="M12 8v4M6 16v-4h12v4"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$key] . '</svg>';
}

function timellow_render_social($class = 'sns-links') {
    echo '<nav class="' . esc_attr($class) . '" aria-label="社交链接">';
    foreach (timellow_social_links() as [$key, $label, $url]) {
        echo '<a class="' . ($class === 'sns-links' ? 'sns-link sns-link-' : 'sidebar-profile-link sidebar-profile-link-') . esc_attr($key) . '" href="' . esc_url($url) . '" aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '"' . ($key === 'email' ? '' : ' target="_blank" rel="noopener noreferrer"') . '>' . timellow_social_icon($key) . '</a>';
    }
    echo '</nav>';
}

function timellow_navigation() {
    echo '<a' . (is_home() || is_front_page() ? ' class="is-current"' : '') . ' href="' . esc_url(home_url('/')) . '">首页</a>';
    $locations = get_nav_menu_locations();
    $items = !empty($locations['primary']) ? wp_get_nav_menu_items($locations['primary']) : [];
    if ($items) {
        foreach ($items as $item) {
            $current = (is_page() && (int) $item->object_id === get_queried_object_id()) || (is_category() && $item->object === 'category' && (int) $item->object_id === get_queried_object_id());
            echo '<a' . ($current ? ' class="is-current"' : '') . ' href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
        }
    } else {
        foreach (get_pages(['parent' => 0, 'sort_column' => 'menu_order,post_title']) as $page) {
            echo '<a' . (is_page($page->ID) ? ' class="is-current"' : '') . ' href="' . esc_url(get_permalink($page)) . '">' . esc_html($page->post_title) . '</a>';
        }
    }
}
