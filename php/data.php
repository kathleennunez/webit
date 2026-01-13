<?php
function data_path(string $file): string {
  return DATA_DIR . '/' . $file;
}

function dataset_from_file(string $file): string {
  return preg_replace('/\.json$/', '', basename($file));
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
    id VARCHAR(64) PRIMARY KEY,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL,
    interests JSON NULL,
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

  $pdo->exec("CREATE TABLE IF NOT EXISTS webinars (
    id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    datetime DATETIME NULL,
    duration VARCHAR(32) NULL,
    category_id INT UNSIGNED NULL,
    instructor VARCHAR(255) NULL,
    premium TINYINT(1) DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 0,
    host_id VARCHAR(64) NOT NULL,
    capacity INT DEFAULT 0,
    popularity INT DEFAULT 0,
    image VARCHAR(255) NULL,
    meeting_url VARCHAR(255) NULL,
    status VARCHAR(32) DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webinars_host (host_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (
    id VARCHAR(64) PRIMARY KEY,
    webinar_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    registered_at DATETIME NOT NULL,
    status VARCHAR(32) NOT NULL,
    INDEX idx_reg_webinar (webinar_id),
    INDEX idx_reg_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    plan VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    renewal_at DATETIME NOT NULL,
    provider VARCHAR(32) NOT NULL,
    INDEX idx_sub_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    provider VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    capture_id VARCHAR(128) NULL,
    refund_id VARCHAR(128) NULL,
    refunded_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_pay_webinar (webinar_id),
    INDEX idx_pay_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id VARCHAR(64) PRIMARY KEY,
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
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL,
    timestamp DATETIME NOT NULL,
    INDEX idx_att_webinar (webinar_id),
    INDEX idx_att_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS canceled (
    id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    canceled_at DATETIME NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
    id VARCHAR(64) PRIMARY KEY,
    admin_user_id VARCHAR(64) NOT NULL,
    snapshot JSON NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_user (admin_user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS admin_broadcasts (
    id VARCHAR(64) PRIMARY KEY,
    admin_user_id VARCHAR(64) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_broadcast_user (admin_user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS admin_webinar_actions (
    id VARCHAR(64) PRIMARY KEY,
    admin_user_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NOT NULL,
    action VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_webinar_user (admin_user_id),
    INDEX idx_admin_webinar_id (webinar_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS saved (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    webinar_id VARCHAR(64) NOT NULL,
    saved_at DATETIME NOT NULL,
    INDEX idx_saved_user (user_id),
    INDEX idx_saved_webinar (webinar_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS waitlist (
    id VARCHAR(64) PRIMARY KEY,
    webinar_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_waitlist_webinar (webinar_id),
    INDEX idx_waitlist_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
    id VARCHAR(64) PRIMARY KEY,
    webinar_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NOT NULL,
    content TEXT NOT NULL,
    rating TINYINT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_feedback_webinar (webinar_id),
    INDEX idx_feedback_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS sms_feedback (
    id VARCHAR(64) PRIMARY KEY,
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

  $defaultTimezoneId = ensure_timezone_id($pdo, 'UTC');
  $columnsToAdd = [
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
  ensure_tables();
  $dataset = dataset_from_file($file);
  $pdo = db_connection();

  switch ($dataset) {
    case 'users':
      $rows = $pdo->query('SELECT users.*, timezones.timezone_name AS timezone_name
        FROM users
        LEFT JOIN timezones ON users.timezone_id = timezones.timezone_id
        ORDER BY users.created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['interests'] = $row['interests'] ? json_decode($row['interests'], true) : [];
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
        if (!empty($row['registered_at'])) {
          $row['registered_at'] = gmdate('c', strtotime($row['registered_at']));
        }
        return $row;
      }, $rows);
    case 'subscriptions':
      $rows = $pdo->query('SELECT * FROM subscriptions ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
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
        if (!empty($row['timestamp'])) {
          $row['timestamp'] = gmdate('c', strtotime($row['timestamp']));
        }
        return $row;
      }, $rows);
    case 'payments':
      $rows = $pdo->query('SELECT * FROM payments ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'notifications':
      $rows = $pdo->query('SELECT * FROM notifications ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
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
        $row['snapshot'] = $row['snapshot'] ? json_decode($row['snapshot'], true) : [];
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'admin_broadcasts':
      $rows = $pdo->query('SELECT * FROM admin_broadcasts ORDER BY created_at DESC')->fetchAll();
      return array_map(function ($row) {
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'admin_webinar_actions':
      $rows = $pdo->query('SELECT * FROM admin_webinar_actions ORDER BY created_at DESC')->fetchAll();
      return array_map(function ($row) {
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'canceled':
      $rows = $pdo->query('SELECT * FROM canceled ORDER BY canceled_at DESC')->fetchAll();
      return array_map(function ($row) {
        if (!empty($row['canceled_at'])) {
          $row['canceled_at'] = gmdate('c', strtotime($row['canceled_at']));
        }
        return $row;
      }, $rows);
    case 'saved':
      $rows = $pdo->query('SELECT * FROM saved ORDER BY saved_at DESC')->fetchAll();
      return array_map(function ($row) {
        if (!empty($row['saved_at'])) {
          $row['saved_at'] = gmdate('c', strtotime($row['saved_at']));
        }
        return $row;
      }, $rows);
    case 'waitlist':
      $rows = $pdo->query('SELECT * FROM waitlist ORDER BY created_at DESC')->fetchAll();
      return array_map(function ($row) {
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'feedback':
      $rows = $pdo->query('SELECT * FROM feedback ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        if (!empty($row['created_at'])) {
          $row['created_at'] = gmdate('c', strtotime($row['created_at']));
        }
        return $row;
      }, $rows);
    case 'sms_feedback':
      $rows = $pdo->query('SELECT * FROM sms_feedback ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
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
  ensure_tables();
  $dataset = dataset_from_file($file);
  $pdo = db_connection();
  $pdo->beginTransaction();

  switch ($dataset) {
    case 'users':
      $pdo->exec('DELETE FROM users');
      $hasTimezoneColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'timezone'")->fetch();
      if ($hasTimezoneColumn) {
        $stmt = $pdo->prepare('INSERT INTO users (id, first_name, last_name, email, password_hash, role, interests, api_token, avatar, timezone_id, timezone, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      } else {
        $stmt = $pdo->prepare('INSERT INTO users (id, first_name, last_name, email, password_hash, role, interests, api_token, avatar, timezone_id, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      }
      foreach ($data as $user) {
        $timezoneValue = $user['timezone'] ?? 'UTC';
        $timezoneId = ensure_timezone_id($pdo, $timezoneValue);
        $values = [
          $user['id'] ?? '',
          $user['first_name'] ?? '',
          $user['last_name'] ?? '',
          $user['email'] ?? '',
          $user['password_hash'] ?? '',
          $user['role'] ?? 'member',
          json_encode($user['interests'] ?? [], JSON_UNESCAPED_SLASHES),
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
      $stmt = $pdo->prepare('INSERT INTO webinars (id, title, description, datetime, duration, category_id, instructor, premium, price, host_id, capacity, popularity, image, meeting_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
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
          $webinar['host_id'] ?? '',
          (int)($webinar['capacity'] ?? 0),
          (int)($webinar['popularity'] ?? 0),
          $webinar['image'] ?? null,
          $webinar['meeting_url'] ?? null,
          $webinar['status'] ?? 'published'
        ]);
      }
      break;
    case 'registrations':
      $pdo->exec('DELETE FROM registrations');
      $stmt = $pdo->prepare('INSERT INTO registrations (id, webinar_id, user_id, registered_at, status) VALUES (?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['user_id'] ?? '',
          !empty($row['registered_at']) ? date('Y-m-d H:i:s', strtotime($row['registered_at'])) : date('Y-m-d H:i:s'),
          $row['status'] ?? 'registered'
        ]);
      }
      break;
    case 'subscriptions':
      $pdo->exec('DELETE FROM subscriptions');
      $stmt = $pdo->prepare('INSERT INTO subscriptions (id, user_id, plan, status, created_at, renewal_at, provider) VALUES (?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['user_id'] ?? '',
          $row['plan'] ?? 'monthly',
          $row['status'] ?? 'active',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s'),
          !empty($row['renewal_at']) ? date('Y-m-d H:i:s', strtotime($row['renewal_at'])) : date('Y-m-d H:i:s'),
          $row['provider'] ?? 'paypal'
        ]);
      }
      break;
    case 'attendance':
      $pdo->exec('DELETE FROM attendance');
      $stmt = $pdo->prepare('INSERT INTO attendance (id, user_id, webinar_id, status, timestamp) VALUES (?, ?, ?, ?, ?)');
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
      $stmt = $pdo->prepare('INSERT INTO payments (id, user_id, webinar_id, amount, provider, status, capture_id, refund_id, refunded_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
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
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'notifications':
      $pdo->exec('DELETE FROM notifications');
      $stmt = $pdo->prepare('INSERT INTO notifications (id, type, payload, canceled_id, broadcast_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
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
      $stmt = $pdo->prepare('INSERT INTO admin (id, admin_user_id, snapshot, created_at) VALUES (?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['admin_user_id'] ?? '',
          json_encode($row['snapshot'] ?? [], JSON_UNESCAPED_SLASHES),
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'admin_broadcasts':
      $pdo->exec('DELETE FROM admin_broadcasts');
      $stmt = $pdo->prepare('INSERT INTO admin_broadcasts (id, admin_user_id, message, category, created_at) VALUES (?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['admin_user_id'] ?? '',
          $row['message'] ?? '',
          $row['category'] ?? 'general',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'admin_webinar_actions':
      $pdo->exec('DELETE FROM admin_webinar_actions');
      $stmt = $pdo->prepare('INSERT INTO admin_webinar_actions (id, admin_user_id, webinar_id, action, created_at) VALUES (?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['admin_user_id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['action'] ?? '',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'canceled':
      $pdo->exec('DELETE FROM canceled');
      $stmt = $pdo->prepare('INSERT INTO canceled (id, title, canceled_at) VALUES (?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['title'] ?? '',
          !empty($row['canceled_at']) ? date('Y-m-d H:i:s', strtotime($row['canceled_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'saved':
      $pdo->exec('DELETE FROM saved');
      $stmt = $pdo->prepare('INSERT INTO saved (id, user_id, webinar_id, saved_at) VALUES (?, ?, ?, ?)');
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
      $stmt = $pdo->prepare('INSERT INTO waitlist (id, webinar_id, user_id, created_at) VALUES (?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['user_id'] ?? '',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'feedback':
      $pdo->exec('DELETE FROM feedback');
      $stmt = $pdo->prepare('INSERT INTO feedback (id, webinar_id, user_id, content, rating, created_at) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['webinar_id'] ?? '',
          $row['user_id'] ?? '',
          $row['content'] ?? '',
          (int)($row['rating'] ?? 0),
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    default:
      break;
  }

  $pdo->commit();
}

function append_json(string $file, array $item): void {
  ensure_tables();
  $dataset = dataset_from_file($file);
  $pdo = db_connection();

  switch ($dataset) {
    case 'users':
      $hasTimezoneColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'timezone'")->fetch();
      if ($hasTimezoneColumn) {
        $stmt = $pdo->prepare('INSERT INTO users (id, first_name, last_name, email, password_hash, role, interests, api_token, avatar, timezone_id, timezone, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      } else {
        $stmt = $pdo->prepare('INSERT INTO users (id, first_name, last_name, email, password_hash, role, interests, api_token, avatar, timezone_id, phone, sms_opt_in, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      }
      $timezoneValue = $item['timezone'] ?? 'UTC';
      $timezoneId = ensure_timezone_id($pdo, $timezoneValue);
      $values = [
        $item['id'] ?? '',
        $item['first_name'] ?? '',
        $item['last_name'] ?? '',
        $item['email'] ?? '',
        $item['password_hash'] ?? '',
        $item['role'] ?? 'member',
        json_encode($item['interests'] ?? [], JSON_UNESCAPED_SLASHES),
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
      $stmt = $pdo->prepare('INSERT INTO webinars (id, title, description, datetime, duration, category_id, instructor, premium, price, host_id, capacity, popularity, image, meeting_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
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
        $item['host_id'] ?? '',
        (int)($item['capacity'] ?? 0),
        (int)($item['popularity'] ?? 0),
        $item['image'] ?? null,
        $item['meeting_url'] ?? null,
        $item['status'] ?? 'published'
      ]);
      break;
    case 'registrations':
      $stmt = $pdo->prepare('INSERT INTO registrations (id, webinar_id, user_id, registered_at, status) VALUES (?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['user_id'] ?? '',
        !empty($item['registered_at']) ? date('Y-m-d H:i:s', strtotime($item['registered_at'])) : date('Y-m-d H:i:s'),
        $item['status'] ?? 'registered'
      ]);
      break;
    case 'subscriptions':
      $stmt = $pdo->prepare('INSERT INTO subscriptions (id, user_id, plan, status, created_at, renewal_at, provider) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['plan'] ?? 'monthly',
        $item['status'] ?? 'active',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s'),
        !empty($item['renewal_at']) ? date('Y-m-d H:i:s', strtotime($item['renewal_at'])) : date('Y-m-d H:i:s'),
        $item['provider'] ?? 'paypal'
      ]);
      break;
    case 'attendance':
      $stmt = $pdo->prepare('INSERT INTO attendance (id, user_id, webinar_id, status, timestamp) VALUES (?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['status'] ?? 'attended',
        !empty($item['timestamp']) ? date('Y-m-d H:i:s', strtotime($item['timestamp'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'payments':
      $stmt = $pdo->prepare('INSERT INTO payments (id, user_id, webinar_id, amount, provider, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['webinar_id'] ?? null,
        $item['amount'] ?? 0,
        $item['provider'] ?? 'paypal',
        $item['status'] ?? 'captured',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'notifications':
      $stmt = $pdo->prepare('INSERT INTO notifications (id, type, payload, canceled_id, broadcast_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
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
      $stmt = $pdo->prepare('INSERT INTO admin (id, admin_user_id, snapshot, created_at) VALUES (?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['admin_user_id'] ?? '',
        json_encode($item['snapshot'] ?? [], JSON_UNESCAPED_SLASHES),
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'admin_broadcasts':
      $stmt = $pdo->prepare('INSERT INTO admin_broadcasts (id, admin_user_id, message, category, created_at) VALUES (?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['admin_user_id'] ?? '',
        $item['message'] ?? '',
        $item['category'] ?? 'general',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'admin_webinar_actions':
      $stmt = $pdo->prepare('INSERT INTO admin_webinar_actions (id, admin_user_id, webinar_id, action, created_at) VALUES (?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['admin_user_id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['action'] ?? '',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'canceled':
      $stmt = $pdo->prepare('INSERT INTO canceled (id, title, canceled_at) VALUES (?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['title'] ?? '',
        !empty($item['canceled_at']) ? date('Y-m-d H:i:s', strtotime($item['canceled_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'saved':
      $stmt = $pdo->prepare('INSERT INTO saved (id, user_id, webinar_id, saved_at) VALUES (?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['user_id'] ?? '',
        $item['webinar_id'] ?? '',
        !empty($item['saved_at']) ? date('Y-m-d H:i:s', strtotime($item['saved_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'waitlist':
      $stmt = $pdo->prepare('INSERT INTO waitlist (id, webinar_id, user_id, created_at) VALUES (?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['user_id'] ?? '',
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'feedback':
      $stmt = $pdo->prepare('INSERT INTO feedback (id, webinar_id, user_id, content, rating, created_at) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['webinar_id'] ?? '',
        $item['user_id'] ?? '',
        $item['content'] ?? '',
        (int)($item['rating'] ?? 0),
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    default:
      break;
  }
}
