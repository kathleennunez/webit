<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$includeIntlTelInput = true;

$user = current_user();
$backLink = previous_page_link('/app/dashboard.php');
$userId = $user['user_id'] ?? '';
$freshUser = $userId ? get_user_by_id($userId) : null;
if ($freshUser) {
  $_SESSION['user'] = $freshUser;
  $user = $freshUser;
}
$profileUser = $user;
$isSelf = true;
$message = '';
$timezones = get_timezones();
if (!$timezones) {
  $timezones = ['UTC'];
}

if (!empty($_GET['user_id'])) {
  $profile = get_user_by_id($_GET['user_id']);
  if ($profile) {
    $profileUser = $profile;
    $isSelf = ($profileUser['user_id'] ?? '') === ($user['user_id'] ?? '');
  }
}

if (!$userId || !get_user_by_id($userId)) {
  logout_user();
  redirect_to('/app/login.php');
}

if ($isSelf && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $payload = $_POST;
  if (!empty($_FILES['avatar']['name'])) {
    $filename = uniqid('avatar_', true) . '_' . basename($_FILES['avatar']['name']);
    $uploadDir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0775, true);
    }
    $targetPath = $uploadDir . '/' . $filename;
    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
      $payload['avatar'] = '/uploads/avatars/' . $filename;
    }
  }
  $profileUser = update_user_profile($userId, $payload);
  $message = 'Account updated.';
}

include __DIR__ . '/../pages/account.html';
