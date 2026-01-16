<?php
function data_path(string $file): string {
  return DATA_DIR . '/' . $file;
}

function dataset_from_file(string $file): string {
  return preg_replace('/\.json$/', '', basename($file));
}

function read_json_file(string $file): array {
  $path = data_path($file);
  if (!file_exists($path)) {
    return [];
  }
  $raw = file_get_contents($path);
  $data = $raw !== false ? json_decode($raw, true) : null;
  return is_array($data) ? $data : [];
}

function write_json_file(string $file, array $data): void {
  $path = data_path($file);
  $dir = dirname($path);
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  file_put_contents($path, $payload !== false ? $payload : '[]');
}

function ensure_timezone_id(PDO $pdo, string $name): int {
  $name = trim($name) ?: 'UTC';
  $stmt = $pdo->prepare('SELECT timezone_id FROM timezones WHERE timezone_name = ?');
  $stmt->execute([$name]);
  $row = $stmt->fetch();
  if ($row && isset($row['timezone_id'])) {
    return (int)$row['timezone_id'];
  }
  $insert = $pdo->prepare('INSERT INTO timezones (timezone_name) VALUES (?)');
  $insert->execute([$name]);
  return (int)$pdo->lastInsertId();
}

function ensure_category_id(PDO $pdo, ?string $name): ?int {
  $name = trim((string)$name);
  if ($name === '') {
    return null;
  }
  $stmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
  $stmt->execute([$name]);
  $row = $stmt->fetch();
  if ($row && isset($row['category_id'])) {
    return (int)$row['category_id'];
  }
  $insert = $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)');
  $insert->execute([$name]);
  return (int)$pdo->lastInsertId();
}

function notification_canceled_id(array $payload): ?string {
  $meta = $payload['meta'] ?? [];
  if (!is_array($meta)) {
    return null;
  }
  if (($meta['status'] ?? '') !== 'canceled') {
    return null;
  }
  $webinarId = $meta['webinar_id'] ?? '';
  return $webinarId !== '' ? (string)$webinarId : null;
}

function notification_broadcast_id(array $payload): ?string {
  $meta = $payload['meta'] ?? [];
  if (!is_array($meta)) {
    return null;
  }
  $broadcastId = $meta['broadcast_id'] ?? '';
  return $broadcastId !== '' ? (string)$broadcastId : null;
}

