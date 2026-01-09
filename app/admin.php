<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_role('admin');

$user = current_user();
$message = '';
$messageTone = 'success';

function admin_format_date(?string $datetime): string {
  if (!$datetime) {
    return '—';
  }
  $ts = strtotime($datetime);
  if ($ts === false) {
    return $datetime;
  }
  return date('M j, Y', $ts);
}

function admin_format_currency(float $amount): string {
  return '$' . number_format($amount, 2);
}

function admin_truncate(string $value, int $limit = 80): string {
  $value = trim($value);
  if (strlen($value) <= $limit) {
    return $value;
  }
  return substr($value, 0, $limit - 3) . '...';
}

$users = read_json('users.json');
$webinars = all_webinars();
$registrations = read_json('registrations.json');
$payments = read_json('payments.json');
$subscriptions = read_json('subscriptions.json');
$feedback = read_json('feedback.json');
$waitlist = read_json('waitlist.json');
$canceled = read_json('canceled.json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'broadcast') {
    $note = trim($_POST['broadcast_message'] ?? '');
    $category = trim($_POST['broadcast_category'] ?? 'general');
    if ($note === '') {
      $message = 'Please add a broadcast message before sending.';
      $messageTone = 'warning';
    } else {
      $count = 0;
      foreach ($users as $entry) {
        if (!empty($entry['id'])) {
          notify_user($entry['id'], $note, $category);
          $count++;
        }
      }
      $message = 'Broadcast sent to ' . $count . ' users.';
    }
  }
  if ($action === 'update-role') {
    $targetId = $_POST['user_id'] ?? '';
    $role = $_POST['role'] ?? 'member';
    $allowed = ['admin', 'host', 'member'];
    if (!$targetId || !in_array($role, $allowed, true)) {
      $message = 'Select a user and valid role.';
      $messageTone = 'warning';
    } else {
      foreach ($users as &$entry) {
        if (($entry['id'] ?? '') === $targetId) {
          $entry['role'] = $role;
          break;
        }
      }
      unset($entry);
      write_json('users.json', $users);
      if (($user['id'] ?? '') === $targetId) {
        $_SESSION['user']['role'] = $role;
      }
      $message = 'User role updated.';
    }
  }
  if ($action === 'update-webinar') {
    $webinarId = $_POST['webinar_id'] ?? '';
    $status = $_POST['status'] ?? 'published';
    $allowed = ['published', 'draft', 'canceled'];
    if (!$webinarId || !in_array($status, $allowed, true)) {
      $message = 'Select a webinar and valid status.';
      $messageTone = 'warning';
    } else {
      update_webinar_fields($webinarId, ['status' => $status]);
      if ($status === 'canceled') {
        $webinar = get_webinar($webinarId);
        add_canceled_webinar($webinarId, $webinar['title'] ?? 'Webinar');
      }
      $message = 'Webinar status updated.';
    }
  }

  $users = read_json('users.json');
  $webinars = all_webinars();
  $registrations = read_json('registrations.json');
  $payments = read_json('payments.json');
  $subscriptions = read_json('subscriptions.json');
  $feedback = read_json('feedback.json');
  $waitlist = read_json('waitlist.json');
  $canceled = read_json('canceled.json');
}

$usersById = [];
foreach ($users as $entry) {
  if (!empty($entry['id'])) {
    $usersById[$entry['id']] = $entry;
  }
}

$webinarsById = [];
foreach ($webinars as $webinar) {
  if (!empty($webinar['id'])) {
    $webinarsById[$webinar['id']] = $webinar;
  }
}

$registrationCounts = [];
foreach ($registrations as $registration) {
  $id = $registration['webinar_id'] ?? '';
  if (!$id) {
    continue;
  }
  $registrationCounts[$id] = ($registrationCounts[$id] ?? 0) + 1;
}

$waitlistCounts = [];
foreach ($waitlist as $entry) {
  $id = $entry['webinar_id'] ?? '';
  if (!$id) {
    continue;
  }
  $waitlistCounts[$id] = ($waitlistCounts[$id] ?? 0) + 1;
}

$activeWebinars = array_values(array_filter($webinars, fn($w) => ($w['status'] ?? 'published') === 'published'));
$totalUsers = count($users);
$totalRevenue = 0.0;
$paidCount = 0;
$failedCount = 0;
foreach ($payments as $payment) {
  $status = strtolower($payment['status'] ?? 'captured');
  $amount = (float)($payment['amount'] ?? 0);
  if (in_array($status, ['captured', 'paid', 'succeeded'], true)) {
    $totalRevenue += $amount;
    $paidCount++;
  } else {
    $failedCount++;
  }
}

$ratingTotal = 0;
foreach ($feedback as $entry) {
  $ratingTotal += (int)($entry['rating'] ?? 0);
}
$ratingAvg = $feedback ? $ratingTotal / count($feedback) : 0;

$activeSubscriptions = array_values(array_filter($subscriptions, fn($s) => ($s['status'] ?? '') === 'active'));

usort($users, function ($a, $b) {
  return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});
$recentUsers = array_slice($users, 0, 6);

usort($payments, function ($a, $b) {
  return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});
$recentPayments = array_slice($payments, 0, 6);

usort($feedback, function ($a, $b) {
  return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});
$recentFeedback = array_slice($feedback, 0, 5);

$webinarMetrics = [];
foreach ($webinars as $webinar) {
  $id = $webinar['id'] ?? '';
  if (!$id) {
    continue;
  }
  $webinarMetrics[] = [
    'webinar' => $webinar,
    'registrations' => $registrationCounts[$id] ?? 0,
    'waitlist' => $waitlistCounts[$id] ?? 0
  ];
}
usort($webinarMetrics, function ($a, $b) {
  if ($a['registrations'] === $b['registrations']) {
    return (int)($b['webinar']['popularity'] ?? 0) <=> (int)($a['webinar']['popularity'] ?? 0);
  }
  return $b['registrations'] <=> $a['registrations'];
});
$topWebinars = array_slice($webinarMetrics, 0, 6);

$waitlistPressure = [];
foreach ($waitlistCounts as $webinarId => $count) {
  $waitlistPressure[] = [
    'webinar' => $webinarsById[$webinarId] ?? ['title' => 'Webinar'],
    'count' => $count
  ];
}
usort($waitlistPressure, fn($a, $b) => $b['count'] <=> $a['count']);
$waitlistPressure = array_slice($waitlistPressure, 0, 5);

include __DIR__ . '/../pages/admin.html';
