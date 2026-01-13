<?php $user = current_user(); ?>
<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<?php $currentCategory = $_GET['category'] ?? ''; ?>
<?php if ($user): ?>
  <?php send_feedback_prompts_for_user($user['id']); ?>
<?php endif; ?>
<?php
function nav_active(string $path, string $currentPage): string {
  return $currentPage === $path ? 'active' : '';
}
function category_active(string $category, string $currentPage, string $currentCategory): string {
  return $currentPage === 'home.php' && $currentCategory === $category ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>webIT.</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <?php if (!empty($includeIntlTelInput)): ?>
    <link href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css" rel="stylesheet">
  <?php endif; ?>
  <?php if (!empty($includeAiChat)): ?>
    <link rel="stylesheet" href="/assets/css/ai-chat.css">
  <?php endif; ?>
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-shell">
  <header class="topbar">
    <div class="container-fluid d-flex align-items-center gap-3">
      <div class="d-flex align-items-center gap-3 flex-shrink-0">
        <?php if ($user): ?>
          <button class="btn btn-outline-primary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sideNav" aria-controls="sideNav">
            <i class="bi bi-list"></i>
          </button>
          <button class="btn btn-outline-primary btn-sm d-none d-lg-inline-flex" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
        <?php endif; ?>
        <a class="brand-pill brand-pill-lg text-decoration-none" href="<?php echo $user ? '/app/home.php' : '/index.php'; ?>">webIT.</a>
      </div>
      <?php $showSearch = $user && ($user['role'] ?? '') !== 'admin' && $currentPage === 'home.php'; ?>
      <?php if ($showSearch): ?>
        <div class="topbar-search mx-auto">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" placeholder="Search webinars, instructors, topics" data-search>
          </div>
        </div>
      <?php endif; ?>
      <div class="d-flex align-items-center gap-3 <?php echo $showSearch ? '' : 'ms-auto'; ?>">
        <?php if ($user): ?>
          <?php
            $notificationCount = notification_count($user['id']);
            $notificationItems = user_notifications($user['id'], 4);
          ?>
          <?php if (($user['role'] ?? '') !== 'admin'): ?>
            <a class="btn btn-outline-primary btn-sm <?php echo nav_active('create-webinar.php', $currentPage); ?>" href="/app/create-webinar.php" title="Create">
              <i class="bi bi-plus-circle"></i>
            </a>
          <?php endif; ?>
          <?php if (($user['role'] ?? '') !== 'admin'): ?>
            <div class="dropdown">
              <button class="btn btn-outline-primary btn-sm position-relative <?php echo nav_active('notifications.php', $currentPage); ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($notificationCount > 0): ?>
                  <span class="badge-notify"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                <?php endif; ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end notification-menu">
                <li class="dropdown-header">Notifications</li>
                <?php if (!$notificationItems): ?>
                  <li class="dropdown-item text-muted">No updates yet.</li>
                <?php endif; ?>
                <?php foreach ($notificationItems as $note): ?>
                  <?php $webinarId = $note['payload']['meta']['webinar_id'] ?? ''; ?>
                  <?php $webinarStatus = $note['payload']['meta']['status'] ?? ''; ?>
                  <?php $webinarTitle = $note['payload']['meta']['title'] ?? ''; ?>
                  <?php if ($webinarId): ?>
                    <li>
                      <?php if ($webinarStatus === 'canceled'): ?>
                        <a class="dropdown-item small <?php echo !($note['payload']['read'] ?? false) ? 'notification-unread' : ''; ?>" href="/app/canceled.php?id=<?php echo sanitize($webinarId); ?>&title=<?php echo urlencode($webinarTitle); ?>">
                          <?php echo sanitize($note['payload']['message'] ?? 'Update'); ?>
                          <div class="text-muted small"><?php echo sanitize($note['created_at']); ?></div>
                        </a>
                      <?php else: ?>
                        <a class="dropdown-item small <?php echo !($note['payload']['read'] ?? false) ? 'notification-unread' : ''; ?>" href="/app/webinar.php?id=<?php echo sanitize($webinarId); ?>">
                          <?php echo sanitize($note['payload']['message'] ?? 'Update'); ?>
                          <div class="text-muted small"><?php echo sanitize($note['created_at']); ?></div>
                        </a>
                      <?php endif; ?>
                    </li>
                  <?php else: ?>
                    <li class="dropdown-item small <?php echo !($note['payload']['read'] ?? false) ? 'notification-unread' : ''; ?>">
                      <?php echo sanitize($note['payload']['message'] ?? 'Update'); ?>
                      <div class="text-muted small"><?php echo sanitize($note['created_at']); ?></div>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/app/notifications.php">View all</a></li>
              </ul>
            </div>
          <?php endif; ?>
          <div class="dropdown">
            <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2 <?php echo nav_active('account.php', $currentPage); ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
              <img src="<?php echo sanitize(avatar_url($user)); ?>" class="avatar-sm" alt="Profile">
              <i class="bi bi-chevron-down"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if (($user['role'] ?? '') !== 'admin'): ?>
                <li><a class="dropdown-item" href="/app/profile.php"><i class="bi bi-person-badge me-2"></i>View profile</a></li>
                <li><a class="dropdown-item" href="/app/account.php"><i class="bi bi-person-circle me-2"></i>Update profile</a></li>
                <li><a class="dropdown-item" href="/app/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li><a class="dropdown-item" href="/app/subscription.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
                <li><hr class="dropdown-divider"></li>
              <?php endif; ?>
              <li>
                <div class="dropdown-item d-flex align-items-center justify-content-between gap-3">
                  <span>Theme</span>
                  <div class="theme-toggle" data-theme-toggle></div>
                </div>
              </li>
              <li><a class="dropdown-item" href="/app/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <?php if ($user): ?>
    <div class="layout">
      <aside class="sidebar d-none d-lg-flex flex-column">
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <div class="sidebar-section">
            <div class="text-muted small mb-2">Admin</div>
            <a class="sidebar-link <?php echo nav_active('admin.php', $currentPage); ?>" href="/app/admin.php"><i class="bi bi-shield-check"></i><span>Admin center</span></a>
          </div>
        <?php else: ?>
          <div class="sidebar-section">
            <a class="sidebar-link <?php echo nav_active('home.php', $currentPage); ?>" href="/app/home.php"><i class="bi bi-compass"></i><span>Browse</span></a>
            <a class="sidebar-link <?php echo nav_active('dashboard.php', $currentPage); ?>" href="/app/dashboard.php"><i class="bi bi-speedometer"></i><span>Dashboard</span></a>
            <a class="sidebar-link <?php echo nav_active('history.php', $currentPage); ?>" href="/app/history.php"><i class="bi bi-clock-history"></i><span>History</span></a>
            <a class="sidebar-link <?php echo nav_active('saved.php', $currentPage); ?>" href="/app/saved.php"><i class="bi bi-bookmark"></i><span>Saved</span></a>
          </div>
          <div class="sidebar-section">
            <div class="text-muted small mb-2">Host Tools</div>
            <a class="sidebar-link <?php echo nav_active('host-tools-published.php', $currentPage); ?>" href="/app/host-tools-published.php"><i class="bi bi-layout-text-window-reverse"></i><span>Published events</span></a>
            <a class="sidebar-link <?php echo nav_active('host-tools-attendees.php', $currentPage); ?>" href="/app/host-tools-attendees.php"><i class="bi bi-people"></i><span>Attendees</span></a>
            <a class="sidebar-link <?php echo nav_active('host-tools-waitlist.php', $currentPage); ?>" href="/app/host-tools-waitlist.php"><i class="bi bi-hourglass-split"></i><span>Waitlist</span></a>
            <a class="sidebar-link <?php echo nav_active('host-tools-capacity.php', $currentPage); ?>" href="/app/host-tools-capacity.php"><i class="bi bi-sliders"></i><span>Capacity limits</span></a>
            <a class="sidebar-link <?php echo nav_active('host-tools-exports.php', $currentPage); ?>" href="/app/host-tools-exports.php"><i class="bi bi-download"></i><span>Data export</span></a>
          </div>
          <div class="sidebar-section">
            <div class="text-uppercase text-muted small mb-2">Categories</div>
            <a class="sidebar-link <?php echo category_active('Education', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Education"><i class="bi bi-book"></i><span>Education</span></a>
            <a class="sidebar-link <?php echo category_active('Business', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Business"><i class="bi bi-briefcase"></i><span>Business</span></a>
            <a class="sidebar-link <?php echo category_active('Wellness', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Wellness"><i class="bi bi-heart-pulse"></i><span>Wellness</span></a>
            <a class="sidebar-link <?php echo category_active('Technology', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Technology"><i class="bi bi-cpu"></i><span>Technology</span></a>
            <a class="sidebar-link <?php echo category_active('Growth', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Growth"><i class="bi bi-stars"></i><span>Growth</span></a>
            <a class="sidebar-link <?php echo category_active('Marketing', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Marketing"><i class="bi bi-megaphone"></i><span>Marketing</span></a>
            <a class="sidebar-link <?php echo category_active('Design', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Design"><i class="bi bi-palette"></i><span>Design</span></a>
            <a class="sidebar-link <?php echo category_active('Leadership', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Leadership"><i class="bi bi-people"></i><span>Leadership</span></a>
            <a class="sidebar-link <?php echo category_active('Finance', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Finance"><i class="bi bi-cash-coin"></i><span>Finance</span></a>
            <a class="sidebar-link <?php echo category_active('Health', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Health"><i class="bi bi-activity"></i><span>Health</span></a>
            <a class="sidebar-link <?php echo category_active('Productivity', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Productivity"><i class="bi bi-lightning-charge"></i><span>Productivity</span></a>
            <a class="sidebar-link <?php echo category_active('Creative', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Creative"><i class="bi bi-brush"></i><span>Creative</span></a>
          </div>
        <?php endif; ?>
      </aside>

      <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sideNav" aria-labelledby="sideNavLabel">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title" id="sideNavLabel">Navigation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
          <?php if (($user['role'] ?? '') === 'admin'): ?>
            <div class="text-muted small mb-2">Admin</div>
            <a class="sidebar-link <?php echo nav_active('admin.php', $currentPage); ?>" href="/app/admin.php"><i class="bi bi-shield-check"></i>Admin center</a>
            <hr>
          <?php else: ?>
            <a class="sidebar-link <?php echo nav_active('home.php', $currentPage); ?>" href="/app/home.php"><i class="bi bi-compass"></i>Browse</a>
            <a class="sidebar-link <?php echo nav_active('dashboard.php', $currentPage); ?>" href="/app/dashboard.php"><i class="bi bi-speedometer"></i>Dashboard</a>
            <a class="sidebar-link <?php echo nav_active('history.php', $currentPage); ?>" href="/app/history.php"><i class="bi bi-clock-history"></i>History</a>
            <a class="sidebar-link <?php echo nav_active('saved.php', $currentPage); ?>" href="/app/saved.php"><i class="bi bi-bookmark"></i>Saved</a>
            <hr>
            <div class="text-muted small mb-2">Host Tools</div>
            <a class="sidebar-link <?php echo nav_active('host-tools-published.php', $currentPage); ?>" href="/app/host-tools-published.php"><i class="bi bi-layout-text-window-reverse"></i>Published events</a>
            <a class="sidebar-link <?php echo nav_active('host-tools-attendees.php', $currentPage); ?>" href="/app/host-tools-attendees.php"><i class="bi bi-people"></i>Attendees</a>
            <a class="sidebar-link <?php echo nav_active('host-tools-waitlist.php', $currentPage); ?>" href="/app/host-tools-waitlist.php"><i class="bi bi-hourglass-split"></i>Waitlist</a>
            <a class="sidebar-link <?php echo nav_active('host-tools-capacity.php', $currentPage); ?>" href="/app/host-tools-capacity.php"><i class="bi bi-sliders"></i>Capacity limits</a>
            <a class="sidebar-link <?php echo nav_active('host-tools-exports.php', $currentPage); ?>" href="/app/host-tools-exports.php"><i class="bi bi-download"></i>Data export</a>
            <hr>
            <div class="text-uppercase text-muted small mb-2">Categories</div>
            <a class="sidebar-link <?php echo category_active('Education', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Education"><i class="bi bi-book"></i>Education</a>
            <a class="sidebar-link <?php echo category_active('Business', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Business"><i class="bi bi-briefcase"></i>Business</a>
            <a class="sidebar-link <?php echo category_active('Wellness', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Wellness"><i class="bi bi-heart-pulse"></i>Wellness</a>
            <a class="sidebar-link <?php echo category_active('Technology', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Technology"><i class="bi bi-cpu"></i>Technology</a>
            <a class="sidebar-link <?php echo category_active('Growth', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Growth"><i class="bi bi-stars"></i>Growth</a>
            <a class="sidebar-link <?php echo category_active('Marketing', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Marketing"><i class="bi bi-megaphone"></i>Marketing</a>
            <a class="sidebar-link <?php echo category_active('Design', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Design"><i class="bi bi-palette"></i>Design</a>
            <a class="sidebar-link <?php echo category_active('Leadership', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Leadership"><i class="bi bi-people"></i>Leadership</a>
            <a class="sidebar-link <?php echo category_active('Finance', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Finance"><i class="bi bi-cash-coin"></i>Finance</a>
            <a class="sidebar-link <?php echo category_active('Health', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Health"><i class="bi bi-activity"></i>Health</a>
            <a class="sidebar-link <?php echo category_active('Productivity', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Productivity"><i class="bi bi-lightning-charge"></i>Productivity</a>
            <a class="sidebar-link <?php echo category_active('Creative', $currentPage, $currentCategory); ?>" href="/app/home.php?category=Creative"><i class="bi bi-brush"></i>Creative</a>
            <hr>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <main class="flex-grow-1 content-area">
