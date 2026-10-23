<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit; }
$wordpress = $argv[1] ?? '';
if (!is_file($wordpress . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php tests/comment-notify.php /path/to/wordpress\n");
    exit(2);
}
define('DISABLE_WP_CRON', true);
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: {$message}\n";
}

verify(function_exists('timellow_notify_config'), 'Timellow notification module is loaded');
verify(has_action('wp_insert_comment', 'timellow_notify_on_comment_insert') !== false, 'New comment hook is registered');
verify(isset(rest_get_server()->get_routes()['/timellow/v1/qq-webhook']), 'QQ webhook route is registered');
verify(get_option('timellow_notify_settings', null) === null, 'Fixture has no existing notification configuration');
$request_args = [];
$mock_http = static function ($preempt, $args) use (&$request_args) {
    $request_args = $args;
    return ['response' => ['code' => 200, 'message' => 'OK'], 'headers' => [], 'body' => '{"ret":0}', 'cookies' => []];
};
add_filter('pre_http_request', $mock_http, 10, 2);
try { $http = timellow_notify_http('POST', 'https://api.bot.qq.com/test', [], ['hello' => 'world']); }
finally { remove_filter('pre_http_request', $mock_http); }
verify($http['status'] === 200 && $http['data']['ret'] === 0 && $request_args['redirection'] === 0
    && $request_args['sslverify'] === true && str_contains($request_args['body'], 'world'), 'WordPress HTTP transport sends JSON without redirects');

