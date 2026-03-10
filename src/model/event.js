/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/event.js — Event model (core shape)
 
   Database: Supabase table `events` (see supabase-schema.sql)
   Data layer: src/services/event.service.js
 
   Event fields (camelCase in app):
     id, title, description, location, eventDate, distance,
     category, registrationOpen, registrationClose, bannerImage,
     series, createdBy, createdAt, updatedAt
   ============================================================ */

/**
 * Event model — LFS events and races.
 * @typedef {Object} Event
 * @property {string} id - UUID
 * @property {string} title - e.g. "Saturday LSD Run", "LFS Half Marathon"
 * @property {string} [description]
 * @property {string} [location]
 * @property {string} eventDate - ISO date
 * @property {string} [distance] - e.g. "10K", "21.1K"
 * @property {string} [category] - e.g. "Road Race", "Training", "LSD"
 * @property {string|null} [registrationOpen] - ISO date
 * @property {string|null} [registrationClose] - ISO date
 * @property {string|null} [bannerImage] - URL
 * @property {string|null} [series] - e.g. "Saturday LSD runs", "Satellite weekly training"
 * @property {string|null} [createdBy] - UUID (user)
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */

/** Default category options for events. */
const EVENT_CATEGORIES = [
  'LSD',
  'Road Race',
  'Training',
  'Training Camp',
  'Social',
  'Other',
];

module.exports = { EVENT_CATEGORIES };
