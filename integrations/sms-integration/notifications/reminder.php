<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyWebinarReminder($phone, $webinarTitle, $startTime)
{
    $message = "⏰ Reminder: Your webinar \"$webinarTitle\" "
             . "starts at $startTime. Please be ready.";

    return sendSMS($phone, $message);
}
