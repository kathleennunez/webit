<?php
require_once __DIR__ . '/php/bootstrap.php';
require_login();

$user = current_user();
$id = $_GET['id'] ?? '';
$title = $_GET['title'] ?? '';
$webinar = $id ? get_webinar($id) : null;
if ($webinar) {
  $title = $webinar['title'] ?? $title;
}

include __DIR__ . '/pages/canceled.html';
