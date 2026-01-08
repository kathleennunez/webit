<?php
require_once __DIR__ . '/../php/bootstrap.php';

$user = require_api_token();

$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
$resource = $path ?: ($_GET['resource'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];
$payload = get_request_body();

switch ($resource) {
  case 'webinars':
    if ($method === 'GET') {
      $id = $_GET['id'] ?? null;
      if ($id) {
        $webinar = get_webinar($id);
        json_response($webinar ? $webinar : ['error' => 'Not found'], $webinar ? 200 : 404);
      }
      json_response(all_webinars());
    }
    if ($method === 'POST') {
      $created = create_webinar($payload, $user['id']);
      json_response($created, 201);
    }
    break;
  case 'registrations':
    if ($method === 'GET') {
      json_response(user_registrations($user['id']));
    }
    if ($method === 'POST') {
      $record = register_for_webinar($payload['webinar_id'] ?? '', $user['id']);
      send_email($user['email'], 'Webinar Registration Confirmed', 'email_registration.html', $record);
      notify_user($user['id'], 'Registration confirmed for your webinar.', 'registration', ['webinar_id' => $payload['webinar_id'] ?? '']);
      $webinar = get_webinar($payload['webinar_id'] ?? '');
      $webinarTime = $webinar ? strtotime($webinar['datetime'] ?? '') : false;
      if ($webinarTime) {
        $oneDay = date('c', strtotime('-1 day', $webinarTime));
        $oneHour = date('c', strtotime('-1 hour', $webinarTime));
        schedule_reminder($user['id'], 'Reminder: ' . ($webinar['title'] ?? 'Webinar') . ' is tomorrow.', $oneDay, ['webinar_id' => $payload['webinar_id'] ?? '']);
        schedule_reminder($user['id'], 'Reminder: ' . ($webinar['title'] ?? 'Webinar') . ' starts in 1 hour.', $oneHour, ['webinar_id' => $payload['webinar_id'] ?? '']);
      }
      json_response($record, 201);
    }
    break;
  case 'subscriptions':
    if ($method === 'GET') {
      json_response(get_subscription($user['id']) ?? []);
    }
    if ($method === 'POST') {
      $sub = create_subscription($user['id'], $payload['plan'] ?? 'monthly');
      send_email($user['email'], 'Subscription Activated', 'email_subscription.html', $sub);
      notify_user($user['id'], 'Subscription activated: ' . ($payload['plan'] ?? 'monthly'), 'subscription', ['plan' => $payload['plan'] ?? 'monthly']);
      json_response($sub, 201);
    }
    break;
  case 'attendance':
    if ($method === 'POST') {
      $attendance = read_json('attendance.json');
      $record = [
        'id' => uniqid('att_', true),
        'user_id' => $user['id'],
        'webinar_id' => $payload['webinar_id'] ?? '',
        'status' => $payload['status'] ?? 'attended',
        'timestamp' => date('c')
      ];
      $attendance[] = $record;
      write_json('attendance.json', $attendance);
      json_response($record, 201);
    }
    json_response(read_json('attendance.json'));
    break;
  case 'payments':
    if ($method === 'POST') {
      $record = log_payment($payload, $user['id']);
      json_response($record, 201);
    }
    json_response(read_json('payments.json'));
    break;
  case 'ai/recommendations':
    if ($method === 'GET') {
      json_response(recommend_webinars($user['id']));
    }
    break;
  default:
    json_response(['error' => 'Unknown endpoint'], 404);
}

json_response(['error' => 'Method not allowed'], 405);
