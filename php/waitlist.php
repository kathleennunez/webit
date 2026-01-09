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
