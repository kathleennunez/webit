<?php
session_start();

define('BASE_PATH', dirname(__DIR__));
define('DATA_DIR', BASE_PATH . '/data');

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
