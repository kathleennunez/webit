<?php
require_once __DIR__ . '/php/bootstrap.php';
require_login();

$user = current_user();
$registrations = user_registrations($user['id']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['unpublish_webinar'])) {
    $id = $_POST['webinar_id'] ?? '';
    update_webinar_fields($id, ['status' => 'draft']);
    $message = 'Webinar moved to drafts.';
  }
  if (isset($_POST['delete_webinar'])) {
    $id = $_POST['webinar_id'] ?? '';
    $webinar = get_webinar($id);
    $title = $webinar['title'] ?? 'Webinar';
    if ($id) {
      add_canceled_webinar($id, $title);
    }
    $registrations = read_json('registrations.json');
    $removedRegistrations = array_filter($registrations, fn($r) => $r['webinar_id'] === $id);
    foreach ($removedRegistrations as $registration) {
      notify_user($registration['user_id'], 'Event canceled: ' . $title, 'webinar', [
        'webinar_id' => $id,
        'status' => 'canceled',
        'title' => $title
      ]);
    }
    $registrations = array_values(array_filter($registrations, fn($r) => $r['webinar_id'] !== $id));
    write_json('registrations.json', $registrations);
    $attendance = read_json('attendance.json');
    $attendance = array_values(array_filter($attendance, fn($a) => $a['webinar_id'] !== $id));
    write_json('attendance.json', $attendance);
    $payments = read_json('payments.json');
    $payments = array_values(array_filter($payments, fn($p) => $p['webinar_id'] !== $id));
    write_json('payments.json', $payments);
    $webinars = all_webinars();
    $webinars = array_values(array_filter($webinars, fn($w) => $w['id'] !== $id));
    write_json('webinars.json', $webinars);
    $message = 'Webinar deleted.';
  }
}

$monthStart = new DateTime('first day of this month');
$monthEnd = new DateTime('last day of this month');
$monthLabel = $monthStart->format('F Y');
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
$publishedWebinars = array_values(array_filter($webinars, fn($w) => ($w['status'] ?? 'published') === 'published'));

include __DIR__ . '/pages/dashboard.html';
