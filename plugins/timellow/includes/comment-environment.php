<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_plugin_comment_device($agent, $kind) {
    if ($agent === '' || preg_match('/bot|crawler|spider|slurp|facebookexternalhit/i', $agent)) {
        return null;
    }

    if ($kind === 'system') {
        $devices = [
            ['iPad', 'iPadOS', 'apple'], ['iPhone', 'iOS', 'apple'], ['iPod', 'iOS', 'apple'],
            ['Windows Phone', 'Windows Phone', 'windows'], ['Android', 'Android', 'android'],
            ['Windows', 'Windows', 'windows'], ['CrOS', 'ChromeOS', 'chrome'],
            ['Macintosh', 'macOS', 'apple'], ['Mac OS X', 'macOS', 'apple'], ['Linux', 'Linux', 'linux'],
        ];
        foreach ($devices as [$needle, $label, $icon]) {
            if (stripos($agent, $needle) !== false) {
                return ['label' => $label, 'icon' => $icon];
            }
        }
        return ['label' => '其他系统', 'icon' => 'system'];
    }

    $browsers = [
        ['MicroMessenger', '微信内置浏览器', 'weixin'],
        ['QQBrowser', 'QQ 浏览器', 'qq'], ['MQQBrowser', 'QQ 浏览器', 'qq'],
        ['SamsungBrowser', 'Samsung Internet', 'browser'],
        ['UCBrowser', 'UC 浏览器', 'browser'],
        ['EdgiOS/', 'Microsoft Edge', 'edge'], ['EdgA/', 'Microsoft Edge', 'edge'],
        ['Edg/', 'Microsoft Edge', 'edge'], ['Edge/', 'Microsoft Edge', 'edge'],
        ['OPR/', 'Opera', 'opera'], ['Opera/', 'Opera', 'opera'],
        ['FxiOS/', 'Firefox', 'firefox-browser'], ['Firefox/', 'Firefox', 'firefox-browser'],
        ['CriOS/', 'Google Chrome', 'chrome'], ['Chrome/', 'Google Chrome', 'chrome'],
        ['Safari/', 'Safari', 'safari'],
        ['Trident/', 'Internet Explorer', 'internet-explorer'],
        ['MSIE ', 'Internet Explorer', 'internet-explorer'],
    ];
    foreach ($browsers as [$needle, $label, $icon]) {
        if (stripos($agent, $needle) !== false) {
            return ['label' => $label, 'icon' => $icon];
        }
    }
    return ['label' => '其他浏览器', 'icon' => 'browser'];
}

function timellow_plugin_comment_location($ip) {
    static $locations = [];
    static $searchers = [];

    $ip = trim((string) $ip);
    if (array_key_exists($ip, $locations)) {
        return $locations[$ip];
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return null;
    }
    if ($ip === '::1' || str_starts_with($ip, '127.') ||
        !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
        return $locations[$ip] = '本地网络';
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) {
        return $locations[$ip] = null;
    }

    $version = str_contains($ip, ':') ? 6 : 4;
    $db_file = TIMELLOW_PLUGIN_DIR . '/vendor/ip2region/ip2region_v' . $version . '.xdb';
    if (!is_readable($db_file)) {
        return $locations[$ip] = null;
    }
    if (!array_key_exists($version, $searchers)) {
        require_once TIMELLOW_PLUGIN_DIR . '/vendor/ip2region/Searcher.class.php';
        try {
            $ip_version = $version === 6 ? \ip2region\xdb\IPv6::default() : \ip2region\xdb\IPv4::default();
            $searchers[$version] = \ip2region\xdb\Searcher::newWithFileOnly($ip_version, $db_file);
        } catch (\Throwable $error) {
            $searchers[$version] = null;
        }
    }
    if ($searchers[$version] === null) {
        return $locations[$ip] = null;
    }
    try {
        $region = $searchers[$version]->search($ip);
    } catch (\Throwable $error) {
        return $locations[$ip] = null;
    }
    if (!$region) {
        return $locations[$ip] = null;
    }

    $parts = array_values(array_filter(array_slice(array_map('trim', explode('|', $region)), 0, 3),
        static fn($part) => $part !== '' && $part !== '0'));
    if (!$parts || in_array(strtolower($parts[0]), ['reserved', 'private', 'unknown'], true)) {
        return $locations[$ip] = null;
    }
    if ($parts[0] === '中国' && count($parts) > 1) {
        array_shift($parts);
    }
    return $locations[$ip] = implode(' · ', array_unique($parts));
}

function timellow_plugin_comment_environment($comment) {
    if (empty(timellow_plugin_options()['comment_environment'])) {
        return null;
    }
    $comment = get_comment($comment);
    if (!$comment instanceof WP_Comment || !in_array($comment->comment_type, ['', 'comment'], true)) {
        return null;
    }
    $agent = substr((string) $comment->comment_agent, 0, 512);
    return [
        'system' => timellow_plugin_comment_device($agent, 'system'),
        'browser' => timellow_plugin_comment_device($agent, 'browser'),
        'location' => timellow_plugin_comment_location($comment->comment_author_IP),
    ];
}
