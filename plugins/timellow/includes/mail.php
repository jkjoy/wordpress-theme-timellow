<?php
if (!defined('ABSPATH')) {
    exit;
}

function timellow_plugin_encrypt_password($password) {
    if (!function_exists('openssl_encrypt')) {
        return false;
    }
    $nonce = random_bytes(12);
    $tag = '';
    $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
    $ciphertext = openssl_encrypt($password, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    return $ciphertext === false ? false : base64_encode($nonce . $tag . $ciphertext);
}

function timellow_plugin_smtp_password() {
    $encoded = get_option('timellow_smtp_password', '');
    $payload = base64_decode($encoded, true);
    if (!$payload || strlen($payload) < 29 || !function_exists('openssl_decrypt')) {
        return '';
    }
    $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
    $plain = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16));
    return $plain === false ? '' : $plain;
}

function timellow_plugin_configure_phpmailer($mailer) {
    $settings = timellow_plugin_options();
    if (empty($settings['smtp_enabled']) || !$settings['smtp_host'] || !is_email($settings['smtp_from_email'])) {
        return;
    }
    $mailer->isSMTP();
    $mailer->Host = $settings['smtp_host'];
    $mailer->Port = (int) $settings['smtp_port'];
    $mailer->SMTPSecure = $settings['smtp_security'] === 'none' ? '' : $settings['smtp_security'];
    $mailer->SMTPAutoTLS = $settings['smtp_security'] !== 'none';
    $mailer->SMTPAuth = (bool) $settings['smtp_auth'];
    $mailer->Username = $settings['smtp_auth'] ? $settings['smtp_username'] : '';
    $mailer->Password = $settings['smtp_auth'] ? timellow_plugin_smtp_password() : '';
    $mailer->setFrom($settings['smtp_from_email'], $settings['smtp_from_name'] ?: get_bloginfo('name'), false);
}
add_action('phpmailer_init', 'timellow_plugin_configure_phpmailer');

function timellow_plugin_mail_from($from) {
    $settings = timellow_plugin_options();
    return !empty($settings['smtp_enabled']) && is_email($settings['smtp_from_email']) ? $settings['smtp_from_email'] : $from;
}
add_filter('wp_mail_from', 'timellow_plugin_mail_from');

function timellow_plugin_mail_from_name($name) {
    $settings = timellow_plugin_options();
    return !empty($settings['smtp_enabled']) && $settings['smtp_from_name'] !== '' ? $settings['smtp_from_name'] : $name;
}
add_filter('wp_mail_from_name', 'timellow_plugin_mail_from_name');
