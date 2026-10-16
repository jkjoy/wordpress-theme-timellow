<?php
// Offline integration checks against the local demo: php tools/update-check.php
if (PHP_SAPI !== 'cli') exit;
define('DISABLE_WP_CRON', true);
require dirname(__DIR__) . '/.local/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$checks = 0;
$requests = [];
$files = [];
$scenario = 'healthy';
$test_settings = ['mirror' => 'github', 'custom_url' => '', 'fallback' => 1];
$payload = "PK\x03\x04Timellow checksum integration fixture";
$old_cache = [];
foreach (['theme', 'plugin'] as $type) $old_cache[$type] = get_transient('timellow_update_' . $type);
$settings_filter = static function () use (&$test_settings) { return $test_settings; };
add_filter('pre_option_timellow_update_settings', $settings_filter);
$http_filter = static function ($pre, $args, $url) use (&$requests, &$scenario, &$files, $payload) {
    $requests[] = $url;
    if ($scenario === 'unpublished') return ['response' => ['code' => 404], 'headers' => [], 'body' => ''];
    $is_mirror = !str_starts_with($url, 'https://github.com/');
    if ($scenario === 'fallback' && $is_mirror) return new WP_Error('offline', 'Mirror unavailable.');
    if (!empty($args['stream'])) {
        file_put_contents($args['filename'], $scenario === 'corrupt' && $is_mirror ? 'damaged download' : $payload);
        $files[] = $args['filename'];
        return ['response' => ['code' => 200], 'headers' => ['content-type' => 'application/zip'], 'body' => ''];
    }
    $type = str_contains($url, 'wordpress-theme-timellow') ? 'theme' : 'plugin';
    $version = $scenario === 'older' ? '0.0.1' : '99.0.0';
    $data = [
        'type' => $type, 'version' => $version, 'requires' => '6.5', 'requires_php' => '8.0',
        'package' => "https://github.com/jkjoy/wordpress-$type-timellow/releases/download/v$version/timellow.zip",
        'sha256' => hash('sha256', $payload), 'changelog' => '<script>unsafe</script>Release notes',
    ];
    if ($scenario === 'invalid') $data['package'] = 'https://example.invalid/unrelated.zip';
    return ['response' => ['code' => 200], 'headers' => ['content-type' => 'application/json'], 'body' => wp_json_encode($data)];
};
add_filter('pre_http_request', $http_filter, 10, 3);

function updater_check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    echo 'PASS ' . $message . "\n";
    $checks++;
}

