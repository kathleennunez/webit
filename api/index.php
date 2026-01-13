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
      $webinarId = $payload['webinar_id'] ?? '';
      $webinar = $webinarId ? get_webinar($webinarId) : null;
      $webinarTitle = $webinar ? ($webinar['title'] ?? 'Webinar') : 'Webinar';
      $webinarDatetime = $webinar ? ($webinar['datetime'] ?? '') : '';
      $displayDatetime = $webinar ? format_datetime_for_user($webinarDatetime, $user['timezone'] ?? null) : '';
      $durationMinutes = $webinar ? parse_duration_minutes($webinar['duration'] ?? '60 min') : 60;
      $hostName = '';
      if ($webinar && !empty($webinar['host_id'])) {
        $hostName = full_name(get_user_by_id($webinar['host_id']));
      }
      if ($hostName === '') {
        $hostName = 'Webinar host';
      }
      $webinarLink = $webinarId ? '/app/webinar.php?id=' . urlencode($webinarId) : '';
      $meetingLink = $webinar['meeting_url'] ?? '';
      $calendarDetails = 'Webinar: ' . $webinarTitle;
      if ($meetingLink) {
        $calendarDetails .= "\nMeeting link: " . $meetingLink;
      }
      $calendarDetails .= "\nView: " . $webinarLink;
      $googleCalendarLink = build_google_calendar_link(
        $webinarTitle,
        $webinarDatetime,
        $durationMinutes,
        $calendarDetails,
        $meetingLink
      );
      $registrationEmailContext = [
        'name' => full_name($user),
        'webinar_title' => $webinarTitle,
        'webinar_datetime' => $displayDatetime ?: $webinarDatetime,
        'webinar_duration' => $durationMinutes . ' minutes',
        'webinar_host' => $hostName,
        'webinar_link' => $webinarLink,
        'google_calendar_link' => $googleCalendarLink,
        'meeting_link' => $meetingLink,
        'registration_id' => $record['id'] ?? '',
        'registered_at' => $record['registered_at'] ?? ''
      ];
      send_email($user['email'], 'Webinar Registration Confirmed', 'email_registration.html', $registrationEmailContext);
      notify_user($user['id'], 'Registration confirmed for your webinar.', 'registration', ['webinar_id' => $payload['webinar_id'] ?? '']);
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
      $planLabel = ucfirst((string)($sub['plan'] ?? 'monthly'));
      $renewalAt = format_datetime_for_user($sub['renewal_at'] ?? '', $user['timezone'] ?? null);
      $subscriptionEmailContext = [
        'name' => full_name($user),
        'plan' => $planLabel,
        'renewal_at' => $renewalAt ?: ($sub['renewal_at'] ?? ''),
        'provider' => $sub['provider'] ?? 'paypal',
        'manage_link' => '/app/settings.php',
        'subscription_id' => $sub['id'] ?? ''
      ];
      send_email($user['email'], 'Subscription Activated', 'email_subscription.html', $subscriptionEmailContext);
      notify_user(
        $user['id'],
        'Subscription payment received for the ' . ($payload['plan'] ?? 'monthly') . ' plan.',
        'subscription',
        ['plan' => $payload['plan'] ?? 'monthly']
      );
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
      $webinarId = $record['webinar_id'] ?? '';
      if ($webinarId) {
        $webinar = get_webinar($webinarId);
        $amount = (float)($record['amount'] ?? 0);
        $formatted = '$' . number_format($amount, 2);
        $paymentDate = format_datetime_for_user($record['created_at'] ?? '', $user['timezone'] ?? null);
        $webinarLink = '/app/webinar.php?id=' . urlencode($webinarId);
        $paymentEmailContext = [
          'name' => full_name($user),
          'webinar_title' => $webinar['title'] ?? 'Webinar',
          'amount_formatted' => $formatted,
          'payment_date' => $paymentDate ?: ($record['created_at'] ?? ''),
          'provider' => $record['provider'] ?? 'paypal',
          'payment_id' => $record['id'] ?? '',
          'webinar_link' => $webinarLink
        ];
        send_email($user['email'], 'Payment Receipt', 'email_payment_receipt.html', $paymentEmailContext);
        notify_user(
          $user['id'],
          'Payment received for premium webinar "' . ($webinar['title'] ?? 'Webinar') . '" (' . $formatted . ').',
          'payment',
          ['webinar_id' => $webinarId]
        );
      }
      json_response($record, 201);
    }
    json_response(read_json('payments.json'));
    break;
  default:
    json_response(['error' => 'Unknown endpoint'], 404);
}

json_response(['error' => 'Method not allowed'], 405);
