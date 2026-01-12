<?php
require_once __DIR__ . '/../../../php/bootstrap.php';
require_login();

$user = current_user();
$payload = json_decode(file_get_contents('php://input'), true);
$webinarId = $payload['webinar_id'] ?? '';
$amount = (float)($payload['amount'] ?? 0);

if (!$webinarId) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing webinar id']);
  exit;
}

$record = log_payment([
  'webinar_id' => $webinarId,
  'amount' => $amount,
  'provider' => 'paypal-sandbox',
  'status' => 'captured'
], $user['id']);

$webinar = get_webinar($webinarId);
$formatted = '$' . number_format($amount, 2);
notify_user(
  $user['id'],
  'Payment received for premium webinar "' . ($webinar['title'] ?? 'Webinar') . '" (' . $formatted . ').',
  'payment',
  ['webinar_id' => $webinarId]
);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'payment' => $record]);
