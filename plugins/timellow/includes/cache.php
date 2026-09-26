<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_plugin_cache_allowed() {
    return !empty(timellow_plugin_options()['cache_enabled'])
        && !is_admin() && !is_user_logged_in() && !is_preview() && !is_feed()
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
}

function timellow_plugin_data_cache_key($key, $settings = null) {
    $settings = $settings ?? timellow_plugin_options();
    return 'timellow_data_' . $settings['cache_revision'] . '_' . $key;
}

function timellow_cached_data($key, $loader, $minutes = null) {
    $settings = timellow_plugin_options();
    $minutes = $minutes ?? (int) $settings['sidebar_cache_ttl'];
    if ($minutes <= 0 || !timellow_plugin_cache_allowed()) {
        return $loader();
    }
    $cache_key = timellow_plugin_data_cache_key($key, $settings);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    $value = $loader();
    if (!is_wp_error($value)) {
        set_transient($cache_key, $value, $minutes * MINUTE_IN_SECONDS);
    }
    return $value;
}

function timellow_cached_content($source = null) {
    global $post, $page;
    $render = static function () use ($source) {
        if ($source === null) {
            the_content();
        } else {
            echo apply_filters('the_content', $source);
        }
    };
    if (!$post instanceof WP_Post || !in_array($post->post_type, ['post', 'page'], true)) {
        $render();
        return;
    }
    if (!timellow_plugin_cache_allowed() || post_password_required($post)) {
        $render();
        return;
    }

    $settings = timellow_plugin_options();
    $default = (int) $settings[$post->post_type . '_cache_ttl'];
    $override = get_post_meta($post->ID, '_timellow_cache_ttl', true);
    $minutes = $override === '' ? $default : (int) $override;
    if ($minutes <= 0) {
        $render();
        return;
    }

    // The content and format are included so edits select a new key immediately.
    $format = get_post_meta($post->ID, '_timellow_markdown_mode', true);
    $key = 'timellow_body_' . md5(implode('|', [
        $settings['cache_revision'], $post->ID, (int) $page, $minutes,
        $post->post_modified_gmt, $post->post_content, $source ?? '', $format,
    ]));
    $html = get_transient($key);
    if ($html === false) {
        ob_start();
        $render();
        $html = ob_get_clean();
        set_transient($key, $html, $minutes * MINUTE_IN_SECONDS);
    }
    echo $html; // Already rendered by the_content() on the cache miss.
}

function timellow_plugin_content_cache_box() {
    foreach (['post', 'page'] as $type) {
        add_meta_box('timellow-content-cache', 'Timellow 正文缓存', 'timellow_plugin_cache_box_html', $type, 'side');
    }
}
add_action('add_meta_boxes', 'timellow_plugin_content_cache_box');

function timellow_plugin_cache_box_html($post) {
    $value = get_post_meta($post->ID, '_timellow_cache_ttl', true);
    wp_nonce_field('timellow_content_cache', 'timellow_content_cache_nonce');
    echo '<p><label for="timellow-cache-ttl">缓存时间（分钟）</label></p>';
    echo '<input id="timellow-cache-ttl" name="timellow_cache_ttl" type="number" min="0" max="10080" step="1" value="' . esc_attr($value) . '" placeholder="使用全局设置" style="width:100%">';
    echo '<p class="description">留空使用全局设置；填 0 关闭该篇缓存。评论始终实时显示。</p>';
}

function timellow_plugin_save_cache_override($post_id) {
    if (!isset($_POST['timellow_content_cache_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['timellow_content_cache_nonce'])), 'timellow_content_cache') ||
        !current_user_can('edit_post', $post_id) ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    $raw = isset($_POST['timellow_cache_ttl']) ? trim((string) wp_unslash($_POST['timellow_cache_ttl'])) : '';
    if ($raw === '') {
        delete_post_meta($post_id, '_timellow_cache_ttl');
    } elseif (ctype_digit($raw)) {
        update_post_meta($post_id, '_timellow_cache_ttl', min(10080, (int) $raw));
    }
}
add_action('save_post', 'timellow_plugin_save_cache_override');

function timellow_plugin_clear_sidebar_cache() {
    foreach (['recent_posts', 'popular_posts', 'recent_comments', 'recent_moments', 'bookmark_groups'] as $key) {
        delete_transient(timellow_plugin_data_cache_key($key));
    }
}
add_action('save_post', 'timellow_plugin_clear_sidebar_cache');
add_action('deleted_post', 'timellow_plugin_clear_sidebar_cache');
add_action('comment_post', 'timellow_plugin_clear_sidebar_cache');
add_action('edit_comment', 'timellow_plugin_clear_sidebar_cache');
add_action('transition_comment_status', 'timellow_plugin_clear_sidebar_cache');
add_action('deleted_comment', 'timellow_plugin_clear_sidebar_cache');

function timellow_plugin_clear_bookmark_cache() {
    delete_transient(timellow_plugin_data_cache_key('bookmark_groups'));
}
foreach (['add_link', 'edit_link', 'delete_link', 'created_link_category', 'edited_link_category', 'delete_link_category'] as $hook) {
    add_action($hook, 'timellow_plugin_clear_bookmark_cache');
}

function timellow_plugin_clear_profile_cache() {
    delete_transient(timellow_plugin_data_cache_key('profile_author'));
}
foreach (['profile_update', 'user_register', 'deleted_user', 'set_user_role'] as $hook) {
    add_action($hook, 'timellow_plugin_clear_profile_cache');
}

function timellow_plugin_is_content_cache_key($key) {
    if (str_starts_with($key, 'timellow_body_') ||
        str_starts_with($key, 'timellow_comment_levels_') ||
        str_starts_with($key, 'timellow_data_')) {
        return true;
    }
    return in_array($key, [
        'timellow_recent_posts', 'timellow_popular_posts', 'timellow_recent_comments',
        'timellow_recent_moments', 'timellow_bookmark_groups', 'timellow_profile_author',
    ], true);
}

function timellow_plugin_clear_all_caches() {
    $settings = timellow_plugin_options();
    $settings['cache_revision'] = wp_generate_uuid4();
    if (!update_option('timellow_plugin_settings', $settings, false)) {
        return false;
    }
    $comment_revision_updated = timellow_plugin_invalidate_comment_levels();

    global $wpdb;
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        '%timellow%'
    ));
    if (!is_array($rows) || $wpdb->last_error !== '') {
        return false;
    }
    $keys = [];
    foreach ($rows as $name) {
        foreach (['_transient_timeout_', '_transient_'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $key = substr($name, strlen($prefix));
                if (timellow_plugin_is_content_cache_key($key)) {
                    $keys[$key] = true;
                }
                break;
            }
        }
    }
    $records_removed = true;
    $missing = new stdClass();
    foreach (array_keys($keys) as $key) {
        delete_transient($key);
        delete_option('_transient_' . $key);
        delete_option('_transient_timeout_' . $key);
        if (get_option('_transient_' . $key, $missing) !== $missing ||
            get_option('_transient_timeout_' . $key, $missing) !== $missing) {
            $records_removed = false;
        }
    }
    return $comment_revision_updated && $records_removed;
}
