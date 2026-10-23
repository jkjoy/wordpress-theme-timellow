<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

function timellow_notify_admin_redirect(string $type, string $message): void
{
    set_transient('timellow_notify_notice_' . get_current_user_id(), ['type' => $type, 'message' => $message], 30);
    wp_safe_redirect(admin_url('options-general.php?page=timellow#timellow-notify'));
    exit;
}

function timellow_notify_save_request(): void
{
    if (!current_user_can('manage_options')) { wp_die('没有权限。'); }
    check_admin_referer('timellow_notify_settings');
    $input = isset($_POST['timellow_notify']) && is_array($_POST['timellow_notify'])
        ? wp_unslash($_POST['timellow_notify']) : [];
    $validated = timellow_notify_validate($input, timellow_notify_config());
    if ($validated['errors']) { timellow_notify_admin_redirect('error', implode(' ', $validated['errors'])); }
    try {
        timellow_notify_save_config($validated['config']);
    } catch (Throwable) {
        timellow_notify_admin_redirect('error', '通知设置保存失败，请检查数据库。');
    }
    timellow_notify_admin_redirect('success', '评论通知设置已保存。');
}
add_action('admin_post_timellow_notify_save', 'timellow_notify_save_request');

function timellow_notify_ajax_request(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json(['ok' => false, 'message' => '请以管理员身份登录。'], 403);
    }
    check_ajax_referer('timellow_notify_action', 'nonce');
    $operation = isset($_POST['do']) && is_string($_POST['do']) ? sanitize_key(wp_unslash($_POST['do'])) : '';
    try {
        $result = ['ok' => true, 'message' => '状态已更新。'];
        switch ($operation) {
            case 'test_qq': case 'test_weixin':
                $result = timellow_notify_test($operation === 'test_qq' ? 'qq' : 'weixin');
                break;
            case 'qq_binding_start':
                timellow_notify_qq_binding_start();
                $result['message'] = '绑定码已生成，请向目标 QQ 会话发送下方绑定命令。';
                break;
            case 'qq_binding_cancel':
                timellow_notify_qq_binding_cancel();
                $result['message'] = '绑定码已取消。';
                break;
            case 'weixin_start':
                $result = array_merge($result, timellow_notify_weixin_start());
                break;
            case 'weixin_check':
                $code = isset($_POST['verify_code']) && is_string($_POST['verify_code']) ? trim(wp_unslash($_POST['verify_code'])) : '';
                $result = array_merge($result, timellow_notify_weixin_check($code));
                break;
            case 'weixin_sync':
                $result = array_merge($result, timellow_notify_weixin_sync());
                break;
            case 'weixin_bind':
                $user_id = isset($_POST['weixin_user_id']) && is_string($_POST['weixin_user_id']) ? trim(wp_unslash($_POST['weixin_user_id'])) : '';
                timellow_notify_weixin_bind($user_id);
                $result['message'] = '微信接收人已绑定，可开启微信通知并保存。';
                break;
            case 'weixin_disconnect':
                timellow_notify_weixin_disconnect();
                $result['message'] = '微信连接已断开，凭证和会话已清除。';
                break;
            case 'status':
                break;
            default:
                wp_send_json(['ok' => false, 'message' => '操作不存在。'], 400);
        }
        $result['status'] = timellow_notify_status();
        wp_send_json($result, $result['ok'] ? 200 : 400);
    } catch (RuntimeException $exception) {
        wp_send_json(['ok' => false, 'message' => $exception->getMessage(), 'status' => timellow_notify_status()], 400);
    } catch (Throwable) {
        wp_send_json(['ok' => false, 'message' => '操作失败，请检查网络、数据库和服务器环境。'], 502);
    }
}
add_action('wp_ajax_timellow_notify_action', 'timellow_notify_ajax_request');

