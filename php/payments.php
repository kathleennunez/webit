<?php
function log_payment(array $payload, string $userId): array {
  $payments = read_json('payments.json');
  $record = [
    'id' => uniqid('pay_', true),
    'user_id' => $userId,
    'webinar_id' => $payload['webinar_id'] ?? null,
    'amount' => $payload['amount'] ?? 0,
    'provider' => $payload['provider'] ?? 'paypal',
    'status' => $payload['status'] ?? 'captured',
    'capture_id' => $payload['capture_id'] ?? null,
    'refund_id' => $payload['refund_id'] ?? null,
    'refunded_at' => $payload['refunded_at'] ?? null,
    'created_at' => date('c')
  ];
  $payments[] = $record;
  write_json('payments.json', $payments);
  return $record;
}

function has_paid_for_webinar(string $userId, string $webinarId): bool {
  $payments = read_json('payments.json');
  foreach ($payments as $payment) {
    if (($payment['user_id'] ?? '') !== $userId) {
      continue;
    }
    if (($payment['webinar_id'] ?? '') !== $webinarId) {
      continue;
    }
    $status = $payment['status'] ?? 'captured';
    if (in_array($status, ['captured', 'paid', 'completed'], true)) {
      return true;
    }
  }
  return false;
}

function latest_payment_for_webinar(string $userId, string $webinarId): ?array {
  $payments = read_json('payments.json');
  $filtered = array_values(array_filter($payments, function ($payment) use ($userId, $webinarId) {
    return ($payment['user_id'] ?? '') === $userId && ($payment['webinar_id'] ?? '') === $webinarId;
  }));
  if (!$filtered) {
    return null;
  }
  usort($filtered, function ($a, $b) {
    return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
  });
  return $filtered[0] ?? null;
}

function update_payment_record(string $paymentId, array $updates): void {
  if ($paymentId === '') {
    return;
  }
  $payments = read_json('payments.json');
  foreach ($payments as &$payment) {
    if (($payment['id'] ?? '') !== $paymentId) {
      continue;
    }
    foreach ($updates as $key => $value) {
      $payment[$key] = $value;
    }
    break;
  }
  unset($payment);
  write_json('payments.json', $payments);
}
