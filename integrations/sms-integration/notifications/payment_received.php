<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyPaymentReceived($phone, $webinarTitle, $amountFormatted)
{
    $message = 'Payment received for "' . $webinarTitle . '" (' . $amountFormatted . ').';

    return sendSMS($phone, $message);
}
