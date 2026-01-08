<?php
require_once __DIR__ . '/php/bootstrap.php';
require_login();

$user = current_user();
$hasSubscription = has_active_subscription($user['id']);
$message = '';
$error = '';
$editing = false;
$formData = [
  'title' => '',
  'description' => '',
  'date' => '',
  'start_time' => '',
  'end_time' => '',
  'category' => 'Education',
  'instructor' => $user['name'],
  'premium' => false,
  'price' => '',
  'meeting_url' => '',
  'image' => '/assets/images/webinar-education.svg'
];

function safe_timezone(string $tz): DateTimeZone {
  try {
    return new DateTimeZone($tz);
  } catch (Exception $e) {
    return new DateTimeZone('UTC');
  }
}

if (!empty($_GET['edit'])) {
  $webinar = get_webinar($_GET['edit']);
  if ($webinar) {
    $editing = true;
    $formData['title'] = $webinar['title'] ?? '';
    $formData['description'] = $webinar['description'] ?? '';
    $formData['category'] = $webinar['category'] ?? 'Education';
    $formData['instructor'] = $webinar['instructor'] ?? $user['name'];
    $formData['premium'] = (bool)($webinar['premium'] ?? false);
    $formData['price'] = $webinar['price'] ?? '';
    $formData['meeting_url'] = $webinar['meeting_url'] ?? '';
    $formData['image'] = $webinar['image'] ?? '/assets/images/webinar-education.svg';
    $userTz = safe_timezone($user['timezone'] ?? 'UTC');
    try {
      $dt = new DateTime($webinar['datetime'] ?? '', new DateTimeZone('UTC'));
      $dt->setTimezone($userTz);
      $formData['date'] = $dt->format('Y-m-d');
      $formData['start_time'] = $dt->format('H:i');
      $durationMinutes = 60;
      if (!empty($webinar['duration']) && preg_match('/(\\d+)/', $webinar['duration'], $matches)) {
        $durationMinutes = (int)$matches[1];
      }
      $endDt = clone $dt;
      $endDt->modify('+' . $durationMinutes . ' minutes');
      $formData['end_time'] = $endDt->format('H:i');
    } catch (Exception $e) {
      $formData['date'] = '';
      $formData['start_time'] = '';
      $formData['end_time'] = '';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_webinar'])) {
  $payload = $_POST;
  $publishing = !isset($_POST['save_draft']);
  $payload['status'] = $publishing ? 'published' : 'draft';
  $wantsPremium = isset($_POST['premium']);
  if ($wantsPremium && !$hasSubscription) {
    $payload['premium'] = false;
    $payload['price'] = 0;
    $error = 'You need an active subscription to publish premium webinars.';
  } else {
    $payload['price'] = $wantsPremium ? (float)($_POST['price'] ?? 0) : 0;
  }

  if ($publishing) {
    if (empty($_POST['date']) || empty($_POST['start_time']) || empty($_POST['end_time'])) {
      $error = 'Please select a date, start time, and end time before publishing.';
    }
  }

  if (!empty($_POST['date']) && !empty($_POST['start_time']) && !empty($_POST['end_time'])) {
    $userTz = safe_timezone($user['timezone'] ?? 'UTC');
    try {
      $dt = new DateTime($_POST['date'] . ' ' . $_POST['start_time'], $userTz);
      $dt->setTimezone(new DateTimeZone('UTC'));
      $payload['datetime'] = $dt->format('c');
      $start = new DateTime($_POST['date'] . ' ' . $_POST['start_time'], $userTz);
      $end = new DateTime($_POST['date'] . ' ' . $_POST['end_time'], $userTz);
      if ($end <= $start) {
        $end->modify('+1 hour');
      }
      $interval = $start->diff($end);
      $minutes = ($interval->h * 60) + $interval->i;
      $payload['duration'] = $minutes . ' min';
    } catch (Exception $e) {
      $payload['datetime'] = $_POST['date'] . ' ' . $_POST['start_time'];
    }
  }

  if ($publishing && empty($error)) {
    $startUtc = strtotime($payload['datetime'] ?? '');
    if (!$startUtc || $startUtc <= time()) {
      $error = 'The webinar start time must be in the future.';
    }
  }

  if (!empty($_FILES['image']['name'])) {
    $filename = uniqid('webinar_', true) . '_' . basename($_FILES['image']['name']);
    $targetPath = __DIR__ . '/uploads/covers/' . $filename;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
      $payload['image'] = '/uploads/covers/' . $filename;
    }
  }

  if (empty($error)) {
    if (!empty($_POST['webinar_id'])) {
      $updated = update_webinar_fields($_POST['webinar_id'], $payload);
      $message = $payload['status'] === 'draft' ? 'Draft updated.' : 'Webinar updated.';
      if ($updated) {
        $editing = true;
        $formData['title'] = $updated['title'] ?? '';
        $formData['description'] = $updated['description'] ?? '';
        $formData['category'] = $updated['category'] ?? 'Education';
        $formData['instructor'] = $updated['instructor'] ?? $user['name'];
        $formData['premium'] = (bool)($updated['premium'] ?? false);
        $formData['price'] = $updated['price'] ?? '';
        $formData['meeting_url'] = $updated['meeting_url'] ?? '';
        $formData['image'] = $updated['image'] ?? '/assets/images/webinar-education.svg';
        $timestamp = strtotime($updated['datetime'] ?? '');
        if ($timestamp) {
          $formData['date'] = date('Y-m-d', $timestamp);
          $formData['time'] = date('H:i', $timestamp);
        }
      }
    } else {
      create_webinar($payload, $user['id']);
      $message = $payload['status'] === 'draft' ? 'Draft saved.' : 'Webinar published.';
    }
  }
}

include __DIR__ . '/pages/create-webinar.html';
