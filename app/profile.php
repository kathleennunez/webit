<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$viewer = current_user();
$profileUser = $viewer;
$profileId = $_GET['user_id'] ?? '';
if ($profileId) {
  $found = get_user_by_id($profileId);
  if ($found) {
    $profileUser = $found;
  } else {
    http_response_code(404);
    echo 'User not found';
    exit;
  }
}

$hostedWebinars = array_values(array_filter(all_webinars(), function ($webinar) use ($profileUser) {
  return ($webinar['host_id'] ?? '') === ($profileUser['id'] ?? '');
}));

$now = time();
$pastEvents = array_values(array_filter($hostedWebinars, function ($webinar) use ($now) {
  $ts = strtotime($webinar['datetime'] ?? '');
  if ($ts === false) {
    return false;
  }
  return $ts < $now;
}));

$futureEvents = array_values(array_filter($hostedWebinars, function ($webinar) use ($now) {
  $ts = strtotime($webinar['datetime'] ?? '');
  if ($ts === false) {
    return false;
  }
  return $ts >= $now;
}));

$pastEventIds = array_map(fn($event) => $event['id'] ?? '', $pastEvents);
$pastEventIdSet = array_flip(array_filter($pastEventIds));
$allFeedback = read_json('feedback.json');
$hostFeedback = array_values(array_filter($allFeedback, function ($entry) use ($pastEventIdSet) {
  $eventId = $entry['webinar_id'] ?? '';
  return $eventId && isset($pastEventIdSet[$eventId]);
}));
$ratingSum = 0;
$ratingCount = 0;
foreach ($hostFeedback as $entry) {
  $rating = (int)($entry['rating'] ?? 0);
  if ($rating >= 1 && $rating <= 5) {
    $ratingSum += $rating;
    $ratingCount += 1;
  }
}
$averageRating = $ratingCount ? round($ratingSum / $ratingCount, 1) : 0;
$pastEventCount = count($pastEvents);

$selectedEventId = $_GET['event'] ?? '';
$selectedEvent = null;
foreach ($pastEvents as $event) {
  if (($event['id'] ?? '') === $selectedEventId) {
    $selectedEvent = $event;
    break;
  }
}

$existingFeedback = $selectedEvent ? feedback_by_user($selectedEvent['id'], $viewer['id']) : null;
$canLeaveFeedback = false;
if ($selectedEvent) {
  $eventTime = strtotime($selectedEvent['datetime'] ?? '');
  $canLeaveFeedback = user_is_registered($selectedEvent['id'], $viewer['id'])
    && ($viewer['id'] ?? '') !== ($profileUser['id'] ?? '')
    && $eventTime !== false
    && $eventTime < $now
    && !$existingFeedback;
}

$feedbackMessage = '';
$feedbackError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
  $content = trim($_POST['feedback'] ?? '');
  $rating = (int)($_POST['rating'] ?? 0);
  if (!$canLeaveFeedback) {
    $feedbackError = 'Only attendees can leave feedback for past events.';
  } elseif ($content === '') {
    $feedbackError = 'Please enter your feedback.';
  } elseif ($rating < 1 || $rating > 5) {
    $feedbackError = 'Please select a rating.';
  } else {
    add_feedback($selectedEvent['id'], $viewer['id'], $content, $rating);
    $redirect = '/app/profile.php?user_id=' . urlencode($profileUser['id']) . '&event=' . urlencode($selectedEvent['id']);
    redirect_to($redirect);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {
  $feedbackId = $_POST['feedback_id'] ?? '';
  $webinarId = $selectedEvent['id'] ?? '';
  delete_feedback($feedbackId, $viewer['id'], $webinarId);
  $redirect = '/app/profile.php?user_id=' . urlencode($profileUser['id']) . '&event=' . urlencode($selectedEvent['id']);
  redirect_to($redirect);
}

$feedbackEntries = $selectedEvent ? feedback_for_webinar($selectedEvent['id']) : [];
$feedbackAuthors = [];
foreach ($feedbackEntries as $entry) {
  $userId = $entry['user_id'] ?? '';
  if ($userId && !isset($feedbackAuthors[$userId])) {
    $feedbackAuthors[$userId] = get_user_by_id($userId);
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

include __DIR__ . '/../pages/profile.html';