function ensure_tables(): void {
  static $ready = false;
  if ($ready) {
    return;
  }
  $pdo = db_connection();
  $pdo->exec("CREATE TABLE IF NOT EXISTS timezones (
    timezone_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timezone_name VARCHAR(64) NOT NULL UNIQUE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $legacyTimezoneName = $pdo->query("SHOW COLUMNS FROM timezones LIKE 'name'")->fetch();
  if ($legacyTimezoneName) {
    $pdo->exec("ALTER TABLE timezones CHANGE name timezone_name VARCHAR(64) NOT NULL");
  }
  $legacyTimezoneId = $pdo->query("SHOW COLUMNS FROM timezones LIKE 'id'")->fetch();
  $timezoneIdColumn = $pdo->query("SHOW COLUMNS FROM timezones LIKE 'timezone_id'")->fetch();
  if ($legacyTimezoneId && !$timezoneIdColumn) {
    $pdo->exec("ALTER TABLE timezones CHANGE id timezone_id INT UNSIGNED NOT NULL AUTO_INCREMENT");
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(64) NOT NULL UNIQUE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $defaultCategories = [
    'Education',
    'Business',
    'Wellness',
    'Technology',
    'Growth',
    'Marketing',
    'Design',
    'Leadership',
    'Finance',
    'Health',
    'Productivity',
    'Creative'
  ];
  foreach ($defaultCategories as $category) {
    ensure_category_id($pdo, $category);
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    user_id VARCHAR(64) PRIMARY KEY,
    alaehscape_user_id INT UNSIGNED NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL,
    api_token VARCHAR(64) NOT NULL,
    avatar VARCHAR(255) NOT NULL,
    timezone_id INT UNSIGNED NOT NULL,
    phone VARCHAR(64) NULL,
    sms_opt_in TINYINT(1) DEFAULT 0,
    company VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    bio TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $legacyUserId = $pdo->query("SHOW COLUMNS FROM users LIKE 'id'")->fetch();
  $userIdColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_id'")->fetch();
  if ($legacyUserId && !$userIdColumn) {
    $pdo->exec("ALTER TABLE users CHANGE id user_id VARCHAR(64) NOT NULL");
  }
  $interestsColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'interests'")->fetch();
  if ($interestsColumn) {
    $pdo->exec("ALTER TABLE users DROP COLUMN interests");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS webinars (
    webinar_id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    datetime DATETIME NULL,
    duration VARCHAR(32) NULL,
    category_id INT UNSIGNED NULL,
    instructor VARCHAR(255) NULL,
    premium TINYINT(1) DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 0,
    user_id VARCHAR(64) NOT NULL,
    capacity INT DEFAULT 0,
    popularity INT DEFAULT 0,
    image VARCHAR(255) NULL,
    meeting_url VARCHAR(255) NULL,
    status VARCHAR(32) DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webinars_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $legacyHostId = $pdo->query("SHOW COLUMNS FROM webinars LIKE 'host_id'")->fetch();
  $webinarUserId = $pdo->query("SHOW COLUMNS FROM webinars LIKE 'user_id'")->fetch();
  if ($legacyHostId && !$webinarUserId) {
    $pdo->exec("ALTER TABLE webinars CHANGE host_id user_id VARCHAR(64) NOT NULL");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (
    registration_id VARCHAR(64) PRIMARY KEY,
    webinar_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    registered_at DATETIME NOT NULL,
    status VARCHAR(32) NOT NULL,
    notification_id VARCHAR(64) NULL,
    INDEX idx_reg_webinar (webinar_id),
    INDEX idx_reg_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $registrationNotification = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'notification_id'")->fetch();
  if (!$registrationNotification) {
    $pdo->exec("ALTER TABLE registrations ADD COLUMN notification_id VARCHAR(64) NULL");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
    subscription_id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    plan VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    renewal_at DATETIME NOT NULL,
    provider VARCHAR(32) NOT NULL,
    notification_id VARCHAR(64) NULL,
    INDEX idx_sub_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $subscriptionNotification = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE 'notification_id'")->fetch();
  if (!$subscriptionNotification) {
    $pdo->exec("ALTER TABLE subscriptions ADD COLUMN notification_id VARCHAR(64) NULL");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    payment_id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    provider VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    capture_id VARCHAR(128) NULL,
    refund_id VARCHAR(128) NULL,
    refunded_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    notification_id VARCHAR(64) NULL,
    INDEX idx_pay_webinar (webinar_id),
    INDEX idx_pay_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $paymentNotification = $pdo->query("SHOW COLUMNS FROM payments LIKE 'notification_id'")->fetch();
  if (!$paymentNotification) {
    $pdo->exec("ALTER TABLE payments ADD COLUMN notification_id VARCHAR(64) NULL");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    notification_id VARCHAR(64) PRIMARY KEY,
    type VARCHAR(32) NOT NULL,
    payload JSON NOT NULL,
    canceled_id VARCHAR(64) NULL,
    broadcast_id VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_note_type (type),
    INDEX idx_note_canceled (canceled_id),
    INDEX idx_note_broadcast (broadcast_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
    attendance_id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL,
    timestamp DATETIME NOT NULL,
    INDEX idx_att_webinar (webinar_id),
    INDEX idx_att_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS canceled (
    canceled_id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    canceled_at DATETIME NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $legacyCanceledId = $pdo->query("SHOW COLUMNS FROM canceled LIKE 'id'")->fetch();
  $canceledIdColumn = $pdo->query("SHOW COLUMNS FROM canceled LIKE 'canceled_id'")->fetch();
  if ($legacyCanceledId && !$canceledIdColumn) {
    $pdo->exec("ALTER TABLE canceled CHANGE id canceled_id VARCHAR(64) NOT NULL");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
    admin_id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $adminSnapshotColumn = $pdo->query("SHOW COLUMNS FROM admin LIKE 'snapshot'")->fetch();
  if ($adminSnapshotColumn) {
    $pdo->exec("ALTER TABLE admin DROP COLUMN snapshot");
  }
  $legacyAdminId = $pdo->query("SHOW COLUMNS FROM admin LIKE 'id'")->fetch();
  $adminIdColumn = $pdo->query("SHOW COLUMNS FROM admin LIKE 'admin_id'")->fetch();
  if ($legacyAdminId && !$adminIdColumn) {
    $pdo->exec("ALTER TABLE admin CHANGE id admin_id VARCHAR(64) NOT NULL");
  }
  $legacyAdminUser = $pdo->query("SHOW COLUMNS FROM admin LIKE 'admin_user_id'")->fetch();
  $adminUserColumn = $pdo->query("SHOW COLUMNS FROM admin LIKE 'user_id'")->fetch();
  if ($legacyAdminUser && !$adminUserColumn) {
    $pdo->exec("ALTER TABLE admin CHANGE admin_user_id user_id VARCHAR(64) NOT NULL");
  }
  $adminWebinarColumn = $pdo->query("SHOW COLUMNS FROM admin LIKE 'webinar_id'")->fetch();
  if ($adminWebinarColumn) {
    $pdo->exec("ALTER TABLE admin DROP COLUMN webinar_id");
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS admin_broadcasts (
    admin_broadcast_id VARCHAR(64) PRIMARY KEY,
    admin_id VARCHAR(64) NOT NULL,
    notification_id VARCHAR(64) NULL,
    message TEXT NOT NULL,
    category VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_broadcast_admin (admin_id),
    INDEX idx_admin_broadcast_notification (notification_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS admin_webinar_actions (
    admin_webinar_action_id VARCHAR(64) PRIMARY KEY,
    admin_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NOT NULL,
    action VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_webinar_user (admin_id),
    INDEX idx_admin_webinar_id (webinar_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS saved (
    saved_id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NOT NULL,
    saved_at DATETIME NOT NULL,
    INDEX idx_saved_user (user_id),
    INDEX idx_saved_webinar (webinar_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist (
    waitlist_id VARCHAR(64) PRIMARY KEY,
    webinar_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    notification_id VARCHAR(64) NULL,
    INDEX idx_waitlist_webinar (webinar_id),
    INDEX idx_waitlist_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $waitlistNotification = $pdo->query("SHOW COLUMNS FROM waitlist LIKE 'notification_id'")->fetch();
  if (!$waitlistNotification) {
    $pdo->exec("ALTER TABLE waitlist ADD COLUMN notification_id VARCHAR(64) NULL");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
    feedback_id VARCHAR(64) PRIMARY KEY,
    webinar_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    content TEXT NOT NULL,
    rating TINYINT NOT NULL,
    created_at DATETIME NOT NULL,
    notification_id VARCHAR(64) NULL,
    INDEX idx_feedback_webinar (webinar_id),
    INDEX idx_feedback_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $feedbackNotification = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'notification_id'")->fetch();
  if (!$feedbackNotification) {
    $pdo->exec("ALTER TABLE feedback ADD COLUMN notification_id VARCHAR(64) NULL");
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS sms_feedback (
    sms_feedback_id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NULL,
    webinar_id VARCHAR(64) NULL,
    phone VARCHAR(64) NOT NULL,
    message TEXT NOT NULL,
    raw_payload JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_sms_feedback_user (user_id),
    INDEX idx_sms_feedback_webinar (webinar_id),
    INDEX idx_sms_feedback_phone (phone)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("DROP TABLE IF EXISTS admin_snapshots");

  $legacyAdminBroadcastUser = $pdo->query("SHOW COLUMNS FROM admin_broadcasts LIKE 'admin_user_id'")->fetch();
  $adminBroadcastAdmin = $pdo->query("SHOW COLUMNS FROM admin_broadcasts LIKE 'admin_id'")->fetch();
  if ($legacyAdminBroadcastUser && !$adminBroadcastAdmin) {
    $pdo->exec("ALTER TABLE admin_broadcasts CHANGE admin_user_id admin_id VARCHAR(64) NOT NULL");
    $adminBroadcastAdmin = true;
  }
  if (!$adminBroadcastAdmin) {
    $pdo->exec("ALTER TABLE admin_broadcasts ADD COLUMN admin_id VARCHAR(64) NOT NULL");
  }
  $adminBroadcastNotification = $pdo->query("SHOW COLUMNS FROM admin_broadcasts LIKE 'notification_id'")->fetch();
  if (!$adminBroadcastNotification) {
    $pdo->exec("ALTER TABLE admin_broadcasts ADD COLUMN notification_id VARCHAR(64) NULL");
  }

  $legacyAdminWebinarUser = $pdo->query("SHOW COLUMNS FROM admin_webinar_actions LIKE 'admin_user_id'")->fetch();
  $adminWebinarAdmin = $pdo->query("SHOW COLUMNS FROM admin_webinar_actions LIKE 'admin_id'")->fetch();
  if ($legacyAdminWebinarUser && !$adminWebinarAdmin) {
    $pdo->exec("ALTER TABLE admin_webinar_actions CHANGE admin_user_id admin_id VARCHAR(64) NOT NULL");
    $adminWebinarAdmin = true;
  }
  if (!$adminWebinarAdmin) {
    $pdo->exec("ALTER TABLE admin_webinar_actions ADD COLUMN admin_id VARCHAR(64) NOT NULL");
  }

  $pdo->exec("DROP TABLE IF EXISTS data_store");

  $primaryKeyRenames = [
    ['table' => 'webinars', 'old' => 'id', 'new' => 'webinar_id'],
    ['table' => 'registrations', 'old' => 'id', 'new' => 'registration_id'],
    ['table' => 'subscriptions', 'old' => 'id', 'new' => 'subscription_id'],
    ['table' => 'payments', 'old' => 'id', 'new' => 'payment_id'],
    ['table' => 'notifications', 'old' => 'id', 'new' => 'notification_id'],
    ['table' => 'attendance', 'old' => 'id', 'new' => 'attendance_id'],
    ['table' => 'admin_broadcasts', 'old' => 'id', 'new' => 'admin_broadcast_id'],
    ['table' => 'admin_webinar_actions', 'old' => 'id', 'new' => 'admin_webinar_action_id'],
    ['table' => 'saved', 'old' => 'id', 'new' => 'saved_id'],
    ['table' => 'waitlist', 'old' => 'id', 'new' => 'waitlist_id'],
    ['table' => 'feedback', 'old' => 'id', 'new' => 'feedback_id'],
    ['table' => 'sms_feedback', 'old' => 'id', 'new' => 'sms_feedback_id']
  ];
  foreach ($primaryKeyRenames as $rename) {
    $oldColumn = $pdo->query("SHOW COLUMNS FROM {$rename['table']} LIKE '{$rename['old']}'")->fetch();
    $newColumn = $pdo->query("SHOW COLUMNS FROM {$rename['table']} LIKE '{$rename['new']}'")->fetch();
    if ($oldColumn && !$newColumn) {
      $pdo->exec("ALTER TABLE {$rename['table']} CHANGE {$rename['old']} {$rename['new']} VARCHAR(64) NOT NULL");
    }
  }

  $defaultTimezoneId = ensure_timezone_id($pdo, 'UTC');
  $columnsToAdd = [
    'alaehscape_user_id' => "ALTER TABLE users ADD COLUMN alaehscape_user_id INT UNSIGNED NULL",
    'sms_opt_in' => "ALTER TABLE users ADD COLUMN sms_opt_in TINYINT(1) DEFAULT 0",
    'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(255) NULL",
    'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(255) NULL",
    'timezone_id' => "ALTER TABLE users ADD COLUMN timezone_id INT UNSIGNED NULL"
  ];
  foreach ($columnsToAdd as $column => $statement) {
    $exists = $pdo->query("SHOW COLUMNS FROM users LIKE '$column'")->fetch();
    if (!$exists) {
      $pdo->exec($statement);
    }
  }
  $alaehscapeColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'alaehscape_user_id'")->fetch();
  if ($alaehscapeColumn && stripos((string)$alaehscapeColumn['Type'], 'int') === false) {
    $pdo->exec('ALTER TABLE users MODIFY alaehscape_user_id INT UNSIGNED NULL');
  }
  $timezoneColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'timezone'")->fetch();
  if ($timezoneColumn) {
    $timezoneRows = $pdo->query("SELECT DISTINCT timezone FROM users WHERE timezone IS NOT NULL AND timezone <> ''")->fetchAll();
    foreach ($timezoneRows as $row) {
      ensure_timezone_id($pdo, $row['timezone']);
    }
    $pdo->exec("UPDATE users u
      JOIN timezones t ON t.timezone_name = u.timezone
      SET u.timezone_id = t.timezone_id
      WHERE u.timezone IS NOT NULL AND u.timezone <> ''");
  }
  $pdo->prepare('UPDATE users SET timezone_id = ? WHERE timezone_id IS NULL OR timezone_id = 0')->execute([$defaultTimezoneId]);
  $timezoneIdColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'timezone_id'")->fetch();
  if ($timezoneIdColumn && ($timezoneIdColumn['Null'] ?? '') === 'YES') {
    $pdo->exec('ALTER TABLE users MODIFY timezone_id INT UNSIGNED NOT NULL');
  }
  $nameColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'name'")->fetch();
  if ($nameColumn) {
    $pdo->exec("UPDATE users
      SET first_name = IF(first_name IS NULL OR first_name = '', SUBSTRING_INDEX(name, ' ', 1), first_name),
          last_name = IF(last_name IS NULL OR last_name = '', TRIM(SUBSTRING(name, LOCATE(' ', name) + 1)), last_name)
      WHERE name IS NOT NULL AND name <> ''");
    $pdo->exec("ALTER TABLE users DROP COLUMN name");
  }

  $paymentColumnsToAdd = [
    'capture_id' => "ALTER TABLE payments ADD COLUMN capture_id VARCHAR(128) NULL",
    'refund_id' => "ALTER TABLE payments ADD COLUMN refund_id VARCHAR(128) NULL",
    'refunded_at' => "ALTER TABLE payments ADD COLUMN refunded_at DATETIME NULL"
  ];
  foreach ($paymentColumnsToAdd as $column => $statement) {
    $exists = $pdo->query("SHOW COLUMNS FROM payments LIKE '$column'")->fetch();
    if (!$exists) {
      $pdo->exec($statement);
    }
  }
  $webinarCategoryColumn = $pdo->query("SHOW COLUMNS FROM webinars LIKE 'category_id'")->fetch();
  if (!$webinarCategoryColumn) {
    $pdo->exec("ALTER TABLE webinars ADD COLUMN category_id INT UNSIGNED NULL");
  }
  $webinarCategoryNameColumn = $pdo->query("SHOW COLUMNS FROM webinars LIKE 'category'")->fetch();
  if ($webinarCategoryNameColumn) {
    $categoryRows = $pdo->query("SELECT DISTINCT category FROM webinars WHERE category IS NOT NULL AND category <> ''")->fetchAll();
    foreach ($categoryRows as $row) {
      ensure_category_id($pdo, $row['category']);
    }
    $pdo->exec("UPDATE webinars w
      JOIN categories c ON c.category_name = w.category
      SET w.category_id = c.category_id
      WHERE w.category IS NOT NULL AND w.category <> ''");
  }
  $noteCanceledColumn = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'canceled_id'")->fetch();
  if (!$noteCanceledColumn) {
    $pdo->exec("ALTER TABLE notifications ADD COLUMN canceled_id VARCHAR(64) NULL");
  }
  $noteBroadcastColumn = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'broadcast_id'")->fetch();
  if (!$noteBroadcastColumn) {
    $pdo->exec("ALTER TABLE notifications ADD COLUMN broadcast_id VARCHAR(64) NULL");
  }

  $ready = true;
}

function read_json(string $file): array {
  $dataset = dataset_from_file($file);
  if ($dataset === 'admin_snapshots') {
    return read_json_file($file);
  }
  ensure_tables();
  $pdo = db_connection();

  switch ($dataset) {
    case 'users':
      $rows = $pdo->query('SELECT users.*, timezones.timezone_name AS timezone_name
        FROM users
        LEFT JOIN timezones ON users.timezone_id = timezones.timezone_id
        ORDER BY users.created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['sms_opt_in'] = (bool)($row['sms_opt_in'] ?? 0);
        $row['first_name'] = $row['first_name'] ?? '';
        $row['last_name'] = $row['last_name'] ?? '';
        $row['timezone_id'] = (int)($row['timezone_id'] ?? 0);
        $row['timezone'] = $row['timezone_name'] ?? ($row['timezone'] ?? 'UTC');
        unset($row['timezone_name']);
        return $row;
      }, $rows);
    case 'webinars':
      $rows = $pdo->query('SELECT webinars.*, categories.category_name AS category_name
        FROM webinars
        LEFT JOIN categories ON webinars.category_id = categories.category_id
        ORDER BY webinars.created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['webinar_id'] ?? '';
        $row['premium'] = (bool)($row['premium'] ?? 0);
        $row['price'] = (float)($row['price'] ?? 0);
        $row['capacity'] = (int)($row['capacity'] ?? 0);
        $row['popularity'] = (int)($row['popularity'] ?? 0);
        $row['category_id'] = isset($row['category_id']) ? (int)$row['category_id'] : null;
        $row['category'] = $row['category_name'] ?? ($row['category'] ?? null);
        unset($row['category_name']);
        if (!empty($row['datetime'])) {
          $row['datetime'] = gmdate('c', strtotime($row['datetime']));
        }
        return $row;
      }, $rows);
    case 'registrations':
      $rows = $pdo->query('SELECT * FROM registrations ORDER BY registered_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['registration_id'] ?? '';
        if (!empty($row['registered_at'])) {
          $row['registered_at'] = gmdate('c', strtotime($row['registered_at']));
        }
        return $row;
      }, $rows);
    case 'subscriptions':
      $rows = $pdo->query('SELECT * FROM subscriptions ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['subscription_id'] ?? '';
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        if (!empty($row['renewal_at'])) {
          $row['renewal_at'] = gmdate('c', strtotime($row['renewal_at']));
        }
        return $row;
      }, $rows);
    case 'attendance':
      $rows = $pdo->query('SELECT * FROM attendance ORDER BY timestamp ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['attendance_id'] ?? '';
        if (!empty($row['timestamp'])) {
          $row['timestamp'] = gmdate('c', strtotime($row['timestamp']));
        }
        return $row;
      }, $rows);
    case 'payments':
      $rows = $pdo->query('SELECT * FROM payments ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['payment_id'] ?? '';
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'notifications':
      $rows = $pdo->query('SELECT * FROM notifications ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['notification_id'] ?? '';
        $row['payload'] = $row['payload'] ? json_decode($row['payload'], true) : [];
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'timezones':
      $rows = $pdo->query('SELECT timezone_name FROM timezones ORDER BY timezone_name ASC')->fetchAll();
      return array_map(fn($row) => $row['timezone_name'], $rows);
    case 'admin':
      $rows = $pdo->query('SELECT * FROM admin ORDER BY created_at DESC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['admin_id'] ?? '';
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'admin_broadcasts':
      $rows = $pdo->query('SELECT * FROM admin_broadcasts ORDER BY created_at DESC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['admin_broadcast_id'] ?? '';
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'admin_webinar_actions':
      $rows = $pdo->query('SELECT * FROM admin_webinar_actions ORDER BY created_at DESC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['admin_webinar_action_id'] ?? '';
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'canceled':
      $rows = $pdo->query('SELECT * FROM canceled ORDER BY canceled_at DESC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['canceled_id'] ?? '';
        if (!empty($row['canceled_at'])) {
          $row['canceled_at'] = gmdate('c', strtotime($row['canceled_at']));
        }
        return $row;
      }, $rows);
    case 'saved':
      $rows = $pdo->query('SELECT * FROM saved ORDER BY saved_at DESC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['saved_id'] ?? '';
        if (!empty($row['saved_at'])) {
          $row['saved_at'] = gmdate('c', strtotime($row['saved_at']));
        }
        return $row;
      }, $rows);
    case 'waitlist':
      $rows = $pdo->query('SELECT * FROM waitlist ORDER BY created_at DESC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['waitlist_id'] ?? '';
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'feedback':
      $rows = $pdo->query('SELECT * FROM feedback ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['feedback_id'] ?? '';
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'sms_feedback':
      $rows = $pdo->query('SELECT * FROM sms_feedback ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['id'] = $row['sms_feedback_id'] ?? '';
        if (!empty($row['raw_payload'])) {
          $row['raw_payload'] = json_decode($row['raw_payload'], true);
        }
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    default:
      return [];
  }
}

function write_json(string $file, array $data): void {
  $dataset = dataset_from_file($file);
  if ($dataset === 'admin_snapshots') {
    write_json_file($file, $data);
    return;
  }
  ensure_tables();
  $pdo = db_connection();
  $pdo->beginTransaction();

  switch ($dataset) {
    case 'users':
      $pdo->exec('DELETE FROM users');
      $hasTimezoneColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'timezone'")->fetch();
      if ($hasTimezoneColumn) {
        $stmt = $pdo->prepare('INSERT INTO users (user_id, alaehscape_user_id, first_name, last_name, email, password_hash, role, api_token, avatar, timezone_id, timezone, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      } else {
        $stmt = $pdo->prepare('INSERT INTO users (user_id, alaehscape_user_id, first_name, last_name, email, password_hash, role, api_token, avatar, timezone_id, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      }
      foreach ($data as $user) {
        $timezoneValue = $user['timezone'] ?? 'UTC';
        $timezoneId = ensure_timezone_id($pdo, $timezoneValue);
        $values = [
          $user['user_id'] ?? '',
          $user['alaehscape_user_id'] ?? null,
          $user['first_name'] ?? '',
          $user['last_name'] ?? '',
          $user['email'] ?? '',
          $user['password_hash'] ?? '',
          $user['role'] ?? 'member',
          $user['api_token'] ?? '',
          $user['avatar'] ?? '',
          $timezoneId
        ];
        if ($hasTimezoneColumn) {
          $values[] = $timezoneValue;
        }
        $values[] = $user['phone'] ?? null;
        $values[] = !empty($user['sms_opt_in']) ? 1 : 0;
        $values[] = $user['company'] ?? null;
        $values[] = $user['location'] ?? null;
        $values[] = $user['bio'] ?? null;
        $stmt->execute($values);
      }
      break;
    case 'webinars':
      $pdo->exec('DELETE FROM webinars');
      $stmt = $pdo->prepare('INSERT INTO webinars (webinar_id, title, description, datetime, duration, category_id, instructor, premium, price, user_id, capacity, popularity, image, meeting_url, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $webinar) {
        $categoryId = ensure_category_id($pdo, $webinar['category'] ?? null);
        $stmt->execute([
          $webinar['id'] ?? '',
          $webinar['title'] ?? '',
          $webinar['description'] ?? '',
          !empty($webinar['datetime']) ? date('Y-m-d H:i:s', strtotime($webinar['datetime'])) : null,
          $webinar['duration'] ?? null,
          $categoryId,
          $webinar['instructor'] ?? null,
          !empty($webinar['premium']) ? 1 : 0,
          $webinar['price'] ?? 0,
          $webinar['user_id'] ?? '',
          (int)($webinar['capacity'] ?? 0),
          (int)($webinar['popularity'] ?? 0),
          $webinar['image'] ?? null,
          $webinar['meeting_url'] ?? null,
          $webinar['status'] ?? 'published',
          !empty($webinar['created_at']) ? date('Y-m-d H:i:s', strtotime($webinar['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'registrations':
      $pdo->exec('DELETE FROM registrations');
      $stmt = $pdo->prepare('INSERT INTO registrations (registration_id, webinar_id, user_id, registered_at, status, notification_id) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['user_id'] ?? '',
          !empty($row['registered_at']) ? date('Y-m-d H:i:s', strtotime($row['registered_at'])) : date('Y-m-d H:i:s'),
          $row['status'] ?? 'registered',
          $row['notification_id'] ?? null
        ]);
      }
      break;
    case 'subscriptions':
      $pdo->exec('DELETE FROM subscriptions');
      $stmt = $pdo->prepare('INSERT INTO subscriptions (subscription_id, user_id, plan, status, created_at, renewal_at, provider, notification_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['user_id'] ?? '',
          $row['plan'] ?? 'monthly',
          $row['status'] ?? 'active',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s'),
          !empty($row['renewal_at']) ? date('Y-m-d H:i:s', strtotime($row['renewal_at'])) : date('Y-m-d H:i:s'),
          $row['provider'] ?? 'paypal',
          $row['notification_id'] ?? null
        ]);
      }
      break;
    case 'attendance':
      $pdo->exec('DELETE FROM attendance');
      $stmt = $pdo->prepare('INSERT INTO attendance (attendance_id, user_id, webinar_id, status, timestamp) VALUES (?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['user_id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['status'] ?? 'attended',
          !empty($row['timestamp']) ? date('Y-m-d H:i:s', strtotime($row['timestamp'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'payments':
      $pdo->exec('DELETE FROM payments');
      $stmt = $pdo->prepare('INSERT INTO payments (payment_id, user_id, webinar_id, amount, provider, status, capture_id, refund_id, refunded_at, created_at, notification_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['user_id'] ?? '',
          $row['webinar_id'] ?? null,
          $row['amount'] ?? 0,
          $row['provider'] ?? 'paypal',
          $row['status'] ?? 'captured',
          $row['capture_id'] ?? null,
          $row['refund_id'] ?? null,
          !empty($row['refunded_at']) ? date('Y-m-d H:i:s', strtotime($row['refunded_at'])) : null,
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s'),
          $row['notification_id'] ?? null
        ]);
      }
      break;
    case 'notifications':
      $pdo->exec('DELETE FROM notifications');
      $stmt = $pdo->prepare('INSERT INTO notifications (notification_id, type, payload, canceled_id, broadcast_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $payload = $row['payload'] ?? [];
        $canceledId = notification_canceled_id($payload);
        $broadcastId = notification_broadcast_id($payload);
        $stmt->execute([
          $row['id'] ?? '',
          $row['type'] ?? 'in-app',
          json_encode($payload, JSON_UNESCAPED_SLASHES),
          $canceledId,
          $broadcastId,
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'timezones':
      foreach ($data as $tz) {
        ensure_timezone_id($pdo, $tz);
      }
      break;
    case 'admin':
      $pdo->exec('DELETE FROM admin');
      $stmt = $pdo->prepare('INSERT INTO admin (admin_id, user_id, created_at) VALUES (?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['admin_id'] ?? '',
          $row['user_id'] ?? '',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'admin_broadcasts':
      $pdo->exec('DELETE FROM admin_broadcasts');
      $stmt = $pdo->prepare('INSERT INTO admin_broadcasts (admin_broadcast_id, admin_id, notification_id, message, category, created_at) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $adminId = $row['admin_id'] ?? $row['admin_user_id'] ?? '';
        $stmt->execute([
          $row['id'] ?? '',
          $adminId,
          $row['notification_id'] ?? null,
          $row['message'] ?? '',
          $row['category'] ?? 'general',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'admin_webinar_actions':
      $pdo->exec('DELETE FROM admin_webinar_actions');
      $stmt = $pdo->prepare('INSERT INTO admin_webinar_actions (admin_webinar_action_id, admin_id, webinar_id, action, created_at) VALUES (?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $adminId = $row['admin_id'] ?? $row['admin_user_id'] ?? '';
        $stmt->execute([
          $row['id'] ?? '',
          $adminId,
          $row['webinar_id'] ?? '',
          $row['action'] ?? '',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'canceled':
      $pdo->exec('DELETE FROM canceled');
      $stmt = $pdo->prepare('INSERT INTO canceled (canceled_id, title, canceled_at) VALUES (?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['canceled_id'] ?? '',
          $row['title'] ?? '',
          !empty($row['canceled_at']) ? date('Y-m-d H:i:s', strtotime($row['canceled_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'saved':
      $pdo->exec('DELETE FROM saved');
      $stmt = $pdo->prepare('INSERT INTO saved (saved_id, user_id, webinar_id, saved_at) VALUES (?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['user_id'] ?? '',
          $row['webinar_id'] ?? '',
          !empty($row['saved_at']) ? date('Y-m-d H:i:s', strtotime($row['saved_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'waitlist':
      $pdo->exec('DELETE FROM waitlist');
      $stmt = $pdo->prepare('INSERT INTO waitlist (waitlist_id, webinar_id, user_id, created_at, notification_id) VALUES (?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['user_id'] ?? '',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s'),
          $row['notification_id'] ?? null
        ]);
      }
      break;
    case 'feedback':
      $pdo->exec('DELETE FROM feedback');
      $stmt = $pdo->prepare('INSERT INTO feedback (feedback_id, webinar_id, user_id, content, rating, created_at, notification_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['user_id'] ?? '',
          $row['content'] ?? '',
          (int)($row['rating'] ?? 0),
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s'),
          $row['notification_id'] ?? null
        ]);
      }
      break;
    default:
      break;
  }

  $pdo->commit();
}

function append_json(string $file, array $item): void {
  $dataset = dataset_from_file($file);
  if ($dataset === 'admin_snapshots') {
    $data = read_json_file($file);
    $data[] = $item;
    write_json_file($file, $data);
    return;
  }
  ensure_tables();
  $pdo = db_connection();

  switch ($dataset) {
    case 'users':
      $hasTimezoneColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'timezone'")->fetch();
      if ($hasTimezoneColumn) {
        $stmt = $pdo->prepare('INSERT INTO users (user_id, alaehscape_user_id, first_name, last_name, email, password_hash, role, api_token, avatar, timezone_id, timezone, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      } else {
        $stmt = $pdo->prepare('INSERT INTO users (user_id, alaehscape_user_id, first_name, last_name, email, password_hash, role, api_token, avatar, timezone_id, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      }
      $timezoneValue = $item['timezone'] ?? 'UTC';
      $timezoneId = ensure_timezone_id($pdo, $timezoneValue);
      $values = [
        $item['user_id'] ?? '',
        $item['alaehscape_user_id'] ?? null,
        $item['first_name'] ?? '',
        $item['last_name'] ?? '',
        $item['email'] ?? '',
        $item['password_hash'] ?? '',
        $item['role'] ?? 'member',
        $item['api_token'] ?? '',
        $item['avatar'] ?? '',
        $timezoneId
      ];
      if ($hasTimezoneColumn) {
        $values[] = $timezoneValue;
      }
      $values[] = $item['phone'] ?? null;
      $values[] = !empty($item['sms_opt_in']) ? 1 : 0;
      $values[] = $item['company'] ?? null;
      $values[] = $item['location'] ?? null;
      $values[] = $item['bio'] ?? null;
      $stmt->execute($values);
      break;
    case 'webinars':
      $stmt = $pdo->prepare('INSERT INTO webinars (webinar_id, title, description, datetime, duration, category_id, instructor, premium, price, user_id, capacity, popularity, image, meeting_url, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $categoryId = ensure_category_id($pdo, $item['category'] ?? null);
      $stmt->execute([
        $item['id'] ?? '',
        $item['title'] ?? '',
        $item['description'] ?? '',
        !empty($item['datetime']) ? date('Y-m-d H:i:s', strtotime($item['datetime'])) : null,
        $item['duration'] ?? null,
        $categoryId,
        $item['instructor'] ?? null,
        !empty($item['premium']) ? 1 : 0,
        $item['price'] ?? 0,
        $item['user_id'] ?? '',
        (int)($item['capacity'] ?? 0),
        (int)($item['popularity'] ?? 0),
        $item['image'] ?? null,
        $item['meeting_url'] ?? null,
        $item['status'] ?? 'published',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'registrations':
      $stmt = $pdo->prepare('INSERT INTO registrations (registration_id, webinar_id, user_id, registered_at, status, notification_id) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['user_id'] ?? '',
        !empty($item['registered_at']) ? date('Y-m-d H:i:s', strtotime($item['registered_at'])) : date('Y-m-d H:i:s'),
        $item['status'] ?? 'registered',
        $item['notification_id'] ?? null
      ]);
      break;
    case 'subscriptions':
      $stmt = $pdo->prepare('INSERT INTO subscriptions (subscription_id, user_id, plan, status, created_at, renewal_at, provider, notification_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['plan'] ?? 'monthly',
        $item['status'] ?? 'active',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s'),
        !empty($item['renewal_at']) ? date('Y-m-d H:i:s', strtotime($item['renewal_at'])) : date('Y-m-d H:i:s'),
        $item['provider'] ?? 'paypal',
        $item['notification_id'] ?? null
      ]);
      break;
    case 'attendance':
      $stmt = $pdo->prepare('INSERT INTO attendance (attendance_id, user_id, webinar_id, status, timestamp) VALUES (?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['status'] ?? 'attended',
        !empty($item['timestamp']) ? date('Y-m-d H:i:s', strtotime($item['timestamp'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'payments':
      $stmt = $pdo->prepare('INSERT INTO payments (payment_id, user_id, webinar_id, amount, provider, status, created_at, notification_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['webinar_id'] ?? null,
        $item['amount'] ?? 0,
        $item['provider'] ?? 'paypal',
        $item['status'] ?? 'captured',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s'),
        $item['notification_id'] ?? null
      ]);
      break;
    case 'notifications':
      $stmt = $pdo->prepare('INSERT INTO notifications (notification_id, type, payload, canceled_id, broadcast_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
      $payload = $item['payload'] ?? [];
      $stmt->execute([
        $item['id'] ?? '',
        $item['type'] ?? 'in-app',
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        notification_canceled_id($payload),
        notification_broadcast_id($payload),
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'timezones':
      ensure_timezone_id($pdo, (string)$item);
      break;
    case 'admin':
      $stmt = $pdo->prepare('INSERT INTO admin (admin_id, user_id, created_at) VALUES (?, ?, ?)');
      $stmt->execute([
        $item['admin_id'] ?? '',
        $item['user_id'] ?? '',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'admin_broadcasts':
      $stmt = $pdo->prepare('INSERT INTO admin_broadcasts (admin_broadcast_id, admin_id, notification_id, message, category, created_at) VALUES (?, ?, ?, ?, ?, ?)');
      $adminId = $item['admin_id'] ?? $item['admin_user_id'] ?? '';
      $stmt->execute([
        $item['id'] ?? '',
        $adminId,
        $item['notification_id'] ?? null,
        $item['message'] ?? '',
        $item['category'] ?? 'general',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'admin_webinar_actions':
      $stmt = $pdo->prepare('INSERT INTO admin_webinar_actions (admin_webinar_action_id, admin_id, webinar_id, action, created_at) VALUES (?, ?, ?, ?, ?)');
      $adminId = $item['admin_id'] ?? $item['admin_user_id'] ?? '';
      $stmt->execute([
        $item['id'] ?? '',
        $adminId,
        $item['webinar_id'] ?? '',
        $item['action'] ?? '',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'canceled':
      $stmt = $pdo->prepare('INSERT INTO canceled (canceled_id, title, canceled_at) VALUES (?, ?, ?)');
      $stmt->execute([
        $item['canceled_id'] ?? '',
        $item['title'] ?? '',
        !empty($item['canceled_at']) ? date('Y-m-d H:i:s', strtotime($item['canceled_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'saved':
      $stmt = $pdo->prepare('INSERT INTO saved (saved_id, user_id, webinar_id, saved_at) VALUES (?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['webinar_id'] ?? '',
        !empty($item['saved_at']) ? date('Y-m-d H:i:s', strtotime($item['saved_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'waitlist':
      $stmt = $pdo->prepare('INSERT INTO waitlist (waitlist_id, webinar_id, user_id, created_at, notification_id) VALUES (?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['user_id'] ?? '',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s'),
        $item['notification_id'] ?? null
      ]);
      break;
    case 'feedback':
      $stmt = $pdo->prepare('INSERT INTO feedback (feedback_id, webinar_id, user_id, content, rating, created_at, notification_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['user_id'] ?? '',
        $item['content'] ?? '',
        (int)($item['rating'] ?? 0),
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s'),
        $item['notification_id'] ?? null
      ]);
      break;
    default:
      break;
  }
}
