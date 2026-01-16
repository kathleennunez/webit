<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();

$user = current_user();
$order = ($_GET['order'] ?? 'latest') === 'oldest' ? 'oldest' : 'latest';
$orderLabel = $order === 'latest' ? 'Latest to oldest' : 'Oldest to latest';
$orderParams = $_GET;
$orderParams['order'] = $order === 'latest' ? 'oldest' : 'latest';
$orderParams['page'] = 1;
$orderLink = ($_SERVER['PHP_SELF'] ?? '') . '?' . http_build_query($orderParams);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['user_id'] ?? '') === ($user['user_id'] ?? '')));
$publishedWebinars = array_values(array_filter($hostedWebinars, fn($w) => ($w['status'] ?? 'published') === 'published'));
$hostedWebinars = $publishedWebinars;
usort($hostedWebinars, function ($a, $b) use ($order) {
  $aTs = strtotime($a['created_at'] ?? '') ?: (strtotime($a['datetime'] ?? '') ?: 0);
  $bTs = strtotime($b['created_at'] ?? '') ?: (strtotime($b['datetime'] ?? '') ?: 0);
  $cmp = $aTs <=> $bTs;
  if ($cmp === 0) {
    $cmp = strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
  }
  return $order === 'latest' ? -$cmp : $cmp;
});
$totalPages = max(1, (int)ceil(count($hostedWebinars) / $perPage));
$page = min($page, $totalPages);
$pagedWebinars = array_slice($hostedWebinars, ($page - 1) * $perPage, $perPage);

$registrations = read_json('registrations.json');
$userMap = [];
$allUsers = read_json('users.json');
foreach ($allUsers as $entry) {
  $userId = $entry['user_id'] ?? '';
  if ($userId) {
    $userMap[$userId] = $entry;
  }
}

$attendeeGroups = [];
$capacityMap = [];
foreach ($hostedWebinars as $webinar) {
  $webinarId = $webinar['id'] ?? '';
  if (!$webinarId) {
    continue;
  }
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
