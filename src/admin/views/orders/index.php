<?php
/**
 * admin/views/orders/index.php — Orders list
 *
 * Variables from route (orders.php):
 *   $orderList    array     Flat rows from OrderModel::getAll()
 *   $statusCounts array     [ status => int ] per-status counts
 *   $filters      array     { status: string }
 *   $total        int       Total rows matching current filter
 *   $pages        int       Total pages
 *   $page         int       Current page (1-based)
 *   $formatPrice  callable
 *   $csrfToken    string
 */

$list         = $orderList    ?? [];
$statusFilter = $filters['status'] ?? '';
$currentPage  = $page         ?? 1;
$formatPrice  = $formatPrice  ?? fn ($v) => 'ZMW ' . number_format((float)$v, 2);
$breadcrumbs  = [
    ['label' => 'Admin',  'url' => '/admin/dashboard'],
    ['label' => 'Orders'],
];
?>

<!-- Page header -->
<div class="admin-page-header">
  <h2 class="admin-page-header__heading">Orders</h2>
</div>

<!-- Stats row -->
<section class="stats-grid" aria-label="Order stats">
  <article class="stat-card stat-card--orange">
    <div class="stat-card__icon"><i class="fas fa-clock" aria-hidden="true"></i></div>
    <p class="stat-card__label">Pending Payment</p>
    <p class="stat-card__value"><?= (int)($statusCounts['pending_payment'] ?? 0) ?></p>
  </article>
  <article class="stat-card stat-card--blue">
    <div class="stat-card__icon"><i class="fas fa-circle-check" aria-hidden="true"></i></div>
    <p class="stat-card__label">Paid</p>
    <p class="stat-card__value"><?= (int)($statusCounts['paid'] ?? 0) ?></p>
  </article>
  <article class="stat-card stat-card--green">
    <div class="stat-card__icon"><i class="fas fa-bag-shopping" aria-hidden="true"></i></div>
    <p class="stat-card__label">Collected</p>
    <p class="stat-card__value"><?= (int)($statusCounts['collected'] ?? 0) ?></p>
  </article>
  <article class="stat-card stat-card--red">
    <div class="stat-card__icon"><i class="fas fa-circle-xmark" aria-hidden="true"></i></div>
    <p class="stat-card__label">Cancelled / Failed</p>
    <p class="stat-card__value"><?= (int)($statusCounts['cancelled'] ?? 0) + (int)($statusCounts['payment_failed'] ?? 0) ?></p>
  </article>
</section>

<!-- Toolbar: status filter -->
<div class="admin-toolbar">
  <form method="GET" action="/admin/orders" class="admin-toolbar__filters">
    <label for="orders-status-filter" class="admin-label">Status</label>
    <select id="orders-status-filter" name="status" class="admin-select"
            onchange="this.form.submit()">
      <option value="">All orders</option>
      <?php foreach (Order::ORDER_STATUS as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>"
          <?= $statusFilter === $s ? 'selected' : '' ?>>
          <?= htmlspecialchars(Order::STATUS_LABELS[$s]) ?>
          (<?= (int)($statusCounts[$s] ?? 0) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($statusFilter !== ''): ?>
      <a href="/admin/orders" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fas fa-xmark" aria-hidden="true"></i> Clear
      </a>
    <?php endif; ?>
  </form>
</div>

<!-- Orders table -->
<?php if (!empty($list)): ?>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $o):
            $badge = Order::STATUS_BADGES[$o['status']] ?? 'muted';
            $lbl   = Order::STATUS_LABELS[$o['status']] ?? $o['status'];
        ?>
        <tr>
          <td>
            <a href="/admin/orders/<?= (int)$o['id'] ?>">
              <strong><?= htmlspecialchars($o['order_number']) ?></strong>
            </a>
          </td>
          <td>
            <?= htmlspecialchars($o['customer_name']) ?>
            <div class="admin-table__sub"><?= htmlspecialchars($o['customer_email']) ?></div>
          </td>
          <td><?= ($formatPrice)($o['total']) ?></td>
          <td>
            <span class="status-pill status-pill--<?= htmlspecialchars($badge) ?>">
              <?= htmlspecialchars($lbl) ?>
            </span>
          </td>
          <td><?= htmlspecialchars(date('d M Y', strtotime($o['created_at']))) ?></td>
          <td>
            <a href="/admin/orders/<?= (int)$o['id'] ?>"
               class="admin-btn admin-btn--primary admin-btn--sm">
              View
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if (($pages ?? 1) > 1): ?>
    <nav class="admin-toolbar" aria-label="Pagination" style="justify-content:center;margin-top:1rem;">
      <?php for ($p = 1; $p <= $pages; $p++):
          $pHref = '/admin/orders?page=' . $p;
          if ($statusFilter !== '') $pHref .= '&status=' . urlencode($statusFilter);
      ?>
        <a href="<?= htmlspecialchars($pHref) ?>"
           class="admin-btn admin-btn--sm <?= $p === $currentPage ? 'admin-btn--primary' : 'admin-btn--ghost' ?>"
           aria-current="<?= $p === $currentPage ? 'page' : 'false' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>

<?php else: ?>

  <div class="admin-empty">
    <i class="fas fa-bag-shopping admin-empty__icon" aria-hidden="true"></i>
    <p class="admin-empty__title">No orders found</p>
    <p class="admin-empty__body">
      <?= $statusFilter !== ''
          ? 'No orders match the selected status filter.'
          : 'Orders will appear here once customers place them.' ?>
    </p>
    <?php if ($statusFilter !== ''): ?>
      <a href="/admin/orders" class="admin-btn admin-btn--ghost">
        <i class="fas fa-xmark" aria-hidden="true"></i> Clear filter
      </a>
    <?php endif; ?>
  </div>

<?php endif; ?>
