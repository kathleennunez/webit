<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$userId = $user['user_id'] ?? '';
$webinarId = $_POST['webinar_id'] ?? '';
$redirect = $_POST['redirect'] ?? '/app/home.php';

if ($webinarId) {
  if (is_webinar_saved($userId, $webinarId)) {
    remove_saved_webinar($userId, $webinarId);
  } else {
    save_webinar_for_user($userId, $webinarId);
  }
}

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
if ($isAjax || strpos($acceptHeader, 'application/json') !== false) {
  $isSaved = $webinarId ? is_webinar_saved($userId, $webinarId) : false;
  json_response(['saved' => $isSaved]);
}

if (!str_starts_with($redirect, '/')) {
  $redirect = '/app/home.php';
}

redirect_to($redirect);
