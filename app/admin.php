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

function admin_filter_month(array $items, string $dateKey, string $month): array {
  if (!$month) {
    return $items;
  }
  return array_values(array_filter($items, function ($item) use ($dateKey, $month) {
    $value = $item[$dateKey] ?? '';
    if (!$value) {
      return false;
    }
    $ts = strtotime($value);
    if ($ts === false) {
      return false;
    }
    return date('Y-m', $ts) === $month;
  }));
}

function admin_paginate(array $items, int $page, int $perPage): array {
  $total = count($items);
  $totalPages = max(1, (int)ceil($total / $perPage));
  $page = max(1, min($page, $totalPages));
  $offset = ($page - 1) * $perPage;
  return [
    'items' => array_slice($items, $offset, $perPage),
    'page' => $page,
    'total_pages' => $totalPages,
    'total' => $total
  ];
}

function admin_query_link(array $overrides): string {
  $params = $_GET;
  foreach ($overrides as $key => $value) {
    if ($value === null) {
      unset($params[$key]);
    } else {
      $params[$key] = $value;
    }
  }
  $query = http_build_query($params);
  return $query ? ('?' . $query) : '';
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
    $allowed = ['admin', 'member'];
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

$monthFilter = '';
if (!empty($_GET['month']) && preg_match('/^\\d{4}-\\d{2}$/', $_GET['month'])) {
  $monthFilter = $_GET['month'];
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

$filteredUsers = admin_filter_month($users, 'created_at', $monthFilter);
$filteredWebinars = admin_filter_month($webinars, 'datetime', $monthFilter);
$filteredRegistrations = admin_filter_month($registrations, 'registered_at', $monthFilter);
$filteredPayments = admin_filter_month($payments, 'created_at', $monthFilter);
$filteredFeedback = admin_filter_month($feedback, 'created_at', $monthFilter);
$filteredWaitlist = admin_filter_month($waitlist, 'created_at', $monthFilter);
$filteredCanceled = admin_filter_month($canceled, 'canceled_at', $monthFilter);

$registrationCounts = [];
foreach ($filteredRegistrations as $registration) {
  $id = $registration['webinar_id'] ?? '';
  if (!$id) {
    continue;
  }
  $registrationCounts[$id] = ($registrationCounts[$id] ?? 0) + 1;
}

$waitlistCounts = [];
foreach ($filteredWaitlist as $entry) {
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

usort($filteredUsers, function ($a, $b) {
  return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});
$usersPage = admin_paginate($filteredUsers, (int)($_GET['page_users'] ?? 1), 10);

usort($filteredPayments, function ($a, $b) {
  return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});
$paymentsPage = admin_paginate($filteredPayments, (int)($_GET['page_payments'] ?? 1), 10);

usort($filteredFeedback, function ($a, $b) {
  return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});
$feedbackPage = admin_paginate($filteredFeedback, (int)($_GET['page_feedback'] ?? 1), 8);

$webinarMetrics = [];
foreach ($filteredWebinars as $webinar) {
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
$webinarsPage = admin_paginate($webinarMetrics, (int)($_GET['page_webinars'] ?? 1), 10);

$waitlistPressure = [];
foreach ($waitlistCounts as $webinarId => $count) {
  $waitlistPressure[] = [
    'webinar' => $webinarsById[$webinarId] ?? ['title' => 'Webinar'],
    'count' => $count
  ];
}
usort($waitlistPressure, fn($a, $b) => $b['count'] <=> $a['count']);
$waitlistPage = admin_paginate($waitlistPressure, (int)($_GET['page_waitlist'] ?? 1), 10);

usort($filteredRegistrations, function ($a, $b) {
  return strtotime($b['registered_at'] ?? '') <=> strtotime($a['registered_at'] ?? '');
});
$registrationsPage = admin_paginate($filteredRegistrations, (int)($_GET['page_attendees'] ?? 1), 10);

usort($filteredCanceled, function ($a, $b) {
  return strtotime($b['canceled_at'] ?? '') <=> strtotime($a['canceled_at'] ?? '');
});
$canceledPage = admin_paginate($filteredCanceled, (int)($_GET['page_canceled'] ?? 1), 8);

$export = $_GET['export'] ?? '';
if ($export) {
  $filename = 'admin-export-' . $export . ($monthFilter ? ('-' . $monthFilter) : '') . '.csv';
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $output = fopen('php://output', 'w');
  if ($export === 'users') {
    fputcsv($output, ['id', 'first_name', 'last_name', 'email', 'role', 'created_at']);
    foreach ($filteredUsers as $entry) {
      fputcsv($output, [
        $entry['id'] ?? '',
        $entry['first_name'] ?? '',
        $entry['last_name'] ?? '',
        $entry['email'] ?? '',
        $entry['role'] ?? '',
        $entry['created_at'] ?? ''
      ]);
    }
  } elseif ($export === 'webinars') {
    fputcsv($output, ['id', 'title', 'datetime', 'status', 'premium', 'price', 'capacity', 'registrations', 'waitlist']);
    foreach ($webinarMetrics as $entry) {
      $webinar = $entry['webinar'] ?? [];
      fputcsv($output, [
        $webinar['id'] ?? '',
        $webinar['title'] ?? '',
        $webinar['datetime'] ?? '',
        $webinar['status'] ?? '',
        !empty($webinar['premium']) ? 'yes' : 'no',
        $webinar['price'] ?? 0,
        $webinar['capacity'] ?? 0,
        $entry['registrations'] ?? 0,
        $entry['waitlist'] ?? 0
      ]);
    }
  } elseif ($export === 'payments') {
    fputcsv($output, ['id', 'user_id', 'webinar_id', 'amount', 'provider', 'status', 'created_at']);
    foreach ($filteredPayments as $payment) {
      fputcsv($output, [
        $payment['id'] ?? '',
        $payment['user_id'] ?? '',
        $payment['webinar_id'] ?? '',
        $payment['amount'] ?? 0,
        $payment['provider'] ?? '',
        $payment['status'] ?? '',
        $payment['created_at'] ?? ''
      ]);
    }
  } elseif ($export === 'feedback') {
    fputcsv($output, ['id', 'user_id', 'webinar_id', 'rating', 'content', 'created_at']);
    foreach ($filteredFeedback as $entry) {
      fputcsv($output, [
        $entry['id'] ?? '',
        $entry['user_id'] ?? '',
        $entry['webinar_id'] ?? '',
        $entry['rating'] ?? 0,
        $entry['content'] ?? '',
        $entry['created_at'] ?? ''
      ]);
    }
  } elseif ($export === 'waitlist') {
    fputcsv($output, ['id', 'webinar_id', 'user_id', 'created_at']);
    foreach ($filteredWaitlist as $entry) {
      fputcsv($output, [
        $entry['id'] ?? '',
        $entry['webinar_id'] ?? '',
        $entry['user_id'] ?? '',
        $entry['created_at'] ?? ''
      ]);
    }
  } elseif ($export === 'attendees') {
    fputcsv($output, ['id', 'webinar_id', 'user_id', 'registered_at', 'status']);
    foreach ($filteredRegistrations as $entry) {
      fputcsv($output, [
        $entry['id'] ?? '',
        $entry['webinar_id'] ?? '',
        $entry['user_id'] ?? '',
        $entry['registered_at'] ?? '',
        $entry['status'] ?? ''
      ]);
    }
  } elseif ($export === 'canceled') {
    fputcsv($output, ['id', 'title', 'canceled_at']);
    foreach ($filteredCanceled as $entry) {
      fputcsv($output, [
        $entry['id'] ?? '',
        $entry['title'] ?? '',
        $entry['canceled_at'] ?? ''
      ]);
    }
  }
  fclose($output);
  exit;
}

include __DIR__ . '/../pages/admin.html';
