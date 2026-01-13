<?php
require_once __DIR__ . '/../../php/bootstrap.php';
require_once __DIR__ . '/paypal-client.php';

$smsSubscriptionPath = BASE_PATH . '/integrations/sms-integration/notifications/subscription_confirmation.php';
if (file_exists($smsSubscriptionPath)) {
  require_once $smsSubscriptionPath;
}
$smsPaymentPath = BASE_PATH . '/integrations/sms-integration/notifications/payment_received.php';
if (file_exists($smsPaymentPath)) {
  require_once $smsPaymentPath;
}

$body = file_get_contents('php://input');
$headers = [];
foreach ($_SERVER as $key => $value) {
  if (str_starts_with($key, 'HTTP_')) {
    $headerName = str_replace('_', '-', substr($key, 5));
    $headers[$headerName] = $value;
  }
}

try {
  if (!paypal_verify_webhook($body, $headers)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
  }

  $event = json_decode($body, true);
  $eventType = $event['event_type'] ?? '';
  $resource = $event['resource'] ?? [];

  if ($eventType !== 'PAYMENT.CAPTURE.COMPLETED') {
    echo 'Ignored';
    exit;
  }

  $customId = $resource['custom_id'] ?? '';
  if (!$customId && !empty($resource['supplementary_data']['related_ids']['order_id'])) {
    $orderId = $resource['supplementary_data']['related_ids']['order_id'];
    $order = paypal_request('GET', '/v2/checkout/orders/' . urlencode($orderId));
    $customId = $order['purchase_units'][0]['custom_id'] ?? '';
  }

  if (!$customId) {
    echo 'No custom id';
    exit;
  }

  $userId = '';
  if (preg_match('/user:([^|]+)/', $customId, $matches)) {
    $userId = $matches[1];
  }
  if (!$userId) {
    echo 'No user id';
    exit;
  }

  $amountValue = (float)($resource['amount']['value'] ?? 0);

  if (str_starts_with($customId, 'sub:')) {
    $plan = strpos($customId, 'plan:yearly') !== false ? 'yearly' : 'monthly';
    $existing = get_subscription($userId);
    if (!$existing) {
      create_subscription($userId, $plan, 'paypal-sandbox');
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
    $user = get_user_by_id($userId);
    if (function_exists('notifySubscriptionConfirmed')) {
      if (sms_opted_in($user)) {
        notifySubscriptionConfirmed($user['phone'], full_name($user));
      }
    }
    if (!empty($user['email'])) {
      $subscription = get_subscription($userId);
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
    }
    echo 'ok';
    exit;
  }

  if (str_starts_with($customId, 'webinar:')) {
    $webinarId = '';
    if (preg_match('/webinar:([^|]+)/', $customId, $matches)) {
      $webinarId = $matches[1];
    }
    if ($webinarId && !has_paid_for_webinar($userId, $webinarId)) {
      $captureId = $resource['id'] ?? '';
      $paymentRecord = log_payment([
        'webinar_id' => $webinarId,
        'amount' => $amountValue,
        'provider' => 'paypal-sandbox',
        'status' => 'captured',
        'capture_id' => $captureId
      ], $userId);
      $webinar = get_webinar($webinarId);
      $formatted = '$' . number_format($amountValue, 2);
      notify_user(
        $userId,
        'Payment received for premium webinar "' . ($webinar['title'] ?? 'Webinar') . '" (' . $formatted . ').',
        'payment',
        ['webinar_id' => $webinarId]
      );
      if (!user_is_registered($webinarId, $userId)) {
        register_for_webinar_with_notifications($webinarId, $userId);
      }
      $user = get_user_by_id($userId);
      if (function_exists('notifyPaymentReceived')) {
        if (sms_opted_in($user)) {
          notifyPaymentReceived($user['phone'], $webinar['title'] ?? 'Webinar', $formatted);
        }
      }
      if (!empty($user['email'])) {
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
      }
    }
    echo 'ok';
    exit;
  }

  echo 'Unhandled';
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Error';
}
