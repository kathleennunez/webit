<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$smsNotificationFiles = [
  BASE_PATH . '/integrations/sms-integration/notifications/registration_confirmation.php',
  BASE_PATH . '/integrations/sms-integration/notifications/waitlist.php'
];
foreach ($smsNotificationFiles as $smsFile) {
  if (file_exists($smsFile)) {
    require_once $smsFile;
  }
}
$paypalClientPath = BASE_PATH . '/integrations/paypal-integration/paypal-client.php';
if (file_exists($paypalClientPath)) {
  require_once $paypalClientPath;
}

$user = current_user();
$id = $_GET['id'] ?? '';
$webinar = get_webinar($id);
if (!$webinar) {
  $canceled = $id ? get_canceled_webinar($id) : null;
  if ($canceled) {
    $title = $canceled['title'] ?? '';
    redirect_to('/app/canceled.php?id=' . urlencode($id) . '&title=' . urlencode($title));
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
$canRefund = $isPremium && $hasPaid;
$isPublished = ($webinar['status'] ?? 'published') === 'published';
$webinarTime = strtotime($webinar['datetime'] ?? '');
$durationMinutes = parse_duration_minutes($webinar['duration'] ?? '60 min');
$webinarEnd = $webinarTime !== false ? $webinarTime + ($durationMinutes * 60) : false;
$isPast = $webinarEnd !== false && $webinarEnd < time();
$capacity = (int)($webinar['capacity'] ?? 0);
$capacityFull = $capacity > 0 && webinar_registration_count($id) >= $capacity;
$capacityRemaining = $capacity > 0 ? max(0, $capacity - webinar_registration_count($id)) : null;
$canRegister = !$locked && $hasPaid && !$alreadyRegistered && !$conflictWebinar && !$isPast && $isPublished && !$capacityFull;
$canPurchase = $isPremium && !$hasPaid && !$alreadyRegistered && !$conflictWebinar && !$isPast && $isPublished && !$capacityFull;
$isWaitlisted = is_user_waitlisted($id, $user['id']);
$paypalClientId = $appConfig['paypal_client_id'] ?? '';
$paymentNotice = !empty($_GET['paid']);
$canWaitlist = !$locked && !$alreadyRegistered && !$isPast && $isPublished && $capacityFull && !$isWaitlisted
  && ($webinar['host_id'] ?? '') !== $user['id'];
$isSaved = is_webinar_saved($user['id'], $id);
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
    $registration = register_for_webinar_with_notifications($id, $user['id']);
    $message = 'You are registered! A confirmation email has been queued.';
    $alreadyRegistered = true;
    $canRegister = false;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unregister']) && !$locked) {
  if ($isPast) {
    $error = 'This webinar has already started.';
  } elseif (!$alreadyRegistered) {
    $error = 'You are not registered for this webinar.';
  } else {
    $refundProcessed = false;
    if ($isPremium && $hasPaid) {
      $payment = latest_payment_for_webinar($user['id'], $id);
      $captureId = $payment['capture_id'] ?? '';
      if (!$captureId) {
        $error = 'Unable to refund this payment automatically. Please contact support.';
      } else {
        try {
          $refund = paypal_request('POST', '/v2/payments/captures/' . urlencode($captureId) . '/refund');
          update_payment_record($payment['id'] ?? '', [
            'status' => 'refunded',
            'refund_id' => $refund['id'] ?? '',
            'refunded_at' => date('c')
          ]);
          $refundProcessed = true;
        } catch (Throwable $e) {
          $error = 'Refund failed. Please try again or contact support.';
        }
      }
    }

    if (!$error) {
      unregister_from_webinar($id, $user['id']);
      $message = $refundProcessed ? 'You have been unregistered and refunded.' : 'You have been unregistered from this webinar.';
      $alreadyRegistered = false;
      $hasPaid = $isPremium ? false : $hasPaid;
      $canRefund = false;
      $capacityRemaining = $capacity > 0 ? max(0, $capacity - webinar_registration_count($id)) : null;
      $capacityFull = $capacity > 0 && $capacityRemaining === 0;
      if ($capacity > 0 && $capacityRemaining > 0) {
        notify_waitlist_openings($id, $capacityRemaining, 'unregister');
      }
      $canRegister = !$isPast && $isPublished && !$capacityFull && !$conflictWebinar && (!$isPremium || $hasPaid);
      $registerLabel = 'Register Now';
      $registerHint = '';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_waitlist']) && !$locked) {
  if (!$capacityFull) {
    $error = 'This webinar still has available spots.';
  } elseif ($isWaitlisted) {
    $error = 'You are already on the waitlist.';
  } elseif ($alreadyRegistered) {
    $error = 'You are already registered for this webinar.';
  } else {
    add_waitlist_entry($id, $user['id']);
    $message = 'You have been added to the waitlist.';
    $isWaitlisted = true;
    if (!empty($user['email'])) {
      $hostName = full_name($hostUser) ?: 'Webinar host';
      $webinarLink = '/app/webinar.php?id=' . urlencode($id);
      $waitlistEmailContext = [
        'name' => full_name($user),
        'webinar_title' => $webinar['title'] ?? 'Webinar',
        'webinar_datetime' => $displayDatetime ?: ($webinar['datetime'] ?? ''),
        'webinar_host' => $hostName,
        'webinar_link' => $webinarLink
      ];
      send_email($user['email'], 'Waitlist Confirmed', 'email_waitlist_joined.html', $waitlistEmailContext);
    }
    if (sms_opted_in($user) && function_exists('notifyWebinarWaitlisted')) {
      notifyWebinarWaitlisted($user['phone'], $webinar['title']);
    }
  }
}

$existingFeedback = feedback_by_user($id, $user['id']);
$canLeaveFeedback = $isPast && $alreadyRegistered && ($webinar['host_id'] ?? '') !== $user['id'] && !$existingFeedback;
$feedbackMessage = '';
$feedbackError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback']) && !$locked) {
  $content = trim($_POST['feedback'] ?? '');
  $rating = (int)($_POST['rating'] ?? 0);
  if (!$canLeaveFeedback) {
    $feedbackError = 'Only attendees can leave feedback for past events.';
  } elseif ($content === '') {
    $feedbackError = 'Please enter your feedback.';
  } elseif ($rating < 1 || $rating > 5) {
    $feedbackError = 'Please select a rating.';
  } else {
    add_feedback($id, $user['id'], $content, $rating);
    $feedbackMessage = 'Thanks for your feedback!';
    $existingFeedback = feedback_by_user($id, $user['id']);
    $canLeaveFeedback = false;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback']) && !$locked) {
  $feedbackId = $_POST['feedback_id'] ?? '';
  delete_feedback($feedbackId, $user['id'], $id);
  $feedbackMessage = 'Feedback deleted.';
  $existingFeedback = null;
  $canLeaveFeedback = $isPast && $alreadyRegistered && ($webinar['host_id'] ?? '') !== $user['id'];
}

$feedbackEntries = $isPast ? feedback_for_webinar($id) : [];
$feedbackAuthors = [];
foreach ($feedbackEntries as $entry) {
  $entryUserId = $entry['user_id'] ?? '';
  if ($entryUserId && !isset($feedbackAuthors[$entryUserId])) {
    $feedbackAuthors[$entryUserId] = get_user_by_id($entryUserId);
  }
}
$eventRatingSum = 0;
$eventRatingCount = 0;
foreach ($feedbackEntries as $entry) {
  $rating = (int)($entry['rating'] ?? 0);
  if ($rating >= 1 && $rating <= 5) {
    $eventRatingSum += $rating;
    $eventRatingCount += 1;
  }
}
$eventAverageRating = $eventRatingCount ? round($eventRatingSum / $eventRatingCount, 1) : 0;

include __DIR__ . '/../pages/webinar.html';
