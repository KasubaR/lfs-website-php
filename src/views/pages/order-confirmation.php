<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/views/pages/order-confirmation.php
 *
 * Variables: $order (array from OrderModel::findByOrderNumber)
 */

$order ??= [];
$orderNumber  = htmlspecialchars($order['order_number']  ?? '—');
$customerName = htmlspecialchars($order['customer_name'] ?? '');
$total        = 'K ' . number_format((float)($order['total'] ?? 0), 2);
$status       = $order['status'] ?? 'pending_payment';
$items        = $order['items'] ?? [];

$styles  = '<link rel="stylesheet" href="' . htmlspecialchars(lfs_public_url('/css/checkout.css'), ENT_QUOTES, 'UTF-8') . '">';
?>

<div class="checkout-header">
  <div class="checkout-header__inner">
    <h1 class="checkout-header__title">Order Confirmed</h1>
  </div>
</div>

<section class="checkout-section">
  <div class="checkout-section__inner" style="max-width:640px; margin:0 auto;">

    <div class="checkout-card">
      <div class="checkout-card__body" style="padding:0;">
        <div class="checkout-confirmation">

          <div class="checkout-confirmation__icon" aria-hidden="true">
            <i class="fas fa-check"></i>
          </div>

          <h2 class="checkout-confirmation__heading">
            <?php if (in_array($status, ['paid', 'completed'])): ?>
              Payment Received!
            <?php elseif ($status === 'payment_failed'): ?>
              Payment Failed
            <?php else: ?>
              Order Placed — Awaiting Payment
            <?php endif; ?>
          </h2>

          <p class="checkout-confirmation__msg">
            <?php if (in_array($status, ['paid', 'completed'])): ?>
              Thank you, <?= $customerName ?>! Your payment has been confirmed.
              We'll notify you via WhatsApp or SMS when your order is ready for pickup.
            <?php elseif ($status === 'payment_failed'): ?>
              Your payment could not be processed. Please
              <a href="/shop/cart" style="color:var(--green);">return to cart</a>
              and try again.
            <?php else: ?>
              We've received your order. Once payment is confirmed we'll send you a
              WhatsApp or SMS notification. Your order number is below.
            <?php endif; ?>
          </p>

          <div class="checkout-confirmation__order-num">
            <div>
              <span class="checkout-confirmation__order-label">Order Number</span>
              <span class="checkout-confirmation__order-val"><?= $orderNumber ?></span>
            </div>
          </div>

          <?php if ($items): ?>
          <div class="checkout-confirmation__summary">
            <?php foreach ($items as $item): ?>
            <div class="checkout-summary__item">
              <div class="checkout-summary__item-info">
                <div class="checkout-summary__item-name">
                  <?= htmlspecialchars($item['name'] ?? '') ?>
                </div>
                <div class="checkout-summary__item-meta">
                  Size: <?= htmlspecialchars($item['size'] ?? '') ?> · Qty <?= (int)($item['qty'] ?? 1) ?>
                </div>
              </div>
              <div class="checkout-summary__item-price">
                K <?= number_format((float)($item['lineTotal'] ?? 0)) ?>
              </div>
            </div>
            <?php endforeach; ?>
            <div class="checkout-summary__row checkout-summary__total-row" style="margin-top:1rem;">
              <span class="checkout-summary__total-label">Total</span>
              <span class="checkout-summary__total-amount"><?= $total ?></span>
            </div>
          </div>
          <?php endif; ?>

          <div class="checkout-confirmation__actions">
            <a href="/shop" class="btn btn-primary">
              <i class="fas fa-store"></i> Continue Shopping
            </a>
            <button class="btn btn-back" onclick="window.print()" type="button">
              <i class="fas fa-print"></i> Print Receipt
            </button>
          </div>

        </div><!-- /.checkout-confirmation -->
      </div>
    </div><!-- /.checkout-card -->

  </div>
</section>
