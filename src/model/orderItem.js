/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/orderItem.js — Order item (line item shape)

   Database: Supabase table `order_items` (see supabase-schema.sql)
   Data layer: use a service (e.g. order.service.js) for Supabase calls.
   ============================================================ */

/**
 * Order item — single line item in an order (product, size, qty, price).
 * @typedef {Object} OrderItem
 * @property {string} id - UUID
 * @property {string} orderId - UUID
 * @property {string} productId - UUID
 * @property {string} [size] - e.g. "M", "L"
 * @property {number} quantity
 * @property {number} price - unit price at time of order
 * @property {string} [createdAt]
 */


