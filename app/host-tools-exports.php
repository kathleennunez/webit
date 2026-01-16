<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();

$user = current_user();
$order = ($_GET['order'] ?? 'latest') === 'oldest' ? 'oldest' : 'latest';
$orderLabel = $order === 'latest' ? 'Latest to oldest' : 'Oldest to latest';
$orderParams = $_GET;
$orderParams['order'] = $order === 'latest' ? 'oldest' : 'latest';
$orderParams['page'] = 1;
$orderLink = ($_SERVER['PHP_SELF'] ?? '') . '?' . http_build_query($orderParams);
$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['user_id'] ?? '') === ($user['user_id'] ?? '')));
$publishedWebinars = array_values(array_filter($hostedWebinars, fn($w) => ($w['status'] ?? 'published') === 'published'));
usort($publishedWebinars, function ($a, $b) use ($order) {
  $aTs = strtotime($a['created_at'] ?? '') ?: (strtotime($a['datetime'] ?? '') ?: 0);
  $bTs = strtotime($b['created_at'] ?? '') ?: (strtotime($b['datetime'] ?? '') ?: 0);
  $cmp = $aTs <=> $bTs;
  if ($cmp === 0) {
    $cmp = strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
  }
  return $order === 'latest' ? -$cmp : $cmp;
});

include __DIR__ . '/../pages/host-tools-exports.html';
