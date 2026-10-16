<?php
// Run against the local demo only: php tools/regression-check.php
if (PHP_SAPI !== 'cli') exit;
define('DISABLE_WP_CRON', true);
$_SERVER['SERVER_NAME'] = '127.0.0.1';
require dirname(__DIR__) . '/.local/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static fn() => new WP_Error('regression_offline', 'Offline regression check.'));

$fixtures = [];
$checks = 0;
$administrator = get_users(['role' => 'administrator', 'number' => 1])[0];
$test_settings = timellow_plugin_options();
$test_settings['cache_revision'] = wp_generate_uuid4();
$test_settings['cache_enabled'] = 0;
add_filter('pre_option_timellow_plugin_settings', static function () use (&$test_settings) { return $test_settings; });

function check_timellow($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
    echo "PASS {$message}\n";
}

function timellow_fixture($fields) {
    global $fixtures, $administrator;
    $id = wp_insert_post(wp_slash(array_merge([
        'post_title' => 'Timellow regression fixture', 'post_status' => 'publish',
        'post_author' => $administrator->ID, 'post_content' => 'PRIVATE_REGRESSION_MARKER',
        'post_date' => '2001-06-01 12:00:00', 'post_date_gmt' => '2001-06-01 04:00:00',
    ], $fields)), true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    $fixtures[] = $id;
    return $id;
}

function timellow_render_fixture($id, $template, $args = []) {
    $_GET = $args;
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(['page_id' => $id]);
    ob_start();
    include dirname(__DIR__) . '/' . $template;
    return ob_get_clean();
}

function timellow_nodes($html, $xpath) {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    return (new DOMXPath($dom))->query($xpath);
}

try {
    wp_set_current_user($administrator->ID);
    foreach (['post', 'page', 'timellow_moment'] as $type) {
        check_timellow(in_array('_timellow_markdown_mode', wp_post_revision_meta_keys($type), true), "$type format participates in revisions");
    }
    $protected = timellow_fixture(['post_type' => 'page', 'post_password' => 'regression-password', 'meta_input' => ['_timellow_markdown_mode' => 'markdown']]);
    wp_set_current_user(0);
    $GLOBALS['post'] = get_post($protected);
    setup_postdata($GLOBALS['post']);
    $html = apply_filters('the_content', get_the_content(null, false, $protected));
    check_timellow(str_contains($html, '<form') && !str_contains($html, 'PRIVATE_REGRESSION_MARKER'), 'Markdown password form remains usable');
    foreach ([0, 1] as $enabled) {
        $test_settings['cache_enabled'] = $enabled;
        ob_start(); timellow_cached_content('PRIVATE_REGRESSION_MARKER'); $html = ob_get_clean();
        check_timellow(str_contains($html, '<form') && !str_contains($html, 'PRIVATE_REGRESSION_MARKER'), "Custom content stays protected with cache=$enabled");
    }
    foreach (['page-links.php', 'page-moments.php', 'page-archives.php'] as $template) {
        $html = timellow_render_fixture($protected, $template);
        check_timellow(str_contains($html, '<form') && !str_contains($html, 'PRIVATE_REGRESSION_MARKER') && !preg_match('/class="(?:links-group|moments-list|archive-list)"/', $html), "$template hides content before password unlock");
    }
    require_once ABSPATH . WPINC . '/class-phpass.php';
    $test_settings['cache_enabled'] = 0;
    $_COOKIE['wp-postpass_' . COOKIEHASH] = (new PasswordHash(8, true))->HashPassword('regression-password');
    $html = timellow_render_fixture($protected, 'page-links.php');
    check_timellow(str_contains($html, 'PRIVATE_REGRESSION_MARKER') && !str_contains($html, 'post-password-form'), 'Correct password reveals the links introduction');
    unset($_COOKIE['wp-postpass_' . COOKIEHASH]);
    $test_settings['cache_enabled'] = 0;

    wp_set_current_user($administrator->ID);
    $article = timellow_fixture(['post_content' => '<p><strong>HTML original</strong></p>', 'meta_input' => ['_timellow_markdown_mode' => 'html']]);
    $html_revision = wp_save_post_revision($article);
    $_POST = ['timellow_md_nonce' => wp_create_nonce('timellow_md_save'), '_timellow_markdown_mode' => 'markdown'];
    wp_update_post(['ID' => $article, 'post_content' => '**Markdown converted**']);
    $revisions = wp_get_post_revisions($article);
    $markdown_revision = reset($revisions)->ID;
    check_timellow(get_post_meta($markdown_revision, '_timellow_markdown_mode', true) === 'markdown', 'Converted content and format share a revision');
    $_POST = [];
    wp_restore_post_revision($html_revision);
    check_timellow(timellow_md_mode($article) === 'html' && str_contains(get_post_field('post_content', $article), '<strong>'), 'Restoring HTML restores its renderer');
    wp_restore_post_revision($markdown_revision);
    check_timellow(timellow_md_mode($article) === 'markdown' && get_post_field('post_content', $article) === '**Markdown converted**', 'Restoring Markdown restores its renderer');
    $_POST = ['timellow_md_nonce' => wp_create_nonce('timellow_md_save'), '_timellow_markdown_mode' => 'html'];
    wp_update_post(['ID' => $article]);
    $revisions = wp_get_post_revisions($article);
    check_timellow(get_post_meta(reset($revisions)->ID, '_timellow_markdown_mode', true) === 'html', 'Format-only updates create a matching revision');

    $preview_data = ['post_ID' => $article, 'post_type' => 'post', 'post_title' => 'Preview', 'content' => '**Unsaved Markdown**', '_timellow_markdown_mode' => 'markdown'];
    $_POST = $preview_data;
    $autosave = wp_create_post_autosave(wp_slash($preview_data));
    check_timellow(!is_wp_error($autosave) && get_post_meta($autosave, '_timellow_markdown_mode', true) === 'markdown' && timellow_md_mode($article) === 'html', 'Preview autosave keeps its own format without changing the published post');
    $GLOBALS['post'] = get_post($article);
    add_filter('get_post_metadata', '_wp_preview_meta_filter', 10, 4);
    check_timellow(timellow_md_mode($article) === 'markdown', 'Frontend preview reads the autosaved format');
    remove_filter('get_post_metadata', '_wp_preview_meta_filter', 10);
    $_POST = [];

    $image = 'https://example.invalid/first.png?x=1&y=2';
    foreach (["![First]($image)", "![First][photo]\n\n[photo]: $image", "```\n![Code](https://example.invalid/code.png)\n```\n\n![First]($image)"] as $source) {
        wp_update_post(['ID' => $article, 'post_content' => $source]);
        update_post_meta($article, '_timellow_markdown_mode', 'markdown');
        check_timellow(timellow_cover($article) === $image, 'Markdown cover recognizes images and ignores fenced code');
    }
    update_post_meta($article, 'cover', 'https://example.invalid/override.png');
    check_timellow(timellow_cover($article) === 'https://example.invalid/override.png', 'Explicit cover still takes priority');

    $archives_page = timellow_fixture(['post_type' => 'page', 'post_content' => 'Archive introduction']);
    $moments_page = timellow_fixture(['post_type' => 'page', 'post_content' => 'Moments introduction']);
    wp_update_post(['ID' => $article, 'post_status' => 'draft']);
    for ($i = 1; $i <= 51; $i++) timellow_fixture(['post_title' => "Archive fixture $i"]);
    for ($i = 1; $i <= 11; $i++) timellow_fixture(['post_type' => 'timellow_moment', 'post_title' => "Moment fixture $i"]);
    timellow_fixture(['post_status' => 'private', 'post_title' => 'Private archive fixture']);
    wp_set_current_user(0);
    $first = timellow_render_fixture($archives_page, 'page-archives.php', ['timellow_year' => 2001]);
    $second = timellow_render_fixture($archives_page, 'page-archives.php', ['timellow_year' => 2001, 'timellow_page' => 2]);
    $path = '//main//article[@class="archive-item"]/a';
    $first_links = timellow_nodes($first, $path);
    $second_links = timellow_nodes($second, $path);
    check_timellow($first_links->length === 50 && $second_links->length === 1, 'Archive limits each page to 50 published articles in the selected year');
    $titles = [];
    foreach ([$first_links, $second_links] as $links) foreach ($links as $link) $titles[] = $link->textContent;
    check_timellow(count(array_unique($titles)) === 51 && !str_contains($first . $second, 'Private archive fixture'), 'Archive pagination has no duplicates or private posts');
    check_timellow(str_contains($first, 'timellow_year=2001&#038;timellow_page=2'), 'Archive next-page URL preserves the year');
    $first = timellow_render_fixture($moments_page, 'page-moments.php');
    $second = timellow_render_fixture($moments_page, 'page-moments.php', ['timellow_page' => 2]);
    $path = '//main//article[@class="moment-item"]';
    $first_moments = timellow_nodes($first, $path);
    $second_moments = timellow_nodes($second, $path);
    $moment_ids = [];
    foreach ([$first_moments, $second_moments] as $nodes) foreach ($nodes as $node) $moment_ids[] = $node->getAttribute('id');
    check_timellow($first_moments->length === 10 && $second_moments->length > 0 && count(array_unique($moment_ids)) === count($moment_ids), 'Moments load in distinct batches of 10');
    $last_page = (int) ceil((int) wp_count_posts('timellow_moment')->publish / 10);
    $last = timellow_render_fixture($moments_page, 'page-moments.php', ['timellow_page' => $last_page]);
    check_timellow(str_contains($first, 'data-load-more-trigger') && !str_contains($last, 'data-load-more-trigger'), 'Load-more link is present until the final batch');
    check_timellow($GLOBALS['post']->ID === $moments_page, 'Secondary queries preserve the page context');

    $test_settings['cache_enabled'] = 1;
    $years = timellow_archive_years();
    $dated = timellow_fixture(['post_date' => '1999-01-01 12:00:00', 'post_date_gmt' => '1999-01-01 04:00:00']);
    check_timellow(!in_array(1999, $years, true) && in_array(1999, timellow_archive_years(), true), 'Publishing an article invalidates the cached archive years');
    wp_delete_post($dated, true);
    check_timellow(!in_array(1999, timellow_archive_years(), true), 'Deleting an article invalidates the cached archive years');

    // Run last: core defines DOING_AUTOSAVE for the remainder of this PHP process.
    wp_set_current_user($administrator->ID);
    $draft = timellow_fixture(['post_status' => 'draft', 'meta_input' => ['_timellow_markdown_mode' => 'html']]);
    $autosave_data = [
        'post_id' => $draft, 'post_type' => 'post', 'post_author' => $administrator->ID,
        'post_title' => 'Autosaved draft', 'content' => '**Draft Markdown**',
        '_wpnonce' => wp_create_nonce('update-post_' . $draft),
        'timellow_md_nonce' => wp_create_nonce('timellow_md_save'), '_timellow_markdown_mode' => 'markdown',
    ];
    $_POST = ['data' => ['wp_autosave' => $autosave_data]];
    $saved = wp_autosave($autosave_data);
    check_timellow($saved === $draft && timellow_md_mode($draft) === 'markdown' && get_post_field('post_content', $draft) === '**Draft Markdown**', 'Draft autosave saves content and format together');
    echo "\n{$checks} regression checks passed.\n";
} finally {
    $_POST = $_GET = [];
    unset($_COOKIE['wp-postpass_' . COOKIEHASH]);
    wp_set_current_user($administrator->ID);
    foreach (array_reverse($fixtures) as $id) wp_delete_post($id, true);
    delete_transient(timellow_plugin_data_cache_key('archive_years'));
}
