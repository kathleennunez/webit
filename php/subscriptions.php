<?php
function get_subscription(string $userId): ?array {
  $subs = read_json('subscriptions.json');
  $latest = null;
  foreach ($subs as $sub) {
    if ($sub['user_id'] === $userId) {
      $latest = $sub;
      if (($sub['status'] ?? '') === 'active') {
        return $sub;
      }
    }
  }
  return $latest;
}

function create_subscription(string $userId, string $plan, string $source = 'paypal'): array {
  $subs = read_json('subscriptions.json');
  $planKey = strtolower(trim($plan));
  $renewalBase = $planKey === 'yearly' || $planKey === 'annual' ? '+1 year' : '+1 month';
  $new = [
    'id' => uniqid('sub_', true),
    'user_id' => $userId,
    'plan' => $plan,
    'status' => 'active',
    'created_at' => date('c'),
    'renewal_at' => date('c', strtotime($renewalBase)),
    'provider' => $source
  ];
  $subs[] = $new;
  write_json('subscriptions.json', $subs);
  return $new;
}

function has_active_subscription(string $userId): bool {
  $sub = get_subscription($userId);
  return $sub && $sub['status'] === 'active';
}
