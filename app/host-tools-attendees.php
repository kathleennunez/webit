<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['host_id'] ?? '') === $user['id']));

$registrations = read_json('registrations.json');
$userMap = [];
$allUsers = read_json('users.json');
foreach ($allUsers as $entry) {
  $userMap[$entry['id']] = $entry;
}

$attendeeGroups = [];
$capacityMap = [];
foreach ($hostedWebinars as $webinar) {
  $webinarId = $webinar['id'];
  $attendees = array_values(array_filter($registrations, fn($r) => ($r['webinar_id'] ?? '') === $webinarId));
  $attendeeDetails = [];
  foreach ($attendees as $registration) {
    $attendee = $userMap[$registration['user_id']] ?? null;
    $attendeeDetails[] = [
      'registration' => $registration,
      'user' => $attendee
    ];
  }
  $attendeeGroups[$webinarId] = $attendeeDetails;
  $capacity = (int)($webinar['capacity'] ?? 0);
  $capacityMap[$webinarId] = [
    'total' => $capacity,
    'used' => count($attendees),
    'remaining' => $capacity > 0 ? max(0, $capacity - count($attendees)) : null
  ];
}

include __DIR__ . '/../pages/host-tools-attendees.html';
