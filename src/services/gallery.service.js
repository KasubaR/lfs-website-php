/* ============================================================
   LFS — Lusaka Fitness Squad
   src/services/gallery.service.js — Gallery data layer (Supabase)
   
   Maps Supabase albums/media tables to the shape expected by
   gallery.controller.js and EJS (_id, camelCase). Uses
   snake_case for all Supabase calls.
   ============================================================ */

'use strict';

const supabase = require('../config/supabase');

/* ════════════════════════════════════════════════════════════
   NORMALIZE: Supabase row → controller/EJS shape
   Add _id (from id) and camelCase keys so templates keep working.
   ════════════════════════════════════════════════════════════ */

function toAlbum(row) {
  if (!row) return null;
  return {
    _id:            row.id,
    id:             row.id,
    title:          row.title,
    description:    row.description,
    category:       row.category,
    date:           row.date,
    location:       row.location,
    event:          row.event,
    tags:           row.tags || [],
    coverImage:     row.cover_image,
    externalUrl:    row.external_url || '',
    mediaCount:     row.media_count ?? 0,
    featured:       row.featured ?? false,
    homepageSlider: row.homepage_slider ?? false,
    eventHighlight: row.event_highlight ?? false,
    sortPriority:   row.sort_priority ?? 0,
    createdAt:      row.created_at,
    updatedAt:      row.updated_at,
  };
}

function toMedia(row) {
  if (!row) return null;
  return {
    _id:        row.id,
    id:         row.id,
    albumId:    row.album_id,
    filename:   row.filename,
    storedName: row.stored_name,
    type:       row.type,
    mimetype:   row.mimetype,
    size:       row.size,
    urls:       row.urls || {},
    caption:    row.caption || '',
    tags:       row.tags || [],
    featured:   row.featured ?? false,
    sortOrder:  row.sort_order ?? 0,
    createdAt:  row.created_at,
    updatedAt:  row.updated_at,
  };
}

/* ════════════════════════════════════════════════════════════
   ALBUMS
   ════════════════════════════════════════════════════════════ */

/**
 * List albums with optional search, category, year.
 * Returns array of albums (with _id, camelCase). Sorted by date desc, created_at desc.
 */
async function getAlbums(query = {}) {
  const { search, category, year } = query;
  let q = supabase.from('albums').select('*');

  if (category) {
    q = q.eq('category', category);
  }
  if (year) {
    const start = `${year}-01-01T00:00:00.000Z`;
    const end   = `${year}-12-31T23:59:59.999Z`;
    q = q.gte('date', start).lte('date', end);
  }
  if (search && search.trim()) {
    const term = `%${search.trim()}%`;
    q = q.or(`title.ilike.${term},description.ilike.${term}`);
  }

  const { data, error } = await q.order('date', { ascending: false, nullsFirst: false })
    .order('created_at', { ascending: false });

  if (error) throw error;
  return (data || []).map(toAlbum);
}

/**
 * Single album by id. Returns null if not found.
 */
async function getAlbumById(id) {
  const { data, error } = await supabase.from('albums').select('*').eq('id', id).maybeSingle();
  if (error) throw error;
  return toAlbum(data);
}

/**
 * Create album. Payload in camelCase; we map to snake_case.
 * Returns created album (with _id).
 */
async function createAlbum(data) {
  const row = {
    title:            data.title,
    description:      data.description,
    category:         data.category,
    date:             data.date || null,
    location:         data.location,
    event:            data.event,
    tags:             data.tags || [],
    cover_image:      data.coverImage,
    external_url:     data.externalUrl || null,
    media_count:      data.mediaCount ?? 0,
    featured:         data.featured ?? false,
    homepage_slider:  data.homepageSlider ?? false,
    event_highlight:  data.eventHighlight ?? false,
    sort_priority:    data.sortPriority ?? 0,
  };
  const { data: inserted, error } = await supabase.from('albums').insert(row).select().single();
  if (error) throw error;
  return toAlbum(inserted);
}

/**
 * Update album by id. Returns updated album.
 */
async function updateAlbum(id, data) {
  const row = {};
  if (data.title !== undefined)         row.title = data.title;
  if (data.description !== undefined)  row.description = data.description;
  if (data.category !== undefined)     row.category = data.category;
  if (data.date !== undefined)         row.date = data.date;
  if (data.location !== undefined)     row.location = data.location;
  if (data.event !== undefined)        row.event = data.event;
  if (data.tags !== undefined)         row.tags = data.tags;
  if (data.coverImage !== undefined)   row.cover_image = data.coverImage;
  if (data.externalUrl !== undefined)  row.external_url = data.externalUrl;
  if (data.mediaCount !== undefined)   row.media_count = data.mediaCount;
  if (data.featured !== undefined)     row.featured = data.featured;
  if (data.homepageSlider !== undefined) row.homepage_slider = data.homepageSlider;
  if (data.eventHighlight !== undefined) row.event_highlight = data.eventHighlight;
  if (data.sortPriority !== undefined) row.sort_priority = data.sortPriority;
  row.updated_at = new Date().toISOString();

  const { data: updated, error } = await supabase.from('albums').update(row).eq('id', id).select().single();
  if (error) throw error;
  return toAlbum(updated);
}

/**
 * Increment or decrement album media_count.
 */
async function incrementAlbumMediaCount(albumId, delta) {
  const { data: album, error: fetchErr } = await supabase.from('albums').select('media_count').eq('id', albumId).single();
  if (fetchErr || !album) throw fetchErr || new Error('Album not found');
  const newCount = Math.max(0, (album.media_count ?? 0) + delta);
  const { error: updateErr } = await supabase.from('albums').update({ media_count: newCount, updated_at: new Date().toISOString() }).eq('id', albumId);
  if (updateErr) throw updateErr;
}

