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
    redirect_to('/app/drafts.php');
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_draft'])) {
  $id = $_POST['webinar_id'] ?? '';
  if ($id) {
    delete_webinar_and_related($id);
  }
  redirect_to('/app/drafts.php');
}

include __DIR__ . '/../pages/drafts.html';
