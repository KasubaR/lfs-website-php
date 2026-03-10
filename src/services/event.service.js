/* ============================================================
   LFS — Lusaka Fitness Squad
   src/services/event.service.js — Event data layer (Supabase)

   Event model: LFS events and races.
   Examples: Saturday LSD Run, LFS Half Marathon, Training Camp.

   Supabase table: events (snake_case)
   App shape: camelCase + _id for EJS compatibility.
   ============================================================ */

'use strict';

const supabase = require('../config/supabase');
const { slugify } = require('../utility/helpers');

/** Return url if valid http(s) URL or site-relative path, otherwise null (safe for img src). */
function sanitizeBannerUrl(url) {
  const v = url === undefined || url === null ? '' : String(url).trim();
  if (v === '') return null;
  if (v.startsWith('/') && !v.includes('//')) return v;
  try {
    const u = new URL(v);
    const protocol = (u.protocol || '').toLowerCase();
    if (protocol === 'http:' || protocol === 'https:') return v;
  } catch (_) { /* invalid URL */ }
  return null;
}

/** Map Supabase row → camelCase shape for controllers/EJS. */
function toEvent(row) {
  if (!row) return null;
  return {
    _id:                row.id,
    id:                 row.id,
    title:              row.title,
    slug:               row.slug ?? null,
    description:        row.description ?? '',
    location:           row.location ?? '',
    eventDate:          row.event_date,
    distance:           row.distance ?? '',
    category:           row.category ?? '',
    series:             row.series ?? null,
    registrationOpen:   row.registration_open ?? null,
    registrationClose:  row.registration_close ?? null,
    bannerImage:        sanitizeBannerUrl(row.banner_image),
    createdBy:          row.created_by ?? null,
    createdAt:          row.created_at,
    updatedAt:          row.updated_at,
  };
}

/**
 * List events with optional filters.
 * @param {object} opts - category, fromDate, toDate, limit
 * @returns {Promise<Array>} events (camelCase), sorted by event_date desc
 */
async function getEvents(opts = {}) {
  const { category, fromDate, toDate, limit = 50 } = opts;
  let q = supabase.from('events').select('*');

  if (category) q = q.eq('category', category);
  if (fromDate) q = q.gte('event_date', fromDate);
  if (toDate) q = q.lte('event_date', toDate);

  const { data, error } = await q
    .order('event_date', { ascending: false })
    .limit(limit);

  if (error) throw error;
  return (data || []).map(toEvent);
}

/**
 * Get a single event by id. Returns null if not found.
 */
async function getEventById(id) {
  const { data, error } = await supabase
    .from('events')
    .select('*')
    .eq('id', id)
    .maybeSingle();
  if (error) throw error;
  return toEvent(data);
}

/**
 * Get a single event by slug. Returns null if not found.
 */
async function getEventBySlug(slug) {
  const { data, error } = await supabase
    .from('events')
    .select('*')
    .eq('slug', slug)
    .maybeSingle();
  if (error) throw error;
  return toEvent(data);
}

/** Error code when slug is already taken (for controller to show friendly message). */
const SLUG_TAKEN_CODE = 'SLUG_TAKEN';

/** Error code when registration/event date order is invalid. */
const DATE_ORDER_INVALID_CODE = 'DATE_ORDER_INVALID';

/** Error code when banner image URL is invalid or not http(s). */
const INVALID_BANNER_URL_CODE = 'INVALID_BANNER_URL';

function parseDate(v) {
  if (v === null || v === undefined || v === '') return null;
  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? null : d.getTime();
}

/**
 * Enforce registrationOpen < registrationClose < eventDate.
 * Throws with code DATE_ORDER_INVALID if any pair is reversed.
 */
function validateRegistrationDateOrder(eventDate, registrationOpen, registrationClose) {
  const tEvent = parseDate(eventDate);
  const tOpen = parseDate(registrationOpen);
  const tClose = parseDate(registrationClose);

  if (tOpen != null && tClose != null && tOpen >= tClose) {
    const err = new Error('Registration open date must be before registration close date.');
    err.code = DATE_ORDER_INVALID_CODE;
    throw err;
  }
  if (tClose != null && tEvent != null && tClose >= tEvent) {
    const err = new Error('Registration close date must be before the event date.');
    err.code = DATE_ORDER_INVALID_CODE;
    throw err;
  }
  if (tOpen != null && tEvent != null && tOpen >= tEvent) {
    const err = new Error('Registration open date must be before the event date.');
    err.code = DATE_ORDER_INVALID_CODE;
    throw err;
  }
}

/**
 * Normalize datetime from form (YYYY-MM-DDTHH:mm, no TZ) to ISO UTC so round-trip is consistent.
 * Leaves null/undefined and already-qualified strings (with Z or offset) unchanged.
 */
function normalizeDateTimeForStorage(v) {
  if (v === null || v === undefined) return null;
  const s = String(v).trim();
  if (s === '') return null;
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(s)) return s + ':00.000Z';
  return v;
}

/**
 * Ensure banner image is empty, a site-relative path (/...), or a valid http/https URL.
 * Throws with code INVALID_BANNER_URL if a non-empty value is neither.
 */
