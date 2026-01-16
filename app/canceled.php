<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$backLink = previous_page_link('/app/home.php');
$id = $_GET['id'] ?? '';
$title = $_GET['title'] ?? '';
$webinar = $id ? get_webinar($id) : null;
if ($webinar) {
  $title = $webinar['title'] ?? $title;
}

include __DIR__ . '/../pages/canceled.html';
