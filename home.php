<?php
require_once __DIR__ . '/php/bootstrap.php';
require_login();

$user = current_user();
$now = time();
$webinars = array_values(array_filter(all_webinars(), function ($w) use ($now) {
  if (($w['status'] ?? 'published') !== 'published') {
    return false;
  }
  $ts = strtotime($w['datetime'] ?? '');
  return $ts === false || $ts >= $now;
}));
$selectedCategory = $_GET['category'] ?? 'all';
if ($selectedCategory !== 'all') {
  $webinars = array_values(array_filter($webinars, function ($webinar) use ($selectedCategory) {
    return strcasecmp($webinar['category'] ?? '', $selectedCategory) === 0;
  }));
}
$shuffled = $webinars;
shuffle($shuffled);
$webinars = $shuffled;
$recommended = recommend_webinars($user['id']);
$selectedCategory = strtolower($selectedCategory);

include __DIR__ . '/pages/home.html';
