<?php
require_once __DIR__ . '/../php/bootstrap.php';

$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } else {
    $message = 'Check your email for a password reset link. If an account exists for that email, we will send a reset link.';
    $normalized = strtolower($email);
    $users = read_json('users.json');
    foreach ($users as $user) {
      if (strtolower($user['email'] ?? '') === $normalized) {
        $resetEntries = read_json_file('password_resets.json');
        $now = time();
        $resetEntries = array_values(array_filter($resetEntries, function ($entry) use ($now, $normalized) {
          $expiresAt = (int)($entry['expires_at'] ?? 0);
          $entryEmail = strtolower($entry['email'] ?? '');
          return $expiresAt > $now && $entryEmail !== $normalized;
        }));

        $token = bin2hex(random_bytes(16));
        $expiresAt = $now + 1800;
        $resetEntries[] = [
          'token' => $token,
          'email' => $normalized,
          'expires_at' => $expiresAt,
          'created_at' => date('c')
        ];
        write_json_file('password_resets.json', $resetEntries);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $resetUrl = $scheme . '://' . $host . '/app/reset-password.php?token=' . urlencode($token);
        $firstName = $user['first_name'] ?? '';

        send_email(
          $normalized,
          'Reset your Webit password',
          'email_password_reset.html',
          [
            'name' => $firstName ?: 'there',
            'reset_url' => $resetUrl,
            'support_email' => 'support@webit.com',
            'expires_in' => '30 minutes'
          ]
        );
        break;
      }
    }
  }
}

include __DIR__ . '/../pages/forgot-password.html';
