<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_plugin_disable_emoji() {
    if (is_admin() || empty(timellow_plugin_options()['optimize_assets'])) {
        return;
    }
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'wp_print_emoji_styles');
    remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'timellow_plugin_disable_emoji');

function timellow_plugin_use_block_editor($use_block_editor, $post_type) {
    $settings = timellow_plugin_options();
    if (!empty($settings['disable_gutenberg'])) {
        return false;
    }
    $markdown_types = apply_filters('timellow_markdown_post_types', ['post', 'page', 'timellow_moment']);
    return !empty($settings['markdown_editor']) && in_array($post_type, $markdown_types, true) ? false : $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'timellow_plugin_use_block_editor', 10, 2);

function timellow_plugin_use_block_widgets($use_block_editor) {
    return !empty(timellow_plugin_options()['disable_gutenberg']) ? false : $use_block_editor;
}
add_filter('use_widgets_block_editor', 'timellow_plugin_use_block_widgets');

function timellow_plugin_trim_core_styles() {
    if (is_admin() || empty(timellow_plugin_options()['disable_block_styles'])) {
        return;
    }
    $styles = wp_styles();
    foreach ($styles->queue as $handle) {
        if (str_starts_with($handle, 'wp-block-') || in_array($handle, ['global-styles', 'classic-theme-styles', 'core-block-supports'], true)) {
            wp_dequeue_style($handle);
        }
    }
    remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
}
add_action('wp_enqueue_scripts', 'timellow_plugin_trim_core_styles', 1000);
add_action('wp_print_styles', 'timellow_plugin_trim_core_styles', 0);
add_action('wp_footer', 'timellow_plugin_trim_core_styles', 5);

function timellow_plugin_gravatar_url($url) {
    $settings = timellow_plugin_options();
    $mirrors = [
        'cravatar' => 'https://cravatar.cn/avatar/',
        'loli' => 'https://gravatar.loli.net/avatar/',
        'custom' => $settings['avatar_custom_url'],
    ];
    $mirror = $mirrors[$settings['avatar_mirror']] ?? '';
    if (!$mirror) {
        return $url;
    }
    $parts = wp_parse_url($url);
    if (!$parts || !isset($parts['host'], $parts['path']) ||
        !preg_match('/(^|\.)gravatar\.com$/i', $parts['host']) ||
        !preg_match('~^/avatar/([a-f0-9]+)(?:/|$)~i', $parts['path'], $match)) {
        return $url;
    }
    return trailingslashit($mirror) . ltrim(substr($parts['path'], strlen('/avatar/')), '/') .
        (isset($parts['query']) ? '?' . $parts['query'] : '');
}
add_filter('get_avatar_url', 'timellow_plugin_gravatar_url');
