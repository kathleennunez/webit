<?php
require_once __DIR__ . '/../../../php/bootstrap.php';

$config = require __DIR__ . '/../config/sms.config.php';
$expectedToken = $config['inbound']['token'] ?? '';
if ($expectedToken) {
    $token = $_GET['token'] ?? '';
    if (!hash_equals($expectedToken, (string)$token)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);
if (!is_array($data)) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$from = (string)($data['from'] ?? '');
$textBody = (string)($data['text'] ?? '');
if (isset($data['event'], $data['payload']) && is_array($data['payload'])) {
    $payload = $data['payload'];
    $from = (string)($payload['phoneNumber'] ?? $from);
    $textBody = (string)($payload['message'] ?? $textBody);
}
$text = strtoupper(trim($textBody));
$normalizedFrom = preg_replace('/\D+/', '', $from);

if ($text === 'STOP') {
    $users = read_json('users.json');
    foreach ($users as &$user) {
        $phone = preg_replace('/\D+/', '', (string)($user['phone'] ?? ''));
        if ($phone && $phone === $normalizedFrom) {
            $user['sms_opt_in'] = false;
        }
    }
    unset($user);
    write_json('users.json', $users);
}

if ($text === 'CONFIRM') {
    $users = read_json('users.json');
    foreach ($users as &$user) {
        $phone = preg_replace('/\D+/', '', (string)($user['phone'] ?? ''));
        if ($phone && $phone === $normalizedFrom) {
            $user['sms_opt_in'] = true;
        }
    }
    unset($user);
    write_json('users.json', $users);
}

log_notification('sms-inbound', [
    'from' => $from,
    'text' => $textBody,
    'payload' => $data
]);

http_response_code(200);
echo 'OK';
