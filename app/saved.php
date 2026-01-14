<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$entries = saved_entries_for_user($user['user_id'] ?? '');
usort($entries, function ($a, $b) {
  return strcmp($b['saved_at'] ?? '', $a['saved_at'] ?? '');
});

$savedWebinars = [];
foreach ($entries as $entry) {
  $webinar = get_webinar($entry['webinar_id'] ?? '');
  if ($webinar) {
    $savedWebinars[] = $webinar;
  }
}

include __DIR__ . '/../pages/saved.html';
