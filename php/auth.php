<?php
function login_user(string $email, string $password): bool {
  /* ===============================
     CHECK LOCAL WEBIT USERS
  ================================ */
  $users = read_json('users.json');
  $normalized = strtolower(trim($email));
  
  foreach ($users as $user) {
    if (strtolower($user['email'] ?? '') === $normalized && 
        password_verify($password, $user['password_hash'])) {
      $_SESSION['user'] = $user;
      return true;
    }
  }
  
  /* ===============================
     FALLBACK TO ALAEHSCAPE API
  ================================ */
  $payload = json_encode([
    'email' => $email,
    'password' => $password
  ]);

  $ch = curl_init("http://172.20.10.3/Ala_Eh_scape/php/api/login.php"); // Update with AlaehScape IP
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
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

      $_SESSION['user'] = $syncedUser;
      return true;
    }
  }
  
  return false;
}

function logout_user(): void {
  session_destroy();
}

function get_user_by_id(string $userId): ?array {
  $users = read_json('users.json');
  foreach ($users as $user) {
    if (($user['user_id'] ?? '') === $userId) {
      return $user;
    }
  }
  return null;
}

function require_api_token(): array {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!str_starts_with($header, 'Bearer ')) {
    json_response(['error' => 'Missing token'], 401);
  }
  $token = substr($header, 7);
  $users = read_json('users.json');
  foreach ($users as $user) {
    if (($user['api_token'] ?? '') === $token) {
      return $user;
    }
  }
  json_response(['error' => 'Invalid token'], 403);
}

function update_user_profile(string $userId, array $payload): array {
  $users = read_json('users.json');
  foreach ($users as &$user) {
    if (($user['user_id'] ?? '') === $userId) {
      if (array_key_exists('first_name', $payload) || array_key_exists('last_name', $payload)) {
        $firstName = trim((string)($payload['first_name'] ?? ($user['first_name'] ?? '')));
        $lastName = trim((string)($payload['last_name'] ?? ($user['last_name'] ?? '')));
        $user['first_name'] = $firstName;
        $user['last_name'] = $lastName;
        $user['name'] = trim($firstName . ' ' . $lastName);
      }
      $user['email'] = $payload['email'] ?? $user['email'];
      $phonePayload = null;
      if (array_key_exists('phone', $payload)) {
        $phonePayload = (string)$payload['phone'];
      } elseif (!empty($payload['phone_display'])) {
        $phonePayload = (string)$payload['phone_display'];
      }
      if ($phonePayload !== null) {
        $user['phone'] = normalize_phone_ph($phonePayload);
      }
      if (array_key_exists('sms_opt_in', $payload)) {
        $user['sms_opt_in'] = !empty($payload['sms_opt_in']);
      }
      $user['company'] = $payload['company'] ?? ($user['company'] ?? '');
      $user['location'] = $payload['location'] ?? ($user['location'] ?? '');
      $user['timezone'] = $payload['timezone'] ?? ($user['timezone'] ?? '');
      $user['bio'] = $payload['bio'] ?? ($user['bio'] ?? '');
      if (!empty($payload['avatar'])) {
        $user['avatar'] = $payload['avatar'];
      }
      write_json('users.json', $users);
      $_SESSION['user'] = $user;
      sync_user_update_to_alaehscape($user);
      return $user;
    }
  }
  throw new RuntimeException('User not found');
}

