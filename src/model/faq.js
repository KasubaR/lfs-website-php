/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/faq.js — FAQ (shape)

   Database: Supabase table `faqs` (see supabase-schema.sql)
   Data layer: use a service (e.g. faq.service.js) for Supabase calls.
   ============================================================ */

/**
 * FAQ — frequently asked question.
 * @typedef {Object} FAQ
 * @property {string} id - UUID
 * @property {string} question
 * @property {string} answer
 * @property {string} [category]
 * @property {string} [createdAt]
 */


