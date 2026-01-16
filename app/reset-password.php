<?php
require_once __DIR__ . '/../php/bootstrap.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$tokenValid = false;
$showExpiredNotice = false;
$resetEmail = '';

$resetEntries = read_json_file('password_resets.json');
$now = time();
foreach ($resetEntries as $entry) {
  $entryToken = (string)($entry['token'] ?? '');
  if ($token && $entryToken && hash_equals($entryToken, $token)) {
    $entryExpires = (int)($entry['expires_at'] ?? 0);
    if ($entryExpires > $now) {
      $tokenValid = true;
      $resetEmail = strtolower($entry['email'] ?? '');
    } else {
      $showExpiredNotice = true;
    }
    break;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['token'] ?? '';
  $newPassword = $_POST['new_password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  $tokenValid = false;
  $resetEmail = '';
  $showExpiredNotice = false;
  $resetEntries = read_json_file('password_resets.json');
  $now = time();
  foreach ($resetEntries as $entry) {
    $entryToken = (string)($entry['token'] ?? '');
    if ($token && $entryToken && hash_equals($entryToken, $token)) {
      $entryExpires = (int)($entry['expires_at'] ?? 0);
      if ($entryExpires > $now) {
        $tokenValid = true;
        $resetEmail = strtolower($entry['email'] ?? '');
      } else {
        $showExpiredNotice = true;
      }
      break;
    }
  }

  if (!$tokenValid) {
    $error = 'This reset link is invalid or has expired.';
  } elseif (strlen($newPassword) < 8) {
    $error = 'Password must be at least 8 characters.';
  } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
    $error = 'Password must include at least one uppercase letter and one number.';
  } elseif ($newPassword !== $confirmPassword) {
    $error = 'Passwords do not match.';
  } else {
    $users = read_json('users.json');
    $userIndex = null;
    foreach ($users as $index => $user) {
      if (strtolower($user['email'] ?? '') === $resetEmail) {
        $userIndex = $index;
        break;
      }
    }

    if ($userIndex === null) {
      $error = 'Account not found. Please request a new reset link.';
    } else {
      $users[$userIndex]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
      write_json('users.json', $users);
      sync_user_update_to_alaehscape($users[$userIndex], ['password' => $newPassword]);

      if (current_user() && strtolower(current_user()['email'] ?? '') === $resetEmail) {
        $_SESSION['user'] = $users[$userIndex];
      }

      $resetEntries = array_values(array_filter($resetEntries, function ($entry) use ($token) {
        $entryToken = (string)($entry['token'] ?? '');
        return !$entryToken || !hash_equals($entryToken, $token);
      }));
      write_json_file('password_resets.json', $resetEntries);
      $message = 'Password updated. You can now sign in.';
      $tokenValid = false;
    }
  }
}

include __DIR__ . '/../pages/reset-password.html';
