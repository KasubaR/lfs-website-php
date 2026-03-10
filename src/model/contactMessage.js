/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/contactMessage.js — Contact message (shape + constants)

   Database: Supabase table `contact_messages` (see supabase-schema.sql)
   Data layer: use a service (e.g. contactMessage.service.js) for Supabase calls.
   ============================================================ */

/**
 * Contact message — submission from the contact form.
 * @typedef {Object} ContactMessage
 * @property {string} id - UUID
 * @property {string} name
 * @property {string} email
 * @property {string} [subject]
 * @property {string} message
 * @property {string} status - New | Read | Responded
 * @property {string} [createdAt]
 */

/** Message status (matches DB check). */
const CONTACT_MESSAGE_STATUS = ['New', 'Read', 'Responded'];

module.exports = {
  CONTACT_MESSAGE_STATUS,
};
