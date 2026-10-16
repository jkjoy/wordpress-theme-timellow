<?php
// Shared with plugins/timellow/includes/updater.php so either component can provide updates.
if (!defined('ABSPATH')) return;
if (!class_exists('Timellow_Updater', false)) {

class Timellow_Updater {
    private static $components = [];

    public static function register($type, $key) {
        self::$components[$type] = [
            'type' => $type, 'key' => $key,
            'repo' => 'jkjoy/wordpress-' . $type . '-timellow',
            'name' => $type === 'theme' ? 'Timellow 主题' : 'Timellow 插件',
        ];
    }

    public static function mirrors() {
        return ['github' => 'GitHub 直连', 'ghfast' => 'ghfast.top', 'ghproxy' => 'gh-proxy.com', 'custom' => '自定义镜像'];
    }

    public static function settings() {
        return wp_parse_args(get_option('timellow_update_settings', []), [
            'mirror' => 'github', 'custom_url' => '', 'fallback' => 1,
        ]);
    }

    public static function clean_settings($input) {
        $mirror = sanitize_key(is_string($input['mirror'] ?? null) ? $input['mirror'] : 'github');
        $url = is_string($input['custom_url'] ?? null) ? esc_url_raw(trim($input['custom_url'])) : '';
        $parts = wp_parse_url($url);
        if ($url !== '' && (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
            isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['query']) || isset($parts['fragment']))) {
            return new WP_Error('invalid_mirror', '自定义镜像需要填写不带账号、参数的 HTTPS 基础地址。');
        }
        if (!isset(self::mirrors()[$mirror]) || ($mirror === 'custom' && $url === '')) {
            return new WP_Error('invalid_mirror', '请选择更新线路，并填写有效的自定义镜像地址。');
        }
        return ['mirror' => $mirror, 'custom_url' => $url ? trailingslashit($url) : '', 'fallback' => empty($input['fallback']) ? 0 : 1];
    }

    public static function urls($original) {
        $settings = self::settings();
        $bases = ['github' => '', 'ghfast' => 'https://ghfast.top/', 'ghproxy' => 'https://gh-proxy.com/', 'custom' => $settings['custom_url']];
        $base = $bases[$settings['mirror']] ?? '';
        $urls = [$base . $original];
        if ($base && $settings['fallback']) $urls[] = $original;
        return array_values(array_unique($urls));
    }

    private static function cache_key($type) {
        return 'timellow_update_' . $type;
    }

    private static function fingerprint() {
        return md5(wp_json_encode(self::settings()));
    }

    public static function cached($type) {
        $cached = get_transient(self::cache_key($type));
        return is_array($cached) && ($cached['source'] ?? '') === self::fingerprint() ? $cached : false;
    }

    public static function validate_manifest($data, $component) {
        if (!is_array($data) || ($data['type'] ?? '') !== $component['type'] ||
            !is_string($data['version'] ?? null) || !preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/D', $data['version']) ||
            !is_string($data['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $data['sha256'])) return false;
        $package = 'https://github.com/' . $component['repo'] . '/releases/download/v' . $data['version'] . '/timellow.zip';
        if (($data['package'] ?? '') !== $package) return false;
        foreach (['requires', 'requires_php'] as $key) {
            if (!is_string($data[$key] ?? null) || !preg_match('/^\d+(?:\.\d+){0,2}$/D', $data[$key])) return false;
        }
        return [
            'version' => $data['version'], 'package' => $package, 'sha256' => $data['sha256'],
            'requires' => $data['requires'], 'requires_php' => $data['requires_php'],
            'changelog' => is_string($data['changelog'] ?? null) ? $data['changelog'] : '',
            'last_updated' => is_string($data['last_updated'] ?? null) ? sanitize_text_field($data['last_updated']) : '',
        ];
    }

    public static function release($type, $force = false, $allow_network = true) {
        if (!isset(self::$components[$type])) return false;
        $cached = self::cached($type);
        if (!$force && $cached) return $cached['data'] ?: new WP_Error('update_unavailable', $cached['error']);
        if (!$allow_network) return false;
        $component = self::$components[$type];
        $original = 'https://github.com/' . $component['repo'] . '/releases/latest/download/update.json';
        $error = '连接失败，请切换线路后重新检查。';
        $data = false;
        $used_url = '';
        foreach (self::urls($original) as $url) {
            $response = wp_safe_remote_get($url, [
                'timeout' => 8, 'redirection' => 5, 'limit_response_size' => 131072,
                'headers' => ['Accept' => 'application/json', 'Cache-Control' => $force ? 'no-cache' : 'max-age=21600'],
            ]);
            if (is_wp_error($response)) continue;
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                $error = $code === 404 ? '尚未找到正式发布的更新信息。' : '更新线路返回 HTTP ' . (int) $code . '，请切换线路重试。';
                continue;
            }
            $data = self::validate_manifest(json_decode(wp_remote_retrieve_body($response), true), $component);
            if ($data) { $used_url = $url; break; }
            $error = '更新信息格式无效，请切换线路重试。';
        }
        $cached = [
            'source' => self::fingerprint(), 'checked_at' => time(), 'data' => $data,
            'error' => $data ? '' : $error, 'url' => $used_url,
        ];
        set_transient(self::cache_key($type), $cached, $data ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS);
        return $data ?: new WP_Error('update_unavailable', $error);
    }

    private static function installed_version($component) {
        if ($component['type'] === 'theme') return wp_get_theme($component['key'])->get('Version');
        $file = WP_PLUGIN_DIR . '/' . $component['key'];
        return is_file($file) ? get_file_data($file, ['version' => 'Version'])['version'] : '';
    }

    public static function updates($transient, $type) {
        if (!isset(self::$components[$type])) return $transient;
        $component = self::$components[$type];
        $installed = self::installed_version($component);
        if (!$installed) return $transient;
        // Reading update counts on the public site must never start an HTTP request.
        $release = self::release($type, false, is_admin() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI));
        if (!$release || is_wp_error($release)) return $transient;
        $transient = is_object($transient) ? clone $transient : new stdClass();
        $key = $component['key'];
        $update = [
            'id' => 'https://github.com/' . $component['repo'], 'slug' => 'timellow',
            'new_version' => $release['version'], 'url' => 'https://github.com/' . $component['repo'] . '/releases',
            'package' => $release['package'], 'requires' => $release['requires'], 'requires_php' => $release['requires_php'],
        ];
        $update[$type === 'theme' ? 'theme' : 'plugin'] = $key;
        $transient->response = (array) ($transient->response ?? []);
        $transient->no_update = (array) ($transient->no_update ?? []);
        $transient->checked = (array) ($transient->checked ?? []);
        $transient->last_checked = $transient->last_checked ?? 0;
        $transient->checked[$key] = $installed;
        unset($transient->response[$key], $transient->no_update[$key]);
        $bucket = version_compare($release['version'], $installed, '>') ? 'response' : 'no_update';
        $transient->{$bucket}[$key] = $type === 'plugin' ? (object) $update : $update;
        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'timellow' || !isset(self::$components['plugin'])) return $result;
        $release = self::release('plugin');
        if (!$release || is_wp_error($release)) return $result;
        return (object) [
            'name' => 'Timellow', 'slug' => 'timellow', 'version' => $release['version'],
            'author' => 'Timellow', 'homepage' => 'https://github.com/jkjoy/wordpress-plugin-timellow',
            'requires' => $release['requires'], 'requires_php' => $release['requires_php'],
            'last_updated' => $release['last_updated'], 'download_link' => $release['package'],
            'sections' => ['description' => 'Timellow 主题配套插件。', 'changelog' => wpautop(esc_html($release['changelog']))],
        ];
    }

    public static function download($reply, $package) {
        if ($reply !== false || !is_string($package)) return $reply;
        foreach (self::$components as $type => $component) {
            $prefix = 'https://github.com/' . $component['repo'] . '/releases/download/';
            if (!str_starts_with($package, $prefix)) continue;
            $release = self::release($type);
            if (is_wp_error($release)) return $release;
            if (!$release || $release['package'] !== $package) return new WP_Error('timellow_release_changed', '更新版本已变化，请重新检查更新。');
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $error = new WP_Error('timellow_download_failed', '安装包下载失败，请切换更新线路。');
            foreach (self::urls($package) as $url) {
                $file = download_url($url, 60);
                if (is_wp_error($file)) { $error = $file; continue; }
                if (hash_equals($release['sha256'], hash_file('sha256', $file))) return $file;
                wp_delete_file($file);
                $error = new WP_Error('timellow_checksum_failed', '安装包校验失败，请切换更新线路后重试。');
            }
            return $error;
        }
        return $reply;
    }

    public static function admin_menu() {
        add_options_page('Timellow 在线更新', 'Timellow 更新', 'manage_options', 'timellow-updates', [__CLASS__, 'settings_page']);
    }

    public static function admin_request() {
        if (!current_user_can('manage_options')) wp_die('没有权限。');
        check_admin_referer('timellow_updates');
        if (isset($_POST['save_mirror'])) {
            $input = isset($_POST['timellow_updates']) && is_array($_POST['timellow_updates']) ? wp_unslash($_POST['timellow_updates']) : [];
            $settings = self::clean_settings($input);
            if (is_wp_error($settings)) wp_die(esc_html($settings->get_error_message()), 'Timellow', ['back_link' => true]);
            update_option('timellow_update_settings', $settings, false);
            foreach (array_keys(self::$components) as $type) delete_transient(self::cache_key($type));
            $result = 'saved';
        } else {
            if (!current_user_can('update_themes') && !current_user_can('update_plugins')) wp_die('没有更新权限。');
            $result = 'checked';
            foreach (array_keys(self::$components) as $type) {
                if (is_wp_error(self::release($type, true))) $result = 'failed';
            }
        }
        wp_safe_redirect(add_query_arg('timellow_update_result', $result, admin_url('options-general.php?page=timellow-updates')));
        exit;
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $settings = self::settings();
        $result = sanitize_key($_GET['timellow_update_result'] ?? '');
        $messages = ['saved' => '更新线路已保存。点击“立即检查更新”获取此线路的最新版本。', 'checked' => '检查完成。', 'failed' => '部分更新未能检查成功，可查看下方状态并切换线路重试。'];
        ?>
        <div class="wrap"><h1>Timellow 在线更新</h1>
        <?php if (isset($messages[$result])): ?><div class="notice <?php echo $result === 'failed' ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html($messages[$result]); ?></p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="timellow_updates">
            <?php wp_nonce_field('timellow_updates'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="timellow-update-mirror">更新线路</label></th><td><select id="timellow-update-mirror" name="timellow_updates[mirror]">
                    <?php foreach (self::mirrors() as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($settings['mirror'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                </select><p class="description">版本检查和安装包下载均使用所选线路；访问较慢时可切换镜像。</p></td></tr>
                <tr><th scope="row"><label for="timellow-update-custom">自定义镜像地址</label></th><td><input id="timellow-update-custom" class="regular-text" type="url" name="timellow_updates[custom_url]" value="<?php echo esc_attr($settings['custom_url']); ?>" placeholder="https://mirror.example.com/"><p class="description">填写支持 GitHub 文件代理的 HTTPS 基础地址；完整 GitHub 下载地址会附加在其后。</p></td></tr>
                <tr><th scope="row">失败后重试</th><td><label><input type="checkbox" name="timellow_updates[fallback]" value="1" <?php checked((bool) $settings['fallback']); ?>> 镜像失败后尝试 GitHub 直连</label></td></tr>
            </table>
            <p><button class="button button-primary" name="save_mirror" value="1">保存线路</button> <button class="button" name="check_updates" value="1">立即检查更新</button></p>
            <p class="description">先保存线路，再检查更新。成功结果缓存 6 小时，手动检查会立即刷新。</p>
        </form>
        <h2>版本状态</h2><table class="widefat striped"><thead><tr><th>组件</th><th>已安装</th><th>最新版本</th><th>检查状态</th></tr></thead><tbody>
        <?php foreach (self::$components as $type => $component): $cached = self::cached($type); ?>
            <tr><td><?php echo esc_html($component['name']); ?></td><td><?php echo esc_html(self::installed_version($component)); ?></td><td><?php echo esc_html($cached['data']['version'] ?? '—'); ?></td><td>
                <?php if (!$cached): ?>尚未检查。
                <?php elseif ($cached['error']): echo esc_html($cached['error']); ?>
                <?php else: echo esc_html(wp_date('Y-m-d H:i', $cached['checked_at']) . ' · ' . (wp_parse_url($cached['url'], PHP_URL_HOST) === 'github.com' ? 'GitHub 直连' : self::mirrors()[$settings['mirror']])); endif; ?>
            </td></tr>
        <?php endforeach; ?></tbody></table>
        <p><a class="button" href="<?php echo esc_url(admin_url('update-core.php')); ?>">前往 WordPress 更新</a></p>
        </div>
        <?php
    }
}

add_filter('site_transient_update_themes', static fn($value) => Timellow_Updater::updates($value, 'theme'));
add_filter('site_transient_update_plugins', static fn($value) => Timellow_Updater::updates($value, 'plugin'));
add_filter('plugins_api', ['Timellow_Updater', 'plugin_info'], 20, 3);
add_filter('upgrader_pre_download', ['Timellow_Updater', 'download'], 10, 2);
add_action('admin_menu', ['Timellow_Updater', 'admin_menu']);
add_action('admin_post_timellow_updates', ['Timellow_Updater', 'admin_request']);
}
