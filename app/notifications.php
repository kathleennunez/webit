<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$userId = $user['user_id'] ?? '';
$notifications = all_user_notifications($userId, 200);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
  mark_all_read($userId);
  $notifications = all_user_notifications($userId, 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
  delete_notifications_for_user($userId);
  $notifications = all_user_notifications($userId, 200);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
  $noteId = $_POST['notification_id'] ?? '';
  if ($noteId) {
    delete_notification_by_id($userId, $noteId);
  }
  $notifications = all_user_notifications($userId, 200);
}

include __DIR__ . '/../pages/notifications.html';
