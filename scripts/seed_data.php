<?php
require_once __DIR__ . '/../php/bootstrap.php';

$categories = [
  'Education','Business','Wellness','Technology','Growth','Marketing','Design','Leadership','Finance','Health','Productivity','Creative'
];

$users = [
  [
    'id' => 'user_admin',
    'first_name' => 'Admin',
    'last_name' => 'User',
    'email' => 'admin@webit.com',
    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
    'role' => 'admin',
    'interests' => [],
    'api_token' => bin2hex(random_bytes(12)),
    'avatar' => '/assets/images/avatar-default.svg',
    'timezone' => 'UTC',
    'phone' => '09938956065',
    'sms_opt_in' => true
  ],
  [
    'id' => 'user_host',
    'first_name' => 'Host',
    'last_name' => 'User',
    'email' => 'host@webit.com',
    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
    'role' => 'member',
    'interests' => [],
    'api_token' => bin2hex(random_bytes(12)),
    'avatar' => '/assets/images/avatar-default.svg',
    'timezone' => 'UTC',
    'phone' => '09938956065',
    'sms_opt_in' => true
  ],
  [
    'id' => 'user_member',
    'first_name' => 'Member',
    'last_name' => 'User',
    'email' => 'user@webit.com',
    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
    'role' => 'member',
    'interests' => [],
    'api_token' => bin2hex(random_bytes(12)),
    'avatar' => '/assets/images/avatar-default.svg',
    'timezone' => 'UTC',
    'phone' => '09938956065',
    'sms_opt_in' => true
  ]
];
for ($i = 1; $i <= 36; $i++) {
  $users[] = [
    'id' => 'user_' . $i,
    'first_name' => 'User',
    'last_name' => (string)$i,
    'email' => 'user' . $i . '@webit.com',
    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
    'role' => 'member',
    'interests' => [$categories[$i % count($categories)]],
    'api_token' => bin2hex(random_bytes(12)),
    'avatar' => '/assets/images/avatar-default.svg',
    'timezone' => 'UTC'
  ];
}

$webinars = [];
$startDate = new DateTime('2024-08-01 09:00', new DateTimeZone('UTC'));
$durations = ['45 min','60 min','75 min','90 min'];
$index = 1;
foreach ($categories as $cat) {
  for ($j = 1; $j <= 15; $j++) {
    $hostIndex = (($index - 1) % 36);
    $dt = clone $startDate;
    $dt->modify('+' . (($index - 1) * 2) . ' hours');
    $webinars[] = [
      'id' => 'web_' . $index,
      'title' => $cat . ' Session ' . $j,
      'description' => 'Practical insights and actionable takeaways for ' . strtolower($cat) . ' growth.',
      'datetime' => $dt->format('c'),
      'duration' => $durations[$index % count($durations)],
      'category' => $cat,
      'instructor' => trim(($users[$hostIndex]['first_name'] ?? '') . ' ' . ($users[$hostIndex]['last_name'] ?? '')),
      'premium' => ($index % 3 === 0),
      'host_id' => $users[$hostIndex]['id'],
      'capacity' => 150 + ($index % 6) * 25,
      'popularity' => $index % 10,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => '',
      'status' => 'published'
    ];
    $index++;
  }
}

write_json('users.json', $users);
write_json('webinars.json', $webinars);
write_json('registrations.json', []);
write_json('subscriptions.json', []);
write_json('attendance.json', []);
write_json('payments.json', []);
write_json('notifications.json', []);
