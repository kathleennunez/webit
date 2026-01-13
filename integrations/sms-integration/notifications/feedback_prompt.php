<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyFeedbackPrompt($phone, $webinarTitle)
{
    $message = 'How was "' . $webinarTitle . '"? Share feedback in the WebIT app.';

    return sendSMS($phone, $message);
}
