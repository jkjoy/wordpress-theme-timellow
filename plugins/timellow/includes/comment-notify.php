<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/comment-notify/http.php';
require_once __DIR__ . '/comment-notify/qq.php';
require_once __DIR__ . '/comment-notify/qq-webhook.php';
require_once __DIR__ . '/comment-notify/weixin.php';

function timellow_notify_defaults(): array
{
    return [
        'qq_enabled' => false, 'qq_app_id' => '', 'qq_app_secret' => '',
        'qq_target_type' => 'c2c', 'qq_target_id' => '',
        'weixin_enabled' => false, 'weixin_user_id' => '',
        'notify_pending' => true, 'notify_approved' => true,
        'notify_admin' => false, 'include_link' => true,
    ];
}

function timellow_notify_config(): array
{
    $config = timellow_notify_defaults();
    $saved = get_option('timellow_notify_settings', []);
    if (!is_array($saved)) { return $config; }
    foreach ($config as $key => $value) {
        if (array_key_exists($key, $saved) && gettype($saved[$key]) === gettype($value)) {
            $config[$key] = $saved[$key];
        }
    }
    return $config;
}

function timellow_notify_state_name(string $key): string
{
    return 'timellow_notify_' . $key;
}

function timellow_notify_read(string $key): array
{
    $value = get_option(timellow_notify_state_name($key), []);
    return is_array($value) ? $value : [];
}

function timellow_notify_write(string $key, array $record): void
{
    $name = timellow_notify_state_name($key);
    if (get_option($name, null) === null) {
        if (!add_option($name, $record, '', false)) { throw new RuntimeException('无法保存通知状态。'); }
    } elseif (!update_option($name, $record, false) && timellow_notify_read($key) !== $record) {
        throw new RuntimeException('无法保存通知状态。');
    }
}

function timellow_notify_compare_and_swap(string $name, string $old, array $new): bool
{
    global $wpdb;
    $exact = $wpdb->is_mysql ? 'BINARY option_value = %s' : 'option_value = %s';
    $sql = $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND {$exact}",
        maybe_serialize($new), $name, $old
    );
    $updated = $wpdb->query($sql);
    if ($updated === false) { throw new RuntimeException('无法更新通知状态。'); }
    if ($updated) {
        wp_cache_delete($name, 'options');
        wp_cache_delete('alloptions', 'options');
    }
    return (bool)$updated;
}

function timellow_notify_state_consume(string $key, array $expected): bool
{
    return $expected !== [] && timellow_notify_compare_and_swap(
        timellow_notify_state_name($key), maybe_serialize($expected), []
    );
}

function timellow_notify_qq_save_bound_target(array $expected, string $target): bool
{
    global $wpdb;
    $name = 'timellow_notify_settings';
    $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
    if (!is_string($raw)) { return false; }
    $current = maybe_unserialize($raw);
    if (!is_array($current)) { return false; }
    foreach (['qq_app_id', 'qq_app_secret', 'qq_target_type', 'qq_target_id'] as $key) {
        if (($current[$key] ?? null) !== $expected[$key]) { return false; }
    }
    if ($current['qq_target_id'] === $target) { return true; }
    $current['qq_target_id'] = $target;
    return timellow_notify_compare_and_swap($name, $raw, $current);
}

function timellow_notify_validate(array $input, array $existing): array
{
    $config = $existing;
    $errors = [];
    foreach (['qq_enabled', 'weixin_enabled', 'notify_pending', 'notify_approved', 'notify_admin', 'include_link'] as $key) {
        $config[$key] = isset($input[$key]) && $input[$key] === '1';
    }
    foreach (['qq_app_id', 'qq_target_type', 'qq_target_id'] as $key) {
        $config[$key] = isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
    }
    $secret = isset($input['qq_app_secret']) && is_string($input['qq_app_secret']) ? trim($input['qq_app_secret']) : '';
    if ($secret !== '') {
        $config['qq_app_secret'] = $secret;
    } elseif ($config['qq_app_id'] !== $existing['qq_app_id']) {
        $config['qq_app_secret'] = '';
        if ($config['qq_app_id'] !== '') { $errors[] = '更换 QQ AppID 时，请重新填写 AppSecret。'; }
    }
    if ($config['qq_app_id'] !== '' && !preg_match('/\A[0-9]{1,32}\z/D', $config['qq_app_id'])) { $errors[] = 'QQ AppID 只能填写数字。'; }
    if ($config['qq_app_secret'] !== '' && !preg_match('/\A[A-Za-z0-9_-]{1,256}\z/D', $config['qq_app_secret'])) { $errors[] = 'QQ AppSecret 格式无效。'; }
    if (!in_array($config['qq_target_type'], ['c2c', 'group', 'channel'], true)) { $errors[] = 'QQ 接收类型无效。'; }
    if ($config['qq_target_id'] !== '' && !preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $config['qq_target_id'])) { $errors[] = 'QQ 接收 ID 格式无效。'; }
    if ($config['qq_enabled'] && ($config['qq_app_id'] === '' || $config['qq_app_secret'] === '' || $config['qq_target_id'] === '')) {
        $errors[] = '启用 QQ 前，请填写 AppID、AppSecret 和接收 ID。';
    }
    if ($config['weixin_enabled']) {
        $account = timellow_notify_read('weixin_account');
        if (empty($account['token']) || !empty($account['expired']) || $config['weixin_user_id'] === '') {
            $errors[] = '启用微信前，请扫码登录并同步绑定接收人。';
        }
    }
    if (!$config['notify_pending'] && !$config['notify_approved']) { $errors[] = '请至少选择一种评论状态。'; }
    return ['config' => $config, 'errors' => array_values(array_unique($errors))];
}

