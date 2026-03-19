<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/OrderItem.php — Order item shape
 *
 * Database: MySQL table `order_items`
 * Data layer: src/services/OrderService.php
 *
 * Fields:
 *   id, orderId, productId, size, quantity, price, createdAt
 *
 * No constants — this is a pure join/line-item table.
 */

declare(strict_types=1);

class OrderItem
{
    // No domain constants required for this model.
}
