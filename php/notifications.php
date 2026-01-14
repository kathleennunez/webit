<?php
$feedbackSmsPath = BASE_PATH . '/integrations/sms-integration/notifications/feedback_prompt.php';
if (file_exists($feedbackSmsPath)) {
  require_once $feedbackSmsPath;
}

$emailGatewayPath = BASE_PATH . '/integrations/email-integration/core/email_gateway.php';
if (file_exists($emailGatewayPath)) {
  require_once $emailGatewayPath;
}

function sms_opted_in(?array $user): bool {
  if (!$user) {
    return false;
  }
  return !empty($user['phone']) && !empty($user['sms_opt_in']);
}

function log_notification(string $type, array $payload): string {
  $notifications = read_json('notifications.json');
  $id = uniqid('note_', true);
  $notifications[] = [
    'id' => $id,
    'type' => $type,
    'payload' => $payload,
    'created_at' => date('c')
  ];
  write_json('notifications.json', $notifications);
  return $id;
}

function send_email(string $to, string $subject, string $template, array $context = []): void {
  $payload = [
    'to' => $to,
    'subject' => $subject,
    'template' => $template,
    'context' => $context
  ];
  log_notification('email', $payload);

  if (!function_exists('send_email_via_gateway')) {
    return;
  }

  log_notification('email-outbound', [
    'to' => $to,
    'subject' => $subject,
    'template' => $template,
    'status' => 'attempted'
  ]);

  $result = send_email_via_gateway($to, $subject, $template, $context);
  log_notification('email-outbound', [
    'to' => $to,
    'subject' => $subject,
    'template' => $template,
    'provider' => $result['provider'] ?? 'phpmailer',
    'status' => ($result['ok'] ?? false) ? 'sent' : 'failed',
    'error' => $result['error'] ?? null
  ]);
}

function send_sms(string $to, string $message): void {
  log_notification('sms', ['to' => $to, 'message' => $message]);
}

function notify_user(string $userId, string $message, string $category = 'general', array $meta = []): string {
  return log_notification('in-app', [
    'user_id' => $userId,
    'message' => $message,
    'category' => $category,
    'meta' => $meta,
    'read' => false
  ]);
}

function schedule_reminder(string $userId, string $message, string $scheduledAt, array $meta = []): void {
  log_notification('in-app', [
    'user_id' => $userId,
    'message' => $message,
    'category' => 'reminder',
    'meta' => $meta,
    'read' => false,
    'scheduled_at' => $scheduledAt
  ]);
}

function user_notifications(string $userId, int $limit = 6): array {
  $notifications = read_json('notifications.json');
  $now = time();
  $filtered = array_values(array_filter($notifications, function ($note) use ($userId) {
    if ($note['type'] !== 'in-app' || ($note['payload']['user_id'] ?? '') !== $userId) {
      return false;
    }
    $scheduled = $note['payload']['scheduled_at'] ?? '';
    if ($scheduled) {
      $scheduledTs = strtotime($scheduled);
      if ($scheduledTs === false || $scheduledTs > time()) {
        return false;
      }
    }
    return true;
  }));
  $filtered = array_reverse($filtered);
  return array_slice($filtered, 0, $limit);
}

function all_user_notifications(string $userId, int $limit = 200): array {
  $notifications = read_json('notifications.json');
  $filtered = array_values(array_filter($notifications, function ($note) use ($userId) {
    if ($note['type'] !== 'in-app' || ($note['payload']['user_id'] ?? '') !== $userId) {
      return false;
    }
    $scheduled = $note['payload']['scheduled_at'] ?? '';
    if ($scheduled) {
      $scheduledTs = strtotime($scheduled);
      if ($scheduledTs === false || $scheduledTs > time()) {
        return false;
      }
    }
    return true;
  }));
  $filtered = array_reverse($filtered);
  return array_slice($filtered, 0, $limit);
}

