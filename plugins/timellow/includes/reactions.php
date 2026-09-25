<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_plugin_reaction_types() {
    return [
        'like' => ['emoji' => '👍', 'label' => '赞'],
        'love' => ['emoji' => '❤️', 'label' => '喜欢'],
        'laugh' => ['emoji' => '😂', 'label' => '好笑'],
        'wow' => ['emoji' => '😮', 'label' => '惊讶'],
        'fire' => ['emoji' => '🔥', 'label' => '精彩'],
        'clap' => ['emoji' => '👏', 'label' => '鼓掌'],
    ];
}

function timellow_plugin_reaction_table() {
    global $wpdb;
    return $wpdb->prefix . 'timellow_reactions';
}

function timellow_plugin_reactions_install() {
    if ((int) get_option('timellow_reactions_schema_version') === 1) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = timellow_plugin_reaction_table();
    $sql = "CREATE TABLE $table (
        moment_id bigint(20) unsigned NOT NULL,
        visitor_hash char(64) NOT NULL,
        reaction varchar(16) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (moment_id, visitor_hash),
        KEY moment_reaction (moment_id, reaction)
    ) {$wpdb->get_charset_collate()};";
    dbDelta($sql);
    $wpdb->get_var("SELECT COUNT(*) FROM $table");
    if ($wpdb->last_error === '') {
        update_option('timellow_reactions_schema_version', 1, false);
    }
}
add_action('init', 'timellow_plugin_reactions_install', 1);

function timellow_plugin_reaction_visitor($create = false) {
    if (is_user_logged_in()) {
        return hash_hmac('sha256', 'user:' . get_current_user_id(), wp_salt('auth'));
    }
    $name = 'timellow_reaction_token';
    $token = $_COOKIE[$name] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
        if (!$create) {
            return null;
        }
        $token = bin2hex(random_bytes(16));
        setcookie($name, $token, [
            'expires' => time() + YEAR_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $token;
    }
    return hash_hmac('sha256', 'guest:' . $token, wp_salt('auth'));
}

function timellow_plugin_reaction_states($moment_ids) {
    global $wpdb;
    $ids = array_values(array_unique(array_filter(array_map('absint', $moment_ids))));
    $states = array_fill_keys($ids, ['counts' => [], 'selected' => '']);
    if (!$ids) {
        return $states;
    }
    $table = timellow_plugin_reaction_table();
    $visitor = timellow_plugin_reaction_visitor();
    foreach (array_chunk($ids, 400) as $batch) {
        $placeholders = implode(', ', array_fill(0, count($batch), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT moment_id, reaction, COUNT(*) AS total FROM $table
             WHERE moment_id IN ($placeholders) GROUP BY moment_id, reaction",
            $batch
        ), ARRAY_A);
        foreach ($rows ?: [] as $row) {
            $states[(int) $row['moment_id']]['counts'][$row['reaction']] = (int) $row['total'];
        }
        if ($visitor) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT moment_id, reaction FROM $table WHERE visitor_hash = %s AND moment_id IN ($placeholders)",
                array_merge([$visitor], $batch)
            ), ARRAY_A);
            foreach ($rows ?: [] as $row) {
                $states[(int) $row['moment_id']]['selected'] = $row['reaction'];
            }
        }
    }
    return $states;
}

function timellow_plugin_react() {
    $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'timellow_react')) {
        wp_send_json_error(['message' => '页面已过期，请刷新后重试。'], 403);
    }
    $moment_id = isset($_POST['moment_id']) && is_scalar($_POST['moment_id']) ? absint($_POST['moment_id']) : 0;
    $moment = get_post($moment_id);
    if (!$moment || $moment->post_type !== 'timellow_moment' || $moment->post_status !== 'publish' || post_password_required($moment)) {
        wp_send_json_error(['message' => '这条说说不可表态。'], 404);
    }
    $reaction = isset($_POST['reaction']) && is_string($_POST['reaction']) ? sanitize_key(wp_unslash($_POST['reaction'])) : '';
    if (!isset(timellow_plugin_reaction_types()[$reaction])) {
        wp_send_json_error(['message' => '不支持这个表情。'], 400);
    }

    $visitor = timellow_plugin_reaction_visitor(true);
    $source = $_SERVER['REMOTE_ADDR'] ?? '';
    $source = filter_var($source, FILTER_VALIDATE_IP) ? $source : $visitor;
    $rate_key = 'timellow_react_rate_' . hash_hmac('sha256', $source, wp_salt('auth'));
    $hits = (int) get_transient($rate_key);
    if ($hits >= 30) {
        wp_send_json_error(['message' => '操作过于频繁，请稍后再试。'], 429);
    }
    set_transient($rate_key, $hits + 1, MINUTE_IN_SECONDS);

    global $wpdb;
    $table = timellow_plugin_reaction_table();
    $previous = $wpdb->get_var($wpdb->prepare(
        "SELECT reaction FROM $table WHERE moment_id = %d AND visitor_hash = %s", $moment_id, $visitor
    ));
    if ($previous === $reaction) {
        $changed = $wpdb->delete($table, ['moment_id' => $moment_id, 'visitor_hash' => $visitor], ['%d', '%s']);
    } else {
        $changed = $wpdb->replace($table, [
            'moment_id' => $moment_id,
            'visitor_hash' => $visitor,
            'reaction' => $reaction,
            'created_at' => current_time('mysql', true),
        ], ['%d', '%s', '%s', '%s']);
    }
    if ($changed === false) {
        wp_send_json_error(['message' => '保存失败，请稍后重试。'], 500);
    }
    wp_send_json_success(timellow_plugin_reaction_states([$moment_id])[$moment_id]);
}
add_action('wp_ajax_timellow_react', 'timellow_plugin_react');
add_action('wp_ajax_nopriv_timellow_react', 'timellow_plugin_react');

function timellow_plugin_delete_moment_reactions($post_id) {
    if (get_post_type($post_id) === 'timellow_moment') {
        global $wpdb;
        $wpdb->delete(timellow_plugin_reaction_table(), ['moment_id' => $post_id], ['%d']);
    }
}
add_action('before_delete_post', 'timellow_plugin_delete_moment_reactions');
