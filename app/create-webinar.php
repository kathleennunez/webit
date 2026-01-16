<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();

$user = current_user();
$hasSubscription = has_active_subscription($user['user_id'] ?? '');
$message = '';
$error = '';
$editing = false;
$backLink = previous_page_link('/app/dashboard.php');
$defaultFormData = [
  'title' => '',
  'description' => '',
  'date' => '',
  'start_time' => '',
  'end_time' => '',
  'category' => 'Education',
  'instructor' => full_name($user),
  'premium' => false,
  'price' => '',
  'capacity' => 0,
  'meeting_url' => '',
  'image' => '/assets/images/webinar-education.svg'
];
$formData = $defaultFormData;

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
    $webinarStart = strtotime($webinar['datetime'] ?? '');
    $webinarStatus = $webinar['status'] ?? 'published';
    if ($webinarStatus === 'published' && $webinarStart && $webinarStart <= time()) {
      redirect_to('/app/host-tools-published.php');
    }
    $editing = true;
    $formData['title'] = $webinar['title'] ?? '';
    $formData['description'] = $webinar['description'] ?? '';
    $formData['category'] = $webinar['category'] ?? 'Education';
    $formData['instructor'] = $webinar['instructor'] ?? full_name($user);
    $formData['premium'] = (bool)($webinar['premium'] ?? false);
    $formData['price'] = $webinar['price'] ?? '';
    $formData['capacity'] = $webinar['capacity'] ?? 0;
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
  $formData['title'] = trim($_POST['title'] ?? '');
  $formData['description'] = trim($_POST['description'] ?? '');
  $formData['date'] = $_POST['date'] ?? '';
  $formData['start_time'] = $_POST['start_time'] ?? '';
  $formData['end_time'] = $_POST['end_time'] ?? '';
  $formData['category'] = $_POST['category'] ?? 'Education';
  $formData['instructor'] = $_POST['instructor'] ?? full_name($user);
  $formData['premium'] = $wantsPremium && $hasSubscription;
  $formData['price'] = $_POST['price'] ?? '';
  $formData['capacity'] = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
  $formData['meeting_url'] = $_POST['meeting_url'] ?? '';
  if ($wantsPremium && !$hasSubscription) {
    $payload['premium'] = false;
    $payload['price'] = 0;
    if ($publishing) {
      $error = 'You need an active subscription to publish premium webinars.';
    }
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

  if (isset($_POST['capacity'])) {
    $payload['capacity'] = max(0, (int)$_POST['capacity']);
  }

  if (!empty($_FILES['image']['name'])) {
    if (!empty($_FILES['image']['error'])) {
      if (empty($error)) {
        $error = 'Unable to upload the cover image. Please try a smaller file.';
      }
    } else {
      $originalName = (string)($_FILES['image']['name'] ?? '');
      $baseName = pathinfo($originalName, PATHINFO_FILENAME);
      $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $baseName);
      $safeBase = trim((string)$safeBase, '-_');
      $safeBase = $safeBase !== '' ? $safeBase : 'cover';
      $filename = uniqid('webinar_', true) . '_' . $safeBase;
      if ($extension !== '') {
        $filename .= '.' . $extension;
      }
      $uploadDir = __DIR__ . '/../uploads/covers';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
      }
      $targetPath = $uploadDir . '/' . $filename;
      if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        $payload['image'] = '/uploads/covers/' . $filename;
        $formData['image'] = $payload['image'];
      } else {
        if (empty($error)) {
          $error = 'Unable to upload the cover image. Please try again.';
        }
      }
    }
  }

  if ($publishing && empty($error)) {
    $meetingUrl = trim($_POST['meeting_url'] ?? '');
    if ($meetingUrl === '') {
      $error = 'Meeting URL is required.';
    } elseif (!filter_var($meetingUrl, FILTER_VALIDATE_URL)) {
      $error = 'Meeting URL must be a valid link.';
    }
  }

  if (empty($error)) {
    if (!empty($_POST['webinar_id'])) {
      $existing = get_webinar($_POST['webinar_id']);
      $existingStart = $existing ? strtotime($existing['datetime'] ?? '') : false;
      $existingStatus = $existing['status'] ?? 'published';
      if ($existingStatus === 'published' && $existingStart && $existingStart <= time()) {
        $error = 'This webinar has already started and cannot be edited.';
      } else {
        $updated = update_webinar_fields($_POST['webinar_id'], $payload);
      }
      if (!empty($updated)) {
        $message = $payload['status'] === 'draft' ? 'Draft updated.' : 'Webinar updated.';
        $editing = true;
        $formData['title'] = $updated['title'] ?? '';
        $formData['description'] = $updated['description'] ?? '';
        $formData['category'] = $updated['category'] ?? 'Education';
        $formData['instructor'] = $updated['instructor'] ?? full_name($user);
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
      create_webinar($payload, $user['user_id'] ?? '');
      $message = $payload['status'] === 'draft' ? 'Draft saved.' : 'Webinar published.';
      $formData = $defaultFormData;
    }
  }
}

include __DIR__ . '/../pages/create-webinar.html';
