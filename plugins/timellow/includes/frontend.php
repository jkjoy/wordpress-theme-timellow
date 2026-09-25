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

function timellow_plugin_has_blocks_on_page() {
    global $wp_query;
    if (is_active_widget(false, false, 'block', true)) {
        return true;
    }
    if ($wp_query instanceof WP_Query) {
        foreach ($wp_query->posts as $post) {
            if ($post instanceof WP_Post && has_blocks($post)) {
                return true;
            }
        }
    }
    return false;
}

function timellow_plugin_trim_core_styles() {
    if (empty(timellow_plugin_options()['optimize_assets']) || timellow_plugin_has_blocks_on_page()) {
        return;
    }
    foreach (['wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles'] as $handle) {
        wp_dequeue_style($handle);
    }
    remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
}
add_action('wp_enqueue_scripts', 'timellow_plugin_trim_core_styles', 100);

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
