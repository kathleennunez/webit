<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['user_id'] ?? '') === ($user['user_id'] ?? '')));
$publishedWebinars = array_values(array_filter($hostedWebinars, fn($w) => ($w['status'] ?? 'published') === 'published'));

include __DIR__ . '/../pages/host-tools-exports.html';
