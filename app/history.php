<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$registrations = user_registrations($user['user_id'] ?? '');
$now = time();
$history = [];
foreach ($registrations as $registration) {
  $webinar = get_webinar($registration['webinar_id'] ?? '');
  if (!$webinar) {
    continue;
  }
  $ts = strtotime($webinar['datetime'] ?? '');
  if ($ts === false || $ts >= $now) {
    continue;
  }
  $history[] = $webinar;
}

include __DIR__ . '/../pages/history.html';
