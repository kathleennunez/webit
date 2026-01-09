<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
send_feedback_prompts_for_user($user['id']);
$registrations = user_registrations($user['id']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $message = '';
}

$requestedMonth = $_GET['month'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $requestedMonth)) {
  $monthStart = DateTime::createFromFormat('Y-m-d', $requestedMonth . '-01');
  if ($monthStart === false) {
    $monthStart = new DateTime('first day of this month');
  }
} else {
  $monthStart = new DateTime('first day of this month');
}
$monthStart->setTime(0, 0, 0);
$monthEnd = (clone $monthStart)->modify('last day of this month');
$monthLabel = $monthStart->format('F Y');
$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');
$eventMap = [];
foreach ($registrations as $reg) {
  $webinar = get_webinar($reg['webinar_id']);
  if (!$webinar) {
    continue;
  }
  if (($webinar['status'] ?? 'published') !== 'published') {
    continue;
  }
  $key = date_key_for_user($webinar['datetime'] ?? '', $user['timezone'] ?? null);
  if (!$key) {
    continue;
  }
  $event = $webinar;
  $event['display_time'] = format_time_for_user($webinar['datetime'] ?? '', $user['timezone'] ?? null);
  $eventMap[$key][] = $event;
}
$calendarWeeks = [];
$cursor = clone $monthStart;
$cursor->modify('last sunday');
while (true) {
  $week = [];
  for ($i = 0; $i < 7; $i++) {
    $dateKey = $cursor->format('Y-m-d');
    $week[] = [
      'date' => clone $cursor,
      'in_month' => $cursor->format('m') === $monthStart->format('m'),
      'events' => $eventMap[$dateKey] ?? []
    ];
    $cursor->modify('+1 day');
  }
  $calendarWeeks[] = $week;
  if ($cursor > $monthEnd && $cursor->format('w') === '0') {
    break;
  }
}

$webinars = array_values(array_filter(all_webinars(), fn($w) => ($w['host_id'] ?? '') === $user['id']));

include __DIR__ . '/../pages/dashboard.html';
