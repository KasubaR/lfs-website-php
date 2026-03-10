/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/controllers/gallery.controller.js
   
   Handles all gallery & media management logic.
   
   Dependencies (npm install):
     multer             — multipart/form-data parsing
     sharp              — image resize + WebP conversion
     uuid               — unique filenames
     express-rate-limit — upload rate limiting (applied in routes/app)
   
   Optional / recommended:
     cloudinary         — cloud video storage + streaming
     multer-storage-cloudinary — Cloudinary multer storage engine
   
   Directory structure created on disk:
     public/uploads/gallery/
       ├── {albumId}/
       │     ├── thumbnails/   (300px WebP)
       │     ├── medium/       (900px WebP)
       │     ├── large/        (1600px WebP)
       │     └── originals/    (original kept for download)
       └── videos/
             └── {albumId}/    (raw video files)
   
   For production: replace local disk storage with Cloudinary.
   ============================================================ */

'use strict';

const path    = require('path');
const fs      = require('fs');
const { v4: uuidv4 } = require('uuid');
const multer  = require('multer');
const sharp   = require('sharp');

/* ── Gallery data layer (Supabase) ────────────────────────── */
const galleryService = require('../../services/gallery.service');

/* ════════════════════════════════════════════════════════════
   CONSTANTS
   ════════════════════════════════════════════════════════════ */
const IS_PROD = process.env.NODE_ENV === 'production';
const DEFAULT_PUBLIC_ROOT = path.join(__dirname, '..', '..', '..', 'public');
const PUBLIC_ROOT = (IS_PROD && process.env.PUBLIC_ROOT) ? process.env.PUBLIC_ROOT : DEFAULT_PUBLIC_ROOT;
const UPLOAD_BASE     = path.join(PUBLIC_ROOT, 'uploads', 'gallery');
const PHOTO_MAX_BYTES = 15 * 1024 * 1024;   // 15 MB
const VIDEO_MAX_BYTES = 200 * 1024 * 1024;  // 200 MB
const PHOTO_MAX_COUNT = 50;

