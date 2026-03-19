<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/model/Order.php — Order model constants
 *
 * Database: MySQL table `orders`
 * Data layer: src/services/OrderService.php
 *
 * Fields:
 *   id, userId, totalAmount, paymentMethod, paymentStatus,
 *   orderStatus, pickupLocation, createdAt, updatedAt
 */

declare(strict_types=1);

class Order
{
    /** Order status values — matches `status` column in the `orders` table. */
    public const ORDER_STATUS = [
        'pending_payment',
        'paid',
        'processing',
        'ready',
        'collected',
        'cancelled',
        'payment_failed',
    ];

    /** Human-readable labels for each order status. */
    public const STATUS_LABELS = [
        'pending_payment' => 'Pending Payment',
        'paid'            => 'Paid',
        'processing'      => 'Processing',
        'ready'           => 'Ready for Pickup',
        'collected'       => 'Collected',
        'cancelled'       => 'Cancelled',
        'payment_failed'  => 'Payment Failed',
    ];

    /**
     * Badge CSS modifier (maps to .badge--{color} classes in admin.css).
     * Used by admin views for coloured status pills.
     */
    public const STATUS_BADGES = [
        'pending_payment' => 'orange',
        'paid'            => 'blue',
        'processing'      => 'blue',
        'ready'           => 'green',
        'collected'       => 'green',
        'cancelled'       => 'red',
        'payment_failed'  => 'red',
    ];

    /** Payment status values — matches `status` column in the `payments` table. */
    public const PAYMENT_STATUS = ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'];
}
