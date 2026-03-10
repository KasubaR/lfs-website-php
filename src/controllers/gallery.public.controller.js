/* ============================================================
   LFS — Lusaka Fitness Squad
   controllers/gallery.public.controller.js — Public gallery (read-only)
   
   GET /gallery         → list albums (grid)
   GET /gallery/:id     → one album with media grid
   ============================================================ */

'use strict';

const path = require('path');
const fs   = require('fs');
const galleryService = require('../services/gallery.service');

const FALLBACK_FOLDER = '21.02.2026-LSD';
const FALLBACK_PATH   = path.join(__dirname, '..', '..', 'public', 'images', FALLBACK_FOLDER);
const IMAGE_EXTS      = new Set(['.webp', '.jpg', '.jpeg', '.png']);

function getFallbackMedia() {
  if (!fs.existsSync(FALLBACK_PATH) || !fs.statSync(FALLBACK_PATH).isDirectory()) return [];
  const baseUrl = `/images/${FALLBACK_FOLDER}`;
  return fs.readdirSync(FALLBACK_PATH)
    .filter(f => IMAGE_EXTS.has(path.extname(f).toLowerCase()))
    .sort()
    .map(f => ({ src: `${baseUrl}/${f}`, alt: `LFS — ${FALLBACK_FOLDER}` }));
}

/**
 * GET /gallery — List all albums (public).
 * If Supabase is unreachable, renders gallery with empty albums and a message (no 500).
 */
async function getIndex(req, res, next) {
  try {
    const albums = await galleryService.getAlbums({});
    res.render('pages/gallery', {
      layout: 'layouts/main',
      title: 'Gallery',
      description: 'Photos and videos from LFS runs, races and community events.',
      albums,
      fallbackMedia: albums.length === 0 ? getFallbackMedia() : [],
    });
  } catch (err) {
    const isNetworkOrSupabase =
      (err && err.message && (err.message.includes('fetch failed') || err.message.includes('ECONNREFUSED') || err.message.includes('ENOTFOUND'))) ||
      (err && err.code === 'PGRST301');
    if (isNetworkOrSupabase) {
      if (process.env.NODE_ENV !== 'production') {
        console.error('[LFS Gallery] Supabase/network error loading albums:', err.message || err);
      }
      return res.render('pages/gallery', {
        layout: 'layouts/main',
        title: 'Gallery',
        description: 'Photos and videos from LFS runs, races and community events.',
        albums: [],
        fallbackMedia: getFallbackMedia(),
        galleryError: 'Gallery is temporarily unavailable. Please try again later.',
      });
    }
    next(err);
  }
}

/**
 * GET /gallery/:id — One album with its media (public).
 */
async function getAlbum(req, res, next) {
  try {
    const album = await galleryService.getAlbumById(req.params.id);
    if (!album) {
      return res.status(404).render('pages/404', {
        layout: 'layouts/main',
        title: 'Album not found',
        description: 'This album may have been removed or the link is invalid.',
      });
    }
    const media = await galleryService.getMediaByAlbumId(req.params.id, 'newest');
    res.render('pages/gallery-album', {
      layout: 'layouts/main',
      title: album.title,
      description: album.description || `Photos and videos from ${album.title}.`,
      styles: '<link rel="stylesheet" href="/css/events.css">',
      album,
      media,
    });
  } catch (err) {
    next(err);
  }
}

module.exports = {
  getIndex,
  getAlbum,
};
