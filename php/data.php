<?php
function data_path(string $file): string {
  return DATA_DIR . '/' . $file;
}

function dataset_from_file(string $file): string {
  return preg_replace('/\.json$/', '', basename($file));
}

function ensure_tables(): void {
  static $ready = false;
  if ($ready) {
    return;
  }
  $pdo = db_connection();
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL,
    interests JSON NULL,
    api_token VARCHAR(64) NOT NULL,
    avatar VARCHAR(255) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    phone VARCHAR(64) NULL,
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
    category VARCHAR(64) NULL,
    instructor VARCHAR(255) NULL,
    premium TINYINT(1) DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 0,
    materials JSON NULL,
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
    created_at DATETIME NOT NULL,
    INDEX idx_pay_webinar (webinar_id),
    INDEX idx_pay_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id VARCHAR(64) PRIMARY KEY,
    type VARCHAR(32) NOT NULL,
    payload JSON NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_note_type (type)
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

  $pdo->exec("CREATE TABLE IF NOT EXISTS timezones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE
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

  $ready = true;
}

function read_json(string $file): array {
  ensure_tables();
  $dataset = dataset_from_file($file);
  $pdo = db_connection();

  switch ($dataset) {
    case 'users':
      $rows = $pdo->query('SELECT * FROM users ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['interests'] = $row['interests'] ? json_decode($row['interests'], true) : [];
        return $row;
      }, $rows);
    case 'webinars':
      $rows = $pdo->query('SELECT * FROM webinars ORDER BY created_at ASC')->fetchAll();
      return array_map(function ($row) {
        $row['materials'] = $row['materials'] ? json_decode($row['materials'], true) : [];
        $row['premium'] = (bool)($row['premium'] ?? 0);
        $row['price'] = (float)($row['price'] ?? 0);
        $row['capacity'] = (int)($row['capacity'] ?? 0);
        $row['popularity'] = (int)($row['popularity'] ?? 0);
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
      $rows = $pdo->query('SELECT name FROM timezones ORDER BY name ASC')->fetchAll();
      return array_map(fn($row) => $row['name'], $rows);
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
      $stmt = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, role, interests, api_token, avatar, timezone, phone, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $user) {
        $stmt->execute([
          $user['id'] ?? '',
          $user['name'] ?? '',
          $user['email'] ?? '',
          $user['password_hash'] ?? '',
          $user['role'] ?? 'member',
          json_encode($user['interests'] ?? [], JSON_UNESCAPED_SLASHES),
          $user['api_token'] ?? '',
          $user['avatar'] ?? '',
          $user['timezone'] ?? 'UTC',
          $user['phone'] ?? null,
          $user['company'] ?? null,
          $user['location'] ?? null,
          $user['bio'] ?? null
        ]);
      }
      break;
    case 'webinars':
      $pdo->exec('DELETE FROM webinars');
      $stmt = $pdo->prepare('INSERT INTO webinars (id, title, description, datetime, duration, category, instructor, premium, price, materials, host_id, capacity, popularity, image, meeting_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $webinar) {
        $stmt->execute([
          $webinar['id'] ?? '',
          $webinar['title'] ?? '',
          $webinar['description'] ?? '',
          !empty($webinar['datetime']) ? date('Y-m-d H:i:s', strtotime($webinar['datetime'])) : null,
          $webinar['duration'] ?? null,
          $webinar['category'] ?? null,
          $webinar['instructor'] ?? null,
          !empty($webinar['premium']) ? 1 : 0,
          $webinar['price'] ?? 0,
          json_encode($webinar['materials'] ?? [], JSON_UNESCAPED_SLASHES),
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
      $stmt = $pdo->prepare('INSERT INTO payments (id, user_id, webinar_id, amount, provider, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['user_id'] ?? '',
          $row['webinar_id'] ?? null,
          $row['amount'] ?? 0,
          $row['provider'] ?? 'paypal',
          $row['status'] ?? 'captured',
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'notifications':
      $pdo->exec('DELETE FROM notifications');
      $stmt = $pdo->prepare('INSERT INTO notifications (id, type, payload, created_at) VALUES (?, ?, ?, ?)');
      foreach ($data as $row) {
        $stmt->execute([
          $row['id'] ?? '',
          $row['type'] ?? 'in-app',
          json_encode($row['payload'] ?? [], JSON_UNESCAPED_SLASHES),
          !empty($row['created_at']) ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : date('Y-m-d H:i:s')
        ]);
      }
      break;
    case 'timezones':
      $pdo->exec('DELETE FROM timezones');
      $stmt = $pdo->prepare('INSERT INTO timezones (name) VALUES (?)');
      foreach ($data as $tz) {
        $stmt->execute([$tz]);
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
      $stmt = $pdo->prepare('INSERT INTO users (id, name, email, password_hash, role, interests, api_token, avatar, timezone, phone, company, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['name'] ?? '',
        $item['email'] ?? '',
        $item['password_hash'] ?? '',
        $item['role'] ?? 'member',
        json_encode($item['interests'] ?? [], JSON_UNESCAPED_SLASHES),
        $item['api_token'] ?? '',
        $item['avatar'] ?? '',
        $item['timezone'] ?? 'UTC',
        $item['phone'] ?? null,
        $item['company'] ?? null,
        $item['location'] ?? null,
        $item['bio'] ?? null
      ]);
      break;
    case 'webinars':
      $stmt = $pdo->prepare('INSERT INTO webinars (id, title, description, datetime, duration, category, instructor, premium, price, materials, host_id, capacity, popularity, image, meeting_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['title'] ?? '',
        $item['description'] ?? '',
        !empty($item['datetime']) ? date('Y-m-d H:i:s', strtotime($item['datetime'])) : null,
        $item['duration'] ?? null,
        $item['category'] ?? null,
        $item['instructor'] ?? null,
        !empty($item['premium']) ? 1 : 0,
        $item['price'] ?? 0,
        json_encode($item['materials'] ?? [], JSON_UNESCAPED_SLASHES),
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
      $stmt = $pdo->prepare('INSERT INTO notifications (id, type, payload, created_at) VALUES (?, ?, ?, ?)');
      $stmt->execute([
        $item['id'] ?? '',
        $item['type'] ?? 'in-app',
        json_encode($item['payload'] ?? [], JSON_UNESCAPED_SLASHES),
        !empty($item['created_at']) ? date('Y-m-d H:i:s', strtotime($item['created_at'])) : date('Y-m-d H:i:s')
      ]);
      break;
    case 'timezones':
      $stmt = $pdo->prepare('INSERT INTO timezones (name) VALUES (?)');
      $stmt->execute([$item]);
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
