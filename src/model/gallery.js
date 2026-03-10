/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/gallery.js — Gallery model (core shapes + constants)

   Database: Supabase tables `albums`, `media` (see supabase-schema.sql)
   Data layer: src/services/gallery.service.js
   ============================================================ */

/**
 * Album — gallery album (event, training, etc.).
 * @typedef {Object} Album
 * @property {string} id - UUID
 * @property {string} title
 * @property {string} [description]
 * @property {string} [category]
 * @property {string} [date] - ISO date
 * @property {string} [location]
 * @property {string} [event]
 * @property {string[]} [tags]
 * @property {string|null} [coverImage] - URL
 * @property {number} [mediaCount]
 * @property {boolean} [featured]
 * @property {boolean} [homepageSlider]
 * @property {boolean} [eventHighlight]
 * @property {number} [sortPriority]
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */

/**
 * Media — single photo or video in an album.
 * @typedef {Object} Media
 * @property {string} id - UUID
 * @property {string} albumId - UUID
 * @property {string} [filename]
 * @property {string} [storedName]
 * @property {'photo'|'video'} type
 * @property {string} [mimetype]
 * @property {number} [size]
 * @property {Object} [urls] - e.g. { full, thumb }
 * @property {string} [caption]
 * @property {string[]} [tags]
 * @property {boolean} [featured]
 * @property {number} [sortOrder]
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */

/** Allowed media types (matches DB check). */
const MEDIA_TYPES = ['photo', 'video'];

/** Optional album categories for admin dropdowns. */
const ALBUM_CATEGORIES = [
  'Event',
  'Training',
  'LSD',
  'Social',
  'Other',
];

module.exports = {
  MEDIA_TYPES,
  ALBUM_CATEGORIES,
};
