<?php
require_once __DIR__ . '/../../php/bootstrap.php';
require_login();
require_once __DIR__ . '/paypal-client.php';

$user = current_user();
$payload = json_decode(file_get_contents('php://input'), true);
$type = $payload['type'] ?? '';

if (!$type) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing type']);
  exit;
}

$amount = 0.0;
$customId = '';
$description = '';

if ($type === 'subscription') {
  $plan = $payload['plan'] ?? 'monthly';
  $planKey = strtolower($plan);
  $amount = $planKey === 'yearly' ? 180.00 : 19.00;
  $customId = 'sub:plan:' . $planKey . '|user:' . $user['id'];
  $description = 'WebIT ' . ucfirst($planKey) . ' subscription';
} elseif ($type === 'webinar') {
  $webinarId = $payload['webinar_id'] ?? '';
  $webinar = $webinarId ? get_webinar($webinarId) : null;
  if (!$webinar || empty($webinar['premium'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webinar']);
    exit;
  }
  if (($webinar['status'] ?? 'published') !== 'published') {
    http_response_code(400);
    echo json_encode(['error' => 'Webinar is unpublished']);
    exit;
  }
  $webinarTime = strtotime($webinar['datetime'] ?? '');
  if ($webinarTime !== false) {
    $durationMinutes = parse_duration_minutes($webinar['duration'] ?? '60 min');
    $endTime = $webinarTime + ($durationMinutes * 60);
    if ($endTime < time()) {
      http_response_code(400);
      echo json_encode(['error' => 'Webinar has already started']);
      exit;
    }
  }
  if (user_is_registered($webinarId, $user['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Already registered']);
    exit;
  }
  if (user_has_registration_conflict($webinarId, $user['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Time conflict with another registration']);
    exit;
  }
  $capacity = (int)($webinar['capacity'] ?? 0);
  if ($capacity > 0 && webinar_registration_count($webinarId) >= $capacity) {
    http_response_code(400);
    echo json_encode(['error' => 'Webinar is at capacity']);
    exit;
  }
  if (has_paid_for_webinar($user['id'], $webinarId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Already paid']);
    exit;
  }
  $amount = (float)($webinar['price'] ?? 0);
  $customId = 'webinar:' . $webinarId . '|user:' . $user['id'];
  $description = $webinar['title'] ?? 'Premium webinar';
} else {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid type']);
  exit;
}

try {
  $order = paypal_request('POST', '/v2/checkout/orders', [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
      'amount' => [
        'currency_code' => 'USD',
        'value' => number_format($amount, 2, '.', '')
      ],
      'description' => $description,
      'custom_id' => $customId
    ]],
    'application_context' => [
      'shipping_preference' => 'NO_SHIPPING'
    ]
  ]);
  echo json_encode(['id' => $order['id'] ?? '']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
