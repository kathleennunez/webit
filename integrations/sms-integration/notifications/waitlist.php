<?php
require_once __DIR__ . '/../core/sms_gateway.php';

function notifyWebinarWaitlisted($phone, $webinarTitle)
{
    $message = "You are currently WAITLISTED for the webinar: "
             . "\"$webinarTitle\". We will notify you if a slot opens.";

    return sendSMS($phone, $message);
}
