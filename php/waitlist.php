<?php
function waitlist_entries(): array {
  return read_json('waitlist.json');
}

function waitlist_for_webinar(string $webinarId): array {
  $waitlist = waitlist_entries();
  return array_values(array_filter($waitlist, fn($entry) => ($entry['webinar_id'] ?? '') === $webinarId));
}

function is_user_waitlisted(string $webinarId, string $userId): bool {
  $waitlist = waitlist_entries();
  foreach ($waitlist as $entry) {
    if (($entry['webinar_id'] ?? '') === $webinarId && ($entry['user_id'] ?? '') === $userId) {
      return true;
    }
  }
  return false;
}

function add_waitlist_entry(string $webinarId, string $userId): void {
  if (!$webinarId || !$userId) {
    return;
  }
  if (is_user_waitlisted($webinarId, $userId)) {
    return;
  }
  $waitlist = waitlist_entries();
  $waitlist[] = [
    'id' => uniqid('wait_', true),
    'webinar_id' => $webinarId,
    'user_id' => $userId,
    'created_at' => date('c')
  ];
  write_json('waitlist.json', $waitlist);
}

function notify_waitlist_openings(string $webinarId, int $availableSeats, string $reason = ''): int {
  if ($availableSeats <= 0 || !$webinarId) {
    return 0;
  }
  $waitlist = waitlist_for_webinar($webinarId);
  if (!$waitlist) {
    return 0;
  }
  usort($waitlist, function ($a, $b) {
    return strtotime($a['created_at'] ?? '') <=> strtotime($b['created_at'] ?? '');
  });
  $webinar = get_webinar($webinarId);
  $title = $webinar['title'] ?? 'webinar';
  $notified = 0;
  foreach (array_slice($waitlist, 0, $availableSeats) as $entry) {
    $userId = $entry['user_id'] ?? '';
    if (!$userId) {
      continue;
    }
    notify_user(
      $userId,
      'A spot just opened for "' . $title . '". Complete your registration while seats last.',
      'waitlist',
      ['webinar_id' => $webinarId, 'reason' => $reason]
    );
    $notified++;
  }
  return $notified;
}
