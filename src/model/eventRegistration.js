/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/eventRegistration.js — Event registration (shape + constants)

   Database: Supabase table `event_registrations` (see supabase-schema.sql)
   Data layer: use a service (e.g. eventRegistration.service.js) for Supabase calls.
   ============================================================ */

/**
 * Event registration — member registered for an event.
 * @typedef {Object} EventRegistration
 * @property {string} id - UUID
 * @property {string} eventId - UUID (event)
 * @property {string} userId - UUID (member/user)
 * @property {string} [bibNumber] - e.g. "001", "A42"
 * @property {string} status - Registered | Completed | Cancelled
 * @property {string} paymentStatus - pending | paid | refunded | free
 * @property {string} [registeredAt] - ISO date
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */

/** Registration status (matches DB check). */
const REGISTRATION_STATUS = ['Registered', 'Completed', 'Cancelled'];

/** Payment status (matches DB check). */
const PAYMENT_STATUS = ['pending', 'paid', 'refunded', 'free'];

module.exports = {
  REGISTRATION_STATUS,
  PAYMENT_STATUS,
};
