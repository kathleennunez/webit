<?php
session_start();

define('BASE_PATH', dirname(__DIR__));
define('DATA_DIR', BASE_PATH . '/data');

function load_env(string $path): void {
  if (!file_exists($path)) {
    return;
  }
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
      continue;
    }
    $parts = explode('=', $trimmed, 2);
    if (count($parts) !== 2) {
      continue;
    }
    $key = trim($parts[0]);
    $value = trim($parts[1]);
    if ($key === '') {
      continue;
    }
    $value = trim($value, "\"'");
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
  }
}

load_env(BASE_PATH . '/.env');
load_env(BASE_PATH . '/integrations/sms-integration/.env');

require_once BASE_PATH . '/php/utils.php';
require_once BASE_PATH . '/php/db.php';
require_once BASE_PATH . '/php/data.php';
require_once BASE_PATH . '/php/auth.php';
require_once BASE_PATH . '/php/webinars.php';
require_once BASE_PATH . '/php/registrations.php';
require_once BASE_PATH . '/php/subscriptions.php';
require_once BASE_PATH . '/php/notifications.php';
require_once BASE_PATH . '/php/saved.php';
require_once BASE_PATH . '/php/waitlist.php';
require_once BASE_PATH . '/php/feedback.php';
require_once BASE_PATH . '/php/reports.php';
require_once BASE_PATH . '/php/payments.php';
require_once BASE_PATH . '/php/timezones.php';

$appConfig = file_exists(BASE_PATH . '/config.php') ? require BASE_PATH . '/config.php' : [];
