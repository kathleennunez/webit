<?php
function db_config(): array {
  $configPath = BASE_PATH . '/config.php';
  return file_exists($configPath) ? require $configPath : [];
}

function db_connection(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $config = db_config();
  $host = $config['db_host'] ?? '127.0.0.1';
  $port = $config['db_port'] ?? '3306';
  $dbName = $config['db_name'] ?? 'webit';
  $user = $config['db_user'] ?? 'root';
  $pass = $config['db_pass'] ?? '';

  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  return $pdo;
}
