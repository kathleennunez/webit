<?php
function all_webinars(): array {
  return read_json('webinars.json');
}

function get_webinar(string $id): ?array {
  foreach (all_webinars() as $webinar) {
    if ($webinar['id'] === $id) {
      return $webinar;
    }
  }
  return null;
}

function create_webinar(array $payload, string $hostId): array {
  $webinars = all_webinars();
  $new = [
    'id' => uniqid('web_', true),
    'title' => $payload['title'] ?? 'Untitled Webinar',
    'description' => $payload['description'] ?? '',
    'datetime' => $payload['datetime'] ?? date('c'),
    'duration' => $payload['duration'] ?? '60 min',
    'category' => $payload['category'] ?? 'Education',
    'instructor' => $payload['instructor'] ?? 'Host',
    'premium' => (bool)($payload['premium'] ?? false),
    'price' => isset($payload['price']) ? (float)$payload['price'] : 0,
    'host_id' => $hostId,
    'capacity' => (int)($payload['capacity'] ?? 100),
    'popularity' => (int)($payload['popularity'] ?? 0),
    'image' => $payload['image'] ?? '/assets/images/webinar-education.svg',
    'meeting_url' => $payload['meeting_url'] ?? '',
    'status' => $payload['status'] ?? 'published'
  ];
  $webinars[] = $new;
  write_json('webinars.json', $webinars);
  return $new;
}

function update_webinar_fields(string $id, array $fields): ?array {
  $webinars = all_webinars();
  foreach ($webinars as &$webinar) {
    if ($webinar['id'] === $id) {
      foreach ($fields as $key => $value) {
        $webinar[$key] = $value;
      }
      write_json('webinars.json', $webinars);
      return $webinar;
    }
  }
  return null;
}

function delete_webinar_and_related(string $id): bool {
  $webinars = all_webinars();
  $found = false;
  $webinars = array_values(array_filter($webinars, function ($webinar) use ($id, &$found) {
    if (($webinar['id'] ?? '') === $id) {
      $found = true;
      return false;
    }
    return true;
  }));
  if (!$found) {
    return false;
  }
  write_json('webinars.json', $webinars);

  $datasets = [
    'registrations.json' => 'webinar_id',
    'waitlist.json' => 'webinar_id',
    'saved.json' => 'webinar_id',
    'attendance.json' => 'webinar_id',
    'payments.json' => 'webinar_id',
    'feedback.json' => 'webinar_id'
  ];
  foreach ($datasets as $file => $key) {
    $rows = read_json($file);
    $filtered = array_values(array_filter($rows, fn($row) => ($row[$key] ?? '') !== $id));
    if (count($filtered) !== count($rows)) {
      write_json($file, $filtered);
    }
  }
  $canceled = read_json('canceled.json');
  $filteredCanceled = array_values(array_filter($canceled, fn($row) => ($row['id'] ?? '') !== $id));
  if (count($filteredCanceled) !== count($canceled)) {
    write_json('canceled.json', $filteredCanceled);
  }
  return true;
}

function add_canceled_webinar(string $id, string $title): void {
  $canceled = read_json('canceled.json');
  $exists = array_filter($canceled, fn($entry) => ($entry['id'] ?? '') === $id);
  if (!$exists) {
    $canceled[] = [
      'id' => $id,
      'title' => $title,
      'canceled_at' => date('c')
    ];
    write_json('canceled.json', $canceled);
  }
}

function get_canceled_webinar(string $id): ?array {
  $canceled = read_json('canceled.json');
  foreach ($canceled as $entry) {
    if (($entry['id'] ?? '') === $id) {
      return $entry;
    }
  }
  return null;
}
