<?php
require_once __DIR__ . '/php/bootstrap.php';

if (current_user()) {
  redirect_to('/home.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  $timezone = $_POST['timezone'] ?? 'UTC';

  if (!$name || !$email || !$password) {
    $error = 'Please fill out all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please provide a valid email address.';
  } elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters.';
  } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $error = 'Password must include at least one uppercase letter and one number.';
  } elseif ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    $users = read_json('users.json');
    foreach ($users as $user) {
      if ($user['email'] === $email) {
        $error = 'Email already exists.';
        break;
      }
    }
    if (!$error) {
      $newUser = [
        'id' => uniqid('user_', true),
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'member',
        'interests' => [],
        'api_token' => bin2hex(random_bytes(12)),
        'avatar' => '/assets/images/avatar-default.svg',
        'timezone' => $timezone
      ];
      $users[] = $newUser;
      write_json('users.json', $users);
      $message = 'Account created. You can now sign in.';
    }
  }
}

$timezones = get_timezones();
if (!$timezones) {
  $timezones = ['UTC'];
}

include __DIR__ . '/pages/signup.html';
