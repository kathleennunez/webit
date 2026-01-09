<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$webinarId = $_POST['webinar_id'] ?? '';
$redirect = $_POST['redirect'] ?? '/app/home.php';

if ($webinarId) {
  if (is_webinar_saved($user['id'], $webinarId)) {
    remove_saved_webinar($user['id'], $webinarId);
  } else {
    save_webinar_for_user($user['id'], $webinarId);
  }
}

if (!str_starts_with($redirect, '/')) {
  $redirect = '/app/home.php';
}

redirect_to($redirect);
