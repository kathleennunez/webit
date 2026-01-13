<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyWebinarCanceled($phone, $webinarTitle)
{
    $message = 'Event canceled: "' . $webinarTitle . '". Check the site for updates.';

    return sendSMS($phone, $message);
}
