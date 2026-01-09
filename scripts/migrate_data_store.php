<?php
require_once __DIR__ . '/../php/bootstrap.php';

$pdo = db_connection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('data_store', $tables, true)) {
  echo "No data_store table found.\n";
  exit(0);
}

$datasets = [
  'users',
  'webinars',
  'registrations',
  'subscriptions',
  'attendance',
  'payments',
  'notifications',
  'timezones',
  'canceled',
  'saved',
  'waitlist',
  'feedback'
];

foreach ($datasets as $dataset) {
  $stmt = $pdo->prepare('SELECT payload FROM data_store WHERE dataset = ? ORDER BY id ASC');
  $stmt->execute([$dataset]);
  $rows = $stmt->fetchAll();
  if (!$rows) {
    continue;
  }
  $data = [];
  foreach ($rows as $row) {
    $decoded = json_decode($row['payload'] ?? '[]', true);
    if (is_array($decoded)) {
      $data[] = $decoded;
    } elseif (is_string($decoded)) {
      $data[] = $decoded;
    }
  }
  $file = $dataset . '.json';
  write_json($file, $data);
  echo 'Migrated ' . $dataset . PHP_EOL;
}
