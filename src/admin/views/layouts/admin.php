<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '') ?>" />
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — LFS Admin' : 'LFS Admin' ?></title>

  <link rel="icon" type="image/svg+xml" href="/favicon.svg">

  <!-- Preconnect -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,300&display=swap" rel="stylesheet" />

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- LFS Styles -->
  <link rel="stylesheet" href="<?= htmlspecialchars(lfs_public_url('/admin/css/admin.css'), ENT_QUOTES, 'UTF-8') ?>" />

  <!-- Page-specific styles -->
  <?= $extraStyles ?? '' ?>
</head>

<body class="admin-body">

<!-- ══════════════════════════════════════════════
     MOBILE OVERLAY
════════════════════════════════════════════════ -->
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

<!-- ══════════════════════════════════════════════
     ADMIN LAYOUT WRAPPER
════════════════════════════════════════════════ -->
<div class="admin-layout" id="adminLayout">

  <!-- ─────────────────────────────────────────
       SIDEBAR
  ───────────────────────────────────────────── -->
  <aside class="admin-sidebar" id="adminSidebar" role="navigation" aria-label="Admin Navigation">

    <!-- Logo -->
    <a href="/admin/dashboard" class="admin-sidebar__logo" aria-label="LFS Admin Home">
      <div class="admin-sidebar__logo-mark" aria-hidden="true">
        <img src="<?= htmlspecialchars(lfs_public_url('/images/Logo/1024%20512%20LFS_512x512%201.svg'), ENT_QUOTES, 'UTF-8') ?>" alt="LFS" style="width:100%;height:100%;object-fit:contain;">
      </div>
      <div class="admin-sidebar__logo-text">
        <span>Lusaka Fitness</span>
        <span>Admin Panel</span>
      </div>
    </a>

    <!-- Navigation -->
    <nav class="admin-sidebar__nav" aria-label="Main navigation">

      <?php
      /**
       * Helper: render a nav item.
       * $page    — activePage key for this item
       * $href    — link target
       * $icon    — FA icon class(es)
       * $label   — display text
       * $badge   — optional int badge count
       */
      $navItem = function (string $page, string $href, string $icon, string $label, int $badge = 0) use ($activePage): void {
          $isActive   = ($activePage ?? '') === $page;
          $activeAttr = $isActive ? ' active' : '';
          $ariaCur    = $isActive ? 'page' : 'false';
          echo '<a href="' . $href . '" class="nav-item' . $activeAttr . '" aria-current="' . $ariaCur . '">';
          echo '<i class="' . $icon . ' nav-item__icon" aria-hidden="true"></i>';
          echo '<span class="nav-item__label">' . htmlspecialchars($label) . '</span>';
          if ($badge > 0) {
              echo '<span class="nav-item__badge" aria-label="' . $badge . ' pending">' . $badge . '</span>';
          }
          echo '</a>' . "\n";
      };

      $counts = $counts ?? [];

      $navItem('dashboard', '/admin/dashboard',  'fas fa-gauge-high',                'Dashboard');
      $navItem('messages',  '/admin/messages',  'fas fa-envelope',                 'Messages', (int)($counts['unreadMessages'] ?? 0));
      // $navItem('members', '/admin/members', 'fas fa-users', 'Members'); // TODO: not yet implemented
      $navItem('events',    '/admin/events',     'fas fa-calendar-days',             'Events');
      $navItem('gallery',   '/admin/gallery',    'fas fa-images',                    'Gallery',  (int)($counts['pendingGallery']  ?? 0));
      $navItem('blog',      '/admin/blog',       'fas fa-pencil',                    'Blog');
      $navItem('faqs',      '/admin/faqs',       'fas fa-circle-question',           'FAQ');
      $navItem('products',  '/admin/products',   'fas fa-shirt',                     'Shop');
      $navItem('orders',    '/admin/orders',     'fas fa-bag-shopping',              'Orders',   (int)($counts['pendingOrders']   ?? 0));
      ?>

    </nav><!-- /.admin-sidebar__nav -->

    <!-- Footer — Go back to site + Logout -->
    <div class="admin-sidebar__footer">
      <a href="/" class="nav-item" aria-label="Go back to site" target="_blank" rel="noopener noreferrer">
        <i class="fas fa-arrow-up-right-from-square nav-item__icon" aria-hidden="true"></i>
        <span class="nav-item__label">Go back to site</span>
      </a>
      <a href="/admin/logout"
         class="nav-item"
         onclick="return confirm('Log out of admin panel?')"
         aria-label="Logout">
        <i class="fas fa-right-from-bracket nav-item__icon" aria-hidden="true"></i>
        <span class="nav-item__label">Logout</span>
      </a>
    </div>

  </aside><!-- /.admin-sidebar -->


  <!-- ─────────────────────────────────────────
       TOP BAR
  ───────────────────────────────────────────── -->
  <header class="admin-topbar" id="adminTopbar" role="banner">

    <!-- Left: toggle + title -->
    <div class="admin-topbar__left">
      <button class="admin-topbar__toggle"
              id="sidebarToggle"
              onclick="toggleSidebar()"
              aria-label="Toggle sidebar"
              aria-expanded="true"
              aria-controls="adminSidebar">
        <i class="fas fa-bars" aria-hidden="true"></i>
      </button>

      <div class="admin-topbar__title-area">
        <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
        <?php if (!empty($breadcrumbs)): ?>
          <nav class="admin-topbar__breadcrumb" aria-label="Breadcrumb">
            <a href="/admin/dashboard"><i class="fas fa-house" aria-hidden="true"></i></a>
            <?php foreach ($breadcrumbs as $idx => $crumb): ?>
              <span class="sep" aria-hidden="true">/</span>
              <?php if ($idx < count($breadcrumbs) - 1): ?>
                <a href="<?= htmlspecialchars($crumb['url'] ?? '#') ?>"><?= htmlspecialchars($crumb['label']) ?></a>
              <?php else: ?>
                <span class="current" aria-current="page"><?= htmlspecialchars($crumb['label']) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: notifications + profile -->
    <div class="admin-topbar__right">

      <!-- Search -->
      <button class="topbar-btn" aria-label="Search" title="Search">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
      </button>

      <!-- Notifications -->
      <div style="position:relative;" class="js-dropdown-wrap">
        <button class="topbar-btn js-dropdown-toggle"
                aria-label="Notifications"
                title="Notifications"
                aria-expanded="false"
                aria-haspopup="true">
          <i class="fas fa-bell" aria-hidden="true"></i>
          <?php if (!empty($notifications['unread'])): ?>
            <span class="topbar-btn__dot" aria-label="<?= (int)$notifications['unread'] ?> unread notifications"></span>
          <?php endif; ?>
        </button>

        <div class="admin-dropdown js-dropdown" role="menu" aria-label="Notifications menu">
          <div class="admin-dropdown__header">
            <strong>Notifications</strong>
            <?= (!empty($notifications['unread'])) ? htmlspecialchars($notifications['unread']) . ' unread' : 'All caught up' ?>
          </div>
          <?php if (!empty($notifications['items'])): ?>
            <?php foreach (array_slice($notifications['items'], 0, 5) as $notif): ?>
              <a href="<?= htmlspecialchars($notif['url'] ?? '#') ?>" class="admin-dropdown__item" role="menuitem">
                <i class="<?= htmlspecialchars($notif['icon'] ?? 'fas fa-circle-info') ?>" aria-hidden="true"></i>
                <?= htmlspecialchars($notif['message'] ?? '') ?>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="admin-dropdown__item" style="cursor:default;color:var(--text-dim);">
              <i class="fas fa-check-circle" aria-hidden="true"></i>
              No new notifications
            </div>
          <?php endif; ?>
          <div class="admin-dropdown__sep"></div>
          <a href="/admin/notifications" class="admin-dropdown__item" role="menuitem">
            <i class="fas fa-list" aria-hidden="true"></i> View all notifications
          </a>
        </div>
      </div>

      <!-- Profile Dropdown -->
      <div style="position:relative;" class="js-dropdown-wrap">
        <button class="admin-profile-trigger js-dropdown-toggle"
                aria-expanded="false"
                aria-haspopup="true"
                aria-label="Profile menu">
          <div class="avatar-sm" aria-hidden="true">
            <img src="/images/Logo/1024%20512%20LFS_512x512%201.svg" alt="LFS" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;">
          </div>
          <span><?= htmlspecialchars(explode(' ', ($adminUser['name'] ?? 'Admin'))[0]) ?></span>
          <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>

        <div class="admin-dropdown js-dropdown" role="menu" aria-label="Profile menu">
          <div class="admin-dropdown__header">
            <strong><?= htmlspecialchars($adminUser['name'] ?? 'Admin User') ?></strong>
            <?= htmlspecialchars($adminUser['email'] ?? '') ?>
          </div>
          <a href="/admin/profile" class="admin-dropdown__item" role="menuitem">
            <i class="fas fa-user" aria-hidden="true"></i> My Profile
          </a>
          <a href="/" class="admin-dropdown__item" target="_blank" rel="noopener" role="menuitem">
            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> View Site
          </a>
          <div class="admin-dropdown__sep"></div>
          <a href="/admin/logout"
             class="admin-dropdown__item danger"
             onclick="return confirm('Log out of admin panel?')"
             role="menuitem">
            <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Logout
          </a>
        </div>
      </div>

    </div><!-- /.admin-topbar__right -->
  </header><!-- /.admin-topbar -->


  <!-- ─────────────────────────────────────────
       MAIN CONTENT AREA
  ───────────────────────────────────────────── -->
  <main class="admin-main" id="adminMain" role="main" aria-label="Main content">

    <!-- Flash messages -->
    <?php if (!empty($flash['success'])): ?>
      <div class="sys-notif sys-notif--info" role="alert" style="margin-bottom:1rem;">
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        <span><?= htmlspecialchars($flash['success']) ?></span>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;" aria-label="Dismiss">
          <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
      <div class="sys-notif sys-notif--error" role="alert" style="margin-bottom:1rem;">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        <span><?= htmlspecialchars($flash['error']) ?></span>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;" aria-label="Dismiss">
          <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
    <?php endif; ?>
    <?php if (!empty($flash['warning'])): ?>
      <div class="sys-notif sys-notif--warning" role="alert" style="margin-bottom:1rem;">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <span><?= htmlspecialchars($flash['warning']) ?></span>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;" aria-label="Dismiss">
          <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
    <?php endif; ?>

    <!-- Page body injected here -->
    <?= $content ?>

  </main><!-- /.admin-main -->