function notification_count(string $userId): int {
  $notifications = read_json('notifications.json');
  $now = time();
  return count(array_filter($notifications, function ($note) use ($userId) {
    if ($note['type'] !== 'in-app' || ($note['payload']['user_id'] ?? '') !== $userId) {
      return false;
    }
    if ($note['payload']['read'] ?? false) {
      return false;
    }
    $scheduled = $note['payload']['scheduled_at'] ?? '';
    if ($scheduled) {
      $scheduledTs = strtotime($scheduled);
      if ($scheduledTs === false || $scheduledTs > time()) {
        return false;
      }
    }
    return true;
  }));
}

function mark_all_read(string $userId): void {
  $notifications = read_json('notifications.json');
  foreach ($notifications as &$note) {
    if ($note['type'] === 'in-app' && ($note['payload']['user_id'] ?? '') === $userId) {
      $note['payload']['read'] = true;
    }
  }
  write_json('notifications.json', $notifications);
}

function has_feedback_prompt(string $userId, string $webinarId): bool {
  $notifications = read_json('notifications.json');
  foreach ($notifications as $note) {
    if ($note['type'] !== 'in-app') {
      continue;
    }
    $payload = $note['payload'] ?? [];
    if (($payload['user_id'] ?? '') !== $userId) {
      continue;
    }
    if (($payload['category'] ?? '') !== 'feedback') {
      continue;
    }
    if (($payload['meta']['webinar_id'] ?? '') === $webinarId) {
      return true;
    }
  }
  return false;
}

function send_feedback_prompts_for_user(string $userId): void {
  $registrations = user_registrations($userId);
  $now = time();
  foreach ($registrations as $registration) {
    $webinarId = $registration['webinar_id'] ?? '';
    if (!$webinarId || has_feedback_prompt($userId, $webinarId)) {
      continue;
    }
    $webinar = get_webinar($webinarId);
    if (!$webinar) {
      continue;
    }
    if (($webinar['user_id'] ?? '') === $userId) {
      continue;
    }
    $ts = strtotime($webinar['datetime'] ?? '');
    if ($ts === false || $ts > $now) {
      continue;
    }
    notify_user(
      $userId,
      'How was "' . ($webinar['title'] ?? 'your webinar') . '"? Leave feedback for the host.',
      'feedback',
      ['webinar_id' => $webinarId]
    );
    $user = get_user_by_id($userId);
    if (function_exists('notifyFeedbackPrompt')) {
      if (sms_opted_in($user)) {
        notifyFeedbackPrompt($user['phone'], $webinar['title'] ?? 'your webinar');
      }
    }
    if (!empty($user['email'])) {
      $hostUser = !empty($webinar['user_id']) ? get_user_by_id($webinar['user_id']) : null;
      $hostName = full_name($hostUser) ?: 'Webinar host';
      $webinarLink = '/app/webinar.php?id=' . urlencode($webinarId);
      $feedbackEmailContext = [
        'name' => full_name($user),
        'webinar_title' => $webinar['title'] ?? 'your webinar',
        'webinar_host' => $hostName,
        'webinar_link' => $webinarLink
      ];
      send_email($user['email'], 'How was the webinar?', 'email_feedback_prompt.html', $feedbackEmailContext);
    }
  }
}

function delete_notifications_for_user(string $userId): void {
  $notifications = read_json('notifications.json');
  $notifications = array_values(array_filter($notifications, function ($note) use ($userId) {
    $payloadUser = $note['payload']['user_id'] ?? '';
    return $payloadUser !== $userId;
  }));
  write_json('notifications.json', $notifications);
}

function delete_notification_by_id(string $userId, string $noteId): void {
  $notifications = read_json('notifications.json');
  $notifications = array_values(array_filter($notifications, function ($note) use ($userId, $noteId) {
    if (($note['id'] ?? '') !== $noteId) {
      return true;
    }
    $payloadUser = $note['payload']['user_id'] ?? '';
    return $payloadUser !== $userId;
  }));
  write_json('notifications.json', $notifications);
}
