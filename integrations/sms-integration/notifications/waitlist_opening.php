<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyWaitlistOpening($phone, $webinarTitle)
{
    $message = 'A spot just opened for "' . $webinarTitle . '". Register while seats last.';

    return sendSMS($phone, $message);
}
