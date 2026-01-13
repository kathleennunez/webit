<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyRegistrationConfirmed($phone, $webinarTitle)
{
    $message = 'Registration confirmed for: "' . $webinarTitle . '".';

    return sendSMS($phone, $message);
}
