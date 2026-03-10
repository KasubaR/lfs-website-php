'use strict';

/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/middleware/albumCoverUpload.js

   Multer config for gallery album cover image upload.
   Saves files to: public/uploads/gallery/covers/
   Field name: coverImageFile
   File is optional; route should handle missing file gracefully.
   ============================================================ */

const path = require('path');
const fs = require('fs');
const multer = require('multer');
const { v4: uuidv4 } = require('uuid');

const IS_PROD = process.env.NODE_ENV === 'production';
const DEFAULT_PUBLIC_ROOT = path.join(__dirname, '..', '..', '..', 'public');
const publicRoot = IS_PROD && process.env.PUBLIC_ROOT ? process.env.PUBLIC_ROOT : DEFAULT_PUBLIC_ROOT;

const COVERS_DIR = path.join(publicRoot, 'uploads', 'gallery', 'covers');
const COVER_MAX_BYTES = 15 * 1024 * 1024; // 15 MB

const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_EXT = ['.jpg', '.jpeg', '.png', '.webp'];

const storage = multer.diskStorage({
  destination(_req, _file, cb) {
    fs.mkdirSync(COVERS_DIR, { recursive: true });
    cb(null, COVERS_DIR);
  },
  filename(_req, file, cb) {
    const ext = path.extname(file.originalname).toLowerCase();
    const safeExt = ALLOWED_EXT.includes(ext) ? ext : '.jpg';
    cb(null, `album-cover-${uuidv4()}${safeExt}`);
  },
});

function fileFilter(_req, file, cb) {
  const ext = path.extname(file.originalname).toLowerCase();
  const allowed = ALLOWED_MIME.includes(file.mimetype) && ALLOWED_EXT.includes(ext);
  if (allowed) {
    cb(null, true);
  } else {
    cb(new multer.MulterError('LIMIT_UNEXPECTED_FILE', 'Cover must be a JPEG, PNG or WebP image.'));
  }
}

const upload = multer({
  storage,
  limits: { fileSize: COVER_MAX_BYTES, files: 1 },
  fileFilter,
}).single('coverImageFile');

/**
 * Middleware: parse multipart form and optionally accept one cover image.
 * For AJAX usage, this middleware does not end the response; the route
 * handler should inspect req.file and respond with JSON.
 * On validation errors, sets req.coverUploadError with a user-friendly message.
 */
function albumCoverUpload(req, res, next) {
  upload(req, res, (err) => {
    if (err instanceof multer.MulterError) {
      if (err.code === 'LIMIT_FILE_SIZE') {
        req.coverUploadError = Object.assign(
          new Error('Cover image must be under 15 MB.'),
          { code: 'COVER_FILE_TOO_LARGE' },
        );
        return next();
      }
      req.coverUploadError = Object.assign(
        new Error(err.message || 'Cover must be a JPEG, PNG or WebP image under 15 MB.'),
        { code: 'COVER_FILE_INVALID' },
      );
      return next();
    }
    if (err) return next(err);
    next();
  });
}

module.exports = albumCoverUpload;

