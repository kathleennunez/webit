<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$phpMailerBase = __DIR__ . '/../PHPMailer-master/src';
if (file_exists($phpMailerBase . '/PHPMailer.php')) {
    require_once $phpMailerBase . '/Exception.php';
    require_once $phpMailerBase . '/PHPMailer.php';
    require_once $phpMailerBase . '/SMTP.php';
}

function send_email_via_gateway(string $to, string $subject, string $template, array $context = []): array
{
    $configPath = __DIR__ . '/../config/email.config.php';
    if (!file_exists($configPath)) {
        return [
            'ok' => false,
            'provider' => null,
            'error' => 'Email config missing.'
        ];
    }

    $config = require $configPath;
    $provider = $config['provider'] ?? 'phpmailer';
    if ($provider !== 'phpmailer') {
        return [
            'ok' => false,
            'provider' => $provider,
            'error' => 'Unsupported email provider.'
        ];
    }

    if (!class_exists(PHPMailer::class)) {
        return [
            'ok' => false,
            'provider' => $provider,
            'error' => 'PHPMailer not available.'
        ];
    }

    $body = render_email_template($template, $context, $config['templates_path'] ?? '');
    $plainBody = trim(strip_tags($body));
    if ($plainBody === '') {
        $plainBody = $subject;
    }

    $from = $config['from'] ?? [];
    $smtp = $config['phpmailer'] ?? [];
    $host = $smtp['host'] ?? '';
    $username = $smtp['username'] ?? '';
    $password = $smtp['password'] ?? '';
    if (!$host || !$username || !$password) {
        return [
            'ok' => false,
            'provider' => $provider,
            'error' => 'SMTP credentials are missing.'
        ];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = (bool)($smtp['auth'] ?? true);
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $smtp['secure'] ?? 'tls';
        $mail->Port = (int)($smtp['port'] ?? 587);

        $fromAddress = $from['address'] ?? 'no-reply@example.com';
        $fromName = $from['name'] ?? 'Webit';
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $plainBody;
        $mail->send();
        return [
            'ok' => true,
            'provider' => $provider,
            'error' => null
        ];
    } catch (Exception $e) {
        $error = $mail->ErrorInfo ?: $e->getMessage();
        return [
            'ok' => false,
            'provider' => $provider,
            'error' => $error
        ];
    }
}

function render_email_template(string $template, array $context, string $templatesPath): string
{
    $template = trim($template);
    if ($template === '') {
        return fallback_email_template($context);
    }

    $templatePath = $template;
    if (!is_file($templatePath) && $templatesPath) {
        $candidate = rtrim($templatesPath, '/') . '/' . $template;
        if (is_file($candidate)) {
            $templatePath = $candidate;
        }
    }

    if (is_file($templatePath)) {
        $contents = file_get_contents($templatePath);
        if ($contents !== false) {
            return replace_template_tokens($contents, $context);
        }
    }

    return fallback_email_template($context);
}

function replace_template_tokens(string $template, array $context): string
{
    if (!$context) {
        return $template;
    }
    $replacements = [];
    foreach ($context as $key => $value) {
        $token = '{{' . $key . '}}';
        $replacements[$token] = htmlspecialchars(stringify_email_value($value), ENT_QUOTES, 'UTF-8');
        $token = '{{ ' . $key . ' }}';
        $replacements[$token] = htmlspecialchars(stringify_email_value($value), ENT_QUOTES, 'UTF-8');
    }
    return strtr($template, $replacements);
}

function stringify_email_value($value): string
{
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($value === null) {
        return '';
    }
    return (string)$value;
}

function fallback_email_template(array $context): string
{
    $details = '';
    if ($context) {
        $details = '<pre style="white-space:pre-wrap;margin:16px 0;padding:12px;border:1px solid #e2e2e2;">'
            . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
            . '</pre>';
    }
    return '<p>You have a new notification.</p>' . $details;
}