</div><!-- /.admin-layout -->


<!-- ══════════════════════════════════════════════
     GLOBAL SCRIPTS
════════════════════════════════════════════════ -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- DataTables (jQuery required) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<link  rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css" />
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<!-- LFS Admin JS -->
<script src="<?= htmlspecialchars(lfs_public_url('/admin/js/dashboard.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

<!-- Vanilla dropdown (replaces Alpine.js x-data toggles) -->
<script>
(function () {
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest('.js-dropdown-toggle');
    const wrap   = e.target.closest('.js-dropdown-wrap');

    // Close all open dropdowns not belonging to the clicked toggle
    document.querySelectorAll('.js-dropdown-wrap').forEach(function (w) {
      if (w !== wrap) {
        w.querySelector('.js-dropdown')?.classList.remove('open');
        const t = w.querySelector('.js-dropdown-toggle');
        if (t) t.setAttribute('aria-expanded', 'false');
      }
    });

    if (toggle) {
      const dropdown = toggle.closest('.js-dropdown-wrap')?.querySelector('.js-dropdown');
      if (dropdown) {
        const nowOpen = dropdown.classList.toggle('open');
        toggle.setAttribute('aria-expanded', nowOpen.toString());
      }
    }
  });
})();
</script>

<!-- Page-specific scripts -->
<?= $extraScripts ?? '' ?>

</body>
</html>
