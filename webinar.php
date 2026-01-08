<?php
require_once __DIR__ . '/php/bootstrap.php';
require_login();

$user = current_user();
$id = $_GET['id'] ?? '';
$webinar = get_webinar($id);
if (!$webinar) {
  $canceled = $id ? get_canceled_webinar($id) : null;
  if ($canceled) {
    $title = $canceled['title'] ?? '';
    redirect_to('/canceled.php?id=' . urlencode($id) . '&title=' . urlencode($title));
  }
  http_response_code(404);
  echo 'Webinar not found';
  exit;
}

$locked = false;
$message = '';
$error = '';
$hostUser = get_user_by_id($webinar['host_id'] ?? '');
$displayDatetime = format_datetime_for_user($webinar['datetime'] ?? '', $user['timezone'] ?? null);
$alreadyRegistered = user_is_registered($id, $user['id']);
$conflictWebinar = user_has_registration_conflict($id, $user['id']);
$isPremium = (bool)($webinar['premium'] ?? false);
$price = (float)($webinar['price'] ?? 0);
$hasPaid = $isPremium ? has_paid_for_webinar($user['id'], $id) : true;
$isPublished = ($webinar['status'] ?? 'published') === 'published';
$webinarTime = strtotime($webinar['datetime'] ?? '');
$isPast = $webinarTime !== false && $webinarTime < time();
$capacity = (int)($webinar['capacity'] ?? 0);
$capacityFull = $capacity > 0 && webinar_registration_count($id) >= $capacity;
$canRegister = !$locked && $hasPaid && !$alreadyRegistered && !$conflictWebinar && !$isPast && $isPublished && !$capacityFull;
$registerLabel = 'Register Now';
$registerHint = '';
if ($alreadyRegistered) {
  $registerLabel = 'Registered';
  $registerHint = 'You are already registered for this webinar.';
} elseif ($isPremium) {
  if ($hasPaid) {
    $registerLabel = 'Complete Registration';
    $registerHint = 'Payment received. Complete your registration to reserve your spot.';
  } else {
    $registerLabel = 'Pay with PayPal';
    $registerHint = $price > 0 ? 'This premium webinar costs $' . $price . '.' : 'This premium webinar requires payment.';
  }
} elseif ($capacityFull) {
  $registerLabel = 'At Capacity';
  $registerHint = 'This webinar has reached its capacity.';
} elseif ($isPast) {
  $registerLabel = 'Webinar Started';
  $registerHint = 'Registration is closed because the webinar has started.';
} elseif (!$isPublished) {
  $registerLabel = 'Unavailable';
  $registerHint = 'This webinar is currently unpublished.';
} elseif ($conflictWebinar) {
  $registerLabel = 'Time Conflict';
  $registerHint = 'This overlaps with your registration for "' . $conflictWebinar['title'] . '".';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && !$locked) {
  if (!$isPublished) {
    $error = 'This webinar is not published.';
  } elseif ($isPast) {
    $error = 'This webinar has already started.';
  } elseif ($isPremium && !$hasPaid) {
    $error = 'Premium webinars require payment to register.';
  } elseif ($alreadyRegistered) {
    $error = 'You are already registered for this webinar.';
  } elseif ($capacityFull) {
    $error = 'This webinar is at capacity.';
  } elseif ($conflictWebinar) {
    $error = 'This webinar conflicts with your registration for "' . $conflictWebinar['title'] . '".';
  } else {
    $registration = register_for_webinar($id, $user['id']);
    send_email($user['email'], 'Registration Confirmed', 'email_registration.html', $registration);
    notify_user($user['id'], 'Registration confirmed for: ' . $webinar['title'], 'registration', ['webinar_id' => $id]);
    if ($webinarTime) {
      $oneDay = date('c', strtotime('-1 day', $webinarTime));
      $oneHour = date('c', strtotime('-1 hour', $webinarTime));
      schedule_reminder($user['id'], 'Reminder: ' . $webinar['title'] . ' is tomorrow.', $oneDay, ['webinar_id' => $id]);
      schedule_reminder($user['id'], 'Reminder: ' . $webinar['title'] . ' starts in 1 hour.', $oneHour, ['webinar_id' => $id]);
    }
    $message = 'You are registered! A confirmation email has been queued.';
    $alreadyRegistered = true;
    $canRegister = false;
  }
}

include __DIR__ . '/pages/webinar.html';
