<?php
require_once __DIR__ . '/../../../php/bootstrap.php';
require_once __DIR__ . '/../notifications/reminder.php';

$pdo = db_connection();
$startMin = date('Y-m-d H:i:s', strtotime('+59 minutes'));
$startMax = date('Y-m-d H:i:s', strtotime('+61 minutes'));
$stmt = $pdo->prepare("
    SELECT u.user_id, u.phone, u.email, u.first_name, u.last_name, u.timezone, u.sms_opt_in, w.webinar_id AS webinar_id, w.title, w.datetime
    FROM registrations r
    JOIN users u ON r.user_id = u.user_id
    JOIN webinars w ON r.webinar_id = w.webinar_id
    WHERE w.datetime BETWEEN :start_min AND :start_max
");
$stmt->execute([
    'start_min' => $startMin,
    'start_max' => $startMax
]);

foreach ($stmt->fetchAll() as $row) {
    if (!empty($row['phone']) && (int)($row['sms_opt_in'] ?? 0) !== 0) {
        notifyWebinarReminder(
            $row['phone'],
            $row['title'],
            date('h:i A', strtotime($row['datetime']))
        );
    }
    if (!empty($row['email'])) {
        $recipientName = full_name([
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? ''
        ]);
        $displayDatetime = format_datetime_for_user($row['datetime'], $row['timezone'] ?? null);
        $reminderEmailContext = [
            'name' => $recipientName,
            'webinar_title' => $row['title'],
            'webinar_datetime' => $displayDatetime ?: $row['datetime'],
            'reminder_label' => 'Starts in 1 hour',
            'webinar_link' => '/app/webinar.php?id=' . urlencode($row['webinar_id'])
        ];
        send_email($row['email'], 'Webinar Reminder: 1 Hour', 'email_reminder.html', $reminderEmailContext);
    }
}

$dayMin = date('Y-m-d H:i:s', strtotime('+23 hours 59 minutes'));
$dayMax = date('Y-m-d H:i:s', strtotime('+24 hours 1 minute'));
$stmtDay = $pdo->prepare("
    SELECT u.user_id, u.email, u.first_name, u.last_name, u.timezone, w.webinar_id AS webinar_id, w.title, w.datetime
    FROM registrations r
    JOIN users u ON r.user_id = u.user_id
    JOIN webinars w ON r.webinar_id = w.webinar_id
    WHERE w.datetime BETWEEN :start_min AND :start_max
      AND u.email IS NOT NULL
");
$stmtDay->execute([
    'start_min' => $dayMin,
    'start_max' => $dayMax
]);

foreach ($stmtDay->fetchAll() as $row) {
    if (empty($row['email'])) {
        continue;
    }
    $recipientName = full_name([
        'first_name' => $row['first_name'] ?? '',
        'last_name' => $row['last_name'] ?? ''
    ]);
    $displayDatetime = format_datetime_for_user($row['datetime'], $row['timezone'] ?? null);
    $reminderEmailContext = [
        'name' => $recipientName,
        'webinar_title' => $row['title'],
        'webinar_datetime' => $displayDatetime ?: $row['datetime'],
        'reminder_label' => 'Starts tomorrow',
        'webinar_link' => '/app/webinar.php?id=' . urlencode($row['webinar_id'])
    ];
    send_email($row['email'], 'Webinar Reminder: Tomorrow', 'email_reminder.html', $reminderEmailContext);
}