/**
 * Delete album by id.
 */
async function deleteAlbum(id) {
  const { error } = await supabase.from('albums').delete().eq('id', id);
  if (error) throw error;
}

/**
 * Count albums where featured = true.
 */
async function countFeaturedAlbums() {
  const { count, error } = await supabase.from('albums').select('*', { count: 'exact', head: true }).eq('featured', true);
  if (error) throw error;
  return count ?? 0;
}

/**
 * Up to 6 photos for the homepage preview grid.
 * Featured photos first, then most recent. Photos only (no videos).
 */
async function getHomepageMedia(limit = 6) {
  const { data, error } = await supabase
    .from('media')
    .select('id, album_id, urls, caption, featured, type')
    .eq('type', 'photo')
    .order('featured', { ascending: false })
    .order('created_at', { ascending: false })
    .limit(limit);
  if (error) throw error;
  return (data || []).map(toMedia);
}

/**
 * All albums for upload page dropdown. Sorted by date desc.
 */
async function getAlbumsForUpload() {
  const { data, error } = await supabase.from('albums').select('*').order('date', { ascending: false, nullsFirst: false });
  if (error) throw error;
  return (data || []).map(toAlbum);
}

/* ════════════════════════════════════════════════════════════
   MEDIA
   ════════════════════════════════════════════════════════════ */

/**
 * Total media count (all albums).
 */
async function countMedia() {
  const { count, error } = await supabase.from('media').select('*', { count: 'exact', head: true });
  if (error) throw error;
  return count ?? 0;
}

/**
 * Media for one album, with sort: newest | oldest | featured.
 */
async function getMediaByAlbumId(albumId, sort = 'newest') {
  let q = supabase.from('media').select('*').eq('album_id', albumId);
  if (sort === 'oldest') {
    q = q.order('created_at', { ascending: true });
  } else if (sort === 'featured') {
    q = q.order('featured', { ascending: false }).order('created_at', { ascending: false });
  } else {
    q = q.order('created_at', { ascending: false });
  }
  const { data, error } = await q;
  if (error) throw error;
  return (data || []).map(toMedia);
}

/**
 * Single media by id.
 */
async function getMediaById(id) {
  const { data, error } = await supabase.from('media').select('*').eq('id', id).maybeSingle();
  if (error) throw error;
  return toMedia(data);
}

/**
 * Create media. Payload camelCase → snake_case.
 */
async function createMedia(data) {
  const row = {
    album_id:    data.albumId,
    filename:    data.filename,
    stored_name: data.storedName,
    type:        data.type,
    mimetype:    data.mimetype,
    size:        data.size,
    urls:        data.urls || {},
    caption:     data.caption ?? '',
    tags:        data.tags || [],
    featured:    data.featured ?? false,
    sort_order:  data.sortOrder ?? 0,
  };
  const { data: inserted, error } = await supabase.from('media').insert(row).select().single();
  if (error) throw error;
  return toMedia(inserted);
}

/**
 * Update media by id. Optional { new: true } to return updated row.
 */
async function updateMedia(id, data, opts = {}) {
  const row = {};
  if (data.caption !== undefined)   row.caption = data.caption;
  if (data.featured !== undefined)  row.featured = data.featured;
  if (data.sortOrder !== undefined) row.sort_order = data.sortOrder;
  if (data.albumId !== undefined)   row.album_id = data.albumId;
  row.updated_at = new Date().toISOString();

  let q = supabase.from('media').update(row).eq('id', id);
  if (opts.new) q = q.select().single();
  const { data: updated, error } = await q;
  if (error) throw error;
  return opts.new ? toMedia(updated) : undefined;
}

/**
 * Delete media by id.
 */
async function deleteMedia(id) {
  const { error } = await supabase.from('media').delete().eq('id', id);
  if (error) throw error;
}

/**
 * Find media by ids. Returns array (with _id, camelCase).
 */
async function findMediaByIds(ids) {
  if (!ids?.length) return [];
  const { data, error } = await supabase.from('media').select('*').in('id', ids);
  if (error) throw error;
  return (data || []).map(toMedia);
}

/**
 * Delete all media in an album (by album_id).
 */
async function deleteMediaByAlbumId(albumId) {
  const { error } = await supabase.from('media').delete().eq('album_id', albumId);
  if (error) throw error;
}

/**
 * Delete multiple media by ids.
 */
async function deleteManyMedia(ids) {
  if (!ids?.length) return;
  const { error } = await supabase.from('media').delete().in('id', ids);
  if (error) throw error;
}

/**
 * Update multiple media (e.g. set featured: true, or album_id for move).
 */
async function updateManyMedia(ids, data) {
  if (!ids?.length) return;
  const row = { updated_at: new Date().toISOString() };
  if (data.featured !== undefined) row.featured = data.featured;
  if (data.albumId !== undefined)  row.album_id = data.albumId;
  const { error } = await supabase.from('media').update(row).in('id', ids);
  if (error) throw error;
}

module.exports = {
  getAlbums,
  getAlbumById,
  createAlbum,
  updateAlbum,
  deleteAlbum,
  incrementAlbumMediaCount,
  countFeaturedAlbums,
  getAlbumsForUpload,
  getHomepageMedia,

  countMedia,
  getMediaByAlbumId,
  getMediaById,
  createMedia,
  updateMedia,
  deleteMedia,
  findMediaByIds,
  deleteMediaByAlbumId,
  deleteManyMedia,
  updateManyMedia,
};
