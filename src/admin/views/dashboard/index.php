<?php
/**
 * admin/views/dashboard/index.php — LFS Admin Dashboard
 *
 * Variables expected from controller:
 *   $adminUser      array   { name, email, role }
 *   $stats          array   { unreadMessages, upcomingEvents, pendingOrders, monthlyRevenue }
 *   $upcomingEvents array
 *   $recentActivity array
 *   $pendingTasks   array   { orders, events, gallery, memberships }
 *   $systemAlerts   array
 *   $chartData      array   { members[], events[], sales[], gallery[] }
 */

$pageTitle   = 'Dashboard';
$activePage  = 'dashboard';
$breadcrumbs = [];
?>

<!-- ══════════════════════════════════════════════
     1. STATS CARDS
════════════════════════════════════════════════ -->
<section class="stats-grid" aria-label="Key metrics">

  <!-- New Messages -->
  <article class="stat-card stat-card--green" aria-label="New contact messages">
    <div class="stat-card__icon" aria-hidden="true"><i class="fas fa-envelope"></i></div>
    <p class="stat-card__label">New Messages</p>
    <p class="stat-card__value" id="stat-new-messages">
      <?= isset($stats) ? number_format($stats['newMessages']) : '—' ?>
    </p>
    <div class="stat-card__meta">
      <span class="stat-card__meta-text">Contact form</span>
    </div>
  </article>

  <!-- Upcoming Events -->
  <article class="stat-card stat-card--red" aria-label="Upcoming events in next 30 days">
    <div class="stat-card__icon" aria-hidden="true"><i class="fas fa-calendar-days"></i></div>
    <p class="stat-card__label">Upcoming Events</p>
    <p class="stat-card__value" id="stat-upcoming-events">
      <?= isset($stats) ? $stats['upcomingEvents'] : '—' ?>
    </p>
    <div class="stat-card__meta">
      <span style="font-size:0.68rem; color:var(--text-dim);">Next 30 days</span>
    </div>
  </article>

  <!-- Pending Orders -->
  <article class="stat-card stat-card--orange" aria-label="Pending shop orders">
    <div class="stat-card__icon" aria-hidden="true"><i class="fas fa-bag-shopping"></i></div>
    <p class="stat-card__label">Pending Orders</p>
    <p class="stat-card__value" id="stat-pending-orders">
      <?= isset($stats) ? $stats['pendingOrders'] : '—' ?>
    </p>
    <div class="stat-card__meta">
      <?php if (isset($stats) && $stats['pendingOrders'] > 0): ?>
        <span class="stat-card__trend stat-card__trend--down" aria-label="Needs attention">
          <i class="fas fa-circle-exclamation" aria-hidden="true"></i> Action needed
        </span>
      <?php else: ?>
        <span style="font-size:0.68rem;color:var(--text-dim);">All clear</span>
      <?php endif; ?>
    </div>
  </article>

  <!-- Monthly Revenue -->
  <article class="stat-card stat-card--gold" aria-label="Monthly revenue">
    <div class="stat-card__icon" aria-hidden="true"><i class="fas fa-circle-dollar-to-slot"></i></div>
    <p class="stat-card__label">Revenue</p>
    <p class="stat-card__value" id="stat-revenue" style="font-size:1.6rem; letter-spacing:0.01em;">
      K <?= isset($stats) ? number_format($stats['monthlyRevenue']) : '—' ?>
    </p>
    <div class="stat-card__meta">
      <span style="font-size:0.68rem;color:var(--text-dim);">This month</span>
    </div>
  </article>

</section>


<!-- ══════════════════════════════════════════════
     2. QUICK ACTIONS
════════════════════════════════════════════════ -->
<section class="quick-actions" aria-label="Quick actions">
  <a href="/admin/events/create"   class="btn-quick btn-quick--green">
    <i class="fas fa-plus" aria-hidden="true"></i> Add Event
  </a>
  <a href="/admin/products/create" class="btn-quick btn-quick--outline">
    <i class="fas fa-plus" aria-hidden="true"></i> Add Product
  </a>
  <a href="/admin/blog/new"        class="btn-quick btn-quick--outline">
    <i class="fas fa-plus" aria-hidden="true"></i> Add Blog
  </a>
  <a href="/admin/gallery/upload"  class="btn-quick btn-quick--outline">
    <i class="fas fa-upload" aria-hidden="true"></i> Upload Gallery
  </a>
