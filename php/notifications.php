<?php
function log_notification(string $type, array $payload): void {
  $notifications = read_json('notifications.json');
  $notifications[] = [
    'id' => uniqid('note_', true),
    'type' => $type,
    'payload' => $payload,
    'created_at' => date('c')
  ];
  write_json('notifications.json', $notifications);
}

function send_email(string $to, string $subject, string $template, array $context = []): void {
  $payload = [
    'to' => $to,
    'subject' => $subject,
    'template' => $template,
    'context' => $context
  ];
  log_notification('email', $payload);
}

function send_sms(string $to, string $message): void {
  log_notification('sms', ['to' => $to, 'message' => $message]);
}

function notify_user(string $userId, string $message, string $category = 'general', array $meta = []): void {
  log_notification('in-app', [
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
