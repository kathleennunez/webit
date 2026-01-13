<?php
set_time_limit(180);
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = [];
}
$userMessage = trim($data['message'] ?? '');

if ($userMessage === '') {
    echo json_encode(['reply' => 'Please enter a question about webIT.']);
    exit;
}

$userType = $data['userType'] ?? 'Guest';

$systemPrompt = <<<EOT
You are the official AI assistant for the webIT platform.

webIT is a subscription-based webinar and virtual event management web application.

Your job is to help users understand and use webIT.

You only answer questions related to webIT, its features, and how the platform works.

If a question is not related to webIT, politely explain that you are designed only to assist with webIT and redirect the user back to webIT topics.

You must be calm, professional, and clear.

Do not guess. Do not invent features. Do not answer outside your scope.

If details are missing, ask a short clarifying question instead of making up an answer.
EOT;

$contextPrompt = <<<EOT
webIT system information:

- Guests can browse public webinars but must sign up to register or host.
- Registered users can attend webinars and also create, edit, and publish webinars.
- Webinars can be free or premium paid.
- Premium webinars require payment via PayPal before registration is completed.
- After payment, users are auto-registered and receive confirmation plus reminders.
- Users can unregister; premium attendees can request an automatic refund before the event starts.
- Webinars have capacity limits.
- When capacity is reached, new users are placed on a waitlist automatically.
- Notifications are sent for registrations and reminders.
- Admin users manage system-level features and the admin center.
- Hosts can publish, unpublish, or delete webinars. Deleting a webinar cancels it and notifies attendees.

Key features:
- Browse and filter webinars by category.
- Save webinars to a personal list.
- Register or join waitlists based on capacity.
- Host tools: manage attendees, waitlist, capacity, and exports.
- Subscriptions for hosts who want to publish premium webinars.

Pricing (default):
- Host subscription: $19 monthly or $180 yearly.
- Premium webinars: price set by the host.

Categories:
- Education, Business, Wellness, Technology, Growth, Marketing, Design, Leadership, Finance, Health, Productivity, Creative.

Refund rules:
- Premium webinar payments can be refunded when you unregister before the webinar starts.
- Refunds are processed automatically through PayPal when available.

Typical user steps:
- Attendee: Browse -> Open a webinar -> Pay if premium -> Auto-registered -> Join session on event day.
- Host: Subscribe (if premium) -> Create webinar -> Publish -> Manage attendees and waitlist.

Current user type: {$userType}
EOT;

$finalPrompt = $systemPrompt . "\n\n" . $contextPrompt . "\n\nUser question:\n" . $userMessage;

$payload = json_encode([
    "model" => "gemma3:4b",
    "prompt" => $finalPrompt,
    "stream" => false,
    "options" => [
        "num_predict" => 256,
        "temperature" => 0.4
    ]
]);

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        'reply' => 'The assistant is unavailable because PHP cURL is not enabled.'
    ]);
    exit;
}

$ch = curl_init("http://localhost:11434/api/generate");
if ($ch === false) {
    http_response_code(500);
    echo json_encode([
        'reply' => 'The assistant is currently unavailable. Please try again later.'
    ]);
    exit;
}
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($ch);

if ($response === false) {
  echo json_encode([
    'reply' => 'The assistant is currently unavailable. Please try again later.'
  ]);
  exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);
if (!is_array($result)) {
  echo json_encode([
    'reply' => 'The assistant is currently unavailable. Please try again later.'
  ]);
  exit;
}

if (!empty($result['error'])) {
  echo json_encode([
    'reply' => 'Assistant error: ' . $result['error']
  ]);
  exit;
}

if ($httpCode >= 400) {
  echo json_encode([
    'reply' => 'The assistant is currently unavailable. Please try again later.'
  ]);
  exit;
}
$reply = trim($result['response'] ?? '');

if ($reply === '') {
    $reply = "I’m here to help with webIT. Please ask a question related to the platform.";
}

echo json_encode(['reply' => $reply]);
