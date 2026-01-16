<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();

$user = current_user();
$userId = $user['user_id'] ?? '';
$payload = get_request_body();
$noteId = $payload['notification_id'] ?? '';

if (!$userId || !$noteId) {
  json_response(['ok' => false], 400);
}

mark_notification_read($userId, $noteId);
json_response(['ok' => true]);
