<?php
require_once __DIR__ . '/../php/bootstrap.php';
require_login();
require_non_admin();

$user = current_user();
$drafts = array_values(array_filter(all_webinars(), fn($w) => ($w['status'] ?? 'published') === 'draft'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_draft'])) {
  $id = $_POST['webinar_id'] ?? '';
  $updated = update_webinar_fields($id, ['status' => 'published']);
  if ($updated) {
    $drafts = array_values(array_filter(all_webinars(), fn($w) => ($w['status'] ?? 'published') === 'draft'));
  }
}

include __DIR__ . '/../pages/drafts.html';