$saved = [];
foreach (['qq_token', 'qq_binding', 'qq_webhook', 'weixin_account', 'weixin_login', 'weixin_sync', 'result_qq', 'result_weixin'] as $key) {
    $saved[$key] = get_option(timellow_notify_state_name($key), null);
}
$post_id = 0;
$comments = [];
try {
    $config = timellow_notify_defaults();
    $config['qq_app_id'] = '123456';
    $config['qq_app_secret'] = 'test_secret_123';
    $config['qq_target_id'] = 'TEST_OPENID';
    timellow_notify_save_config($config);

    $validated = timellow_notify_validate(['qq_enabled' => '1', 'qq_app_id' => '987654', 'qq_target_type' => 'c2c', 'qq_target_id' => 'TEST_OPENID', 'notify_pending' => '1'], timellow_notify_config());
    verify($validated['errors'] !== [], 'Changing QQ AppID without a new secret is rejected');

    $admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int)($admin[0] ?? 0));
    ob_start();
    timellow_notify_render_settings();
    $html = ob_get_clean();
    verify(str_contains($html, 'timellow-notify-form') && !str_contains($html, $config['qq_app_secret']), 'Settings render without exposing AppSecret');
    timellow_notify_enqueue_admin('settings_page_timellow');
    global $wp_scripts;
    verify(str_contains($wp_scripts->registered['timellow-notify-settings']->src, '/assets/comment-notify/settings.js'), 'Admin assets use the plugin URL');

    $body = wp_json_encode(['op' => 13, 'd' => ['plain_token' => 'challenge', 'event_ts' => (string)time()]]);
    $timestamp = (string)time();
    $secret_key = sodium_crypto_sign_secretkey(timellow_notify_qq_keypair($config['qq_app_secret']));
    $headers = ['x-bot-appid' => $config['qq_app_id'], 'x-signature-timestamp' => $timestamp,
        'x-signature-ed25519' => bin2hex(sodium_crypto_sign_detached($timestamp . $body, $secret_key))];
    $request = new WP_REST_Request('POST', '/timellow/v1/qq-webhook');
    foreach ($headers as $key => $value) { $request->set_header($key, $value); }
    $request->set_body($body);
    $response = rest_do_request($request);
    verify($response->get_status() === 200 && ($response->get_data()['plain_token'] ?? '') === 'challenge', 'Signed QQ challenge succeeds through WordPress REST');
    $headers['x-signature-ed25519'] = str_repeat('0', 128);
    verify(timellow_notify_qq_webhook_handle('POST', $body, $headers)['status'] === 403, 'Invalid QQ signature is rejected');

    $binding = timellow_notify_qq_binding_start();
    $event = ['op' => 0, 't' => 'C2C_MESSAGE_CREATE', 'd' => [
        'content' => '绑定通知 ' . $binding['code'], 'author' => ['user_openid' => 'BOUND_OPENID'],
    ]];
    $raw = wp_json_encode($event);
    $headers['x-signature-ed25519'] = bin2hex(sodium_crypto_sign_detached($timestamp . $raw, $secret_key));
    verify(timellow_notify_qq_webhook_handle('POST', $raw, $headers)['status'] === 200
        && timellow_notify_config()['qq_target_id'] === 'BOUND_OPENID'
        && timellow_notify_read('qq_binding') === [], 'Signed QQ event binds a target once');

    verify(!str_contains(timellow_notify_text('&lt;@everyone&gt;正文<script>bad</script>', 400), '<@'), 'Encoded QQ mentions are removed');
    $qr = timellow_notify_weixin_start(static fn(): array => ['status' => 200, 'data' => [
        'qrcode' => 'TEST_QR', 'qrcode_img_content' => 'https://liteapp.weixin.qq.com/qr/test',
    ]]);
    verify($qr['status'] === 'wait' && timellow_notify_read('weixin_login')['qrcode'] === 'TEST_QR', 'Weixin QR login starts on an official URL');
    $login = timellow_notify_weixin_check('', static fn(): array => ['status' => 200, 'data' => [
        'status' => 'confirmed', 'bot_token' => 'TEST_WEIXIN_TOKEN', 'ilink_bot_id' => 'bot@im.bot',
        'ilink_user_id' => 'owner@im.wechat', 'baseurl' => 'https://ilinkai.weixin.qq.com',
    ]]);
    verify($login['status'] === 'confirmed' && timellow_notify_read('weixin_account')['token'] === 'TEST_WEIXIN_TOKEN', 'Weixin QR confirmation stores the account');
    $synced = timellow_notify_weixin_sync(static fn(): array => ['status' => 200, 'data' => [
        'ret' => 0, 'get_updates_buf' => 'cursor', 'msgs' => [
            ['message_type' => 1, 'from_user_id' => 'intruder@im.wechat', 'context_token' => 'intruder'],
            ['message_type' => 1, 'from_user_id' => 'owner@im.wechat', 'context_token' => 'owner-context'],
        ],
    ]]);
    verify($synced['status'] === 'bound' && timellow_notify_config()['weixin_user_id'] === 'owner@im.wechat', 'Weixin sync binds only the scanning owner');
    $sent = [];
    timellow_notify_weixin_send(timellow_notify_config(), '通知', static function ($method, $url, $headers, $payload) use (&$sent): array {
        $sent = $payload;
        return ['status' => 200, 'data' => ['ret' => 0]];
    });
    verify(($sent['msg']['context_token'] ?? '') === 'owner-context'
        && ($sent['msg']['to_user_id'] ?? '') === 'owner@im.wechat', 'Weixin sends with the bound conversation context');
    timellow_notify_weixin_disconnect();
    verify(timellow_notify_read('weixin_account') === [] && timellow_notify_config()['weixin_user_id'] === '', 'Weixin disconnect clears credentials and recipient');

    $post_id = wp_insert_post(['post_title' => '通知测试文章', 'post_status' => 'publish', 'post_type' => 'post'], true);
    verify(is_int($post_id) && $post_id > 0, 'Test post was created');
    foreach ([['1', ''], ['0', ''], ['spam', ''], ['1', 'pingback'], ['1', '', 1]] as $case) {
        $comments[] = wp_insert_comment([
            'comment_post_ID' => $post_id, 'comment_author' => '测试访客',
            'comment_content' => '<b>测试评论内容</b>', 'comment_approved' => $case[0],
            'comment_type' => $case[1], 'user_id' => $case[2] ?? 0,
        ]);
    }
    $config = timellow_notify_config();
    $config['qq_enabled'] = true;
    timellow_notify_save_config($config);
    $messages = [];
    $transport = static function (string $method, string $url, array $headers, ?array $payload = null) use (&$messages): array {
        if (str_contains($url, 'getAppAccessToken')) { return ['status' => 200, 'data' => ['access_token' => 'TEST_TOKEN', 'expires_in' => 3600]]; }
        $messages[] = $payload;
        return ['status' => 200, 'data' => ['id' => 'TEST_MESSAGE']];
    };
    foreach ($comments as $id) { timellow_notify_comment_created((int)$id, $transport); }
    verify(count($messages) === 2, 'Only approved and pending visitor comments send notifications');
    verify(str_contains($messages[0]['content'], '已发布') && str_contains($messages[1]['content'], '待审核')
        && str_contains($messages[0]['content'], '测试评论内容'), 'Notifications include status and comment text');
    verify(timellow_notify_weixin_qr_url('https://example.com/scan') === '', 'Non-Weixin QR URLs are rejected');
} finally {
    foreach ($comments as $id) { if ($id) { wp_delete_comment($id, true); } }
    if ($post_id) { wp_delete_post($post_id, true); }
    delete_option('timellow_notify_settings');
    foreach ($saved as $key => $value) {
        $name = timellow_notify_state_name($key);
        if ($value === null) { delete_option($name); }
        else { update_option($name, $value, false); }
    }
}
