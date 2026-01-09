<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$message = '';

$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['host_id'] ?? '') === $user['id']));
$publishedWebinars = array_values(array_filter($hostedWebinars, fn($w) => ($w['status'] ?? 'published') === 'published'));

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
    $isPast = false;
    if ($webinar && !empty($webinar['datetime'])) {
      $startTs = strtotime($webinar['datetime']);
      $durationMinutes = parse_duration_minutes($webinar['duration'] ?? '60 min');
      $endTs = $startTs ? $startTs + ($durationMinutes * 60) : false;
      $isPast = $endTs !== false && $endTs < time();
    }
    if ($id) {
      add_canceled_webinar($id, $title);
    }
    $registrations = read_json('registrations.json');
    $removedRegistrations = array_filter($registrations, fn($r) => $r['webinar_id'] === $id);
    if (!$isPast) {
      foreach ($removedRegistrations as $registration) {
        notify_user($registration['user_id'], 'Event canceled: ' . $title, 'webinar', [
          'webinar_id' => $id,
          'status' => 'canceled',
          'title' => $title
        ]);
      }
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
    $hostedWebinars = array_values(array_filter($webinars, fn($w) => ($w['host_id'] ?? '') === $user['id']));
    $publishedWebinars = array_values(array_filter($hostedWebinars, fn($w) => ($w['status'] ?? 'published') === 'published'));
    $message = 'Webinar deleted.';
  }
}

include __DIR__ . '/../pages/host-tools-published.html';
