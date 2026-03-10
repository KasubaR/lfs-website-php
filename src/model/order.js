/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/order.js — Order model (purchases: shape + constants)

   Database: Supabase table `orders` (see supabase-schema.sql)
   Data layer: use a service (e.g. order.service.js) for Supabase calls.
   ============================================================ */

/**
 * Order — purchase (shop).
 * @typedef {Object} Order
 * @property {string} id - UUID
 * @property {string} userId - UUID
 * @property {number} totalAmount
 * @property {string} paymentMethod - MTN | Airtel | Bank Transfer | Card
 * @property {string} paymentStatus - pending | paid | failed | refunded
 * @property {string} orderStatus - Pending | Paid | Processing | Completed | Cancelled
 * @property {string} [pickupLocation]
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */

/** Payment methods (matches DB check). */
const PAYMENT_METHODS = ['MTN', 'Airtel', 'Bank Transfer', 'Card'];

/** Order status (matches DB check). */
const ORDER_STATUS = ['Pending', 'Paid', 'Processing', 'Completed', 'Cancelled'];

/** Payment status (matches DB check). */
const PAYMENT_STATUS = ['pending', 'paid', 'failed', 'refunded'];

module.exports = {
  PAYMENT_METHODS,
  ORDER_STATUS,
  PAYMENT_STATUS,
};
