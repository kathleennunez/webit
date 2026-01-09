<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$message = '';

$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['host_id'] ?? '') === $user['id']));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_capacity'])) {
  $id = $_POST['webinar_id'] ?? '';
  $capacity = (int)($_POST['capacity'] ?? 0);
  update_webinar_fields($id, ['capacity' => max(0, $capacity)]);
  $hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['host_id'] ?? '') === $user['id']));
  $message = 'Capacity updated.';
}

include __DIR__ . '/../pages/host-tools-capacity.html';
