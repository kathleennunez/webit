<?php
function webinar_report(string $webinarId): array {
  $registrations = read_json('registrations.json');
  $payments = read_json('payments.json');

  $registrationsCount = count(array_filter($registrations, fn($r) => $r['webinar_id'] === $webinarId));
  $revenue = array_sum(array_map(function ($p) use ($webinarId) {
    return $p['webinar_id'] === $webinarId ? ($p['amount'] ?? 0) : 0;
  }, $payments));

  return [
    'registrations' => $registrationsCount,
    'revenue' => $revenue
  ];
}

function user_report(string $userId): array {
  $registrations = read_json('registrations.json');
  $subscriptions = read_json('subscriptions.json');
  $forum = read_json('forum.json');

  $attended = array_filter($registrations, fn($r) => $r['user_id'] === $userId);
  $subscription = array_values(array_filter($subscriptions, fn($s) => $s['user_id'] === $userId));
  $posts = array_filter($forum, fn($p) => $p['user_id'] === $userId);

  return [
    'webinars_attended' => count($attended),
    'subscription_status' => $subscription[0]['status'] ?? 'none',
    'forum_posts' => count($posts)
  ];
}