</section>


<!-- ══════════════════════════════════════════════
     3. UPCOMING EVENTS
════════════════════════════════════════════════ -->
<section class="admin-panel" aria-label="Upcoming events">
  <div class="admin-panel__header">
    <h2 class="admin-panel__title">
      <span class="admin-panel__title-dot" style="background:var(--flag-red);" aria-hidden="true"></span>
      Upcoming Events
    </h2>
    <a href="/admin/events/list" class="admin-panel__action">View all →</a>
  </div>
  <div class="admin-panel__body">
    <?php if (!empty($upcomingEvents)): ?>
      <ul class="upcoming-events" aria-label="Next scheduled events">
        <?php foreach ($upcomingEvents as $ev): ?>
          <li class="upcoming-events__item">
            <div class="upcoming-events__main">
              <span class="upcoming-events__title"><?= htmlspecialchars($ev['title']) ?></span>
              <span class="upcoming-events__meta">
                <i class="fas fa-calendar-day" aria-hidden="true"></i>
                <?php if (!empty($ev['eventDate'])): ?>
                  <?= date('j M Y', strtotime($ev['eventDate'])) ?>
                <?php else: ?>
                  TBA
                <?php endif; ?>
                <?php if (!empty($ev['location'])): ?>
                  &nbsp;·&nbsp;
                  <i class="fas fa-location-dot" aria-hidden="true"></i>
                  <?= htmlspecialchars($ev['location']) ?>
                <?php endif; ?>
              </span>
            </div>
            <div class="upcoming-events__actions">
              <a href="/events/<?= htmlspecialchars($ev['slug'] ?? $ev['id']) ?>"
                 class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener">
                View
              </a>
              <a href="/admin/events/<?= htmlspecialchars($ev['id']) ?>/edit"
                 class="admin-btn admin-btn--primary admin-btn--sm">
                Edit
              </a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="upcoming-events__empty">
        No upcoming events scheduled. Create a new event to see it here.
      </p>
    <?php endif; ?>
  </div>
</section>


<!-- ══════════════════════════════════════════════
     4. CHARTS ROW
════════════════════════════════════════════════ -->
<section class="charts-grid" aria-label="Analytics charts">

  <!-- Sales performance -->
  <div class="admin-panel" aria-label="Sales performance">
    <div class="admin-panel__header">
      <h2 class="admin-panel__title">
        <span class="admin-panel__title-dot" style="background:var(--flag-orange);" aria-hidden="true"></span>
        Sales Performance
      </h2>
    </div>
    <div class="admin-panel__body">
      <div class="chart-wrapper" style="height:260px;position:relative;">
        <div class="loading-overlay" id="chartSalesLoader" aria-label="Loading chart">
          <div class="loading-spinner"></div>
        </div>
        <canvas id="chartSales" aria-label="Sales performance chart"></canvas>
      </div>
    </div>
  </div>

</section>


<!-- ══════════════════════════════════════════════
     5. RECENT ACTIVITY + PENDING TASKS
