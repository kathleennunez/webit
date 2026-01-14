<?php
function login_user(string $email, string $password): bool {
  $users = read_json('users.json');
  $normalized = strtolower(trim($email));
  foreach ($users as $user) {
    if (strtolower($user['email'] ?? '') === $normalized && password_verify($password, $user['password_hash'])) {
      $_SESSION['user'] = $user;
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
      return $user;
    }
  }
  throw new RuntimeException('User not found');
}
