<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$emailMessage = '';
$emailError = '';
$passwordMessage = '';
$passwordError = '';
$deleteError = '';

$users = read_json('users.json');
$userIndex = null;
foreach ($users as $index => $entry) {
  if (($entry['id'] ?? '') === $user['id']) {
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
        if (strtolower($entry['email'] ?? '') === $newEmailNormalized && ($entry['id'] ?? '') !== $user['id']) {
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
      $userId = $user['id'];
      $webinars = read_json('webinars.json');
      $hostedIds = [];
      $remainingWebinars = [];
      foreach ($webinars as $webinar) {
        if (($webinar['host_id'] ?? '') === $userId) {
          $hostedIds[] = $webinar['id'];
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
      $canceled = array_values(array_filter($canceled, fn($entry) => !in_array(($entry['id'] ?? ''), $hostedIds, true)));

      $users = array_values(array_filter($users, fn($entry) => ($entry['id'] ?? '') !== $userId));

      write_json('webinars.json', $remainingWebinars);
      write_json('registrations.json', $registrations);
      write_json('payments.json', $payments);
      write_json('attendance.json', $attendance);
      write_json('subscriptions.json', $subscriptions);
      write_json('notifications.json', $notifications);
      write_json('canceled.json', $canceled);
      write_json('users.json', $users);

      logout_user();
      redirect_to('/index.php');
    }
  }
}

include __DIR__ . '/../pages/settings.html';
