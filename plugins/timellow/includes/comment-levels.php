<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_plugin_comment_level_counts($comments) {
    static $counts = [];
    static $cached_revision = null;

    if (empty(timellow_plugin_options()['comment_levels'])) {
        return [];
    }
    $revision = (string) get_option('timellow_comment_levels_revision', '0');
    if ($revision !== $cached_revision) {
        $counts = [];
        $cached_revision = $revision;
    }

    $emails = [];
    foreach ($comments as $comment) {
        if (!$comment instanceof WP_Comment || !in_array($comment->comment_type, ['', 'comment'], true)) {
            continue;
        }
        $email = strtolower(trim((string) $comment->comment_author_email));
        if (is_email($email)) {
            $emails[$email] = true;
        }
    }
    $missing = array_keys(array_diff_key($emails, $counts));
    if (!$missing) {
        return $counts;
    }
    sort($missing);

    $key = 'timellow_comment_levels_' . md5($revision . '|' . implode('|', $missing));
    $found = get_transient($key);
    if (!is_array($found)) {
        global $wpdb;
        $found = array_fill_keys($missing, 0);
        $complete = true;
        foreach (array_chunk($missing, 400) as $batch) {
            $placeholders = implode(', ', array_fill(0, count($batch), '%s'));
            $sql = $wpdb->prepare(
                "SELECT LOWER(comment_author_email) AS email, COUNT(*) AS total
                 FROM {$wpdb->comments}
                 WHERE comment_approved = '1' AND comment_type IN ('', 'comment')
                   AND LOWER(comment_author_email) IN ($placeholders)
                 GROUP BY LOWER(comment_author_email)",
                $batch
            );
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if ($rows === null) {
                $complete = false;
                break;
            }
            foreach ($rows as $row) {
                $found[$row['email']] = (int) $row['total'];
            }
        }
        if ($complete) {
            set_transient($key, $found, 12 * HOUR_IN_SECONDS);
        }
    }
    $counts = array_replace($counts, $found);
    return $counts;
}

function timellow_plugin_prime_comment_levels($comments) {
    timellow_plugin_comment_level_counts($comments);
    return $comments;
}
add_filter('comments_array', 'timellow_plugin_prime_comment_levels');

function timellow_plugin_comment_level($comment) {
    $comment = get_comment($comment);
    if (!$comment instanceof WP_Comment || !in_array($comment->comment_type, ['', 'comment'], true)) {
        return null;
    }
    $email = strtolower(trim((string) $comment->comment_author_email));
    if (!is_email($email)) {
        return null;
    }
    $counts = timellow_plugin_comment_level_counts([$comment]);
    $count = $counts[$email] ?? 0;
    if ($count < 1) {
        return null;
    }

    $level = 1;
    foreach ([1, 5, 20, 50, 100, 200] as $index => $minimum) {
        if ($count >= $minimum) {
            $level = $index + 1;
        }
    }
    return ['level' => $level, 'count' => $count];
}

function timellow_plugin_invalidate_comment_levels() {
    update_option('timellow_comment_levels_revision', wp_generate_uuid4(), false);
}
foreach (['wp_insert_comment', 'edit_comment', 'transition_comment_status', 'deleted_comment'] as $hook) {
    add_action($hook, 'timellow_plugin_invalidate_comment_levels');
}
