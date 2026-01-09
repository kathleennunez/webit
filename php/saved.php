<?php
function saved_entries_for_user(string $userId): array {
  $saved = read_json('saved.json');
  return array_values(array_filter($saved, fn($entry) => ($entry['user_id'] ?? '') === $userId));
}

function saved_webinar_ids(string $userId): array {
  $entries = saved_entries_for_user($userId);
  return array_values(array_unique(array_map(fn($entry) => $entry['webinar_id'] ?? '', $entries)));
}

function is_webinar_saved(string $userId, string $webinarId): bool {
  if (!$webinarId) {
    return false;
  }
  $entries = saved_entries_for_user($userId);
  foreach ($entries as $entry) {
    if (($entry['webinar_id'] ?? '') === $webinarId) {
      return true;
    }
  }
  return false;
}

function save_webinar_for_user(string $userId, string $webinarId): void {
  $saved = read_json('saved.json');
  foreach ($saved as $entry) {
    if (($entry['user_id'] ?? '') === $userId && ($entry['webinar_id'] ?? '') === $webinarId) {
      return;
    }
  }
  $saved[] = [
    'id' => uniqid('save_', true),
    'user_id' => $userId,
    'webinar_id' => $webinarId,
    'saved_at' => date('c')
  ];
  write_json('saved.json', $saved);
}

function remove_saved_webinar(string $userId, string $webinarId): void {
  $saved = read_json('saved.json');
  $saved = array_values(array_filter($saved, function ($entry) use ($userId, $webinarId) {
    return ($entry['user_id'] ?? '') !== $userId || ($entry['webinar_id'] ?? '') !== $webinarId;
  }));
  write_json('saved.json', $saved);
}
