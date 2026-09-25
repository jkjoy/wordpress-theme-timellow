<?php
// Run with: php tools/seed-demo.php
define('WP_INSTALLING', true);
require dirname(__DIR__) . '/.local/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
add_filter('pre_wp_mail', static fn() => true);

if (!is_blog_installed()) {
    $password = wp_generate_password(22, true, false);
    wp_install('Timellow 演示', 'timellow', 'demo@example.test', false, '', $password);
    file_put_contents(dirname(__DIR__) . '/.local/demo-login.txt', "用户名: timellow\n密码: {$password}\n后台: http://127.0.0.1:8098/wp-admin/\n");
}

update_option('blogdescription', '记录生活、设计与技术');
update_option('timezone_string', 'Asia/Shanghai');
update_option('posts_per_page', 5);
update_option('permalink_structure', '/%postname%/');
update_option('default_comment_status', 'open');
switch_theme('timellow');
if (!is_plugin_active('sqlite-database-integration/load.php')) activate_plugin('sqlite-database-integration/load.php', '', false, true);
if (!function_exists('timellow_moments_type')) require_once dirname(__DIR__) . '/functions.php';
timellow_moments_type();
set_theme_mod('subtitle', '在时间里，慢慢记录');
set_theme_mod('layout_mode', 'two_column');
set_theme_mod('paging_mode', 'loadmore');
set_theme_mod('footer_note', '一个关于日常与灵感的角落');

function demo_post($slug, $title, $content, $category, $tags, $days_ago, $sticky = false) {
    $existing = get_page_by_path($slug, OBJECT, 'post');
    if ($existing) return $existing->ID;
    $date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
    $id = wp_insert_post([
        'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug,
        'post_title' => $title, 'post_content' => $content, 'post_date' => $date,
        'comment_status' => 'open',
    ], true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    wp_set_post_terms($id, [$category], 'category');
    wp_set_post_terms($id, $tags, 'post_tag');
    if ($sticky) stick_post($id);
    return $id;
}

foreach (['生活随笔' => '关于日常、阅读和沿途风景。', '设计观察' => '界面、排版与视觉秩序的思考。', '技术笔记' => '开发过程中的实践记录。'] as $name => $description) {
    if (!term_exists($name, 'category')) wp_insert_term($name, 'category', ['description' => $description]);
}

$posts = [
    ['hello-timellow', '你好，Timellow', '<p>欢迎来到这个小小的记录空间。这里会放设计、技术、读书和生活里的片段。愿每一篇文字都能留住一点值得回看的时间。</p><h2>从这里开始</h2><p>你可以通过顶部导航浏览归档、分类和标签，也可以在侧边栏发现最近更新。</p><blockquote><p>让想法沉淀，让日子发光。</p></blockquote>', '生活随笔', ['开始', '写作'], 0, true],
    ['slow-design', '慢一点，设计会更清楚', '<p>好的界面并不急于表达全部。留白、文字和恰当的层次，往往比复杂的装饰更有力量。</p><h2>从内容出发</h2><p>先确定读者真正要完成的事，再决定按钮、卡片和动效应该出现在哪里。</p>', '设计观察', ['设计', '思考'], 2],
    ['wordpress-notes', '把博客迁移到 WordPress 的一些笔记', '<p>迁移时最重要的是保持内容结构清晰。文章、独立页面、分类和标签各自承担不同的职责。</p><pre><code class="language-php">add_theme_support(\'post-thumbnails\');\nregister_nav_menus([\'primary\' => \'主导航\']);</code></pre><p>这个主题保留了 Timellow 的安静气质，同时使用 WordPress 原生接口管理内容。</p>', '技术笔记', ['WordPress', '主题'], 4],
    ['weekend-reading', '周末阅读：在纸页之间放慢脚步', '<p>把手机放在一边，翻几页书，记下让自己停顿的句子。阅读也是一种整理心绪的方式。</p><ul><li>读完一本短篇小说</li><li>记录三段喜欢的文字</li><li>去附近的咖啡馆走走</li></ul>', '生活随笔', ['阅读', '生活'], 7],
    ['color-and-rhythm', '颜色与节奏：一份小型界面练习', '<p>温和的绿色、冷静的文字颜色和少量青色强调，形成能长时间阅读的视觉节奏。</p><p>当页面足够安静，内容本身就会成为主角。</p>', '设计观察', ['设计', '配色'], 10],
    ['php-tips', '常用的 PHP 模板开发细节', '<p>输出前转义、查询后重置上下文，以及在模板中明确数据来源，都是长期维护的基础。</p><pre><code class="language-php">echo esc_html( get_the_title() );</code></pre>', '技术笔记', ['PHP', 'WordPress'], 14],
    ['morning-walk', '清晨散步，沿着熟悉的街道', '<p>天刚亮的时候，城市还没有完全醒来。换一条路走，熟悉的街道也会出现新鲜的细节。</p>', '生活随笔', ['生活', '随笔'], 19],
    ['typography-notes', '关于中文排版的几个细节', '<p>适宜的行高、舒展的段距和稳定的标题层级，让长文章更容易阅读。字体是其中的一部分，节奏同样重要。</p>', '设计观察', ['排版', '设计'], 25],
];
foreach ($posts as $post) demo_post(...$post);

$pages = [
    ['about', '关于', '<p>这里是 Timellow 的 WordPress 演示站。主题关注阅读体验，也保留了博客应有的轻松感。</p><p>你可以在后台替换这段文字，写下自己的介绍。</p>'],
    ['archives', '归档', '<p>按时间整理的文章目录。</p>'],
    ['categories', '分类', '<p>从感兴趣的主题开始阅读。</p>'],
    ['tags', '标签', '<p>每个标签都是一条通往相关内容的小路。</p>'],
    ['links', '友链', '<p>一些值得常去的站点。</p>\n<!-- timellow-links-start -->\n# 博客与创作\nWordPress | https://wordpress.org/ | 开源内容管理系统 |\nTypecho | https://typecho.org/ | 轻量博客程序 |\n# 设计与灵感\nA List Apart | https://alistapart.com/ | 关于网页设计和开发的文章 |\n<!-- timellow-links-end -->'],
    ['moments', '说说', '<p>短一些的近况和瞬间。</p>'],
];
foreach ($pages as [$slug, $title, $content]) {
    $content = str_replace('\\n', "\n", $content);
    $existing = get_page_by_path($slug);
    if ($existing) {
        if ($slug === 'links') wp_update_post(['ID' => $existing->ID, 'post_content' => $content]);
        continue;
    }
    wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'comment_status' => $slug === 'links' ? 'open' : 'closed']);
}

