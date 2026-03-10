/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/eventResult.js — Event result (race result shape)

   Database: Supabase table `event_results` (see supabase-schema.sql)
   Data layer: use a service (e.g. eventResult.service.js) for Supabase calls.
   ============================================================ */

/**
 * Event result — single race result (e.g. 1st Place, 02:14:32, Open, LFS).
 * @typedef {Object} EventResult
 * @property {string} id - UUID
 * @property {string} eventId - UUID (event)
 * @property {string} runnerName
 * @property {number} position - e.g. 1 (1st), 2 (2nd)
 * @property {string} time - e.g. "02:14:32"
 * @property {string} [category] - e.g. "Open", "Senior Men"
 * @property {string} [club] - e.g. "LFS"
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */


