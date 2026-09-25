<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_plugin_admin_menu() {
    add_options_page('Timellow', 'Timellow', 'manage_options', 'timellow', 'timellow_plugin_settings_page');
}
add_action('admin_menu', 'timellow_plugin_admin_menu');

function timellow_plugin_admin_redirect($result) {
    wp_safe_redirect(add_query_arg('timellow_result', $result, admin_url('options-general.php?page=timellow')));
    exit;
}

function timellow_plugin_clean_mirror_url($value) {
    $value = esc_url_raw(trim((string) $value));
    $parts = wp_parse_url($value);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return '';
    }
    return trailingslashit($value);
}

function timellow_plugin_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('没有权限。');
    }
    check_admin_referer('timellow_plugin_settings');
    $input = isset($_POST['timellow']) && is_array($_POST['timellow']) ? wp_unslash($_POST['timellow']) : [];
    $old = timellow_plugin_options();
    $settings = $old;
    foreach (['optimize_assets', 'disable_gutenberg', 'disable_block_styles', 'disable_front_admin_bar', 'markdown_editor', 'comment_levels', 'comment_environment', 'smtp_enabled', 'smtp_auth'] as $key) {
        $settings[$key] = !empty($input[$key]) ? 1 : 0;
    }
    foreach (['post_cache_ttl', 'page_cache_ttl', 'sidebar_cache_ttl'] as $key) {
        $settings[$key] = min(10080, max(0, (int) ($input[$key] ?? 0)));
    }
    $mirror = sanitize_key($input['avatar_mirror'] ?? 'off');
    $settings['avatar_mirror'] = in_array($mirror, ['off', 'cravatar', 'loli', 'custom'], true) ? $mirror : 'off';
    $settings['avatar_custom_url'] = timellow_plugin_clean_mirror_url($input['avatar_custom_url'] ?? '');
    if ($settings['avatar_mirror'] === 'custom' && !$settings['avatar_custom_url']) {
        timellow_plugin_admin_redirect('invalid_mirror');
    }

    $host = strtolower(trim(sanitize_text_field($input['smtp_host'] ?? '')));
    if ($host !== '' && !preg_match('/^[a-z0-9.-]+$/i', $host)) {
        timellow_plugin_admin_redirect('invalid_host');
    }
    $settings['smtp_host'] = $host;
    $settings['smtp_port'] = min(65535, max(1, (int) ($input['smtp_port'] ?? 587)));
    $security = sanitize_key($input['smtp_security'] ?? 'tls');
    $settings['smtp_security'] = in_array($security, ['none', 'ssl', 'tls'], true) ? $security : 'tls';
    $settings['smtp_username'] = sanitize_text_field($input['smtp_username'] ?? '');
    $settings['smtp_from_email'] = sanitize_email($input['smtp_from_email'] ?? '');
    $settings['smtp_from_name'] = sanitize_text_field($input['smtp_from_name'] ?? '');
    if ($settings['smtp_enabled'] && (!$settings['smtp_host'] || !is_email($settings['smtp_from_email']))) {
        timellow_plugin_admin_redirect('invalid_smtp');
    }

    $password = isset($input['smtp_password']) ? (string) $input['smtp_password'] : '';
    if ($password !== '') {
        $encrypted = timellow_plugin_encrypt_password($password);
        if ($encrypted === false) {
            timellow_plugin_admin_redirect('encryption_failed');
        }
        update_option('timellow_smtp_password', $encrypted, false);
    } elseif (!empty($input['clear_smtp_password'])) {
        delete_option('timellow_smtp_password');
    }
    update_option('timellow_plugin_settings', $settings, false);
    update_option('comments_notify', !empty($input['notify_author']) ? '1' : '0');
    update_option('moderation_notify', !empty($input['notify_moderator']) ? '1' : '0');
    if ($settings['sidebar_cache_ttl'] !== $old['sidebar_cache_ttl']) {
        timellow_plugin_clear_sidebar_cache();
    }
    timellow_plugin_admin_redirect('saved');
}
add_action('admin_post_timellow_save_settings', 'timellow_plugin_save_settings');

function timellow_plugin_send_test() {
    if (!current_user_can('manage_options')) {
        wp_die('没有权限。');
    }
    check_admin_referer('timellow_plugin_test_mail');
    $email = sanitize_email(wp_unslash($_POST['test_email'] ?? ''));
    $settings = timellow_plugin_options();
    if (!is_email($email) || empty($settings['smtp_enabled']) || !$settings['smtp_host']) {
        timellow_plugin_admin_redirect('test_invalid');
    }
    $error = '';
    $capture = static function ($wp_error) use (&$error) {
        $error = $wp_error->get_error_message();
    };
    add_action('wp_mail_failed', $capture);
    $sent = wp_mail($email, 'Timellow SMTP 测试', '这是一封来自 Timellow 的 SMTP 测试邮件。');
    remove_action('wp_mail_failed', $capture);
    if (!$sent) {
        set_transient('timellow_mail_error_' . get_current_user_id(), sanitize_text_field($error ?: '发信失败，请检查 SMTP 设置。'), MINUTE_IN_SECONDS);
    }
    timellow_plugin_admin_redirect($sent ? 'test_sent' : 'test_failed');
}
add_action('admin_post_timellow_test_mail', 'timellow_plugin_send_test');

function timellow_plugin_checkbox($name, $checked, $label) {
    echo '<label><input type="checkbox" name="timellow[' . esc_attr($name) . ']" value="1" ' . checked($checked, true, false) . '> ' . esc_html($label) . '</label>';
}

function timellow_plugin_number($settings, $key) {
    echo '<input type="number" name="timellow[' . esc_attr($key) . ']" min="0" max="10080" step="1" value="' . esc_attr($settings[$key]) . '" class="small-text"> 分钟';
}

