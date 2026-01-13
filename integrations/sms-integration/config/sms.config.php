<?php

return [
    'provider' => 'sms_gate',
    'inbound' => [
        'token' => getenv('SMS_INBOUND_TOKEN') ?: ''
    ],
    'sms_gate' => [
        'mode' => getenv('SMS_GATE_MODE') ?: 'local',
        'local_url' => getenv('SMS_GATE_LOCAL_URL') ?: 'http://127.0.0.1:8080',
        'cloud_url' => getenv('SMS_GATE_CLOUD_URL') ?: 'https://api.sms-gate.app/3rdparty/v1',
        'username' => getenv('SMS_GATE_USERNAME') ?: '',
        'password' => getenv('SMS_GATE_PASSWORD') ?: ''
    ],
    'legacy_gateway' => [
        'gateway_url' => getenv('SMS_GATEWAY_URL') ?: 'http://10.42.212.112:8080/messages',
        'username' => getenv('SMS_GATEWAY_USERNAME') ?: 'sms',
        'password' => getenv('SMS_GATEWAY_PASSWORD') ?: '8m2fKZur'
    ]
];