════════════════════════════════════════════════ -->
<section class="content-grid" aria-label="Activity and tasks">

  <!-- Recent Activity Feed -->
  <div class="admin-panel" aria-label="Recent activity">
    <div class="admin-panel__header">
      <h2 class="admin-panel__title">
        <span class="admin-panel__title-dot" style="background:var(--flag-green);" aria-hidden="true"></span>
        Recent Activity
      </h2>
      <a href="/admin/activity" class="admin-panel__action" aria-label="View all activity">View all →</a>
    </div>
    <div class="admin-panel__body" style="padding:0 1.25rem;" id="activityFeed">

      <?php if (!empty($recentActivity)): ?>
        <?php foreach (array_slice($recentActivity, 0, 8) as $item): ?>
          <div class="activity-item">
            <div class="activity-icon activity-icon--<?= htmlspecialchars($item['type']) ?>" aria-hidden="true">
              <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
            </div>
            <div class="activity-content">
              <p class="activity-title"><?= htmlspecialchars($item['title']) ?></p>
              <p class="activity-sub"><?= htmlspecialchars($item['subtitle']) ?></p>
            </div>
            <time class="activity-time" datetime="<?= htmlspecialchars($item['isoDate']) ?>"><?= htmlspecialchars($item['timeAgo']) ?></time>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- Skeleton placeholders while JS loads -->
        <?php for ($i = 0; $i < 5; $i++): ?>
          <div class="activity-item" aria-hidden="true">
            <div class="skeleton" style="width:32px;height:32px;border-radius:8px;flex-shrink:0;"></div>
            <div class="activity-content">
              <div class="skeleton" style="height:12px;width:60%;margin-bottom:6px;"></div>
              <div class="skeleton" style="height:10px;width:40%;"></div>
            </div>
            <div class="skeleton" style="height:10px;width:40px;"></div>
          </div>
        <?php endfor; ?>
      <?php endif; ?>

    </div>
  </div>


  <!-- Pending Tasks -->
  <div class="admin-panel" aria-label="Pending tasks">
    <div class="admin-panel__header">
      <h2 class="admin-panel__title">
        <span class="admin-panel__title-dot" style="background:var(--flag-orange);" aria-hidden="true"></span>
        Pending Tasks
      </h2>
      <span style="font-size:0.7rem;color:var(--text-dim);">Needs attention</span>
    </div>
    <div class="admin-panel__body" style="padding:0 1.25rem;">

      <?php $tasks = $pendingTasks ?? []; ?>

      <!-- Orders -->
      <div class="task-item">
        <div class="task-priority task-priority--high" aria-hidden="true"></div>
        <span class="task-label">
          <i class="fas fa-bag-shopping" style="margin-right:0.4rem;opacity:0.5;" aria-hidden="true"></i>
          Orders to process
        </span>
        <span class="task-count" aria-label="<?= (int)($tasks['orders'] ?? 0) ?> orders"><?= (int)($tasks['orders'] ?? 0) ?></span>
        <a href="/admin/orders?status=pending" class="task-action" aria-label="Process pending orders">Process →</a>
      </div>

      <!-- Events -->
      <div class="task-item">
        <div class="task-priority task-priority--medium" aria-hidden="true"></div>
        <span class="task-label">
          <i class="fas fa-calendar-days" style="margin-right:0.4rem;opacity:0.5;" aria-hidden="true"></i>
          Events to publish
        </span>
        <span class="task-count" aria-label="<?= (int)($tasks['events'] ?? 0) ?> events"><?= (int)($tasks['events'] ?? 0) ?></span>
        <a href="/admin/events?status=draft" class="task-action" aria-label="Review draft events">Review →</a>
      </div>

    </div><!-- /.admin-panel__body -->

    <!-- System Alerts -->
    <div class="admin-panel__header" style="margin-top:0.5rem;">
      <h2 class="admin-panel__title">
        <span class="admin-panel__title-dot" style="background:var(--flag-red);" aria-hidden="true"></span>
        System Alerts
      </h2>
    </div>
    <div style="padding:0.75rem 1.25rem 1.25rem;" id="sysAlerts">
      <?php if (!empty($systemAlerts)): ?>
        <?php foreach ($systemAlerts as $alert): ?>
          <div class="sys-notif sys-notif--<?= htmlspecialchars($alert['type']) ?>" role="alert">
            <i class="<?= htmlspecialchars($alert['icon']) ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($alert['message']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="sys-notif sys-notif--info" role="status">
          <i class="fas fa-check-circle" aria-hidden="true"></i>
          <span>All systems operational</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

</section>


<!-- ══════════════════════════════════════════════
     6. CHART DATA — injected for dashboard.js
════════════════════════════════════════════════ -->
<script id="dashboardData" type="application/json">
  <?= json_encode([
    'chartData' => $chartData ?? null,
    'stats'     => $stats     ?? null,
  ], JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
