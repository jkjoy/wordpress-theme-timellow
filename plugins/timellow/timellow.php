<?php
/**
 * Plugin Name: Timellow
 * Description: Front-end optimization, content caching, SMTP mail, and Gravatar mirrors for the Timellow theme.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Timellow
 * License: GPL-2.0-or-later
 * Text Domain: timellow
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TIMELLOW_PLUGIN_VERSION', '1.0.0');
define('TIMELLOW_PLUGIN_DIR', __DIR__);

require_once TIMELLOW_PLUGIN_DIR . '/includes/cache.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/frontend.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/mail.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/admin.php';

function timellow_plugin_defaults() {
    return [
        'optimize_assets' => 1,
        'post_cache_ttl' => 60,
        'page_cache_ttl' => 60,
        'sidebar_cache_ttl' => 5,
        'avatar_mirror' => 'cravatar',
        'avatar_custom_url' => '',
        'smtp_enabled' => 0,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_security' => 'tls',
        'smtp_auth' => 1,
        'smtp_username' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => '',
    ];
}

function timellow_plugin_options() {
    return wp_parse_args(get_option('timellow_plugin_settings', []), timellow_plugin_defaults());
}

function timellow_plugin_activate() {
    add_option('timellow_plugin_settings', timellow_plugin_defaults(), '', false);
}
register_activation_hook(__FILE__, 'timellow_plugin_activate');
