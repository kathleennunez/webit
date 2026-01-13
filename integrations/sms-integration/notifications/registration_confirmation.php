<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyRegistrationConfirmed($phone, $webinarTitle, $webinarDatetime = '')
{
    $title = trim((string)$webinarTitle);
    $datetime = trim((string)$webinarDatetime);
    $message = 'Registration confirmed for: "' . $title . '".';
    if ($datetime !== '') {
        $message .= ' Schedule: ' . $datetime . '.';
    }

    return sendSMS($phone, $message);
}
