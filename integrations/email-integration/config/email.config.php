<?php

return [
    'provider' => 'phpmailer',
    'from' => [
        'address' => getenv('EMAIL_FROM_ADDRESS') ?: 'no-reply@example.com',
        'name' => getenv('EMAIL_FROM_NAME') ?: 'Webit'
    ],
    'templates_path' => getenv('EMAIL_TEMPLATES_PATH') ?: (__DIR__ . '/../templates'),
    'phpmailer' => [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int)(getenv('SMTP_PORT') ?: 587),
        'secure' => getenv('SMTP_SECURE') ?: 'tls',
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'auth' => getenv('SMTP_AUTH') !== 'false'
    ]
];
