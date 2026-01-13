<?php
function register_for_webinar(string $webinarId, string $userId): array {
  $registrations = read_json('registrations.json');
  $registrations[] = [
    'id' => uniqid('reg_', true),
    'webinar_id' => $webinarId,
    'user_id' => $userId,
    'registered_at' => date('c'),
    'status' => 'registered'
  ];
  write_json('registrations.json', $registrations);
  return end($registrations);
}

function user_registrations(string $userId): array {
  $registrations = read_json('registrations.json');
  return array_values(array_filter($registrations, fn($r) => $r['user_id'] === $userId));
}

function user_is_registered(string $webinarId, string $userId): bool {
  $registrations = read_json('registrations.json');
  foreach ($registrations as $registration) {
    if ($registration['webinar_id'] === $webinarId && $registration['user_id'] === $userId) {
      return true;
    }
  }
  return false;
}

function unregister_from_webinar(string $webinarId, string $userId): void {
  $registrations = read_json('registrations.json');
  $registrations = array_values(array_filter($registrations, function ($registration) use ($webinarId, $userId) {
    return ($registration['webinar_id'] ?? '') !== $webinarId || ($registration['user_id'] ?? '') !== $userId;
  }));
  write_json('registrations.json', $registrations);
}

function webinar_registration_count(string $webinarId): int {
  $registrations = read_json('registrations.json');
  return count(array_filter($registrations, fn($r) => $r['webinar_id'] === $webinarId));
}

function user_has_registration_conflict(string $webinarId, string $userId): ?array {
  $target = get_webinar($webinarId);
  if (!$target || empty($target['datetime'])) {
    return null;
  }
  if (($target['status'] ?? 'published') !== 'published') {
    return null;
  }
  $targetStart = strtotime($target['datetime']);
  if ($targetStart === false) {
    return null;
  }
  $targetDuration = parse_duration_minutes($target['duration'] ?? '60 min');
  $targetEnd = $targetStart + ($targetDuration * 60);

  $registrations = user_registrations($userId);
  foreach ($registrations as $registration) {
    if ($registration['webinar_id'] === $webinarId) {
      continue;
    }
    $webinar = get_webinar($registration['webinar_id']);
    if (!$webinar || empty($webinar['datetime'])) {
      continue;
    }
    if (($webinar['status'] ?? 'published') !== 'published') {
      continue;
    }
    $start = strtotime($webinar['datetime']);
    if ($start === false) {
      continue;
    }
    $duration = parse_duration_minutes($webinar['duration'] ?? '60 min');
    $end = $start + ($duration * 60);
    if ($targetStart < $end && $start < $targetEnd) {
      return $webinar;
    }
  }
  return null;
}

function register_for_webinar_with_notifications(string $webinarId, string $userId): ?array {
  if (user_is_registered($webinarId, $userId)) {
    return null;
  }
  $webinar = get_webinar($webinarId);
  if (!$webinar) {
    return null;
  }
  $user = get_user_by_id($userId);
  if (!$user) {
    return null;
  }

  $registration = register_for_webinar($webinarId, $userId);
  $hostUser = !empty($webinar['host_id']) ? get_user_by_id($webinar['host_id']) : null;
  $hostName = full_name($hostUser);
  if ($hostName === '') {
    $hostName = 'Webinar host';
  }
  $displayDatetime = format_datetime_for_user($webinar['datetime'] ?? '', $user['timezone'] ?? null);
  $durationMinutes = parse_duration_minutes($webinar['duration'] ?? '60 min');
  $webinarLink = '/app/webinar.php?id=' . urlencode($webinarId);
  $meetingLink = $webinar['meeting_url'] ?? '';
  $calendarDetails = 'Webinar: ' . ($webinar['title'] ?? 'Webinar');
  if ($meetingLink) {
    $calendarDetails .= "\nMeeting link: " . $meetingLink;
  }
  $calendarDetails .= "\nView: " . $webinarLink;
  $googleCalendarLink = build_google_calendar_link(
    $webinar['title'] ?? 'Webinar',
    $webinar['datetime'] ?? '',
    $durationMinutes,
    $calendarDetails,
    $meetingLink
  );
  $registrationEmailContext = [
    'name' => full_name($user),
    'webinar_title' => $webinar['title'] ?? 'Webinar',
    'webinar_datetime' => $displayDatetime ?: ($webinar['datetime'] ?? ''),
    'webinar_duration' => $durationMinutes . ' minutes',
    'webinar_host' => $hostName,
    'webinar_link' => $webinarLink,
    'google_calendar_link' => $googleCalendarLink,
    'meeting_link' => $meetingLink,
    'registration_id' => $registration['id'] ?? '',
    'registered_at' => $registration['registered_at'] ?? ''
  ];

  if (!empty($user['email'])) {
    send_email($user['email'], 'Registration Confirmed', 'email_registration.html', $registrationEmailContext);
  }
  notify_user($userId, 'Registration confirmed for: ' . ($webinar['title'] ?? 'Webinar'), 'registration', ['webinar_id' => $webinarId]);

  $smsEnabled = function_exists('sms_opted_in') ? sms_opted_in($user) : false;
  $smsTemplateReady = function_exists('notifyRegistrationConfirmed');
  if ($smsEnabled && $smsTemplateReady) {
    notifyRegistrationConfirmed($user['phone'], $webinar['title'] ?? 'Webinar', $displayDatetime ?: ($webinar['datetime'] ?? ''));
  } elseif (function_exists('log_notification')) {
    log_notification('sms-debug', [
      'event' => 'registration_confirmation',
      'user_id' => $userId,
      'phone' => $user['phone'] ?? '',
      'sms_opt_in' => $user['sms_opt_in'] ?? null,
      'sms_opted_in' => $smsEnabled,
      'template_ready' => $smsTemplateReady
    ]);
  }

  $webinarTime = strtotime($webinar['datetime'] ?? '');
  if ($webinarTime) {
    $oneDay = date('c', strtotime('-1 day', $webinarTime));
    $oneHour = date('c', strtotime('-1 hour', $webinarTime));
    schedule_reminder($userId, 'Reminder: ' . ($webinar['title'] ?? 'Webinar') . ' is tomorrow.', $oneDay, ['webinar_id' => $webinarId]);
    schedule_reminder($userId, 'Reminder: ' . ($webinar['title'] ?? 'Webinar') . ' starts in 1 hour.', $oneHour, ['webinar_id' => $webinarId]);
  }

  return $registration;
}
