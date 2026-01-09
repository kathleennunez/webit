<?php
function recommend_webinars(string $userId, int $limit = 3): array {
  $user = get_user_by_id($userId);
  $interests = $user['interests'] ?? [];
  $registrations = user_registrations($userId);
  $attendedIds = array_map(fn($r) => $r['webinar_id'], $registrations);

  $now = time();
  $webinars = array_values(array_filter(all_webinars(), function ($webinar) use ($now) {
    if (($webinar['status'] ?? 'published') !== 'published') {
      return false;
    }
    $ts = strtotime($webinar['datetime'] ?? '');
    return $ts === false || $ts >= $now;
  }));
  usort($webinars, function ($a, $b) use ($interests, $attendedIds) {
    $scoreA = (in_array($a['category'], $interests, true) ? 2 : 0) + ($a['popularity'] ?? 0);
    $scoreB = (in_array($b['category'], $interests, true) ? 2 : 0) + ($b['popularity'] ?? 0);
    $scoreA -= in_array($a['id'], $attendedIds, true) ? 3 : 0;
    $scoreB -= in_array($b['id'], $attendedIds, true) ? 3 : 0;
    return $scoreB <=> $scoreA;
  });

  return array_slice($webinars, 0, $limit);
}