function timellow_notify_save_config(array $config): void
{
    $old = timellow_notify_config();
    $config = array_replace(timellow_notify_defaults(), array_intersect_key($config, timellow_notify_defaults()));
    if ($old['qq_app_id'] !== $config['qq_app_id'] || $old['qq_app_secret'] !== $config['qq_app_secret']) {
        foreach (['qq_token', 'qq_binding', 'qq_webhook'] as $key) { timellow_notify_write($key, []); }
    } elseif ($old['qq_target_type'] !== $config['qq_target_type'] || $old['qq_target_id'] !== $config['qq_target_id']) {
        timellow_notify_write('qq_binding', []);
    }
    update_option('timellow_notify_settings', $config, false);
}

function timellow_notify_status(): array
{
    $config = timellow_notify_config();
    return [
        'qq' => array_merge(timellow_notify_qq_webhook_status(), [
            'target_type' => $config['qq_target_type'], 'target_id' => $config['qq_target_id'], 'enabled' => $config['qq_enabled'],
        ]),
        'weixin' => array_merge(timellow_notify_weixin_status(), ['enabled' => $config['weixin_enabled']]),
        'results' => ['qq' => timellow_notify_read('result_qq'), 'weixin' => timellow_notify_read('result_weixin')],
    ];
}

function timellow_notify_text(string $value, int $limit): string
{
    $text = wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = trim((string)preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $text));
    $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    return !is_array($characters) ? '' : (count($characters) > $limit ? implode('', array_slice($characters, 0, $limit)) . '…' : $text);
}

function timellow_notify_deliver(string $channel, array $config, string $text, ?callable $transport = null): array
{
    try {
        match ($channel) {
            'qq' => timellow_notify_qq_send($config, $text, $transport),
            'weixin' => timellow_notify_weixin_send($config, $text, $transport),
            default => throw new RuntimeException('无效的通知渠道。'),
        };
        $result = ['ok' => true, 'message' => ($channel === 'qq' ? 'QQ' : '微信') . '接口已接受通知。', 'time' => time()];
    } catch (Throwable $exception) {
        $result = ['ok' => false, 'message' => get_class($exception) === RuntimeException::class
            ? $exception->getMessage() : '通知发送失败，请检查服务器环境。', 'time' => time()];
    }
    try { timellow_notify_write('result_' . $channel, $result); }
    catch (Throwable) { error_log('[Timellow] 无法保存评论通知结果。'); }
    return $result;
}

function timellow_notify_test(string $channel, ?callable $transport = null): array
{
    $config = timellow_notify_config();
    $text = '评论通知连接测试' . "\n站点：" . timellow_notify_text((string)get_bloginfo('name'), 60)
        . "\n时间：" . wp_date('Y-m-d H:i:s');
    if ($config['include_link']) { $text .= "\n" . home_url('/'); }
    return timellow_notify_deliver($channel, $config, $text, $transport);
}

function timellow_notify_comment_created(int $comment_id, ?callable $transport = null): void
{
    $config = timellow_notify_config();
    if (!$config['qq_enabled'] && !$config['weixin_enabled']) { return; }
    $comment = get_comment($comment_id);
    $status = $comment instanceof WP_Comment ? (string)$comment->comment_approved : '';
    if (!$comment instanceof WP_Comment || !in_array($comment->comment_type, ['', 'comment'], true)
        || !in_array($status, ['0', '1'], true)
        || !$config[$status === '1' ? 'notify_approved' : 'notify_pending']
        || (!$config['notify_admin'] && (int)$comment->user_id > 0)) { return; }
    $text = '新评论 · ' . timellow_notify_text((string)get_bloginfo('name'), 60)
        . "\n文章：" . timellow_notify_text((string)get_the_title($comment->comment_post_ID), 100)
        . "\n状态：" . ($status === '1' ? '已发布' : '待审核')
        . "\n评论人：" . timellow_notify_text((string)$comment->comment_author, 50)
        . "\n\n" . timellow_notify_text((string)$comment->comment_content, 400);
    if ($config['include_link']) {
        $text .= "\n\n" . ($status === '1'
            ? get_comment_link($comment) : get_permalink($comment->comment_post_ID) . '#comments');
    }
    foreach (['qq', 'weixin'] as $channel) {
        if ($config[$channel . '_enabled']) { timellow_notify_deliver($channel, $config, $text, $transport); }
    }
}

function timellow_notify_on_comment_insert($comment_id): void
{
    try { timellow_notify_comment_created((int)$comment_id); }
    catch (Throwable) { error_log('[Timellow] 无法准备评论通知。'); }
}
add_action('wp_insert_comment', 'timellow_notify_on_comment_insert', 10, 1);

function timellow_notify_register_rest(): void
{
    register_rest_route('timellow/v1', '/qq-webhook', [
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request): WP_REST_Response {
            try {
                $response = timellow_notify_qq_webhook_handle($request->get_method(), $request->get_body(), [
                    'x-bot-appid' => $request->get_header('x-bot-appid'),
                    'x-signature-timestamp' => $request->get_header('x-signature-timestamp'),
                    'x-signature-ed25519' => $request->get_header('x-signature-ed25519'),
                ]);
            } catch (Throwable) {
                error_log('[Timellow] QQ Webhook 处理失败。');
                $response = ['status' => 500, 'body' => ['op' => 12, 'd' => 1]];
            }
            $reply = new WP_REST_Response($response['body'], $response['status']);
            $reply->header('Cache-Control', 'no-store');
            return $reply;
        },
    ]);
}
add_action('rest_api_init', 'timellow_notify_register_rest');
