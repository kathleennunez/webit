<?php
require_once __DIR__ . '/../../../php/bootstrap.php';
require_once __DIR__ . '/../notifications/reminder.php';

$pdo = db_connection();
$startMin = date('Y-m-d H:i:s', strtotime('+59 minutes'));
$startMax = date('Y-m-d H:i:s', strtotime('+61 minutes'));
$stmt = $pdo->prepare("
    SELECT u.phone, w.title, w.datetime
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN webinars w ON r.webinar_id = w.id
    WHERE w.datetime BETWEEN :start_min AND :start_max
      AND u.sms_opt_in = 1
      AND u.phone IS NOT NULL
");
$stmt->execute([
    'start_min' => $startMin,
    'start_max' => $startMax
]);

foreach ($stmt->fetchAll() as $row) {
    if (empty($row['phone'])) {
        continue;
    }
    notifyWebinarReminder(
        $row['phone'],
        $row['title'],
        date('h:i A', strtotime($row['datetime']))
    );
}