function timellow_notify_enqueue_admin($hook): void
{
    if ($hook !== 'settings_page_timellow') { return; }
    $base = plugin_dir_url(TIMELLOW_PLUGIN_DIR . '/timellow.php') . 'assets/comment-notify/';
    wp_enqueue_script('timellow-notify-qr', $base . 'vendor/qrcode.js', [], TIMELLOW_PLUGIN_VERSION, true);
    wp_enqueue_script('timellow-notify-qr-utf8', $base . 'vendor/qrcode_UTF8.js', ['timellow-notify-qr'], TIMELLOW_PLUGIN_VERSION, true);
    wp_enqueue_script('timellow-notify-settings', $base . 'settings.js', ['timellow-notify-qr-utf8'], TIMELLOW_PLUGIN_VERSION, true);
}
add_action('admin_enqueue_scripts', 'timellow_notify_enqueue_admin');

function timellow_notify_render_settings(): void
{
    $config = timellow_notify_config();
    $notice = get_transient('timellow_notify_notice_' . get_current_user_id());
    if ($notice) {
        delete_transient('timellow_notify_notice_' . get_current_user_id());
        echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }
    ?>
    <hr>
    <h2 id="timellow-notify">QQ / 微信评论通知</h2>
    <p>新评论提交后通知站长；审核通过时不再次发送。垃圾评论与回响不触发通知。其他插件已启用同类通知时，请关闭重复的渠道。</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="timellow-notify-form">
        <input type="hidden" name="action" value="timellow_notify_save">
        <?php wp_nonce_field('timellow_notify_settings'); ?>
        <h3>QQ 官方机器人</h3>
        <table class="form-table" role="presentation">
            <tr><th scope="row">QQ 通知</th><td><?php timellow_notify_checkbox('qq_enabled', $config['qq_enabled'], '启用 QQ 评论通知'); ?></td></tr>
            <tr><th scope="row"><label for="timellow-qq-app-id">AppID</label></th><td><input id="timellow-qq-app-id" name="timellow_notify[qq_app_id]" value="<?php echo esc_attr($config['qq_app_id']); ?>" class="regular-text" autocomplete="off"></td></tr>
            <tr><th scope="row"><label for="timellow-qq-secret">AppSecret</label></th><td><input id="timellow-qq-secret" type="password" name="timellow_notify[qq_app_secret]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $config['qq_app_secret'] !== '' ? '已保存，留空保持' : '填写机器人 AppSecret'; ?>"><p class="description">更换 AppID 时须重新填写 AppSecret。</p></td></tr>
            <tr><th scope="row">接收类型</th><td><select name="timellow_notify[qq_target_type]" id="timellow-qq-target-type"><?php foreach (['c2c' => '私聊（用户 OpenID）', 'group' => 'QQ 群（群 OpenID）', 'channel' => '频道（子频道 ID）'] as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($config['qq_target_type'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
            <tr><th scope="row"><label for="timellow-qq-target-id">接收 ID</label></th><td><input id="timellow-qq-target-id" name="timellow_notify[qq_target_id]" value="<?php echo esc_attr($config['qq_target_id']); ?>" class="regular-text" autocomplete="off"><p class="description">私聊／群聊可用下方绑定码自动获取 OpenID；频道填写子频道 ID。QQ 号和群号不能直接使用。</p></td></tr>
        </table>
        <h3>微信连接</h3>
        <table class="form-table" role="presentation">
            <tr><th scope="row">微信通知</th><td><?php timellow_notify_checkbox('weixin_enabled', $config['weixin_enabled'], '启用微信评论通知'); ?></td></tr>
            <tr><th scope="row">接收人</th><td><input name="timellow_notify[weixin_user_id]" value="<?php echo esc_attr($config['weixin_user_id']); ?>" class="regular-text" readonly placeholder="尚未绑定"><p class="description">使用下方扫码、同步与绑定操作设置接收人。</p></td></tr>
        </table>
        <h3>通知规则</h3>
        <table class="form-table" role="presentation">
            <tr><th scope="row">评论状态</th><td><?php timellow_notify_checkbox('notify_pending', $config['notify_pending'], '通知待审核的新评论'); ?><br><?php timellow_notify_checkbox('notify_approved', $config['notify_approved'], '通知直接发布的新评论'); ?></td></tr>
            <tr><th scope="row">登录用户</th><td><?php timellow_notify_checkbox('notify_admin', $config['notify_admin'], '通知登录用户发表的评论'); ?></td></tr>
            <tr><th scope="row">文章链接</th><td><?php timellow_notify_checkbox('include_link', $config['include_link'], '附上文章链接'); ?><p class="description">QQ 平台拒绝未备案的链接域名时，可关闭。</p></td></tr>
        </table>
        <?php submit_button('保存评论通知设置'); ?>
    </form>
    <div id="comment-notify-settings" data-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('timellow_notify_action')); ?>" data-status="<?php echo esc_attr(wp_json_encode(timellow_notify_status())); ?>">
        <h3>QQ Webhook 与接收人绑定</h3>
        <p><label for="comment-notify-webhook">官方事件回调地址</label><br><input id="comment-notify-webhook" type="text" class="large-text" readonly autocomplete="off" spellcheck="false"></p>
        <p class="description">在 QQ 开放平台配置 HTTPS 回调；私聊订阅 <code>C2C_MESSAGE_CREATE</code>，群聊订阅 <code>GROUP_AT_MESSAGE_CREATE</code>。先保存凭证，再生成绑定码，向私聊发送绑定命令；群聊须 @机器人发送。完成后刷新状态、启用通知并保存。</p>
        <p id="comment-notify-qq-status"></p>
        <div id="comment-notify-binding" hidden><p><label for="comment-notify-command">一次性绑定命令</label><br><input id="comment-notify-command" type="text" readonly autocomplete="off" spellcheck="false"></p><p id="comment-notify-binding-hint"></p></div>
        <p><button type="button" class="button" data-action="qq_binding_start">生成绑定码</button> <button type="button" class="button" data-action="qq_binding_cancel" hidden>取消绑定</button> <button type="button" class="button" data-action="status">刷新状态</button></p>
        <p id="comment-notify-qq-events" class="description"></p>
        <h3>微信扫码与绑定</h3>
        <p id="comment-notify-weixin-status"></p>
        <p class="description">获取二维码 → 微信扫码确认 → 检查登录状态 → 给机器人发送“绑定通知” → 同步接收人 → 开启微信通知并保存。</p>
        <div id="comment-notify-qr-section" hidden><div id="comment-notify-qr" aria-live="polite"></div><p><a id="comment-notify-qr-link" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">打开微信官方登录链接</a></p></div>
        <p id="comment-notify-verify" hidden><label for="comment-notify-verify-code">手机微信显示的数字验证码</label><br><input id="comment-notify-verify-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="12" pattern="[0-9]{1,12}"></p>
        <p><button type="button" class="button" data-action="weixin_start">获取登录二维码</button> <button type="button" class="button" data-action="weixin_check" hidden>检查登录状态</button> <button type="button" class="button" data-action="weixin_sync" hidden>同步微信接收人</button> <button type="button" class="button" data-action="weixin_disconnect" hidden>退出微信登录</button></p>
        <div id="comment-notify-candidates" hidden><label for="comment-notify-candidate">选择刚发送消息的接收人</label> <select id="comment-notify-candidate"><option value="">请选择接收人</option></select> <button type="button" class="button" data-action="weixin_bind">绑定选中接收人</button></div>
        <p class="description">微信主动消息受会话有效期限制。退出登录会清除凭证和接收人，并关闭微信通知。</p>
        <h3>测试与最近结果</h3>
        <p>修改设置后请先保存，再进行绑定或测试。网络超时不代表消息一定没有送达，请先检查 QQ／微信再决定是否重试。</p>
        <p><button type="button" class="button" data-action="test_qq">发送 QQ 测试</button> <button type="button" class="button" data-action="test_weixin">发送微信测试</button></p>
        <p id="comment-notify-result-qq"></p><p id="comment-notify-result-weixin"></p>
        <p id="comment-notify-message" role="status" aria-live="polite" hidden></p>
    </div>
    <?php
}

function timellow_notify_checkbox(string $name, bool $checked, string $label): void
{
    echo '<label><input type="checkbox" name="timellow_notify[' . esc_attr($name) . ']" value="1" ' . checked($checked, true, false) . '> ' . esc_html($label) . '</label>';
}
