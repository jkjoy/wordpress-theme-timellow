<?php
/**
 * Plugin Name: Timellow
 * Description: Front-end optimization, content caching, SMTP mail, and Gravatar mirrors for the Timellow theme.
 * Version: 1.9.0
 * Update URI: https://github.com/jkjoy/wordpress-plugin-timellow
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Timellow
 * License: GPL-2.0-or-later
 * Text Domain: timellow
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TIMELLOW_PLUGIN_VERSION', '1.9.0');
define('TIMELLOW_PLUGIN_DIR', __DIR__);

require_once TIMELLOW_PLUGIN_DIR . '/includes/updater.php';
Timellow_Updater::register('plugin', plugin_basename(__FILE__));
if (wp_get_theme('timellow')->exists()) Timellow_Updater::register('theme', 'timellow');

require_once TIMELLOW_PLUGIN_DIR . '/includes/cache.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/frontend.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/mail.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/admin.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/comment-levels.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/comment-environment.php';
require_once TIMELLOW_PLUGIN_DIR . '/includes/reactions.php';

function timellow_plugin_load_markdown() {
    // The former standalone plugin may still be installed on upgraded sites.
    if (!function_exists('timellow_md_post_types')) {
        require_once TIMELLOW_PLUGIN_DIR . '/includes/markdown.php';
    }
}
add_action('plugins_loaded', 'timellow_plugin_load_markdown');

function timellow_plugin_defaults() {
    return [
        'optimize_assets' => 1,
        'disable_gutenberg' => 1,
        'disable_block_styles' => 1,
        'disable_front_admin_bar' => 1,
        'markdown_editor' => 1,
        'comment_levels' => 1,
        'comment_environment' => 1,
        'cache_enabled' => 1,
        'cache_revision' => '0',
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
    timellow_plugin_reactions_install();
    timellow_plugin_deactivate_legacy_markdown();
}
register_activation_hook(__FILE__, 'timellow_plugin_activate');

function timellow_plugin_deactivate_legacy_markdown() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if (is_plugin_active('timellow-markdown/timellow-markdown.php')) {
        deactivate_plugins('timellow-markdown/timellow-markdown.php', true);
    }
}
add_action('admin_init', 'timellow_plugin_deactivate_legacy_markdown', 0);
