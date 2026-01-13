<?php
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}

function json_response($data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($data, JSON_PRETTY_PRINT);
  exit;
}

function redirect_to(string $path): void {
  header('Location: ' . $path);
  exit;
}

function sanitize(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function get_request_body(): array {
  $raw = file_get_contents('php://input');
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : $_POST;
}

function require_login(): void {
  if (!isset($_SESSION['user'])) {
    redirect_to('/app/login.php');
  }
}

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function require_role(string $role): void {
  $user = current_user();
  if (!$user || $user['role'] !== $role) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

function require_non_admin(): void {
  $user = current_user();
  if ($user && ($user['role'] ?? '') === 'admin') {
    redirect_to('/app/admin.php');
  }
}

function avatar_url(?array $user): string {
  if (!$user) {
    return '/assets/images/avatar-default.svg';
  }
  return $user['avatar'] ?? '/assets/images/avatar-default.svg';
}

function parse_utc_datetime(string $datetime): ?DateTime {
  try {
    return new DateTime($datetime, new DateTimeZone('UTC'));
  } catch (Exception $e) {
    return null;
  }
}

function format_datetime_for_user(string $datetime, ?string $timezone): string {
  $dt = parse_utc_datetime($datetime);
  if (!$dt) {
    return $datetime;
  }
  $tz = new DateTimeZone($timezone ?: 'UTC');
  $dt->setTimezone($tz);
  return $dt->format('M j, Y g:i A');
}

function format_time_for_user(string $datetime, ?string $timezone): string {
  $dt = parse_utc_datetime($datetime);
  if (!$dt) {
    return '';
  }
  $tz = new DateTimeZone($timezone ?: 'UTC');
  $dt->setTimezone($tz);
  return $dt->format('g:i A');
}

function ics_escape(string $value): string {
  $escaped = str_replace('\\', '\\\\', $value);
  $escaped = str_replace(';', '\;', $escaped);
  $escaped = str_replace(',', '\,', $escaped);
  $escaped = str_replace("\r", '', $escaped);
  return str_replace("\n", '\n', $escaped);
}

function build_ics_content(
  string $title,
  string $startDatetime,
  int $durationMinutes,
  string $uid = '',
  string $description = '',
  string $url = ''
): string {
  $start = parse_utc_datetime($startDatetime);
  if (!$start) {
    return '';
  }
  $end = clone $start;
  $end->modify('+' . max(1, $durationMinutes) . ' minutes');
  $uidValue = $uid !== '' ? $uid : bin2hex(random_bytes(8)) . '@webit';
  $format = 'Ymd\THis\Z';
  $lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Webit//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:' . ics_escape($uidValue),
    'DTSTAMP:' . gmdate($format),
    'DTSTART:' . $start->setTimezone(new DateTimeZone('UTC'))->format($format),
    'DTEND:' . $end->setTimezone(new DateTimeZone('UTC'))->format($format),
    'SUMMARY:' . ics_escape($title),
    'DESCRIPTION:' . ics_escape($description ?: ('Webinar: ' . $title))
  ];
  if ($url !== '') {
    $lines[] = 'URL:' . ics_escape($url);
  }
  $lines[] = 'END:VEVENT';
  $lines[] = 'END:VCALENDAR';
  return implode("\r\n", $lines) . "\r\n";
}

function build_ics_data_uri(
  string $title,
  string $startDatetime,
  int $durationMinutes,
  string $uid = '',
  string $description = '',
  string $url = ''
): string {
  $ics = build_ics_content($title, $startDatetime, $durationMinutes, $uid, $description, $url);
  if ($ics === '') {
    return '';
  }
  return 'data:text/calendar;charset=utf-8;base64,' . base64_encode($ics);
}

function build_google_calendar_link(
  string $title,
  string $startDatetime,
  int $durationMinutes,
  string $details = '',
  string $location = ''
): string {
  $start = parse_utc_datetime($startDatetime);
  if (!$start) {
    return '';
  }
  $end = clone $start;
  $end->modify('+' . max(1, $durationMinutes) . ' minutes');
  $format = 'Ymd\THis\Z';
  $dates = $start->setTimezone(new DateTimeZone('UTC'))->format($format)
    . '/' . $end->setTimezone(new DateTimeZone('UTC'))->format($format);
  $query = [
    'action' => 'TEMPLATE',
    'text' => $title,
    'dates' => $dates
  ];
  if ($details !== '') {
    $query['details'] = $details;
  }
  if ($location !== '') {
    $query['location'] = $location;
  }
  return 'https://calendar.google.com/calendar/render?' . http_build_query($query);
}

function date_key_for_user(string $datetime, ?string $timezone): string {
  $dt = parse_utc_datetime($datetime);
  if (!$dt) {
    return '';
  }
  $tz = new DateTimeZone($timezone ?: 'UTC');
  $dt->setTimezone($tz);
  return $dt->format('Y-m-d');
}

function parse_duration_minutes(string $duration): int {
  if (preg_match('/(\d+)/', $duration, $matches)) {
    return max(1, (int)$matches[1]);
  }
  return 60;
}

function full_name(?array $user): string {
  if (!$user) {
    return '';
  }
  $first = trim((string)($user['first_name'] ?? ''));
  $last = trim((string)($user['last_name'] ?? ''));
  $combined = trim($first . ' ' . $last);
  if ($combined !== '') {
    return $combined;
  }
  return trim((string)($user['name'] ?? ''));
}

function normalize_phone_ph(string $phone): string {
  $trimmed = trim($phone);
  if ($trimmed === '') {
    return '';
  }
  if ($trimmed[0] === '+') {
    $digits = preg_replace('/\D+/', '', $trimmed);
    if (str_starts_with($digits, '63') && strlen($digits) === 11 && ($digits[2] ?? '') === '9') {
      return '+63' . '9' . substr($digits, 2);
    }
    return '+' . $digits;
  }
  $digits = preg_replace('/\D+/', '', $trimmed);
  if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
    return '+63' . substr($digits, 1);
  }
  if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
    return '+' . $digits;
  }
  if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
    return '+63' . $digits;
  }
  return $trimmed;
}
