<?php
function login_user(string $email, string $password): bool {
  $users = read_json('users.json');
  foreach ($users as $user) {
    if ($user['email'] === $email && password_verify($password, $user['password_hash'])) {
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
    if ($user['id'] === $userId) {
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
    if ($user['id'] === $userId) {
      $user['name'] = $payload['name'] ?? $user['name'];
      $user['email'] = $payload['email'] ?? $user['email'];
      $user['phone'] = $payload['phone'] ?? ($user['phone'] ?? '');
      $user['company'] = $payload['company'] ?? ($user['company'] ?? '');
      $user['location'] = $payload['location'] ?? ($user['location'] ?? '');
      $user['timezone'] = $payload['timezone'] ?? ($user['timezone'] ?? '');
      $user['bio'] = $payload['bio'] ?? ($user['bio'] ?? '');
      if (isset($payload['interests'])) {
        $interests = array_map('trim', explode(',', $payload['interests']));
        $user['interests'] = array_values(array_filter($interests));
      }
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