function validateBannerImageUrl(bannerImage) {
  const v = bannerImage === undefined || bannerImage === null ? '' : String(bannerImage).trim();
  if (v === '') return;
  if (v.startsWith('/') && !v.includes('//')) return;
  try {
    const url = new URL(v);
    const protocol = (url.protocol || '').toLowerCase();
    if (protocol !== 'http:' && protocol !== 'https:') {
      const err = new Error('Banner image must be an http or https URL.');
      err.code = INVALID_BANNER_URL_CODE;
      throw err;
    }
  } catch (e) {
    if (e && e.code === INVALID_BANNER_URL_CODE) throw e;
    const err = new Error('Banner image is not a valid URL.');
    err.code = INVALID_BANNER_URL_CODE;
    throw err;
  }
}

/**
 * Create an event. Payload in camelCase; mapped to snake_case.
 * Slug: use data.slug if provided, otherwise generate from title via slugify().
 * Throws with code SLUG_TAKEN if slug already exists.
 * Throws with code DATE_ORDER_INVALID if registration/event date order is invalid.
 * Throws with code INVALID_BANNER_URL if banner image is not a valid http(s) URL.
 */
async function createEvent(data) {
  validateRegistrationDateOrder(
    data.eventDate,
    data.registrationOpen,
    data.registrationClose
  );
  validateBannerImageUrl(data.bannerImage);

  const slug = (data.slug && String(data.slug).trim())
    ? String(data.slug).trim()
    : slugify(data.title || 'event');

  const existing = await getEventBySlug(slug);
  if (existing) {
    const err = new Error('This slug is already in use. Choose another.');
    err.code = SLUG_TAKEN_CODE;
    throw err;
  }

  const row = {
    title:               data.title,
    slug:                slug,
    description:         data.description ?? '',
    location:            data.location ?? '',
    event_date:          normalizeDateTimeForStorage(data.eventDate),
    distance:            data.distance ?? '',
    category:            data.category ?? '',
    series:              data.series ?? null,
    registration_open:   normalizeDateTimeForStorage(data.registrationOpen),
    registration_close:  normalizeDateTimeForStorage(data.registrationClose),
    banner_image:        data.bannerImage ?? null,
    created_by:          data.createdBy ?? null,
  };
  const { data: inserted, error } = await supabase.from('events').insert(row).select().single();
  if (error) throw error;
  return toEvent(inserted);
}

/**
 * Update an event by id. Partial updates supported.
 * Throws with code SLUG_TAKEN if slug is changed and already taken by another event.
 * Throws with code DATE_ORDER_INVALID if registration/event date order is invalid.
 * Throws with code INVALID_BANNER_URL if banner image is not a valid http(s) URL.
 * Returns null if no row matches id (e.g. deleted); otherwise returns the updated event.
 */
async function updateEvent(id, data) {
  const eventDate = data.eventDate !== undefined ? data.eventDate : null;
  const registrationOpen = data.registrationOpen !== undefined ? data.registrationOpen : null;
  const registrationClose = data.registrationClose !== undefined ? data.registrationClose : null;
  validateRegistrationDateOrder(eventDate, registrationOpen, registrationClose);
  if (data.bannerImage !== undefined) validateBannerImageUrl(data.bannerImage);

  if (data.slug !== undefined) {
    const existingBySlug = await getEventBySlug(data.slug);
    if (existingBySlug && existingBySlug.id !== id) {
      const err = new Error('This slug is already in use. Choose another.');
      err.code = SLUG_TAKEN_CODE;
      throw err;
    }
  }

  const row = { updated_at: new Date().toISOString() };
  if (data.title !== undefined)             row.title = data.title;
  if (data.slug !== undefined)              row.slug = data.slug;
  if (data.description !== undefined)       row.description = data.description;
  if (data.location !== undefined)          row.location = data.location;
  if (data.eventDate !== undefined)         row.event_date = normalizeDateTimeForStorage(data.eventDate);
  if (data.distance !== undefined)          row.distance = data.distance;
  if (data.category !== undefined)          row.category = data.category;
   if (data.series !== undefined)            row.series = data.series;
  if (data.registrationOpen !== undefined)  row.registration_open = normalizeDateTimeForStorage(data.registrationOpen);
  if (data.registrationClose !== undefined) row.registration_close = normalizeDateTimeForStorage(data.registrationClose);
  if (data.bannerImage !== undefined)       row.banner_image = data.bannerImage;
  if (data.createdBy !== undefined)         row.created_by = data.createdBy;

  const { data: updated, error } = await supabase.from('events').update(row).eq('id', id).select().maybeSingle();
  if (error) throw error;
  return updated ? toEvent(updated) : null;
}

/**
 * Delete an event by id.
 */
async function deleteEvent(id) {
  const { error } = await supabase.from('events').delete().eq('id', id);
  if (error) throw error;
}

/**
 * Get upcoming events (event_date >= now). Optional limit.
 */
async function getUpcomingEvents(limit = 10) {
  const now = new Date().toISOString();
  const { data, error } = await supabase
    .from('events')
    .select('*')
    .gte('event_date', now)
    .order('event_date', { ascending: true })
    .limit(limit);
  if (error) throw error;
  return (data || []).map(toEvent);
}

module.exports = {
  toEvent,
  getEvents,
  getEventById,
  getEventBySlug,
  createEvent,
  updateEvent,
  deleteEvent,
  getUpcomingEvents,
  SLUG_TAKEN_CODE,
  DATE_ORDER_INVALID_CODE,
  INVALID_BANNER_URL_CODE,
};
