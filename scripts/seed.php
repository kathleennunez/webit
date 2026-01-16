<?php
require_once __DIR__ . '/../php/bootstrap.php';

function usage(): void {
  echo "Usage:\n";
  echo "  php scripts/seed.php --fresh   # clear + seed (default)\n";
  echo "  php scripts/seed.php --seed    # seed only\n";
  echo "  php scripts/seed.php --clear   # clear only\n";
}

function clear_data(): void {
  write_json('users.json', []);
  write_json('webinars.json', []);
  write_json('registrations.json', []);
  write_json('subscriptions.json', []);
  write_json('payments.json', []);
  write_json('notifications.json', []);
  write_json('saved.json', []);
  write_json('attendance.json', []);
  write_json('waitlist.json', []);
  write_json('feedback.json', []);
  write_json('sms_feedback.json', []);
  write_json('canceled.json', []);
  write_json('admin.json', []);
  write_json('admin_broadcasts.json', []);
  write_json('admin_webinar_actions.json', []);
  write_json('admin_snapshots.json', []);
}

function seed_data(): void {
  $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
  $passwordHash = password_hash('webITpass1', PASSWORD_DEFAULT);
  $makeId = fn(string $prefix) => uniqid($prefix, true);
  $isoAt = function (string $modify, int $hour, int $minute) use ($now): string {
    return $now->modify($modify)->setTime($hour, $minute)->format('c');
  };

  $users = [
    [
      'user_id' => 'user_ada',
      'first_name' => 'Ada',
      'last_name' => 'Lovelace',
      'email' => 'ada@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'member',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'Europe/London',
      'phone' => '+447700900001',
      'sms_opt_in' => true,
      'company' => 'Analytical Engine',
      'location' => 'London, UK',
      'bio' => 'Mathematics, algorithms, and early computing concepts.'
    ],
    [
      'user_id' => 'user_grace',
      'first_name' => 'Grace',
      'last_name' => 'Hopper',
      'email' => 'grace@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'admin',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'America/New_York',
      'phone' => '+12025550111',
      'sms_opt_in' => false,
      'company' => 'US Navy',
      'location' => 'Arlington, VA',
      'bio' => 'Compiler pioneer and systems thinker.'
    ],
    [
      'user_id' => 'user_lin',
      'first_name' => 'Linus',
      'last_name' => 'Torvalds',
      'email' => 'linus@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'member',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'America/Los_Angeles',
      'phone' => '+14155550123',
      'sms_opt_in' => true,
      'company' => 'Open Source',
      'location' => 'Portland, OR',
      'bio' => 'Kernel engineering and collaboration at scale.'
    ],
    [
      'user_id' => 'user_margaret',
      'first_name' => 'Margaret',
      'last_name' => 'Hamilton',
      'email' => 'margaret@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'member',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'America/New_York',
      'phone' => '+12025550144',
      'sms_opt_in' => false,
      'company' => 'NASA',
      'location' => 'Cambridge, MA',
      'bio' => 'Software reliability and mission-critical systems.'
    ],
    [
      'user_id' => 'user_turing',
      'first_name' => 'Alan',
      'last_name' => 'Turing',
      'email' => 'alan@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'member',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'Europe/London',
      'phone' => '+447700900002',
      'sms_opt_in' => true,
      'company' => 'Bletchley Park',
      'location' => 'London, UK',
      'bio' => 'Foundations of computation and AI.'
    ],
    [
      'user_id' => 'user_radia',
      'first_name' => 'Radia',
      'last_name' => 'Perlman',
      'email' => 'radia@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'member',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'America/Los_Angeles',
      'phone' => '+14155550666',
      'sms_opt_in' => false,
      'company' => 'Networking Labs',
      'location' => 'Sunnyvale, CA',
      'bio' => 'Network protocols and scalable systems.'
    ],
    [
      'user_id' => 'user_dennis',
      'first_name' => 'Dennis',
      'last_name' => 'Ritchie',
      'email' => 'dennis@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'member',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'America/New_York',
      'phone' => '+12025550999',
      'sms_opt_in' => true,
      'company' => 'Bell Labs',
      'location' => 'Murray Hill, NJ',
      'bio' => 'Systems programming and language design.'
    ],
    [
      'user_id' => 'user_katherine',
      'first_name' => 'Katherine',
      'last_name' => 'Johnson',
      'email' => 'katherine@webit.test',
      'password_hash' => $passwordHash,
      'role' => 'member',
      'api_token' => bin2hex(random_bytes(8)),
      'avatar' => '/assets/images/avatar-default.svg',
      'timezone' => 'America/New_York',
      'phone' => '+12025550777',
      'sms_opt_in' => false,
      'company' => 'NASA',
      'location' => 'Hampton, VA',
      'bio' => 'Applied mathematics and orbital mechanics.'
    ]
  ];
  write_json('users.json', $users);

  $webinars = [
    [
      'id' => 'web_kernel',
      'title' => 'Building Resilient Kernels',
      'description' => 'A deep dive into kernel architecture, stability, and performance trade-offs.',
      'datetime' => $isoAt('+5 days', 16, 0),
      'duration' => '90 min',
      'category' => 'Technology',
      'instructor' => 'Linus Torvalds',
      'premium' => true,
      'price' => 49,
      'user_id' => 'user_lin',
      'capacity' => 120,
      'popularity' => 92,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/kernel',
      'status' => 'published',
      'created_at' => $isoAt('-12 days', 10, 30)
    ],
    [
      'id' => 'web_compilers',
      'title' => 'Compilers: From Theory to Practice',
      'description' => 'Designing compilers that balance optimization, safety, and developer experience.',
      'datetime' => $isoAt('+9 days', 18, 0),
      'duration' => '75 min',
      'category' => 'Education',
      'instructor' => 'Grace Hopper',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_grace',
      'capacity' => 200,
      'popularity' => 88,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/compilers',
      'status' => 'published',
      'created_at' => $isoAt('-10 days', 14, 0)
    ],
    [
      'id' => 'web_networks',
      'title' => 'Reliable Networks at Scale',
      'description' => 'Routing, redundancy, and how to keep packets moving in massive systems.',
      'datetime' => $isoAt('+2 days', 15, 0),
      'duration' => '60 min',
      'category' => 'Business',
      'instructor' => 'Radia Perlman',
      'premium' => true,
      'price' => 35,
      'user_id' => 'user_radia',
      'capacity' => 90,
      'popularity' => 76,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/networks',
      'status' => 'published',
      'created_at' => $isoAt('-6 days', 9, 0)
    ],
    [
      'id' => 'web_mission',
      'title' => 'Mission-Critical Software Patterns',
      'description' => 'Practices and patterns for software that cannot fail.',
      'datetime' => $isoAt('-6 days', 19, 0),
      'duration' => '80 min',
      'category' => 'Leadership',
      'instructor' => 'Margaret Hamilton',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_margaret',
      'capacity' => 150,
      'popularity' => 81,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/mission',
      'status' => 'published',
      'created_at' => $isoAt('-20 days', 11, 15)
    ],
    [
      'id' => 'web_computation',
      'title' => 'Computation, Machines, and Meaning',
      'description' => 'Exploring the conceptual limits of computation and intelligence.',
      'datetime' => $isoAt('-12 days', 17, 0),
      'duration' => '70 min',
      'category' => 'Growth',
      'instructor' => 'Alan Turing',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_turing',
      'capacity' => 110,
      'popularity' => 65,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/computation',
      'status' => 'published',
      'created_at' => $isoAt('-25 days', 8, 45)
    ],
    [
      'id' => 'web_unix',
      'title' => 'Unix Philosophy for Modern Teams',
      'description' => 'How small tools, clear contracts, and composability scale organizations.',
      'datetime' => $isoAt('+12 days', 20, 0),
      'duration' => '60 min',
      'category' => 'Productivity',
      'instructor' => 'Dennis Ritchie',
      'premium' => true,
      'price' => 25,
      'user_id' => 'user_dennis',
      'capacity' => 100,
      'popularity' => 72,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/unix',
      'status' => 'published',
      'created_at' => $isoAt('-4 days', 13, 30)
    ],
    [
      'id' => 'web_orbits',
      'title' => 'Designing with Trajectories',
      'description' => 'Mathematical intuition for complex systems and navigation.',
      'datetime' => $isoAt('+18 days', 14, 0),
      'duration' => '50 min',
      'category' => 'Education',
      'instructor' => 'Katherine Johnson',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_katherine',
      'capacity' => 140,
      'popularity' => 59,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/orbits',
      'status' => 'published',
      'created_at' => $isoAt('-7 days', 16, 10)
    ],
    [
      'id' => 'web_ai_ethics',
      'title' => 'Practical AI Ethics for Teams',
      'description' => 'Frameworks for responsible AI, bias audits, and model governance.',
      'datetime' => $isoAt('-8 days', 14, 0),
      'duration' => '65 min',
      'category' => 'Leadership',
      'instructor' => 'Ada Lovelace',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_ada',
      'capacity' => 160,
      'popularity' => 68,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/ai-ethics',
      'status' => 'published',
      'created_at' => $isoAt('-15 days', 10, 0)
    ],
    [
      'id' => 'web_product',
      'title' => 'Product Thinking for Engineers',
      'description' => 'Translate customer needs into reliable technical roadmaps.',
      'datetime' => $isoAt('-14 days', 16, 30),
      'duration' => '50 min',
      'category' => 'Productivity',
      'instructor' => 'Grace Hopper',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_grace',
      'capacity' => 120,
      'popularity' => 61,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/product',
      'status' => 'published',
      'created_at' => $isoAt('-18 days', 15, 0)
    ],
    [
      'id' => 'web_observability',
      'title' => 'Observability for Distributed Systems',
      'description' => 'Tracing, logging, and alerting strategies that cut through noise.',
      'datetime' => $isoAt('+7 days', 13, 0),
      'duration' => '70 min',
      'category' => 'Technology',
      'instructor' => 'Margaret Hamilton',
      'premium' => true,
      'price' => 29,
      'user_id' => 'user_margaret',
      'capacity' => 130,
      'popularity' => 74,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/observability',
      'status' => 'published',
      'created_at' => $isoAt('-3 days', 11, 45)
    ],
    [
      'id' => 'web_secure',
      'title' => 'Zero-Trust Security Playbook',
      'description' => 'Security fundamentals for modern web stacks and teams.',
      'datetime' => $isoAt('+15 days', 17, 0),
      'duration' => '55 min',
      'category' => 'Business',
      'instructor' => 'Katherine Johnson',
      'premium' => true,
      'price' => 39,
      'user_id' => 'user_katherine',
      'capacity' => 100,
      'popularity' => 53,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/zero-trust',
      'status' => 'published',
      'created_at' => $isoAt('-5 days', 9, 50)
    ],
    [
      'id' => 'web_teamwork',
      'title' => 'Remote Team Rituals That Work',
      'description' => 'Cadences, documentation, and async practices for healthy teams.',
      'datetime' => $isoAt('-3 days', 11, 0),
      'duration' => '45 min',
      'category' => 'Growth',
      'instructor' => 'Radia Perlman',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_radia',
      'capacity' => 180,
      'popularity' => 57,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => 'https://meet.example.com/teamwork',
      'status' => 'published',
      'created_at' => $isoAt('-9 days', 12, 0)
    ],
    [
      'id' => 'web_draft',
      'title' => 'Designing Safe Systems (Draft)',
      'description' => 'Early draft notes on safety in distributed systems.',
      'datetime' => $isoAt('+22 days', 10, 0),
      'duration' => '55 min',
      'category' => 'Technology',
      'instructor' => 'Ada Lovelace',
      'premium' => false,
      'price' => 0,
      'user_id' => 'user_ada',
      'capacity' => 0,
      'popularity' => 0,
      'image' => '/assets/images/webinar-education.svg',
      'meeting_url' => '',
      'status' => 'draft',
      'created_at' => $isoAt('-2 days', 9, 0)
    ]
  ];
  $categories = [
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
  $hostPool = [
    ['id' => 'user_ada', 'name' => 'Ada Lovelace'],
    ['id' => 'user_grace', 'name' => 'Grace Hopper'],
    ['id' => 'user_lin', 'name' => 'Linus Torvalds'],
    ['id' => 'user_margaret', 'name' => 'Margaret Hamilton'],
    ['id' => 'user_turing', 'name' => 'Alan Turing'],
    ['id' => 'user_radia', 'name' => 'Radia Perlman'],
    ['id' => 'user_dennis', 'name' => 'Dennis Ritchie'],
    ['id' => 'user_katherine', 'name' => 'Katherine Johnson']
  ];
  $titlePrefixes = ['Modern', 'Practical', 'Advanced', 'Hands-on', 'Strategic', 'Applied'];
  $titleSuffixes = ['Studio', 'Lab', 'Playbook', 'Blueprint', 'Masterclass', 'Clinic'];
  $durationOptions = ['45 min', '50 min', '60 min', '70 min', '75 min', '90 min'];
  $priceOptions = [19, 25, 29, 35, 39, 49];
  $slugify = function (string $value): string {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-');
  };
  $webinarsByCategory = [];
  foreach ($webinars as $entry) {
    $category = $entry['category'] ?? 'Education';
    $webinarsByCategory[$category][] = $entry;
  }
  foreach ($categories as $categoryIndex => $category) {
    $existing = $webinarsByCategory[$category] ?? [];
    $needed = max(0, 6 - count($existing));
    $slug = $slugify($category);
    for ($i = 1; $i <= $needed; $i++) {
      $host = $hostPool[($categoryIndex + $i) % count($hostPool)];
      $prefix = $titlePrefixes[($i + $categoryIndex) % count($titlePrefixes)];
      $suffix = $titleSuffixes[($i + $categoryIndex) % count($titleSuffixes)];
      $title = $prefix . ' ' . $category . ' ' . $suffix;
      $description = 'A focused session on ' . $category . ' fundamentals, tactics, and real-world examples.';
      $daysOffset = (($i + $categoryIndex) % 2 === 0)
        ? '+' . (4 + $i + $categoryIndex) . ' days'
        : '-' . (6 + $i + $categoryIndex) . ' days';
      $hour = 10 + (($i + $categoryIndex) % 8);
      $minute = (($i + $categoryIndex) % 2) * 30;
      $createdDays = 20 + ($i * 2) + $categoryIndex;
      $premium = (($i + $categoryIndex) % 3) === 0;
      $price = $premium ? $priceOptions[($i + $categoryIndex) % count($priceOptions)] : 0;
      $capacity = 80 + (($i + $categoryIndex) % 6) * 20;
      $popularity = 40 + (($i + $categoryIndex) % 7) * 6;
      $index = count($existing) + $i;
      $webinars[] = [
        'id' => 'web_' . $slug . '_' . $index,
        'title' => $title,
        'description' => $description,
        'datetime' => $isoAt($daysOffset, $hour, $minute),
        'duration' => $durationOptions[($i + $categoryIndex) % count($durationOptions)],
        'category' => $category,
        'instructor' => $host['name'],
        'premium' => $premium,
        'price' => $price,
        'user_id' => $host['id'],
        'capacity' => $capacity,
        'popularity' => $popularity,
        'image' => '/assets/images/webinar-education.svg',
        'meeting_url' => 'https://meet.example.com/' . $slug . '-' . $index,
        'status' => 'published',
        'created_at' => $isoAt('-' . $createdDays . ' days', 9 + (($i + $categoryIndex) % 6), 15)
      ];
    }
  }
  write_json('webinars.json', $webinars);

  $registrations = [
    [
      'id' => $makeId('reg_'),
      'webinar_id' => 'web_kernel',
      'user_id' => 'user_ada',
      'registered_at' => $isoAt('-2 days', 12, 0),
      'status' => 'registered'
    ],
    [
      'id' => $makeId('reg_'),
      'webinar_id' => 'web_networks',
      'user_id' => 'user_turing',
      'registered_at' => $isoAt('-1 days', 10, 30),
      'status' => 'registered'
    ],
    [
      'id' => $makeId('reg_'),
      'webinar_id' => 'web_mission',
      'user_id' => 'user_katherine',
      'registered_at' => $isoAt('-10 days', 9, 0),
      'status' => 'attended'
    ],
    [
      'id' => $makeId('reg_'),
      'webinar_id' => 'web_computation',
      'user_id' => 'user_radia',
      'registered_at' => $isoAt('-18 days', 15, 45),
      'status' => 'attended'
    ],
    [
      'id' => $makeId('reg_'),
      'webinar_id' => 'web_ai_ethics',
      'user_id' => 'user_turing',
      'registered_at' => $isoAt('-9 days', 11, 10),
      'status' => 'attended'
    ],
    [
      'id' => $makeId('reg_'),
      'webinar_id' => 'web_product',
      'user_id' => 'user_ada',
      'registered_at' => $isoAt('-15 days', 10, 15),
      'status' => 'attended'
    ],
    [
      'id' => $makeId('reg_'),
      'webinar_id' => 'web_teamwork',
      'user_id' => 'user_dennis',
      'registered_at' => $isoAt('-4 days', 10, 0),
      'status' => 'attended'
    ]
  ];
  write_json('registrations.json', $registrations);

  $waitlist = [
    [
      'id' => $makeId('wait_'),
      'webinar_id' => 'web_kernel',
      'user_id' => 'user_katherine',
      'created_at' => $isoAt('-1 days', 11, 0)
    ],
    [
      'id' => $makeId('wait_'),
      'webinar_id' => 'web_unix',
      'user_id' => 'user_ada',
      'created_at' => $isoAt('-3 days', 8, 30)
    ]
  ];
  write_json('waitlist.json', $waitlist);

  $feedback = [
    [
      'id' => $makeId('feed_'),
      'webinar_id' => 'web_mission',
      'user_id' => 'user_katherine',
      'content' => 'Clear frameworks for safety and risk management. Loved the examples.',
      'rating' => 5,
      'created_at' => $isoAt('-5 days', 13, 20)
    ],
    [
      'id' => $makeId('feed_'),
      'webinar_id' => 'web_computation',
      'user_id' => 'user_radia',
      'content' => 'Thought-provoking and grounded in fundamentals.',
      'rating' => 4,
      'created_at' => $isoAt('-11 days', 18, 10)
    ],
    [
      'id' => $makeId('feed_'),
      'webinar_id' => 'web_ai_ethics',
      'user_id' => 'user_turing',
      'content' => 'Balanced practical steps with ethics frameworks.',
      'rating' => 5,
      'created_at' => $isoAt('-7 days', 16, 20)
    ],
    [
      'id' => $makeId('feed_'),
      'webinar_id' => 'web_product',
      'user_id' => 'user_ada',
      'content' => 'Helpful for planning and prioritization.',
      'rating' => 4,
      'created_at' => $isoAt('-13 days', 12, 0)
    ],
    [
      'id' => $makeId('feed_'),
      'webinar_id' => 'web_teamwork',
      'user_id' => 'user_dennis',
      'content' => 'Great tips on async workflows.',
      'rating' => 5,
      'created_at' => $isoAt('-2 days', 10, 30)
    ]
  ];
  write_json('feedback.json', $feedback);

  $payments = [
    [
      'id' => $makeId('pay_'),
      'user_id' => 'user_ada',
      'webinar_id' => 'web_kernel',
      'amount' => 49,
      'provider' => 'paypal',
      'status' => 'captured',
      'capture_id' => 'CAPTURE123',
      'created_at' => $isoAt('-2 days', 12, 5)
    ],
    [
      'id' => $makeId('pay_'),
      'user_id' => 'user_turing',
      'webinar_id' => 'web_networks',
      'amount' => 35,
      'provider' => 'paypal',
      'status' => 'captured',
      'capture_id' => 'CAPTURE456',
      'created_at' => $isoAt('-1 days', 10, 35)
    ],
    [
      'id' => $makeId('pay_'),
      'user_id' => 'user_katherine',
      'webinar_id' => 'web_secure',
      'amount' => 39,
      'provider' => 'paypal',
      'status' => 'captured',
      'capture_id' => 'CAPTURE789',
      'created_at' => $isoAt('-4 days', 9, 0)
    ],
    [
      'id' => $makeId('pay_'),
      'user_id' => 'user_margaret',
      'webinar_id' => 'web_observability',
      'amount' => 29,
      'provider' => 'paypal',
      'status' => 'captured',
      'capture_id' => 'CAPTURE321',
      'created_at' => $isoAt('-2 days', 14, 45)
    ]
  ];
  write_json('payments.json', $payments);

  $subscriptions = [
    [
      'id' => $makeId('sub_'),
      'user_id' => 'user_lin',
      'plan' => 'monthly',
      'status' => 'active',
      'created_at' => $isoAt('-30 days', 9, 0),
      'renewal_at' => $isoAt('+1 month', 9, 0),
      'provider' => 'paypal'
    ],
    [
      'id' => $makeId('sub_'),
      'user_id' => 'user_radia',
      'plan' => 'annual',
      'status' => 'active',
      'created_at' => $isoAt('-60 days', 14, 0),
      'renewal_at' => $isoAt('+11 months', 14, 0),
      'provider' => 'paypal'
    ]
  ];
  write_json('subscriptions.json', $subscriptions);

  $saved = [
    [
      'id' => $makeId('save_'),
      'user_id' => 'user_ada',
      'webinar_id' => 'web_unix',
      'saved_at' => $isoAt('-1 days', 9, 15)
    ],
    [
      'id' => $makeId('save_'),
      'user_id' => 'user_turing',
      'webinar_id' => 'web_kernel',
      'saved_at' => $isoAt('-2 days', 17, 40)
    ],
    [
      'id' => $makeId('save_'),
      'user_id' => 'user_katherine',
      'webinar_id' => 'web_observability',
      'saved_at' => $isoAt('-1 days', 8, 0)
    ],
    [
      'id' => $makeId('save_'),
      'user_id' => 'user_dennis',
      'webinar_id' => 'web_secure',
      'saved_at' => $isoAt('-3 days', 10, 10)
    ]
  ];
  write_json('saved.json', $saved);

  $attendance = [
    [
      'id' => $makeId('att_'),
      'user_id' => 'user_katherine',
      'webinar_id' => 'web_mission',
      'status' => 'attended',
      'timestamp' => $isoAt('-6 days', 19, 5)
    ],
    [
      'id' => $makeId('att_'),
      'user_id' => 'user_radia',
      'webinar_id' => 'web_computation',
      'status' => 'attended',
      'timestamp' => $isoAt('-12 days', 17, 5)
    ],
    [
      'id' => $makeId('att_'),
      'user_id' => 'user_turing',
      'webinar_id' => 'web_ai_ethics',
      'status' => 'attended',
      'timestamp' => $isoAt('-8 days', 14, 5)
    ],
    [
      'id' => $makeId('att_'),
      'user_id' => 'user_ada',
      'webinar_id' => 'web_product',
      'status' => 'attended',
      'timestamp' => $isoAt('-14 days', 16, 35)
    ],
    [
      'id' => $makeId('att_'),
      'user_id' => 'user_dennis',
      'webinar_id' => 'web_teamwork',
      'status' => 'attended',
      'timestamp' => $isoAt('-3 days', 11, 10)
    ]
  ];
  write_json('attendance.json', $attendance);

  $notifications = [
    [
      'id' => $makeId('note_'),
      'type' => 'in-app',
      'payload' => [
        'user_id' => 'user_ada',
        'message' => 'Registration confirmed for: Building Resilient Kernels',
        'category' => 'registration',
        'meta' => ['webinar_id' => 'web_kernel'],
        'read' => false
      ],
      'created_at' => $isoAt('-2 days', 12, 10)
    ],
    [
      'id' => $makeId('note_'),
      'type' => 'in-app',
      'payload' => [
        'user_id' => 'user_turing',
        'message' => 'Waitlist spot opened for Reliable Networks at Scale.',
        'category' => 'waitlist',
        'meta' => ['webinar_id' => 'web_networks'],
        'read' => true
      ],
      'created_at' => $isoAt('-1 days', 11, 5)
    ],
    [
      'id' => $makeId('note_'),
      'type' => 'in-app',
      'payload' => [
        'user_id' => 'user_katherine',
        'message' => 'How was "Mission-Critical Software Patterns"? Leave feedback.',
        'category' => 'feedback',
        'meta' => ['webinar_id' => 'web_mission'],
        'read' => false
      ],
      'created_at' => $isoAt('-4 days', 9, 20)
    ],
    [
      'id' => $makeId('note_'),
      'type' => 'in-app',
      'payload' => [
        'user_id' => 'user_dennis',
        'message' => 'Registration confirmed for: Remote Team Rituals That Work',
        'category' => 'registration',
        'meta' => ['webinar_id' => 'web_teamwork'],
        'read' => true
      ],
      'created_at' => $isoAt('-3 days', 10, 5)
    ],
    [
      'id' => $makeId('note_'),
      'type' => 'in-app',
      'payload' => [
        'user_id' => 'user_ada',
        'message' => 'Payment received for Zero-Trust Security Playbook.',
        'category' => 'payment',
        'meta' => ['webinar_id' => 'web_secure'],
        'read' => false
      ],
      'created_at' => $isoAt('-4 days', 9, 5)
    ]
  ];
  write_json('notifications.json', $notifications);

  $smsFeedback = [
    [
      'id' => $makeId('sms_'),
      'user_id' => 'user_ada',
      'webinar_id' => 'web_mission',
      'phone' => '+447700900001',
      'message' => 'Great session. The checklist approach really helped.',
      'raw_payload' => ['source' => 'twilio', 'sid' => 'SM001'],
      'created_at' => $isoAt('-5 days', 20, 10)
    ],
    [
      'id' => $makeId('sms_'),
      'user_id' => 'user_turing',
      'webinar_id' => 'web_computation',
      'phone' => '+447700900002',
      'message' => 'Loved the Q&A. Would attend again.',
      'raw_payload' => ['source' => 'twilio', 'sid' => 'SM002'],
      'created_at' => $isoAt('-11 days', 19, 0)
    ]
  ];
  write_json('sms_feedback.json', $smsFeedback);

  $canceled = [
    [
      'canceled_id' => 'web_canceled',
      'title' => 'Future of Secure Systems',
      'canceled_at' => $isoAt('-3 days', 15, 0)
    ]
  ];
  write_json('canceled.json', $canceled);

  write_json('admin.json', [
    [
      'admin_id' => $makeId('admin_'),
      'user_id' => 'user_grace',
      'created_at' => $isoAt('-120 days', 9, 0)
    ]
  ]);
}

$mode = $argv[1] ?? '--fresh';
if (!in_array($mode, ['--fresh', '--seed', '--clear'], true)) {
  usage();
  exit(1);
}

if ($mode === '--clear') {
  clear_data();
  echo "Database cleared.\n";
  exit(0);
}

if ($mode === '--fresh') {
  clear_data();
}

seed_data();
echo "Seed data loaded.\n";
