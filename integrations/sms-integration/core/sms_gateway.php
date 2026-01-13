<?php

$config = require __DIR__ . '/../config/sms.config.php';

function sendSMS($recipient, $message)
{
    global $config;

    if (function_exists('normalize_phone_ph')) {
        $recipient = normalize_phone_ph((string)$recipient);
    }

    if (function_exists('log_notification')) {
        log_notification('sms-outbound', [
            'to' => $recipient,
            'message' => $message,
            'provider' => $config['provider'] ?? 'legacy',
            'status' => 'attempted'
        ]);
    }

    $provider = $config['provider'] ?? 'legacy';
    if ($provider === 'sms_gate') {
        $result = sendSMSViaSMSGate($recipient, $message, $config['sms_gate'] ?? []);
        $ok = is_array($result) ? (bool)($result['ok'] ?? false) : (bool)$result;
        if (function_exists('log_notification')) {
            log_notification('sms-outbound', [
                'to' => $recipient,
                'message' => $message,
                'provider' => 'sms_gate',
                'status' => $ok ? 'sent' : 'failed',
                'http_status' => is_array($result) ? ($result['status'] ?? null) : null,
                'response' => is_array($result) ? ($result['response'] ?? null) : null,
                'error' => is_array($result) ? ($result['error'] ?? null) : null
            ]);
        }
        return $ok ? ($result['response'] ?? true) : false;
    }

    $result = sendSMSViaLegacyGateway($recipient, $message, $config['legacy_gateway'] ?? []);
    $ok = is_array($result) ? (bool)($result['ok'] ?? false) : (bool)$result;
    if (function_exists('log_notification')) {
        log_notification('sms-outbound', [
            'to' => $recipient,
            'message' => $message,
            'provider' => 'legacy',
            'status' => $ok ? 'sent' : 'failed',
            'http_status' => is_array($result) ? ($result['status'] ?? null) : null,
            'response' => is_array($result) ? ($result['response'] ?? null) : null,
            'error' => is_array($result) ? ($result['error'] ?? null) : null
        ]);
    }
    return $ok ? (is_array($result) ? ($result['response'] ?? true) : $result) : false;
}

function sendSMSViaSMSGate($recipient, $message, array $smsGateConfig)
{
    $username = $smsGateConfig['username'] ?? '';
    $password = $smsGateConfig['password'] ?? '';
    $mode = $smsGateConfig['mode'] ?? 'local';
    $baseUrl = $mode === 'cloud' ? ($smsGateConfig['cloud_url'] ?? '') : ($smsGateConfig['local_url'] ?? '');

    if (!$username || !$password || !$baseUrl) {
        error_log('SMS Gate config missing username, password, or base URL.');
        return [
            'ok' => false,
            'status' => null,
            'response' => null,
            'error' => 'Missing SMS Gate credentials or base URL.'
        ];
    }

    $url = rtrim($baseUrl, '/') . '/message';
    $payload = json_encode([
        'textMessage' => ['text' => $message],
        'phoneNumbers' => [$recipient]
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $username . ':' . $password,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log('SMS Gate send failed: HTTP ' . $status . ' ' . $error);
        if ($response) {
            error_log('SMS Gate response: ' . $response);
        }
        return [
            'ok' => false,
            'status' => $status,
            'response' => $response,
            'error' => $error
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'response' => $response,
        'error' => null
    ];
}

function sendSMSViaLegacyGateway($recipient, $message, array $legacyConfig)
{
    $gatewayUrl = $legacyConfig['gateway_url'] ?? '';
    $username = $legacyConfig['username'] ?? '';
    $password = $legacyConfig['password'] ?? '';
    if (!$gatewayUrl || !$username || !$password) {
        error_log('Legacy SMS config missing gateway URL or credentials.');
        return [
            'ok' => false,
            'status' => null,
            'response' => null,
            'error' => 'Missing legacy gateway URL or credentials.'
        ];
    }

    $payload = [
        'phoneNumbers' => [$recipient],
        'message' => $message
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($username . ':' . $password)
            ],
            'content' => json_encode($payload),
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($gatewayUrl, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    $status = null;
    if ($statusLine && preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
        $status = (int)$matches[1];
    }
    if ($response === false) {
        $lastError = error_get_last();
        $statusLabel = $statusLine ?: 'No response headers';
        error_log('SMS gateway request failed: ' . $statusLabel);
        if (!empty($lastError['message'])) {
            error_log('SMS gateway error: ' . $lastError['message']);
        }
        return [
            'ok' => false,
            'status' => $status,
            'response' => null,
            'error' => $lastError['message'] ?? 'Legacy gateway request failed.'
        ];
    }
    return [
        'ok' => true,
        'status' => $status,
        'response' => $response,
        'error' => null
    ];
}
