<?php
function feedback_for_webinar(string $webinarId): array {
  $feedback = read_json('feedback.json');
  $filtered = array_values(array_filter($feedback, fn($entry) => ($entry['webinar_id'] ?? '') === $webinarId));
  return array_reverse($filtered);
}

function add_feedback(string $webinarId, string $userId, string $content, int $rating): void {
  $content = trim($content);
  if (!$webinarId || !$userId || $content === '' || $rating < 1 || $rating > 5) {
    return;
  }
  $feedback = read_json('feedback.json');
  $feedback[] = [
    'id' => uniqid('fb_', true),
    'webinar_id' => $webinarId,
    'user_id' => $userId,
    'content' => $content,
    'rating' => $rating,
    'created_at' => date('c')
  ];
  write_json('feedback.json', $feedback);
}

function feedback_by_user(string $webinarId, string $userId): ?array {
  $feedback = read_json('feedback.json');
  foreach ($feedback as $entry) {
    if (($entry['webinar_id'] ?? '') === $webinarId && ($entry['user_id'] ?? '') === $userId) {
      return $entry;
    }
  }
  return null;
}

function delete_feedback(string $feedbackId, string $userId, string $webinarId = ''): void {
  $feedback = read_json('feedback.json');
  $feedback = array_values(array_filter($feedback, function ($entry) use ($feedbackId, $userId) {
    if ($feedbackId !== '' && ($entry['id'] ?? '') === $feedbackId) {
      return ($entry['user_id'] ?? '') !== $userId;
    }
    return true;
  }));
  if ($webinarId !== '') {
    $feedback = array_values(array_filter($feedback, function ($entry) use ($webinarId, $userId) {
      return ($entry['webinar_id'] ?? '') !== $webinarId || ($entry['user_id'] ?? '') !== $userId;
    }));
  }
  write_json('feedback.json', $feedback);
}
