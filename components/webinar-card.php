<?php
$webinar = $webinar ?? [];
$user = current_user();
$hostUser = get_user_by_id($webinar['host_id'] ?? '');
$displayDatetime = format_datetime_for_user($webinar['datetime'] ?? '', $user['timezone'] ?? null);
$description = $webinar['description'] ?? '';
?>
<div class="col" data-webinar-card data-title="<?php echo strtolower($webinar['title']); ?>" data-speaker="<?php echo strtolower($webinar['instructor']); ?>" data-category="<?php echo strtolower($webinar['category']); ?>">
  <a href="/webinar.php?id=<?php echo sanitize($webinar['id']); ?>" class="text-decoration-none text-reset">
    <div class="card glass-panel h-100 card-hover">
    <img src="<?php echo sanitize($webinar['image'] ?? '/assets/images/webinar-education.svg'); ?>" class="card-img-top" alt="Webinar preview">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge-soft"><?php echo sanitize($webinar['category']); ?></span>
        <?php if ($webinar['premium'] ?? false): ?>
          <span class="badge bg-warning text-dark">Premium<?php echo isset($webinar['price']) && $webinar['price'] > 0 ? ' • $' . sanitize((string)$webinar['price']) : ''; ?></span>
        <?php endif; ?>
      </div>
      <h5 class="card-title mb-1"><?php echo sanitize($webinar['title']); ?></h5>
      <p class="text-muted mb-3 card-desc"><?php echo sanitize($description); ?></p>
      <div class="d-flex align-items-center gap-2 mb-3">
        <img src="<?php echo sanitize(avatar_url($hostUser)); ?>" class="avatar-sm" alt="Host">
        <span class="text-muted small">Hosted by <?php echo sanitize($webinar['instructor']); ?></span>
      </div>
      <div class="d-flex justify-content-between">
        <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i><?php echo sanitize($displayDatetime); ?></span>
        <span class="text-muted small"><i class="bi bi-clock me-1"></i><?php echo sanitize($webinar['duration']); ?></span>
      </div>
    </div>
    </div>
  </a>
</div>
