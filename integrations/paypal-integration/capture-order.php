<?php
require_once __DIR__ . '/../../php/bootstrap.php';
require_login();
require_once __DIR__ . '/paypal-client.php';

$smsSubscriptionPath = BASE_PATH . '/integrations/sms-integration/notifications/subscription_confirmation.php';
if (file_exists($smsSubscriptionPath)) {
  require_once $smsSubscriptionPath;
}
$smsPaymentPath = BASE_PATH . '/integrations/sms-integration/notifications/payment_received.php';
if (file_exists($smsPaymentPath)) {
  require_once $smsPaymentPath;
}

$user = current_user();
$userId = $user['user_id'] ?? '';
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

  if (strpos($customId, 'user:' . $userId) === false) {
    http_response_code(403);
    echo json_encode(['error' => 'User mismatch']);
    exit;
  }

  if ($type === 'subscription') {
    $plan = 'monthly';
    if (strpos($customId, 'plan:yearly') !== false) {
      $plan = 'yearly';
    }
    $existing = get_subscription($userId);
    if (!$existing) {
      $subscription = create_subscription($userId, $plan, 'paypal-sandbox');
      $planLabel = ucfirst((string)($subscription['plan'] ?? $plan));
      $renewalAt = format_datetime_for_user($subscription['renewal_at'] ?? '', $user['timezone'] ?? null);
      $subscriptionEmailContext = [
        'name' => full_name($user),
        'plan' => $planLabel,
        'renewal_at' => $renewalAt ?: ($subscription['renewal_at'] ?? ''),
        'provider' => $subscription['provider'] ?? 'paypal',
        'manage_link' => '/app/settings.php',
        'subscription_id' => $subscription['id'] ?? ''
      ];
      send_email($user['email'], 'Subscription Activated', 'email_subscription.html', $subscriptionEmailContext);
    } else {
      $subscriptions = read_json('subscriptions.json');
      foreach ($subscriptions as &$entry) {
        if (($entry['user_id'] ?? '') === $userId) {
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
      $userId,
      'Subscription payment received for the ' . $plan . ' plan.',
      'subscription',
      ['plan' => $plan]
    );
    if (sms_opted_in($user) && function_exists('notifySubscriptionConfirmed')) {
      notifySubscriptionConfirmed($user['phone'], full_name($user));
    }
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
    $captureId = $unit['payments']['captures'][0]['id'] ?? '';
    if (!has_paid_for_webinar($userId, $webinarId)) {
      $paymentRecord = log_payment([
        'webinar_id' => $webinarId,
        'amount' => $amountValue,
        'provider' => 'paypal-sandbox',
        'status' => 'captured',
        'capture_id' => $captureId
      ], $userId);
      $webinar = get_webinar($webinarId);
      $formatted = '$' . number_format($amountValue, 2);
      $paymentDate = format_datetime_for_user($paymentRecord['created_at'] ?? '', $user['timezone'] ?? null);
      $webinarLink = '/app/webinar.php?id=' . urlencode($webinarId);
      $paymentEmailContext = [
        'name' => full_name($user),
        'webinar_title' => $webinar['title'] ?? 'Webinar',
        'amount_formatted' => $formatted,
        'payment_date' => $paymentDate ?: ($paymentRecord['created_at'] ?? ''),
        'provider' => $paymentRecord['provider'] ?? 'paypal',
        'payment_id' => $paymentRecord['id'] ?? '',
        'webinar_link' => $webinarLink
      ];
      send_email($user['email'], 'Payment Receipt', 'email_payment_receipt.html', $paymentEmailContext);
      notify_user(
        $userId,
        'Payment received for premium webinar "' . ($webinar['title'] ?? 'Webinar') . '" (' . $formatted . ').',
        'payment',
        ['webinar_id' => $webinarId]
      );
      if (sms_opted_in($user) && function_exists('notifyPaymentReceived')) {
        notifyPaymentReceived($user['phone'], $webinar['title'] ?? 'Webinar', $formatted);
      }
    }
    if (!user_is_registered($webinarId, $userId)) {
      register_for_webinar_with_notifications($webinarId, $userId);
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
