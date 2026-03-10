/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/middleware/eventBannerUpload.js

   Multer config for event banner image upload.
   Saves to public/images/events/ with a safe unique filename.
   File is optional (no error when no file is sent).
   On cPanel, set PUBLIC_ROOT to your web root, e.g. /home/lfszambia/public_html
   ============================================================ */

'use strict';

const path = require('path');
const fs = require('fs');
const multer = require('multer');
const { v4: uuidv4 } = require('uuid');

const IS_PROD = process.env.NODE_ENV === 'production';
const DEFAULT_PUBLIC_ROOT = path.join(__dirname, '..', '..', '..', 'public');
const publicRoot = (IS_PROD && process.env.PUBLIC_ROOT) ? process.env.PUBLIC_ROOT : DEFAULT_PUBLIC_ROOT;
const EVENTS_BANNER_DIR = path.join(publicRoot, 'images', 'events');
const BANNER_MAX_BYTES = 15 * 1024 * 1024; // 15 MB

const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_EXT = ['.jpg', '.jpeg', '.png', '.webp'];

const storage = multer.diskStorage({
  destination(req, file, cb) {
    fs.mkdirSync(EVENTS_BANNER_DIR, { recursive: true });
    cb(null, EVENTS_BANNER_DIR);
  },
  filename(req, file, cb) {
    const ext = path.extname(file.originalname).toLowerCase();
    const safeExt = ALLOWED_EXT.includes(ext) ? ext : '.jpg';
    cb(null, `event-${uuidv4()}${safeExt}`);
  },
});

function fileFilter(req, file, cb) {
  const ext = path.extname(file.originalname).toLowerCase();
  const allowed = ALLOWED_MIME.includes(file.mimetype) && ALLOWED_EXT.includes(ext);
  if (allowed) {
    cb(null, true);
  } else {
    cb(new multer.MulterError('LIMIT_UNEXPECTED_FILE', 'Banner must be a JPEG, PNG or WebP image.'));
  }
}

const upload = multer({
  storage,
  limits: { fileSize: BANNER_MAX_BYTES, files: 1 },
  fileFilter,
}).single('bannerImageFile');

/**
 * Middleware: parse multipart form and optionally accept one banner image.
 * req.file is set only when a file was uploaded; no error when field is missing.
 * On upload validation errors, sets req.bannerUploadError and calls next() so the controller can re-render the form.
 */
function eventBannerUpload(req, res, next) {
  upload(req, res, (err) => {
    if (err instanceof multer.MulterError) {
      if (err.code === 'LIMIT_FILE_SIZE') {
        req.bannerUploadError = Object.assign(new Error('Banner image must be under 15 MB.'), { code: 'BANNER_FILE_TOO_LARGE' });
        return next();
      }
      req.bannerUploadError = Object.assign(new Error(err.message || 'Banner must be a JPEG, PNG or WebP image under 15 MB.'), { code: 'BANNER_FILE_INVALID' });
      return next();
    }
    if (err) return next(err);
    next();
  });
}

module.exports = eventBannerUpload;
