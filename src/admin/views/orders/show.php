<?php
/**
 * admin/views/orders/show.php — Order detail + status update
 *
 * Variables from route (orders.php):
 *   $order       array       All order columns + items[]
 *   $payment     array|null  Latest payment row, or null if none yet
 *   $formatPrice callable
 *   $csrfToken   string
 */

$formatPrice = $formatPrice ?? fn ($v) => 'ZMW ' . number_format((float)$v, 2);
$id          = (int)($order['id'] ?? 0);
$orderBadge  = Order::STATUS_BADGES[$order['status']] ?? 'muted';
$orderLabel  = Order::STATUS_LABELS[$order['status']] ?? $order['status'];

$breadcrumbs = [
    ['label' => 'Admin',  'url' => '/admin/dashboard'],
    ['label' => 'Orders', 'url' => '/admin/orders'],
    ['label' => $order['order_number']],
];
?>

<!-- Page header with back link -->
<div class="admin-page-header">
  <a href="/admin/orders" class="admin-page-header__back">
    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to orders
  </a>
  <h2 class="admin-page-header__heading">
    <?= htmlspecialchars($order['order_number']) ?>
  </h2>
</div>

<div class="order-detail">

  <!-- ── Left: order summary ─────────────────────────── -->
  <div class="order-detail__main">

    <!-- Order meta -->
    <div class="admin-card">
      <p class="admin-card__title">Order details</p>
      <table class="admin-table admin-table--compact">
        <tbody>
          <tr>
            <th>Status</th>
            <td>
              <span class="status-pill status-pill--<?= htmlspecialchars($orderBadge) ?>">
                <?= htmlspecialchars($orderLabel) ?>
              </span>
            </td>
          </tr>
          <tr><th>Placed</th>    <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($order['created_at']))) ?></td></tr>
          <tr><th>Updated</th>   <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($order['updated_at']))) ?></td></tr>
          <tr><th>Subtotal</th>  <td><?= ($formatPrice)($order['subtotal'] ?? 0) ?></td></tr>
          <tr>
            <th>Total</th>
            <td><strong><?= ($formatPrice)($order['total'] ?? 0) ?></strong></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Customer -->
    <div class="admin-card">
      <p class="admin-card__title">Customer</p>
      <table class="admin-table admin-table--compact">
        <tbody>
          <tr><th>Name</th>  <td><?= htmlspecialchars($order['customer_name']) ?></td></tr>
          <tr>
            <th>Email</th>
            <td>
              <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>">
                <?= htmlspecialchars($order['customer_email']) ?>
              </a>
            </td>
          </tr>
          <?php if (!empty($order['customer_phone'])): ?>
          <tr>
            <th>Phone</th>
            <td>
              <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>">
                <?= htmlspecialchars($order['customer_phone']) ?>
              </a>
            </td>
          </tr>
          <?php endif; ?>
          <?php if (!empty($order['notes'])): ?>
          <tr><th>Notes</th> <td><?= nl2br(htmlspecialchars($order['notes'])) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Line items -->
    <div class="admin-card">
      <p class="admin-card__title">Items</p>
      <?php if (!empty($order['items'])): ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Unit price</th>
                <th>Line total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($order['items'] as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['name']  ?? '') ?></td>
                <td><?= htmlspecialchars($item['size']  ?? '—') ?></td>
                <td><?= (int)($item['qty'] ?? 1) ?></td>
                <td><?= ($formatPrice)($item['unitPrice'] ?? 0) ?></td>
                <td><strong><?= ($formatPrice)($item['lineTotal'] ?? 0) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="4"><strong>Subtotal</strong></td>
                <td><?= ($formatPrice)($order['subtotal'] ?? 0) ?></td>
              </tr>
              <tr>
                <td colspan="4"><strong>Total</strong></td>
                <td><strong><?= ($formatPrice)($order['total'] ?? 0) ?></strong></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php else: ?>
        <p class="admin-empty__body">No line items recorded.</p>
      <?php endif; ?>
    </div>

  </div><!-- /.order-detail__main -->

  <!-- ── Right: sidebar ──────────────────────────────── -->
  <div class="order-detail__aside">

    <!-- Update status — mirrors messages/show.php pattern -->
    <div class="admin-card">
      <p class="admin-card__title">Update status</p>
      <form method="post" action="/admin/orders/<?= $id ?>/status">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
        <div class="admin-form__field">
          <select name="status" class="admin-select">
            <?php foreach (Order::ORDER_STATUS as $s): ?>
              <option value="<?= htmlspecialchars($s) ?>"
                <?= $order['status'] === $s ? 'selected' : '' ?>>
                <?= htmlspecialchars(Order::STATUS_LABELS[$s]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary"
                onclick="return confirm('Update order status?')">
          Save status
        </button>
      </form>
    </div>

    <!-- Payment info -->
    <div class="admin-card">
      <p class="admin-card__title">Payment</p>
      <?php if ($payment): ?>
        <?php
        $payBadge = match($payment['status']) {
            'completed' => 'green',
            'failed'    => 'red',
            'cancelled' => 'red',
            'refunded'  => 'muted',
            default     => 'orange',
        };
        ?>
        <table class="admin-table admin-table--compact">
          <tbody>
            <tr>
              <th>Status</th>
              <td>
                <span class="status-pill status-pill--<?= $payBadge ?>">
                  <?= htmlspecialchars(ucfirst($payment['status'])) ?>
                </span>
              </td>
            </tr>
            <tr><th>Method</th>   <td><?= htmlspecialchars($payment['payment_method'] ?? '—') ?></td></tr>
            <tr><th>Amount</th>   <td><?= ($formatPrice)($payment['amount'] ?? 0) ?></td></tr>
            <?php if (!empty($payment['lenco_reference'])): ?>
            <tr><th>Ref</th>      <td><code><?= htmlspecialchars($payment['lenco_reference']) ?></code></td></tr>
            <?php endif; ?>
            <?php if (!empty($payment['lenco_provider'])): ?>
            <tr><th>Provider</th> <td><?= htmlspecialchars(strtoupper($payment['lenco_provider'])) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($payment['lenco_status'])): ?>
            <tr><th>Lenco</th>    <td><?= htmlspecialchars($payment['lenco_status']) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($payment['completed_at'])): ?>
            <tr><th>Paid at</th>  <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($payment['completed_at']))) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($payment['failed_at'])): ?>
            <tr><th>Failed at</th><td><?= htmlspecialchars(date('d M Y, H:i', strtotime($payment['failed_at']))) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($payment['failure_reason'])): ?>
            <tr><th>Reason</th>   <td><?= htmlspecialchars($payment['failure_reason']) ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="admin-empty__body">No payment attempt recorded yet.</p>
      <?php endif; ?>
    </div>

  </div><!-- /.order-detail__aside -->

</div><!-- /.order-detail -->
