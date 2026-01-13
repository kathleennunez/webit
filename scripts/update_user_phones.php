<?php
require_once __DIR__ . '/../php/bootstrap.php';

$targets = [
  'admin@webit.com' => '09938956065',
  'host@webit.com' => '09938956065',
  'user@webit.com' => '09938956065'
];

$users = read_json('users.json');
$updated = 0;

foreach ($users as &$user) {
  $email = strtolower(trim($user['email'] ?? ''));
  if (isset($targets[$email])) {
    $user['phone'] = $targets[$email];
    $user['sms_opt_in'] = true;
    $updated++;
  }
}
unset($user);

write_json('users.json', $users);

echo 'Updated users: ' . $updated . PHP_EOL;
