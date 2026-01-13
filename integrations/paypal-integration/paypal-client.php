<?php
function paypal_config(): array {
  $config = file_exists(__DIR__ . '/../../config.php') ? require __DIR__ . '/../../config.php' : [];
  return [
    'client_id' => $config['paypal_client_id'] ?? '',
    'secret' => $config['paypal_secret'] ?? '',
    'webhook_id' => $config['paypal_webhook_id'] ?? '',
    'env' => $config['paypal_env'] ?? 'sandbox'
  ];
}

function paypal_base_url(string $env): string {
  return $env === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

function paypal_access_token(): string {
  static $cached = null;
  if ($cached) {
    return $cached;
  }
  $config = paypal_config();
  $clientId = $config['client_id'];
  $secret = $config['secret'];
  if (!$clientId || !$secret) {
    throw new RuntimeException('Missing PayPal credentials.');
  }
  $url = paypal_base_url($config['env']) . '/v1/oauth2/token';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_USERPWD => $clientId . ':' . $secret,
    CURLOPT_POSTFIELDS => 'grant_type=client_credentials'
  ]);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($status < 200 || $status >= 300) {
    throw new RuntimeException('PayPal token request failed: ' . $response);
  }
  $data = json_decode($response, true);
  $cached = $data['access_token'] ?? '';
  if (!$cached) {
    throw new RuntimeException('PayPal token missing in response.');
  }
  return $cached;
}

function paypal_request(string $method, string $path, array $payload = []): array {
  $config = paypal_config();
  $token = paypal_access_token();
  $url = paypal_base_url($config['env']) . $path;
  $ch = curl_init($url);
  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
  ];
  $options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers
  ];
  if ($payload) {
    $options[CURLOPT_POSTFIELDS] = json_encode($payload);
  }
  curl_setopt_array($ch, $options);
  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $data = json_decode($response, true);
  if ($status < 200 || $status >= 300) {
    $message = is_array($data) ? json_encode($data) : $response;
    throw new RuntimeException('PayPal API error: ' . $message);
  }
  return is_array($data) ? $data : [];
}

function paypal_verify_webhook(string $body, array $headers): bool {
  $config = paypal_config();
  if (!$config['webhook_id']) {
    return false;
  }
  $payload = [
    'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
    'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
    'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
    'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
    'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
    'webhook_id' => $config['webhook_id'],
    'webhook_event' => json_decode($body, true)
  ];
  $result = paypal_request('POST', '/v1/notifications/verify-webhook-signature', $payload);
  return ($result['verification_status'] ?? '') === 'SUCCESS';
}
