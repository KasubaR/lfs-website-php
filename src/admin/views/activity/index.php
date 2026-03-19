<?php
/**
 * admin/views/activity/index.php — Full activity feed (View all)
 *
 * Variables from admin.php route:
 *   $recentActivity  array  Items from ActivityService::getRecentActivity(50)
 *   $pageTitle       string
 *   $activePage      string
 *   $breadcrumbs     array
 */

$recentActivity = $recentActivity ?? [];
?>

<div class="admin-page-header">
  <a href="/admin/dashboard" class="admin-page-header__back">
    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to dashboard
  </a>
  <h2 class="admin-page-header__heading">Recent Activity</h2>
</div>

<div class="admin-panel" aria-label="All activity">
  <div class="admin-panel__body">
    <?php if (!empty($recentActivity)): ?>
      <?php foreach ($recentActivity as $item): ?>
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
      <p class="admin-empty__body">No activity yet.</p>
    <?php endif; ?>
  </div>
</div>
