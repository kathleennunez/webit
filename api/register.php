<?php
// WebIT/api/register.php
require_once __DIR__ . '/../php/bootstrap.php';

// Verify API key for external requests
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$isExternalRequest = !empty($apiKey);

if ($isExternalRequest && $apiKey !== 'ALAEHSCAPE_SECRET') {
    json_response(['status' => 'error', 'message' => 'Invalid API key'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'Invalid request method'], 405);
}

// Get input data
$payload = get_request_body();
$name = trim($payload['name'] ?? '');
$email = trim($payload['email'] ?? '');
$phone = trim($payload['phone'] ?? '');
$password = $payload['password'] ?? '';
$alaehscapeUserId = null; // Get AlaehScape user ID if syncing from AlaehScape
if (array_key_exists('alaehscape_user_id', $payload) && $payload['alaehscape_user_id'] !== '' && $payload['alaehscape_user_id'] !== null) {
    $alaehscapeUserId = (int)$payload['alaehscape_user_id'];
}

// Split name into first and last
$nameParts = explode(' ', $name, 2);
$firstName = trim($nameParts[0] ?? '');
$lastName = trim($nameParts[1] ?? '');

if (empty($firstName) || empty($email) || empty($password)) {
    json_response([
        'status' => 'error',
        'message' => 'Name, email, and password are required'
    ], 400);
}

// Check if email already exists
$users = read_json('users.json');
$emailNormalized = strtolower($email);

foreach ($users as $user) {
    if (strtolower($user['email'] ?? '') === $emailNormalized) {
        json_response([
            'status' => 'error',
            'message' => 'Email already exists'
        ], 409);
    }
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Generate unique ID
$userId = uniqid('user_', true);

// Ensure ID is truly unique
$idExists = true;
while ($idExists) {
    $idExists = false;
    foreach ($users as $user) {
        if (($user['id'] ?? '') === $userId) {
            $idExists = true;
            $userId = uniqid('user_', true);
            break;
        }
    }
}

/* 1️⃣ Insert locally in WebIT with AlaehScape user ID reference */
$newUser = [
    'user_id' => $userId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $emailNormalized,
    'password_hash' => $passwordHash,
    'role' => 'member',
    'interests' => [],
    'api_token' => bin2hex(random_bytes(12)),
    'avatar' => '/assets/images/avatar-default.svg',
    'timezone' => 'Asia/Manila',
    'phone' => normalize_phone_ph($phone),
    'sms_opt_in' => false,
    'alaehscape_user_id' => $alaehscapeUserId // Store AlaehScape user ID
];

$users[] = $newUser;

try {
    write_json('users.json', $users);
} catch (Exception $e) {
    error_log("WebIT registration failed for {$email}: " . $e->getMessage());
    json_response([
        'status' => 'error',
        'message' => 'Registration failed: ' . $e->getMessage()
    ], 500);
}

/* 2️⃣ Sync to AlaehScape (only if not from external API) */
if (!$isExternalRequest) {
    $curlPayload = json_encode([
        "name" => $name,
        "email" => $email,
        "phone" => $phone,
        "password" => $password,
        "webit_user_id" => $userId // Send WebIT user ID
    ]);

    $ch = curl_init("http://172.20.10.3/Ala_Eh_scape/php/api/register.php"); // Update with AlaehScape IP
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $curlPayload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-KEY: WEBIT_SECRET'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);

    $syncResponse = curl_exec($ch);
    $syncHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If sync successful, update alaehscape_user_id
    if ($syncHttpCode === 200) {
        $responseData = json_decode($syncResponse, true);
        if (isset($responseData['user_id'])) {
            // Update the user with AlaehScape ID
            $users = read_json('users.json');
            foreach ($users as &$user) {
                if ($user['id'] === $userId) {
                    $user['alaehscape_user_id'] = $responseData['user_id'];
                    break;
                }
            }
            write_json('users.json', $users);
        }
    } else {
        error_log("Failed to sync registration to AlaehScape for {$email}: " . $syncResponse);
    }
}

json_response([
    'status' => 'success',
    'message' => 'Registration successful',
    'user' => [
        'id' => $newUser['id'],
        'name' => trim($firstName . ' ' . $lastName),
        'email' => $newUser['email']
    ]
]);