function timellow_plugin_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $settings = timellow_plugin_options();
    $result = sanitize_key($_GET['timellow_result'] ?? '');
    $messages = [
        'saved' => '设置已保存。', 'invalid_mirror' => '请填写有效的 HTTPS 头像镜像地址。',
        'invalid_host' => 'SMTP 主机名格式无效。', 'invalid_smtp' => '启用 SMTP 需要主机名和有效的发件人邮箱。',
        'encryption_failed' => '无法加密 SMTP 密码；设置未保存。', 'test_sent' => '测试邮件已提交发送。',
        'test_failed' => '测试邮件发送失败。', 'test_invalid' => '请先启用 SMTP 并填写有效的收件地址。',
    ];
    echo '<div class="wrap"><h1>Timellow</h1>';
    if (isset($messages[$result])) {
        $class = in_array($result, ['saved', 'test_sent'], true) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($messages[$result]);
        if ($result === 'test_failed') {
            $error = get_transient('timellow_mail_error_' . get_current_user_id());
            if ($error) {
                echo ' ' . esc_html($error);
                delete_transient('timellow_mail_error_' . get_current_user_id());
            }
        }
        echo '</p></div>';
    }
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="timellow_save_settings">
        <?php wp_nonce_field('timellow_plugin_settings'); ?>
        <h2>编辑器与前端资源</h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row">古腾堡编辑器</th><td><?php timellow_plugin_checkbox('disable_gutenberg', (bool) $settings['disable_gutenberg'], '禁用文章、页面和小工具的区块编辑器'); ?></td></tr>
            <tr><th scope="row">区块样式</th><td><?php timellow_plugin_checkbox('disable_block_styles', (bool) $settings['disable_block_styles'], '前台不加载 WordPress 区块样式'); ?><p class="description">已有区块内容依赖这些样式时，可关闭此项。</p></td></tr>
            <tr><th scope="row">前台工具栏</th><td><?php timellow_plugin_checkbox('disable_front_admin_bar', (bool) $settings['disable_front_admin_bar'], '隐藏前台 WordPress 工具栏'); ?><p class="description">仅影响网站前台；后台工具栏保持可用。</p></td></tr>
            <tr><th scope="row">Markdown 编辑器</th><td><?php timellow_plugin_checkbox('markdown_editor', (bool) $settings['markdown_editor'], '启用文章、页面和说说的 Markdown 编辑器'); ?><p class="description">关闭后继续解析已有 Markdown 内容；开启时，这些内容类型使用经典编辑界面。</p></td></tr>
            <tr><th scope="row">Emoji 资源</th><td><?php timellow_plugin_checkbox('optimize_assets', (bool) $settings['optimize_assets'], '移除 WordPress 前台 Emoji 外部脚本'); ?><p class="description">主题资源仍从本站加载；其他插件资源不会被改写。</p></td></tr>
        </table>
        <h2>缓存</h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row"><label for="timellow-post-cache">文章正文</label></th><td><span id="timellow-post-cache"><?php timellow_plugin_number($settings, 'post_cache_ttl'); ?></span></td></tr>
            <tr><th scope="row">页面正文</th><td><?php timellow_plugin_number($settings, 'page_cache_ttl'); ?></td></tr>
            <tr><th scope="row">侧栏列表与书签</th><td><?php timellow_plugin_number($settings, 'sidebar_cache_ttl'); ?><p class="description">填写 0 关闭；文章和页面还可以在编辑页单独覆盖。登录用户、预览及评论不使用正文缓存。</p></td></tr>
        </table>
        <h2>评论</h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row">评论者等级</th><td><?php timellow_plugin_checkbox('comment_levels', (bool) $settings['comment_levels'], '按邮箱的已审核评论数显示等级'); ?><p class="description">仅统计普通评论；等级表示活跃度，不验证邮箱归属。</p></td></tr>
            <tr><th scope="row">评论环境</th><td><?php timellow_plugin_checkbox('comment_environment', (bool) $settings['comment_environment'], '显示评论时的系统、浏览器及 IP 归属地'); ?><p class="description">仅显示大致地区，不公开 IP；使用插件内置的本地归属地数据库，不向第三方发送访客 IP。历史评论缺少浏览器或 IP 信息时隐藏对应项目。</p></td></tr>
        </table>
        <h2>头像镜像</h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row">Gravatar 地址</th><td><select name="timellow[avatar_mirror]">
                <?php foreach (['off' => '原始地址', 'cravatar' => 'Cravatar', 'loli' => 'gravatar.loli.net', 'custom' => '自定义镜像'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['avatar_mirror'], $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></td></tr>
            <tr><th scope="row"><label for="timellow-avatar-custom">自定义镜像基础地址</label></th><td><input id="timellow-avatar-custom" type="url" class="regular-text" name="timellow[avatar_custom_url]" value="<?php echo esc_attr($settings['avatar_custom_url']); ?>" placeholder="https://example.com/avatar/"><p class="description">填写镜像的头像目录地址；仅改写 Gravatar 请求，保留头像尺寸等参数。</p></td></tr>
        </table>
        <h2>SMTP 邮件</h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row">启用 SMTP</th><td><?php timellow_plugin_checkbox('smtp_enabled', (bool) $settings['smtp_enabled'], '使用以下服务器发送 WordPress 邮件'); ?></td></tr>
            <tr><th scope="row"><label for="timellow-smtp-host">服务器</label></th><td><input id="timellow-smtp-host" name="timellow[smtp_host]" value="<?php echo esc_attr($settings['smtp_host']); ?>" class="regular-text" autocomplete="off" placeholder="smtp.example.com"></td></tr>
            <tr><th scope="row">端口</th><td><input type="number" name="timellow[smtp_port]" min="1" max="65535" value="<?php echo esc_attr($settings['smtp_port']); ?>" class="small-text"></td></tr>
            <tr><th scope="row">加密方式</th><td><select name="timellow[smtp_security]"><?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => '无'] as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($settings['smtp_security'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
            <tr><th scope="row">身份验证</th><td><?php timellow_plugin_checkbox('smtp_auth', (bool) $settings['smtp_auth'], '服务器需要用户名和密码'); ?></td></tr>
            <tr><th scope="row">用户名</th><td><input name="timellow[smtp_username]" value="<?php echo esc_attr($settings['smtp_username']); ?>" class="regular-text" autocomplete="off"></td></tr>
            <tr><th scope="row">密码</th><td><input type="password" name="timellow[smtp_password]" value="" class="regular-text" autocomplete="new-password" placeholder="留空保持现有密码"><p><label><input type="checkbox" name="timellow[clear_smtp_password]" value="1"> 清除已保存密码</label></p><p class="description">密码单独加密保存，不在设置页面回显。</p></td></tr>
            <tr><th scope="row">发件人邮箱</th><td><input type="email" name="timellow[smtp_from_email]" value="<?php echo esc_attr($settings['smtp_from_email']); ?>" class="regular-text"></td></tr>
            <tr><th scope="row">发件人名称</th><td><input name="timellow[smtp_from_name]" value="<?php echo esc_attr($settings['smtp_from_name']); ?>" class="regular-text"></td></tr>
            <tr><th scope="row">评论通知</th><td><label><input type="checkbox" name="timellow[notify_author]" value="1" <?php checked((bool) get_option('comments_notify')); ?>> 通过 WordPress 通知文章作者</label><br><label><input type="checkbox" name="timellow[notify_moderator]" value="1" <?php checked((bool) get_option('moderation_notify')); ?>> 待审核评论通知管理员</label><p class="description">沿用 WordPress 原生通知；启用 SMTP 后这些邮件会走上述服务器。</p></td></tr>
        </table>
        <?php submit_button('保存设置'); ?>
    </form>
    <h2>发送测试邮件</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="timellow_test_mail">
        <?php wp_nonce_field('timellow_plugin_test_mail'); ?>
        <label for="timellow-test-email">收件邮箱</label> <input id="timellow-test-email" type="email" name="test_email" class="regular-text" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required>
        <?php submit_button('发送测试', 'secondary', 'submit', false); ?>
    </form></div>
    <?php
}
