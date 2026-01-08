<?php
require_once __DIR__ . '/php/bootstrap.php';
require_login();

$user = current_user();
$notifications = array_values(array_filter(user_notifications($user['id'], 200), function ($note) {
  return !($note['payload']['read'] ?? false);
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
  mark_all_read($user['id']);
  $notifications = array_values(array_filter(user_notifications($user['id'], 200), function ($note) {
    return !($note['payload']['read'] ?? false);
  }));
}

include __DIR__ . '/pages/notifications.html';
