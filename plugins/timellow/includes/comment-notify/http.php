<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

function timellow_notify_http(string $method, string $url, array $headers = [], ?array $payload = null, int $timeout = 6): array
{
    $request_headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json; charset=utf-8'];
    foreach ($headers as $header) {
        $parts = explode(':', $header, 2);
        if (count($parts) !== 2) { throw new RuntimeException('通知请求头格式无效。'); }
        $request_headers[trim($parts[0])] = trim($parts[1]);
    }
    $args = [
        'method' => $method,
        'headers' => $request_headers,
        'timeout' => $timeout,
        'redirection' => 0,
        'sslverify' => true,
        'limit_response_size' => 1048576,
    ];
    if ($payload !== null) {
        $args['body'] = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    $response = wp_safe_remote_request($url, $args);
    if (is_wp_error($response)) {
        $timeout_error = str_contains($response->get_error_message(), 'cURL error 28');
        throw new RuntimeException('通知接口连接失败或超时，请检查服务器网络和 HTTPS 证书。', $timeout_error ? 28 : 0);
    }
    $status = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) { throw new RuntimeException('通知接口返回了无效的 JSON 响应（HTTP ' . $status . '）。'); }
    return ['status' => $status, 'data' => $data];
}

function timellow_notify_api_code(array $data): string
{
    $value = $data['errcode'] ?? $data['err_code'] ?? $data['code'] ?? $data['ret'] ?? '';
    return is_scalar($value) && preg_match('/\A-?[0-9]{1,12}\z/', (string)$value) ? (string)$value : '';
}

function timellow_notify_api_error(string $provider, array $response): RuntimeException
{
    $code = timellow_notify_api_code($response['data']);
    $message = $provider . '接口失败（HTTP ' . (int)$response['status'] . ($code !== '' ? '，错误码 ' . $code : '') . '）。';
    if ($provider === 'QQ') {
        $message .= match ($code) {
            '40034105' => '请在 QQ 中开启机器人的主动消息权限。',
            '40054004', '40034101', '40054003' => '请添加机器人好友或将机器人加入目标群，并检查 OpenID。',
            '40054013' => '接收人已关闭机器人主动消息。',
            '40054010', '304003' => '文章域名未获允许，可关闭通知中的文章链接后重试。',
            '40054016', '304018' => '机器人离线；频道推送需要其他程序保持 WebSocket 在线。',
            '304023', '304024' => '消息正在平台审核，请勿重复发送。',
            default => (int)$response['status'] === 429 || $code === '40034100' ? '已达到平台频率限制，请稍后再试。' : '请检查机器人凭证、接收 ID 和平台权限。',
        };
    }
    return new RuntimeException($message);
}
