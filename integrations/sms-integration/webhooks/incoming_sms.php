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
$message = trim($textBody);

$normalizedFrom = $from;
if (function_exists('normalize_phone_ph')) {
    $normalizedFrom = normalize_phone_ph($from);
}
$normalizedDigits = preg_replace('/\D+/', '', (string)$normalizedFrom);

$userId = null;
if ($normalizedDigits !== '') {
    $users = read_json('users.json');
    foreach ($users as $user) {
        $userPhone = (string)($user['phone'] ?? '');
        $userDigits = preg_replace('/\D+/', '', $userPhone);
        if ($userDigits && $userDigits === $normalizedDigits) {
            $userId = $user['id'] ?? null;
            break;
        }
    }
}

$webinarId = null;
if ($userId) {
    $attendance = read_json('attendance.json');
    $latestTs = null;
    foreach ($attendance as $record) {
        if (($record['user_id'] ?? '') !== $userId) {
            continue;
        }
        $timestamp = $record['timestamp'] ?? '';
        $ts = $timestamp ? strtotime($timestamp) : false;
        if ($ts === false) {
            continue;
        }
        if ($latestTs === null || $ts > $latestTs) {
            $latestTs = $ts;
            $webinarId = $record['webinar_id'] ?? null;
        }
    }
}

if ($message !== '') {
    $payloadJson = json_encode($data, JSON_UNESCAPED_SLASHES);
    $createdAt = date('Y-m-d H:i:s');
    $phoneValue = $normalizedFrom !== '' ? $normalizedFrom : $from;

    $pdo = db_connection();
    $stmt = $pdo->prepare('INSERT INTO sms_feedback (id, user_id, webinar_id, phone, message, raw_payload, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        uniqid('smsfb_', true),
        $userId,
        $webinarId,
        $phoneValue,
        $message,
        $payloadJson,
        $createdAt
    ]);
}

log_notification('sms-inbound', [
    'from' => $from,
    'text' => $textBody,
    'payload' => $data
]);

http_response_code(200);
echo 'OK';
