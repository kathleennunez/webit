<?php
require_once __DIR__ . '/../../../php/bootstrap.php';
require_login();

$user = current_user();
$payload = json_decode(file_get_contents('php://input'), true);
$plan = $payload['plan'] ?? 'monthly';

$subscription = create_subscription($user['id'], $plan, 'paypal-sandbox');
send_email($user['email'], 'Subscription Activated', 'email_subscription.html', $subscription);
notify_user(
  $user['id'],
  'Subscription payment received for the ' . $plan . ' plan.',
  'subscription',
  ['plan' => $plan]
);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'subscription' => $subscription]);
