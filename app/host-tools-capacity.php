<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();

$user = current_user();
$message = '';
$order = ($_GET['order'] ?? 'latest') === 'oldest' ? 'oldest' : 'latest';
$orderLabel = $order === 'latest' ? 'Latest to oldest' : 'Oldest to latest';
$orderParams = $_GET;
$orderParams['order'] = $order === 'latest' ? 'oldest' : 'latest';
$orderParams['page'] = 1;
$orderLink = ($_SERVER['PHP_SELF'] ?? '') . '?' . http_build_query($orderParams);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['user_id'] ?? '') === ($user['user_id'] ?? '')));
$publishedWebinars = array_values(array_filter($hostedWebinars, fn($w) => ($w['status'] ?? 'published') === 'published'));
$hostedWebinars = $publishedWebinars;
usort($hostedWebinars, function ($a, $b) use ($order) {
  $aTs = strtotime($a['created_at'] ?? '') ?: (strtotime($a['datetime'] ?? '') ?: 0);
  $bTs = strtotime($b['created_at'] ?? '') ?: (strtotime($b['datetime'] ?? '') ?: 0);
  $cmp = $aTs <=> $bTs;
  if ($cmp === 0) {
    $cmp = strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
  }
  return $order === 'latest' ? -$cmp : $cmp;
});
$totalPages = max(1, (int)ceil(count($hostedWebinars) / $perPage));
$page = min($page, $totalPages);
$pagedWebinars = array_slice($hostedWebinars, ($page - 1) * $perPage, $perPage);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_capacity'])) {
  $id = $_POST['webinar_id'] ?? '';
  $capacity = (int)($_POST['capacity'] ?? 0);
  $current = $id ? get_webinar($id) : null;
  $previousCapacity = (int)($current['capacity'] ?? 0);
  $previousRemaining = $previousCapacity > 0 ? max(0, $previousCapacity - webinar_registration_count($id)) : 0;
  update_webinar_fields($id, ['capacity' => max(0, $capacity)]);
  $newRemaining = $capacity > 0 ? max(0, $capacity - webinar_registration_count($id)) : 0;
  if ($newRemaining > $previousRemaining) {
    notify_waitlist_openings($id, $newRemaining, 'capacity');
  }
  $hostedWebinars = array_values(array_filter(all_webinars(), fn($w) => ($w['user_id'] ?? '') === ($user['user_id'] ?? '')));
  $publishedWebinars = array_values(array_filter($hostedWebinars, fn($w) => ($w['status'] ?? 'published') === 'published'));
  $hostedWebinars = $publishedWebinars;
  usort($hostedWebinars, function ($a, $b) use ($order) {
    $aTs = strtotime($a['created_at'] ?? '') ?: (strtotime($a['datetime'] ?? '') ?: 0);
    $bTs = strtotime($b['created_at'] ?? '') ?: (strtotime($b['datetime'] ?? '') ?: 0);
    $cmp = $aTs <=> $bTs;
    if ($cmp === 0) {
      $cmp = strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
    }
    return $order === 'latest' ? -$cmp : $cmp;
  });
  $totalPages = max(1, (int)ceil(count($hostedWebinars) / $perPage));
  $page = min($page, $totalPages);
  $pagedWebinars = array_slice($hostedWebinars, ($page - 1) * $perPage, $perPage);
  $message = 'Capacity updated.';
}

include __DIR__ . '/../pages/host-tools-capacity.html';
