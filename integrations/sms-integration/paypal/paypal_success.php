<?php
require_once __DIR__ . '/../../../php/bootstrap.php';
require_once __DIR__ . '/../notifications/subscription_confirmation.php';

if (empty($_GET['user_id']) || empty($_GET['subscription_id'])) {
    http_response_code(400);
    echo 'Invalid PayPal response';
    exit;
}

$userId = trim((string)$_GET['user_id']);
$plan = $_GET['plan'] ?? 'monthly';
$user = get_user_by_id($userId);
if (!$user) {
    http_response_code(404);
    echo 'User not found';
    exit;
}

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

if (sms_opted_in($user)) {
    notifySubscriptionConfirmed($user['phone'], full_name($user));
}

echo 'Subscription successful!';
