<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$type = $_GET['type'] ?? '';
$webinarIdFilter = $_GET['webinar_id'] ?? '';

$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['user_id'] ?? '') === ($user['user_id'] ?? '')));
$webinarMap = [];
foreach ($hostedWebinars as $webinar) {
  $webinarId = $webinar['id'] ?? '';
  if ($webinarId) {
    $webinarMap[$webinarId] = $webinar;
  }
}

if (!$type || !in_array($type, ['attendees', 'revenue', 'webinars', 'waitlist'], true)) {
  http_response_code(400);
  echo 'Invalid export type';
  exit;
}

if ($webinarIdFilter && !isset($webinarMap[$webinarIdFilter])) {
  http_response_code(404);
  echo 'Webinar not found';
  exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="webit-' . $type . '.csv"');

$output = fopen('php://output', 'w');
if ($output === false) {
  exit;
}

if ($type === 'attendees') {
  fputcsv($output, ['webinar_id', 'webinar_title', 'attendee_name', 'attendee_email', 'registered_at']);
  $registrations = read_json('registrations.json');
  $users = read_json('users.json');
  $userMap = [];
  foreach ($users as $entry) {
    $userId = $entry['user_id'] ?? '';
    if ($userId) {
      $userMap[$userId] = $entry;
    }
  }
  foreach ($registrations as $registration) {
    $webinarId = $registration['webinar_id'] ?? '';
    if (!isset($webinarMap[$webinarId])) {
      continue;
    }
    if ($webinarIdFilter && $webinarId !== $webinarIdFilter) {
      continue;
    }
    $attendee = $userMap[$registration['user_id']] ?? [];
    fputcsv($output, [
      $webinarId,
      $webinarMap[$webinarId]['title'] ?? '',
      full_name($attendee),
      $attendee['email'] ?? '',
      $registration['registered_at'] ?? ''
    ]);
  }
}

if ($type === 'waitlist') {
  fputcsv($output, ['webinar_id', 'webinar_title', 'attendee_name', 'attendee_email', 'waitlisted_at']);
  $waitlist = read_json('waitlist.json');
  $users = read_json('users.json');
  $userMap = [];
  foreach ($users as $entry) {
    $userId = $entry['user_id'] ?? '';
    if ($userId) {
      $userMap[$userId] = $entry;
    }
  }
  foreach ($waitlist as $entry) {
    $webinarId = $entry['webinar_id'] ?? '';
    if (!isset($webinarMap[$webinarId])) {
      continue;
    }
    if ($webinarIdFilter && $webinarId !== $webinarIdFilter) {
      continue;
    }
    $attendee = $userMap[$entry['user_id']] ?? [];
    fputcsv($output, [
      $webinarId,
      $webinarMap[$webinarId]['title'] ?? '',
      full_name($attendee),
      $attendee['email'] ?? '',
      $entry['created_at'] ?? ''
    ]);
  }
}

if ($type === 'revenue') {
  fputcsv($output, ['webinar_id', 'webinar_title', 'payments_count', 'total_revenue']);
  $payments = read_json('payments.json');
  $totals = [];
  foreach ($payments as $payment) {
    $webinarId = $payment['webinar_id'] ?? '';
    if (!isset($webinarMap[$webinarId])) {
      continue;
    }
    if ($webinarIdFilter && $webinarId !== $webinarIdFilter) {
      continue;
    }
    $amount = (float)($payment['amount'] ?? 0);
    if (!isset($totals[$webinarId])) {
      $totals[$webinarId] = ['count' => 0, 'sum' => 0.0];
    }
    $totals[$webinarId]['count'] += 1;
    $totals[$webinarId]['sum'] += $amount;
  }
  foreach ($webinarMap as $webinarId => $webinar) {
    if ($webinarIdFilter && $webinarId !== $webinarIdFilter) {
      continue;
    }
    $stats = $totals[$webinarId] ?? ['count' => 0, 'sum' => 0.0];
    fputcsv($output, [
      $webinarId,
      $webinar['title'] ?? '',
      $stats['count'],
      number_format($stats['sum'], 2, '.', '')
    ]);
  }
}

if ($type === 'webinars') {
  fputcsv($output, ['webinar_id', 'title', 'datetime', 'status', 'premium', 'price', 'capacity', 'registrations']);
  $registrations = read_json('registrations.json');
  $counts = [];
  foreach ($registrations as $registration) {
    $webinarId = $registration['webinar_id'] ?? '';
    if (!isset($webinarMap[$webinarId])) {
      continue;
    }
    if ($webinarIdFilter && $webinarId !== $webinarIdFilter) {
      continue;
    }
    $counts[$webinarId] = ($counts[$webinarId] ?? 0) + 1;
  }
  foreach ($webinarMap as $webinarId => $webinar) {
    if ($webinarIdFilter && $webinarId !== $webinarIdFilter) {
      continue;
    }
    fputcsv($output, [
      $webinarId,
      $webinar['title'] ?? '',
      $webinar['datetime'] ?? '',
      $webinar['status'] ?? 'published',
      !empty($webinar['premium']) ? 'yes' : 'no',
      $webinar['price'] ?? 0,
      $webinar['capacity'] ?? 0,
      $counts[$webinarId] ?? 0
    ]);
  }
}

fclose($output);
exit;
