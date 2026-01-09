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