const ALLOWED_PHOTO_MIME = ['image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_VIDEO_MIME = ['video/mp4', 'video/quicktime', 'video/webm'];
const ALLOWED_PHOTO_EXT  = ['.jpg', '.jpeg', '.png', '.webp'];
const ALLOWED_VIDEO_EXT  = ['.mp4', '.mov', '.webm'];

const IMAGE_SIZES = {
  thumbnail: { width: 300,  quality: 75 },
  medium:    { width: 900,  quality: 78 },
  large:     { width: 1600, quality: 80 },
};

/* ════════════════════════════════════════════════════════════
   MULTER CONFIGURATION
   ════════════════════════════════════════════════════════════ */

/**
 * Multer disk storage — saves to a temp directory.
 * Sharp processes the image afterwards and saves final variants.
 */
const tempStorage = multer.diskStorage({
  destination(req, file, cb) {
    const tempDir = path.join(UPLOAD_BASE, 'temp');
    fs.mkdirSync(tempDir, { recursive: true });
    cb(null, tempDir);
  },
  filename(req, file, cb) {
    const ext  = path.extname(file.originalname).toLowerCase();
    const name = uuidv4() + ext;
    cb(null, name);
  },
});

/**
 * File filter — validates MIME type AND extension.
 * Two-layer check to prevent extension spoofing.
 */
function fileFilter(req, file, cb) {
  const ext       = path.extname(file.originalname).toLowerCase();
  const isPhoto   = ALLOWED_PHOTO_MIME.includes(file.mimetype) && ALLOWED_PHOTO_EXT.includes(ext);
  const isVideo   = ALLOWED_VIDEO_MIME.includes(file.mimetype) && ALLOWED_VIDEO_EXT.includes(ext);

  if (isPhoto || isVideo) {
    cb(null, true);
  } else {
    cb(new multer.MulterError('LIMIT_UNEXPECTED_FILE', 'Unsupported file type.'));
  }
}

/* Single-file upload instance (handleUploadMiddleware uses its own below) */
const upload = multer({
  storage: tempStorage,
  limits: {
    fileSize: VIDEO_MAX_BYTES,   // enforced per-file; extra photo check in processUpload
    files: 1,
  },
  fileFilter,
});

/**
 * Exported middleware — select the multer handler at runtime.
 * Called as a route middleware before processUpload.
 */
function handleUploadMiddleware(req, res, next) {
  const uploader = multer({
    storage: tempStorage,
    limits: { fileSize: VIDEO_MAX_BYTES, files: 1 },
    fileFilter,
  }).single('file');

  uploader(req, res, (err) => {
    if (err instanceof multer.MulterError) {
      return res.status(400).json({ success: false, message: err.message });
    }
    if (err) {
      return res.status(400).json({ success: false, message: err.message || 'Upload failed.' });
    }
    next();
  });
}

/* ════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════ */

/**
 * Ensure all required album subdirectories exist.
 * NOTE: Directory names must match IMAGE_SIZES keys and URLs.
 */
function ensureAlbumDirs(albumId) {
  const subdirs = [...Object.keys(IMAGE_SIZES), 'originals']; // e.g. ['thumbnail','medium','large','originals']
  subdirs.forEach((sub) => {
    fs.mkdirSync(path.join(UPLOAD_BASE, albumId, sub), { recursive: true });
  });
}

/**
 * Process an uploaded image through Sharp.
 * Generates thumbnail, medium, large WebP variants.
 * Moves the original to the originals folder.
 *
 * @param {string} tempPath    — path to the uploaded temp file
 * @param {string} albumId     — album _id (used for directory)
 * @param {string} baseName    — filename without extension
 * @returns {Object}           — { thumbnail, medium, large, original } URL paths
 */
async function processImage(tempPath, albumId, baseName) {
  ensureAlbumDirs(albumId);
  const urls = {};

  for (const [sizeName, opts] of Object.entries(IMAGE_SIZES)) {
    const outFile = path.join(UPLOAD_BASE, albumId, sizeName, baseName + '.webp');
    await sharp(tempPath)
      .resize({ width: opts.width, withoutEnlargement: true })
      .webp({ quality: opts.quality })
      .toFile(outFile);
    urls[sizeName] = `/uploads/gallery/${albumId}/${sizeName}/${baseName}.webp`;
  }

  // Move original (keep for download; don't process further)
  const origOut = path.join(UPLOAD_BASE, albumId, 'originals', path.basename(tempPath));
  fs.copyFileSync(tempPath, origOut);
  fs.unlinkSync(tempPath);
  urls.original = `/uploads/gallery/${albumId}/originals/${path.basename(origOut)}`;

  return urls;
}

/**
 * Delete all size variants for a media item from disk.
 */
function deleteMediaFiles(media) {
  if (!media?.urls) return;
  Object.values(media.urls).forEach(urlPath => {
    if (!urlPath) return;
    const absPath = path.join(__dirname, '..', '..', '..', 'public', urlPath);
    fs.unlink(absPath, () => {}); // fire-and-forget
  });
}

/**
 * Parse a comma-separated tags string into a clean array.
 * e.g. "race, training, LSD" → ['race', 'training', 'lsd']
 */
function parseTags(raw) {
  if (!raw) return [];
  return raw.split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
}

/* ════════════════════════════════════════════════════════════
   ALBUM CONTROLLERS
   ════════════════════════════════════════════════════════════ */

/**
 * GET /admin/gallery/albums
 * List albums with search/filter support.
 * On Supabase/network failure, renders page with empty data and an error message (no 500).
 */
async function getAlbums(req, res, next) {
  try {
    const { search, category, year } = req.query;

    const [albums, totalMedia, featuredCount] = await Promise.all([
      galleryService.getAlbums({ search, category, year }),
      galleryService.countMedia(),
      galleryService.countFeaturedAlbums(),
    ]);

    res.render('gallery/albums', {
      layout: 'layouts/admin',
      albums,
      stats: {
        totalAlbums:  albums.length,
        totalMedia,
        featuredCount,
      },
      categories:      ['Race', 'Training', 'LSD', 'Social'],
      searchQuery:     search   || '',
      filterCategory:  category || '',
      filterYear:      year     || '',
    });
  } catch (err) {
    const isNetworkOrSupabase =
      (err && err.message && (err.message.includes('fetch failed') || err.message.includes('ECONNREFUSED') || err.message.includes('ENOTFOUND'))) ||
      (err && err.code === 'PGRST301');
    if (isNetworkOrSupabase) {
      if (process.env.NODE_ENV !== 'production') {
        console.error('[LFS Gallery] Supabase/network error loading albums:', err.message || err);
      }
      return res.render('gallery/albums', {
        layout: 'layouts/admin',
        albums:            [],
        stats:             { totalAlbums: 0, totalMedia: 0, featuredCount: 0 },
        categories:       ['Race', 'Training', 'LSD', 'Social'],
        searchQuery:      req.query.search || '',
        filterCategory:   req.query.category || '',
        filterYear:       req.query.year || '',
        galleryError:     'Unable to load albums. Check that Supabase is reachable and your project is not paused.',
      });
    }
    next(err);
  }
}

/**
 * GET /admin/gallery/albums/create
 * Render create-album form (reuses the same edit template with empty data).
 */
async function getCreateAlbum(req, res, next) {
  try {
    res.render('gallery/album-form', {
      layout:     'layouts/admin',
      pageTitle:  'Gallery — Create Album',
      album:      null,
      categories: ['Race', 'Training', 'LSD', 'Social'],
      breadcrumbs: [
        { label: 'Gallery', url: '/admin/gallery' },
        { label: 'Albums',  url: '/admin/gallery/albums' },
        { label: 'Create Album' },
      ],
    });
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/albums
 * Create a new album.
 */
async function createAlbum(req, res, next) {
  try {
    const {
      title, description, category, date,
      location, event, tags, featured,
      homepageSlider, eventHighlight, sortPriority,
      coverImage, externalUrl,
    } = req.body;

    await galleryService.createAlbum({
      title:          title?.trim(),
      description:    description?.trim(),
      category,
      date:           date ? new Date(date) : undefined,
      location:       location?.trim(),
      event:          event?.trim(),
      tags:           parseTags(tags),
      featured:       featured === 'on',
      homepageSlider: homepageSlider === 'on',
      eventHighlight: eventHighlight === 'on',
      sortPriority:   parseInt(sortPriority) || 0,
      coverImage:     coverImage?.trim() || null,
      externalUrl:    externalUrl?.trim() || null,
      mediaCount:     0,
    });

    req.flash?.('success', `Album "${title}" created.`);
    res.redirect('/admin/gallery/albums');
  } catch (err) { next(err); }
}

/**
 * GET /admin/gallery/albums/:id/edit
 * Render edit form for an existing album.
 */
async function getEditAlbum(req, res, next) {
  try {
    const album = await galleryService.getAlbumById(req.params.id);
    if (!album) return res.status(404).redirect('/admin/gallery/albums');

    res.render('gallery/album-form', {
      layout:     'layouts/admin',
      pageTitle:  'Gallery — Edit Album',
      album,
      categories: ['Race', 'Training', 'LSD', 'Social'],
      breadcrumbs: [
        { label: 'Gallery', url: '/admin/gallery' },
        { label: 'Albums',  url: '/admin/gallery/albums' },
        { label: album.title, url: `/admin/gallery/albums/${album._id}/manage` },
        { label: 'Edit' },
      ],
    });
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/albums/:id
 * Update an album.
 */
async function updateAlbum(req, res, next) {
  try {
    const {
      title, description, category, date,
      location, event, tags, featured,
      homepageSlider, eventHighlight, sortPriority,
      coverImage, externalUrl,
    } = req.body;

    await galleryService.updateAlbum(req.params.id, {
      title:          title?.trim(),
      description:    description?.trim(),
      category,
      date:           date ? new Date(date) : undefined,
      location:       location?.trim(),
      event:          event?.trim(),
      tags:           parseTags(tags),
      featured:       featured === 'on',
      homepageSlider: homepageSlider === 'on',
      eventHighlight: eventHighlight === 'on',
      sortPriority:   parseInt(sortPriority) || 0,
      coverImage:     typeof coverImage === 'string' ? coverImage.trim() : undefined,
      externalUrl:    typeof externalUrl === 'string' ? externalUrl.trim() : undefined,
    });

    req.flash?.('success', 'Album updated.');
    res.redirect(`/admin/gallery/albums/${req.params.id}/manage`);
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/albums/:id/delete
 * Delete an album and all its associated media files.
 */
async function deleteAlbum(req, res, next) {
  try {
    const albumId = req.params.id;

    // Delete all media records + files
    const mediaItems = await galleryService.getMediaByAlbumId(albumId);
    mediaItems.forEach(deleteMediaFiles);
    await galleryService.deleteMediaByAlbumId(albumId);

    // Remove disk directory
    const albumDir = path.join(UPLOAD_BASE, albumId);
    fs.rm(albumDir, { recursive: true, force: true }, () => {});

    await galleryService.deleteAlbum(albumId);

    req.flash?.('success', 'Album deleted.');
    res.redirect('/admin/gallery/albums');
  } catch (err) { next(err); }
}

/**
 * PATCH /admin/gallery/albums/:id/feature
 * Toggle featured flag on an album.
 */
async function toggleAlbumFeatured(req, res, next) {
  try {
    const album = await galleryService.getAlbumById(req.params.id);
    if (!album) return res.status(404).json({ success: false });

    const updated = await galleryService.updateAlbum(req.params.id, {
      featured: !album.featured,
    });
    res.json({ success: true, featured: updated.featured });
  } catch (err) { next(err); }
}

/**
 * GET /admin/gallery/albums/:id/manage
 * Media management grid for a single album.
 */
async function getManageAlbum(req, res, next) {
  try {
    const album = await galleryService.getAlbumById(req.params.id);
    if (!album) return res.status(404).redirect('/admin/gallery/albums');

    const sortKey = ['newest', 'oldest', 'featured'].includes(req.query.sort) ? req.query.sort : 'newest';
    const media = await galleryService.getMediaByAlbumId(req.params.id, sortKey);

    res.render('gallery/manage', {
      layout: 'layouts/admin',
      album,
      media,
    });
  } catch (err) { next(err); }
}

/* ════════════════════════════════════════════════════════════
   UPLOAD CONTROLLERS
   ════════════════════════════════════════════════════════════ */

/**
 * GET /admin/gallery/upload
 * Render the upload page.
 */
async function getUploadPage(req, res, next) {
  try {
    const albums = await galleryService.getAlbumsForUpload();
    res.render('gallery/upload', {
      layout: 'layouts/admin',
      albums,
      selectedAlbum: req.query.album || '',
    });
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/upload
 * Process a single uploaded file (called in a fetch() loop by the browser).
 * 
 * Validation layers:
 *   1. Multer fileFilter — rejects unsupported MIME / ext
 *   2. Photo size cap    — 15 MB
 *   3. Video format check
 *   4. (Optional) MIME re-check via `file-type` package
 *
 * Returns JSON:
 *   { success: true,  message, mediaId, urls }
 *   { success: false, message }
 */
async function processUpload(req, res) {
  try {
    if (!req.file) {
      return res.status(400).json({ success: false, message: 'No file received.' });
    }

    const { albumId, type } = req.body;
    if (!albumId) {
      fs.unlink(req.file.path, () => {});
      return res.status(400).json({ success: false, message: 'albumId is required.' });
    }

    // Verify album exists
    const album = await galleryService.getAlbumById(albumId);
    if (!album) {
      fs.unlink(req.file.path, () => {});
      return res.status(404).json({ success: false, message: 'Album not found.' });
    }

    const isPhoto = ALLOWED_PHOTO_MIME.includes(req.file.mimetype);
    const isVideo = ALLOWED_VIDEO_MIME.includes(req.file.mimetype);

    /* Extra photo size validation (multer limit is set to video max) */
    if (isPhoto && req.file.size > PHOTO_MAX_BYTES) {
      fs.unlink(req.file.path, () => {});
      return res.status(400).json({ success: false, message: 'Photo exceeds 15 MB limit.' });
    }

    let urls = {};
    const baseName = path.parse(req.file.filename).name;

    if (isPhoto) {
      /* ── Image Processing Pipeline ──
         1. Resize to thumbnail / medium / large
         2. Convert to WebP
         3. Compress at 75–80% quality
      */
      urls = await processImage(req.file.path, albumId, baseName);
    } else if (isVideo) {
      /* ── Video Storage ──
         For production: upload to Cloudinary/Vimeo and get back a URL.
         Here we store locally as a placeholder.
         TODO: integrate Cloudinary upload_stream.
      */
      const videoDir = path.join(UPLOAD_BASE, 'videos', albumId);
      fs.mkdirSync(videoDir, { recursive: true });
      const destPath = path.join(videoDir, req.file.filename);
      fs.renameSync(req.file.path, destPath);
      urls = {
        original:  `/uploads/gallery/videos/${albumId}/${req.file.filename}`,
        thumbnail: null, // generate from first frame in production
        medium:    null,
        large:     null,
      };
    } else {
      fs.unlink(req.file.path, () => {});
      return res.status(400).json({ success: false, message: 'Unsupported file type.' });
    }

    // Persist media record
    const media = await galleryService.createMedia({
      albumId,
      filename:    req.file.originalname,
      storedName:  req.file.filename,
      type:        isPhoto ? 'photo' : 'video',
      mimetype:    req.file.mimetype,
      size:        req.file.size,
      urls,
      caption:     '',
      tags:        [],
      featured:    false,
      sortOrder:   0,
    });

    // Increment album mediaCount
    await galleryService.incrementAlbumMediaCount(albumId, 1);

    return res.json({
      success:  true,
      message:  'Uploaded successfully.',
      mediaId:  media._id,
      urls,
    });
  } catch (err) {
    // Clean up temp file on unexpected error
    if (req.file?.path) fs.unlink(req.file.path, () => {});
    console.error('[Gallery] Upload error:', err.message);
    return res.status(500).json({ success: false, message: 'Server error during upload.' });
  }
}

/* ════════════════════════════════════════════════════════════
   MEDIA ITEM CONTROLLERS
   ════════════════════════════════════════════════════════════ */

/**
 * PATCH /admin/gallery/media/:id/caption
 */
async function updateCaption(req, res, next) {
  try {
    const { caption } = req.body;
    await galleryService.updateMedia(req.params.id, { caption: caption?.trim() });
    res.json({ success: true });
  } catch (err) { next(err); }
}

/**
 * PATCH /admin/gallery/media/:id/feature
 */
async function toggleMediaFeatured(req, res, next) {
  try {
    const media = await galleryService.getMediaById(req.params.id);
    if (!media) return res.status(404).json({ success: false });

    const updated = await galleryService.updateMedia(
      req.params.id,
      { featured: !media.featured },
      { new: true }
    );
    res.json({ success: true, featured: updated.featured });
  } catch (err) { next(err); }
}

/**
 * DELETE /admin/gallery/media/:id
 */
async function deleteMedia(req, res, next) {
  try {
    const media = await galleryService.getMediaById(req.params.id);
    if (!media) return res.status(404).json({ success: false, message: 'Not found.' });

    deleteMediaFiles(media);
    await galleryService.deleteMedia(req.params.id);
    await galleryService.incrementAlbumMediaCount(media.albumId, -1);

    res.json({ success: true });
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/media/reorder
 * Swap sortOrder values between source and target items.
 */
async function reorderMedia(req, res, next) {
  try {
    const { sourceId, targetId } = req.body;
    const [src, tgt] = await Promise.all([
      galleryService.getMediaById(sourceId),
      galleryService.getMediaById(targetId),
    ]);
    if (!src || !tgt) return res.status(404).json({ success: false });

    await Promise.all([
      galleryService.updateMedia(sourceId, { sortOrder: tgt.sortOrder }),
      galleryService.updateMedia(targetId, { sortOrder: src.sortOrder }),
    ]);

    res.json({ success: true });
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/media/bulk-delete
 */
async function bulkDeleteMedia(req, res, next) {
  try {
    const { ids } = req.body;
    if (!ids?.length) return res.status(400).json({ success: false });

    const items = await galleryService.findMediaByIds(ids);
    items.forEach(deleteMediaFiles);

    // Decrement album counts
    const albumCounts = {};
    items.forEach(item => {
      albumCounts[item.albumId] = (albumCounts[item.albumId] || 0) + 1;
    });
    await Promise.all(
      Object.entries(albumCounts).map(([aid, count]) =>
        galleryService.incrementAlbumMediaCount(aid, -count)
      )
    );

    await galleryService.deleteManyMedia(ids);
    res.json({ success: true, deleted: ids.length });
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/media/bulk-feature
 */
async function bulkFeatureMedia(req, res, next) {
  try {
    const { ids } = req.body;
    if (!ids?.length) return res.status(400).json({ success: false });
    await galleryService.updateManyMedia(ids, { featured: true });
    res.json({ success: true });
  } catch (err) { next(err); }
}

/**
 * POST /admin/gallery/media/bulk-move
 */
async function bulkMoveMedia(req, res, next) {
  try {
    const { ids, targetAlbumId } = req.body;
    if (!ids?.length || !targetAlbumId) {
      return res.status(400).json({ success: false });
    }

    // Count moves per source album
    const items = await galleryService.findMediaByIds(ids);
    const albumCounts = {};
    items.forEach(item => {
      albumCounts[item.albumId] = (albumCounts[item.albumId] || 0) + 1;
    });

    // Decrement source albums, increment target
    await Promise.all([
      ...Object.entries(albumCounts).map(([aid, count]) =>
        galleryService.incrementAlbumMediaCount(aid, -count)
      ),
      galleryService.incrementAlbumMediaCount(targetAlbumId, ids.length),
    ]);

    await galleryService.updateManyMedia(ids, { albumId: targetAlbumId });
    res.json({ success: true });
  } catch (err) { next(err); }
}

/* ════════════════════════════════════════════════════════════
   EXPORTS
   ════════════════════════════════════════════════════════════ */
module.exports = {
  /* Multer middleware */
  handleUploadMiddleware,

  /* Album */
  getAlbums,
  getCreateAlbum,
  createAlbum,
  getEditAlbum,
  updateAlbum,
  deleteAlbum,
  toggleAlbumFeatured,
  getManageAlbum,

  /* Upload */
  getUploadPage,
  processUpload,

  /* Media */
  updateCaption,
  toggleMediaFeatured,
  deleteMedia,
  reorderMedia,
  bulkDeleteMedia,
  bulkFeatureMedia,
  bulkMoveMedia,
};