try {
    foreach (['theme', 'plugin'] as $type) delete_transient('timellow_update_' . $type);
    updater_check(Timellow_Updater::updates(false, 'theme') === false && !$requests, 'Public update-count reads do not make HTTP requests');
    foreach (['http://mirror.example.com/', 'https://user:pass@mirror.example.com/', 'https://mirror.example.com/?token=secret', 'https://mirror.example.com/#part'] as $url) {
        updater_check(is_wp_error(Timellow_Updater::clean_settings(['mirror' => 'custom', 'custom_url' => $url])), 'Reject invalid custom update endpoints');
    }
    updater_check(is_wp_error(Timellow_Updater::clean_settings(['mirror' => 'custom'])), 'Custom mode requires an endpoint');
    $clean = Timellow_Updater::clean_settings(['mirror' => 'custom', 'custom_url' => 'https://mirror.example.com/proxy', 'fallback' => 1]);
    updater_check(!is_wp_error($clean) && $clean['custom_url'] === 'https://mirror.example.com/proxy/', 'Custom mirror paths are normalized');
    $test_settings = $clean;
    $original = 'https://github.com/jkjoy/wordpress-theme-timellow/releases/latest/download/update.json';
    updater_check(Timellow_Updater::urls($original) === ['https://mirror.example.com/proxy/' . $original, $original], 'Custom proxy forwards the full release URL and retains direct fallback');

    $test_settings = ['mirror' => 'ghfast', 'custom_url' => '', 'fallback' => 1];
    $scenario = 'fallback';
    $requests = [];
    $theme = Timellow_Updater::release('theme', true);
    updater_check(is_array($theme) && $requests === ['https://ghfast.top/' . $original, $original], 'Manifest requests fall back from mirror to GitHub');
    $count = count($requests);
    Timellow_Updater::release('theme');
    updater_check(count($requests) === $count, 'Cached manifests avoid repeat requests');
    $test_settings['mirror'] = 'ghproxy';
    $scenario = 'healthy';
    $theme = Timellow_Updater::release('theme');
    updater_check(end($requests) === 'https://gh-proxy.com/' . $original, 'Changing mirrors invalidates the cached route');
    $count = count($requests);
    Timellow_Updater::release('theme', true);
    updater_check(count($requests) === $count + 1, 'Manual checks bypass the local cache');

    set_current_screen('dashboard');
    $base = (object) ['response' => ['other-theme' => ['new_version' => '2.0']], 'no_update' => []];
    $updates = apply_filters('site_transient_update_themes', $base);
    updater_check(is_array($updates->response['timellow']) && $updates->response['timellow']['new_version'] === '99.0.0' && isset($updates->response['other-theme']), 'Native theme update entries preserve unrelated updates');
    $updates = apply_filters('site_transient_update_plugins', false);
    updater_check(is_object($updates->response['timellow/timellow.php']) && $updates->response['timellow/timellow.php']->new_version === '99.0.0', 'Native plugin update entries use the installed plugin path');
    $info = apply_filters('plugins_api', false, 'plugin_information', (object) ['slug' => 'timellow']);
    updater_check($info->version === '99.0.0' && !str_contains($info->sections['changelog'], '<script>'), 'Plugin details expose the version and escape release notes');

    $scenario = 'corrupt';
    $requests = [];
    $file = apply_filters('upgrader_pre_download', false, $theme['package'], null, []);
    updater_check(is_string($file) && file_get_contents($file) === $payload && count($requests) === 2 && str_starts_with($requests[0], 'https://gh-proxy.com/'), 'Package download uses the selected mirror and retries after checksum failure');
    wp_delete_file($file);
    updater_check(array_reduce($files, static fn($clean, $path) => $clean && !is_file($path), true), 'Temporary and corrupt downloads are removed');
    $test_settings['fallback'] = 0;
    $scenario = 'healthy';
    $theme = Timellow_Updater::release('theme', true);
    $scenario = 'corrupt';
    $requests = [];
    $error = Timellow_Updater::download(false, $theme['package']);
    updater_check(is_wp_error($error) && $error->get_error_code() === 'timellow_checksum_failed' && count($requests) === 1, 'Disabling fallback prevents direct requests and rejects corrupt packages');
    updater_check(Timellow_Updater::download(false, 'https://example.invalid/other.zip') === false, 'Other updaters and packages are untouched');
    updater_check(Timellow_Updater::download('/tmp/handled-by-another-updater', $theme['package']) === '/tmp/handled-by-another-updater', 'Existing download filter results are respected');

    $scenario = 'invalid';
    updater_check(is_wp_error(Timellow_Updater::release('theme', true)), 'Reject manifests pointing outside the configured release repository');
    $scenario = 'unpublished';
    $error = Timellow_Updater::release('theme', true);
    updater_check(is_wp_error($error) && str_contains($error->get_error_message(), '尚未找到'), 'Unpublished releases report unavailable status');
    $count = count($requests);
    Timellow_Updater::release('theme');
    updater_check(count($requests) === $count, 'Failed checks use a short cache to avoid repeated timeouts');
    $scenario = 'older';
    Timellow_Updater::release('theme', true);
    $updates = apply_filters('site_transient_update_themes', false);
    updater_check(!isset($updates->response['timellow']) && isset($updates->no_update['timellow']), 'Older releases never offer a downgrade');
    wp_set_current_user(0);
    ob_start(); Timellow_Updater::settings_page(); $html = ob_get_clean();
    updater_check($html === '', 'Update settings are restricted to administrators');
    echo "\n$checks updater integration checks passed.\n";
} finally {
    remove_filter('pre_http_request', $http_filter, 10);
    remove_filter('pre_option_timellow_update_settings', $settings_filter);
    foreach ($old_cache as $type => $cached) {
        if ($cached === false) delete_transient('timellow_update_' . $type);
        else set_transient('timellow_update_' . $type, $cached, 6 * HOUR_IN_SECONDS);
    }
    foreach ($files as $file) if (is_file($file)) wp_delete_file($file);
}