function delete_user_account(string $userId): void {
  $users = read_json('users.json');
  $userExists = false;
  foreach ($users as $entry) {
    if (($entry['user_id'] ?? '') === $userId) {
      $userExists = true;
      break;
    }
  }
  if (!$userExists) {
    throw new RuntimeException('User not found');
  }

  $webinars = read_json('webinars.json');
  $hostedIds = [];
  $remainingWebinars = [];
  foreach ($webinars as $webinar) {
    if (($webinar['user_id'] ?? '') === $userId) {
      $hostedIds[] = $webinar['id'] ?? '';
      continue;
    }
    $remainingWebinars[] = $webinar;
  }

  $registrations = read_json('registrations.json');
  $registrations = array_values(array_filter($registrations, function ($registration) use ($userId, $hostedIds) {
    $webinarId = $registration['webinar_id'] ?? '';
    return ($registration['user_id'] ?? '') !== $userId && !in_array($webinarId, $hostedIds, true);
  }));

  $payments = read_json('payments.json');
  $payments = array_values(array_filter($payments, function ($payment) use ($userId, $hostedIds) {
    $webinarId = $payment['webinar_id'] ?? '';
    return ($payment['user_id'] ?? '') !== $userId && !in_array($webinarId, $hostedIds, true);
  }));

  $attendance = read_json('attendance.json');
  $attendance = array_values(array_filter($attendance, function ($record) use ($userId, $hostedIds) {
    $webinarId = $record['webinar_id'] ?? '';
    return ($record['user_id'] ?? '') !== $userId && !in_array($webinarId, $hostedIds, true);
  }));

  $subscriptions = read_json('subscriptions.json');
  $subscriptions = array_values(array_filter($subscriptions, fn($sub) => ($sub['user_id'] ?? '') !== $userId));

  $notifications = read_json('notifications.json');
  $notifications = array_values(array_filter($notifications, function ($note) use ($userId) {
    $payloadUser = $note['payload']['user_id'] ?? '';
    return $payloadUser !== $userId;
  }));

  $canceled = read_json('canceled.json');
  $canceled = array_values(array_filter($canceled, fn($entry) => !in_array(($entry['canceled_id'] ?? ''), $hostedIds, true)));

  $users = array_values(array_filter($users, fn($entry) => ($entry['user_id'] ?? '') !== $userId));

  write_json('webinars.json', $remainingWebinars);
  write_json('registrations.json', $registrations);
  write_json('payments.json', $payments);
  write_json('attendance.json', $attendance);
  write_json('subscriptions.json', $subscriptions);
  write_json('notifications.json', $notifications);
  write_json('canceled.json', $canceled);
  write_json('users.json', $users);
}

function alaehscape_api_base(): string {
  return 'http://172.20.10.2/Ala_Eh_scape/php/api';
}

function alaehscape_request(string $endpoint, array $payload): array {
  $ch = curl_init(alaehscape_api_base() . '/' . ltrim($endpoint, '/'));
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
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
  return ['http_code' => $httpCode, 'body' => $response];
}

function build_alaehscape_user_payload(array $user, array $overrides = []): array {
  $firstName = $overrides['first_name'] ?? ($user['first_name'] ?? '');
  $lastName = $overrides['last_name'] ?? ($user['last_name'] ?? '');
  $payload = [
    'webit_user_id' => $user['user_id'] ?? '',
    'alaehscape_user_id' => $user['alaehscape_user_id'] ?? null,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'name' => trim($firstName . ' ' . $lastName),
    'email' => $overrides['email'] ?? ($user['email'] ?? ''),
    'phone' => $overrides['phone'] ?? ($user['phone'] ?? ''),
    'timezone' => $overrides['timezone'] ?? ($user['timezone'] ?? ''),
    'company' => $overrides['company'] ?? ($user['company'] ?? ''),
    'location' => $overrides['location'] ?? ($user['location'] ?? ''),
    'bio' => $overrides['bio'] ?? ($user['bio'] ?? ''),
    'avatar' => $overrides['avatar'] ?? ($user['avatar'] ?? ''),
    'sms_opt_in' => array_key_exists('sms_opt_in', $overrides) ? !empty($overrides['sms_opt_in']) : !empty($user['sms_opt_in'])
  ];
  if (!empty($overrides['password'])) {
    $payload['password'] = $overrides['password'];
  }
  if (!empty($overrides['password_hash'])) {
    $payload['password_hash'] = $overrides['password_hash'];
  }
  return $payload;
}

function sync_user_update_to_alaehscape(array $user, array $overrides = []): void {
  $payload = build_alaehscape_user_payload($user, $overrides);
  $result = alaehscape_request('update-user.php', $payload);
  if (($result['http_code'] ?? 0) !== 200) {
    error_log('Failed to sync user update to AlaEhScape: ' . ($result['body'] ?? ''));
  }
}

function sync_user_delete_to_alaehscape(array $user): void {
  $payload = [
    'webit_user_id' => $user['user_id'] ?? '',
    'alaehscape_user_id' => $user['alaehscape_user_id'] ?? null,
    'email' => $user['email'] ?? ''
  ];
  $result = alaehscape_request('delete-user.php', $payload);
  if (($result['http_code'] ?? 0) !== 200) {
    error_log('Failed to sync user delete to AlaEhScape: ' . ($result['body'] ?? ''));
  }
}
