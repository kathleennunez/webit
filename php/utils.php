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
    redirect_to('/login.php');
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
