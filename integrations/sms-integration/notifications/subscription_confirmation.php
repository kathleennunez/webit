<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifySubscriptionConfirmed($phone, $name)
{
    $message = "Hi $name! 🎉 Your premium webinar subscription is confirmed. "
             . "Thank you.";

    return sendSMS($phone, $message);
}
