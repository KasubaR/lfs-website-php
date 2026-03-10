/* ============================================================
   LFS — Lusaka Fitness Squad
   src/model/blogPost.js — Blog post (news/updates shape + constants)

   Database: Supabase table `blog_posts` (see supabase-schema.sql)
   Data layer: use a service (e.g. blogPost.service.js) for Supabase calls.
   ============================================================ */

/**
 * Blog post — news and updates.
 * @typedef {Object} BlogPost
 * @property {string} id - UUID
 * @property {string} title
 * @property {string} slug - URL-friendly unique
 * @property {string} [content]
 * @property {string} [featuredImage] - URL
 * @property {string|null} [authorId] - UUID
 * @property {string} category - Club News | Race Reports | Training Tips | Announcements
 * @property {boolean} [published]
 * @property {string} [createdAt]
 * @property {string} [updatedAt]
 */

/** Blog categories (matches DB check). */
const BLOG_CATEGORIES = [
  'Club News',
  'Race Reports',
  'Training Tips',
  'Announcements',
];

module.exports = {
  BLOG_CATEGORIES,
};
