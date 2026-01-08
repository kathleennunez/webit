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