$moment_data = [
    ['first-moment', '第一条说说', '<p>今天把博客重新整理了一遍，首页终于有了想要的节奏。</p>', 1, '书桌前'],
    ['coffee-note', '午后片刻', '<p>一杯咖啡，一段没有安排的下午。读到好句子时停下来，刚刚好。</p>', 3, '街角咖啡馆'],
    ['evening-light', '傍晚的光', '<p>傍晚的光落在窗边，提醒我记下今天的好心情。</p>', 6, '家里'],
];
foreach ($moment_data as [$slug, $title, $content, $days, $location]) {
    if (get_page_by_path($slug, OBJECT, 'timellow_moment')) continue;
    $id = wp_insert_post(['post_type' => 'timellow_moment', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'post_date' => date('Y-m-d H:i:s', strtotime("-{$days} days"))]);
    update_post_meta($id, 'location', $location);
}

$sample = get_page_by_path('hello-timellow', OBJECT, 'post');
if ($sample && !get_comments(['post_id' => $sample->ID, 'count' => true])) {
    wp_insert_comment(['comment_post_ID' => $sample->ID, 'comment_author' => '访客', 'comment_author_email' => 'visitor@example.test', 'comment_content' => '喜欢这个安静的布局，期待后续更新。', 'comment_approved' => 1]);
}
$hello = get_page_by_path('hello-world', OBJECT, 'post');
if ($hello) wp_delete_post($hello->ID, true);
$sample_page = get_page_by_path('sample-page');
if ($sample_page) wp_delete_post($sample_page->ID, true);
flush_rewrite_rules();
echo "演示站已初始化。文章: " . wp_count_posts('post')->publish . "，页面: " . wp_count_posts('page')->publish . "，说说: " . wp_count_posts('timellow_moment')->publish . "\n";
