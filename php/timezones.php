<?php
function timezone_cache_path(): string {
  return DATA_DIR . '/timezones.json';
}

function fetch_timezones_from_api(): array {
  $config = file_exists(BASE_PATH . '/config.php') ? require BASE_PATH . '/config.php' : [];
  $apiKey = $config['timezonedb_key'] ?? '';
  if (!$apiKey) {
    return [];
  }
  $url = 'https://api.timezonedb.com/v2.1/list-time-zone?format=json&key=' . urlencode($apiKey);
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 5
    ]
  ]);
  $response = @file_get_contents($url, false, $context);
  if ($response === false) {
    return [];
  }
  $decoded = json_decode($response, true);
  if (!is_array($decoded) || empty($decoded['zones'])) {
    return [];
  }
  $timezones = [];
  foreach ($decoded['zones'] as $zone) {
    if (!empty($zone['zoneName'])) {
      $timezones[] = $zone['zoneName'];
    }
  }
  return $timezones;
}

function fallback_timezones(): array {
  return [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'Europe/London',
    'Europe/Paris',
    'Asia/Tokyo',
    'Asia/Singapore',
    'Australia/Sydney'
  ];
}

function get_timezones(): array {
  $cacheFile = timezone_cache_path();
  $cached = [];
  if (file_exists($cacheFile)) {
    $cached = read_json('timezones.json');
  }
  if (!$cached || count($cached) < 10) {
    $timezones = fetch_timezones_from_api();
    if ($timezones) {
      write_json('timezones.json', $timezones);
      return $timezones;
    }
    $fallback = fallback_timezones();
    write_json('timezones.json', $fallback);
    return $fallback;
  }
  return $cached;
}
