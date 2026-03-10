/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/routes/gallery.routes.js — Gallery Module Routes
   
   Mount point: /admin/gallery  (from admin.routes.js)
   All routes protected by adminAuth + role('admin') middleware.
   ============================================================ */

'use strict';

const express = require('express');
const rateLimit = require('express-rate-limit');
const router  = express.Router();
const galleryController = require('../controllers/gallery.controller');
const albumCoverUpload = require('../middleware/albumCoverUpload');
const csrf = require('../../middleware/csrf.middleware');

/* ── Upload rate limit: 100 requests per 15 min per IP ────── */
const uploadLimiter = rateLimit({
  windowMs:  15 * 60 * 1000,
  max:       100,
  message:   { success: false, message: 'Too many uploads. Please wait and try again.' },
  standardHeaders: true,
  legacyHeaders:   false,
});

/* ════════════════════════════════════════════════════════════
   ALBUM ROUTES
   ════════════════════════════════════════════════════════════ */

/**
 * GET  /admin/gallery
 * Redirect to albums index.
 */
router.get('/', (req, res) => res.redirect('/admin/gallery/albums'));

/**
 * GET  /admin/gallery/albums
 * List all albums with stats + filter/search support.
 * Query params: search, category, year
 */
router.get('/albums', galleryController.getAlbums);

/**
 * GET  /admin/gallery/albums/create
 * Render create-album form.
 */
router.get('/albums/create', galleryController.getCreateAlbum);

/**
 * POST /admin/gallery/albums
 * Create a new album.
 * Body: title, description, category, date, location, event, tags
 */
router.post('/albums', galleryController.createAlbum);

/**
 * GET  /admin/gallery/albums/:id/edit
 * Render edit-album form.
 */
router.get('/albums/:id/edit', galleryController.getEditAlbum);

/**
 * POST /admin/gallery/albums/:id
 * Update an existing album.
 * Body: title, description, category, date, location, event, tags,
 *       featured, homepageSlider, eventHighlight, sortPriority
 */
router.post('/albums/:id', galleryController.updateAlbum);

/**
 * POST /admin/gallery/albums/:id/delete
 * Delete an album and all its media.
 * (Using POST for HTML form method override.)
 */
router.post('/albums/:id/delete', galleryController.deleteAlbum);

/**
 * PATCH /admin/gallery/albums/:id/feature
 * Toggle the featured flag on an album.
 */
router.patch('/albums/:id/feature', galleryController.toggleAlbumFeatured);

/**
 * GET  /admin/gallery/albums/:id/manage
 * Render the media management grid for a specific album.
 * Query params: sort (newest|oldest|featured)
 */
router.get('/albums/:id/manage', galleryController.getManageAlbum);


/* ════════════════════════════════════════════════════════════
   UPLOAD ROUTES
   ════════════════════════════════════════════════════════════ */

/**
 * GET  /admin/gallery/upload
 * Render the upload page.
 * Query params: album (pre-select an album)
 */
router.get('/upload', galleryController.getUploadPage);

/**
 * POST /admin/gallery/upload
 * Handle single-file upload (called via fetch() in the browser).
 * Expects multipart/form-data with:
 *   - file     (the binary file)
 *   - albumId  (target album _id)
 *   - type     ('photo' | 'video')
 *
 * Images are processed by Sharp:
 *   thumb  → 300px wide  WebP ~75% quality
 *   medium → 900px wide  WebP ~78% quality
 *   large  → 1600px wide WebP ~80% quality
 *
 * Returns JSON: { success, message, mediaId, urls }
 */
router.post(
  '/upload',
  uploadLimiter,
  galleryController.handleUploadMiddleware,
  galleryController.processUpload
);


/* ════════════════════════════════════════════════════════════
   MEDIA ITEM ROUTES
   ════════════════════════════════════════════════════════════ */

/**
 * PATCH /admin/gallery/media/:id/caption
 * Update the caption / alt text for a media item.
 * Body: { caption }
 */
router.patch('/media/:id/caption', galleryController.updateCaption);

/**
 * PATCH /admin/gallery/media/:id/feature
 * Toggle featured flag on a single media item.
 * Returns JSON: { featured }
 */
router.patch('/media/:id/feature', galleryController.toggleMediaFeatured);

/**
 * DELETE /admin/gallery/media/:id
 * Delete a single media item (removes all size variants from disk/cloud).
 */
router.delete('/media/:id', galleryController.deleteMedia);

/**
 * POST /admin/gallery/media/reorder
 * Drag-and-drop reorder within an album.
 * Body: { sourceId, targetId, albumId }
 */
router.post('/media/reorder', galleryController.reorderMedia);

/**
 * POST /admin/gallery/media/bulk-delete
 * Bulk delete multiple media items.
 * Body: { ids: [] }
 */
router.post('/media/bulk-delete', galleryController.bulkDeleteMedia);

/**
 * POST /admin/gallery/media/bulk-feature
 * Bulk mark items as featured.
 * Body: { ids: [] }
 */
router.post('/media/bulk-feature', galleryController.bulkFeatureMedia);

/**
 * POST /admin/gallery/media/bulk-move
 * Move multiple items to a different album.
 * Body: { ids: [], targetAlbumId }
 */
router.post('/media/bulk-move', galleryController.bulkMoveMedia);

/**
 * POST /admin/gallery/cover-upload
 * AJAX endpoint to upload a single cover image for an album.
 * Expects multipart/form-data with:
 *   - coverImageFile (file)
 *
 * Returns JSON:
 *   { success: true, url }
 *   { success: false, message }
 */
router.post('/cover-upload', albumCoverUpload, csrf.verify, (req, res) => {
  if (req.coverUploadError) {
    return res.status(400).json({ success: false, message: req.coverUploadError.message });
  }
  if (!req.file) {
    return res.status(400).json({ success: false, message: 'No cover image uploaded.' });
  }
  const url = `/uploads/gallery/covers/${req.file.filename}`;
  return res.json({ success: true, url });
});


module.exports = router;
