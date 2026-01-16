<?php
// WebIT/api/login.php
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
$email = trim($payload['email'] ?? '');
$password = $payload['password'] ?? '';

if (empty($email) || empty($password)) {
    json_response([
        'status' => 'error',
        'message' => 'Email and password are required'
    ], 400);
}

/* ===============================
   USER LOGIN (LOCAL WEBIT)
================================ */
$users = read_json('users.json');
$normalizedEmail = strtolower($email);

foreach ($users as $user) {
    if (strtolower($user['email'] ?? '') === $normalizedEmail && 
        password_verify($password, $user['password_hash'])) {
        
        // For API requests from AlaehScape, return user data
        if ($isExternalRequest) {
            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            json_response([
                'status' => 'success',
                'user' => [
                    'id' => $user['id'],
                    'name' => $fullName,
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'password' => $user['password_hash'], // Hashed password for sync
                    'webit_user_id' => $user['id'] // Include WebIT ID
                ]
            ]);
        }

        // For web requests
        $_SESSION['user'] = $user;
        json_response([
            'status' => 'success',
            'role' => $user['role'] ?? 'member',
            'redirect' => '/app/home.php'
        ]);
    }
}

/* ===============================
   FALLBACK LOGIN → ALAEHSCAPE API
================================ */
if (!$isExternalRequest) { // Avoid infinite loop
    $curlPayload = json_encode([
        'email' => $email,
        'password' => $password
    ]);

    $ch = curl_init("http://172.20.10.3/Ala_Eh_scape/php/api/login.php"); // Update with AlaehScape IP
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

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $responseData = json_decode($response, true);

        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            /* AUTO-SYNC USER FROM ALAEHSCAPE TO WEBIT */
            $users = read_json('users.json');
            
            // Check if user already exists
            $userExists = false;
            $syncedUser = null;
            
            foreach ($users as $existingUser) {
                if (strtolower($existingUser['email'] ?? '') === strtolower($responseData['user']['email'])) {
                    $userExists = true;
                    $syncedUser = $existingUser;
                    break;
                }
            }
            
            // Create new user if doesn't exist
            if (!$userExists) {
                $nameParts = explode(' ', $responseData['user']['name'], 2);
                $syncedUser = [
                    'user_id' => uniqid('user_', true),
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => $nameParts[1] ?? '',
                    'email' => strtolower($responseData['user']['email']),
                    'password_hash' => $responseData['user']['password'], // Already hashed
                    'role' => 'member',
                    'interests' => [],
                    'api_token' => bin2hex(random_bytes(12)),
                    'avatar' => '/assets/images/avatar-default.svg',
                    'timezone' => 'Asia/Manila',
                    'phone' => $responseData['user']['phone'] ?? '',
                    'sms_opt_in' => false,
                    'alaehscape_user_id' => $responseData['user']['alaehscape_user_id'] ?? null // Store AlaehScape ID
                ];
                $users[] = $syncedUser;
                write_json('users.json', $users);
            }

            // Set session
            $_SESSION['user'] = $syncedUser;
            
            json_response([
                'status' => 'success',
                'role' => $syncedUser['role'] ?? 'member',
                'redirect' => '/app/home.php'
            ]);
        }
    }
}

/* ===============================
   LOGIN FAILED
================================ */
json_response([
    'status' => 'error',
    'message' => 'Invalid credentials'
], 401);
