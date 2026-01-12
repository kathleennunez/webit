<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_once __DIR__ . '/paypal-client.php';

$user = current_user();
$payload = json_decode(file_get_contents('php://input'), true);
$orderId = $payload['order_id'] ?? '';
$type = $payload['type'] ?? '';

if (!$orderId || !$type) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing order id or type']);
  exit;
}

header('Content-Type: application/json');

try {
  $capture = paypal_request('POST', '/v2/checkout/orders/' . urlencode($orderId) . '/capture');
  $status = $capture['status'] ?? '';
  if ($status !== 'COMPLETED') {
    http_response_code(400);
    echo json_encode(['error' => 'Capture not completed', 'status' => $status]);
    exit;
  }
  $unit = $capture['purchase_units'][0] ?? [];
  $customId = $unit['custom_id'] ?? '';
  if (!$customId) {
    $order = paypal_request('GET', '/v2/checkout/orders/' . urlencode($orderId));
    $customId = $order['purchase_units'][0]['custom_id'] ?? '';
  }
  $amountValue = (float)($unit['payments']['captures'][0]['amount']['value'] ?? $unit['amount']['value'] ?? 0);

  if (strpos($customId, 'user:' . $user['id']) === false) {
    http_response_code(403);
    echo json_encode(['error' => 'User mismatch']);
    exit;
  }

  if ($type === 'subscription') {
    $plan = 'monthly';
    if (strpos($customId, 'plan:yearly') !== false) {
      $plan = 'yearly';
    }
    $existing = get_subscription($user['id']);
    if (!$existing) {
      $subscription = create_subscription($user['id'], $plan, 'paypal-sandbox');
      send_email($user['email'], 'Subscription Activated', 'email_subscription.html', $subscription);
    } else {
      $subscriptions = read_json('subscriptions.json');
      foreach ($subscriptions as &$entry) {
        if (($entry['user_id'] ?? '') === $user['id']) {
          $entry['plan'] = $plan;
          $entry['status'] = 'active';
          $entry['provider'] = 'paypal-sandbox';
          $entry['renewal_at'] = $plan === 'yearly' ? date('c', strtotime('+1 year')) : date('c', strtotime('+1 month'));
          break;
        }
      }
      unset($entry);
      write_json('subscriptions.json', $subscriptions);
    }
    notify_user(
      $user['id'],
      'Subscription payment received for the ' . $plan . ' plan.',
      'subscription',
      ['plan' => $plan]
    );
    echo json_encode(['success' => true]);
    exit;
  }

  if ($type === 'webinar') {
    $webinarId = '';
    if (preg_match('/webinar:([^|]+)/', $customId, $matches)) {
      $webinarId = $matches[1];
    }
    if (!$webinarId) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing webinar id']);
      exit;
    }
    if (!has_paid_for_webinar($user['id'], $webinarId)) {
      log_payment([
        'webinar_id' => $webinarId,
        'amount' => $amountValue,
        'provider' => 'paypal-sandbox',
        'status' => 'captured'
      ], $user['id']);
      $webinar = get_webinar($webinarId);
      $formatted = '$' . number_format($amountValue, 2);
      notify_user(
        $user['id'],
        'Payment received for premium webinar "' . ($webinar['title'] ?? 'Webinar') . '" (' . $formatted . ').',
        'payment',
        ['webinar_id' => $webinarId]
      );
    }
    echo json_encode(['success' => true]);
    exit;
  }

  http_response_code(400);
  echo json_encode(['error' => 'Invalid type']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
