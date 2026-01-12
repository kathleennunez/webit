<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$subscription = get_subscription($user['id']);
$paypalClientId = $appConfig['paypal_client_id'] ?? '';
$paymentNotice = !empty($_GET['paid']);

include __DIR__ . '/../pages/subscription.html';
