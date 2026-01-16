<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$backLink = previous_page_link('/app/account.php');
$userId = $user['user_id'] ?? '';
$emailMessage = '';
$emailError = '';
$passwordMessage = '';
$passwordError = '';
$deleteError = '';

$users = read_json('users.json');
$userIndex = null;
foreach ($users as $index => $entry) {
  if (($entry['user_id'] ?? '') === $userId) {
    $userIndex = $index;
    break;
  }
}

if ($userIndex === null) {
  logout_user();
  redirect_to('/app/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $currentHash = $users[$userIndex]['password_hash'] ?? '';

  if (isset($_POST['change_email'])) {
    $newEmail = trim($_POST['new_email'] ?? '');
    $newEmailNormalized = strtolower($newEmail);
    $currentPassword = $_POST['current_password_email'] ?? '';

    if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
      $emailError = 'Please provide a valid email address.';
    } elseif (!password_verify($currentPassword, $currentHash)) {
      $emailError = 'Current password is incorrect.';
    } else {
      foreach ($users as $entry) {
        if (strtolower($entry['email'] ?? '') === $newEmailNormalized && ($entry['user_id'] ?? '') !== $userId) {
          $emailError = 'Email already exists.';
          break;
        }
      }
    }

    if (!$emailError) {
      $users[$userIndex]['email'] = $newEmailNormalized;
      write_json('users.json', $users);
      $_SESSION['user'] = $users[$userIndex];
      $user = $_SESSION['user'];
      sync_user_update_to_alaehscape($users[$userIndex]);
      $emailMessage = 'Email updated.';
    }
  }

  if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPassword, $currentHash)) {
      $passwordError = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
      $passwordError = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
      $passwordError = 'Password must include at least one uppercase letter and one number.';
    } elseif ($newPassword !== $confirmPassword) {
      $passwordError = 'Passwords do not match.';
    }

    if (!$passwordError) {
      $users[$userIndex]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
      write_json('users.json', $users);
      $_SESSION['user'] = $users[$userIndex];
      sync_user_update_to_alaehscape($users[$userIndex], ['password' => $newPassword]);
      $passwordMessage = 'Password updated.';
    }
  }

  if (isset($_POST['delete_account'])) {
    $currentPassword = $_POST['current_password_delete'] ?? '';
    $confirmDelete = $_POST['confirm_delete'] ?? '';

    if (!password_verify($currentPassword, $currentHash)) {
      $deleteError = 'Current password is incorrect.';
    } elseif ($confirmDelete !== 'yes') {
      $deleteError = 'Please confirm account deletion.';
    }

    if (!$deleteError) {
      $userForSync = $users[$userIndex] ?? $user;
      sync_user_delete_to_alaehscape($userForSync);
      delete_user_account($userId);

      logout_user();
      redirect_to('/index.php');
    }
  }
}

include __DIR__ . '/../pages/settings.html';
