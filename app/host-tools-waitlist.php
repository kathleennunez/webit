<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['user_id'] ?? '') === ($user['user_id'] ?? '')));

$capacityMap = [];
foreach ($hostedWebinars as $webinar) {
  $webinarId = $webinar['id'] ?? '';
  if (!$webinarId) {
    continue;
  }
  $capacity = (int)($webinar['capacity'] ?? 0);
  $capacityMap[$webinarId] = [
    'total' => $capacity,
    'remaining' => $capacity > 0 ? max(0, $capacity - webinar_registration_count($webinarId)) : null
  ];
}

$allUsers = read_json('users.json');
$userMap = [];
foreach ($allUsers as $entry) {
  $userId = $entry['user_id'] ?? '';
  if ($userId) {
    $userMap[$userId] = $entry;
  }
}

$waitlist = waitlist_entries();
$waitlistByWebinar = [];
foreach ($waitlist as $entry) {
  $webinarId = $entry['webinar_id'] ?? '';
  if (!$webinarId) {
    continue;
  }
  $waitlistByWebinar[$webinarId][] = $entry;
}

include __DIR__ . '/../pages/host-tools-waitlist.html';
