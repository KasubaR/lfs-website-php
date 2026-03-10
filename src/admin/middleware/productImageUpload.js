'use strict';

/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/middleware/productImageUpload.js

   Multer config for product image uploads.
   Saves to public/uploads/products/{productId}/ with safe filenames.

   Usage (example):
     router.post(
       '/products/:id/images',
       productImageUpload.single('productImage'),
       controller.handleProductImageUpload
     );

   After this middleware:
     • req.file          — multer file object (if a file was uploaded)
     • req.productImages — array of URL paths to saved images (for convenience)

   NOTE:
     This middleware does not touch Supabase. Controllers are responsible
     for persisting req.productImages into the products.images JSONB and
     thumbnail fields as needed.
   ============================================================ */

const path = require('path');
const fs = require('fs');
const multer = require('multer');
const { v4: uuidv4 } = require('uuid');

const IS_PROD = process.env.NODE_ENV === 'production';
const DEFAULT_PUBLIC_ROOT = path.join(__dirname, '..', '..', '..', 'public');
const PUBLIC_ROOT = (IS_PROD && process.env.PUBLIC_ROOT) ? process.env.PUBLIC_ROOT : DEFAULT_PUBLIC_ROOT;

const PRODUCT_UPLOAD_ROOT = path.join(PUBLIC_ROOT, 'uploads', 'products');
const PRODUCT_MAX_BYTES = 15 * 1024 * 1024; // 15 MB per image

const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_EXT = ['.jpg', '.jpeg', '.png', '.webp'];

const storage = multer.diskStorage({
  destination(req, file, cb) {
    // For existing products we use :id param; for new products we generate a temporary upload key.
    const uploadKey = req.params?.id || req.body?.productId || uuidv4();
    req.productUploadKey = String(uploadKey);
    const dir = path.join(PRODUCT_UPLOAD_ROOT, req.productUploadKey);
    fs.mkdirSync(dir, { recursive: true });
    cb(null, dir);
  },
  filename(_req, file, cb) {
    const ext = path.extname(file.originalname).toLowerCase();
    const safeExt = ALLOWED_EXT.includes(ext) ? ext : '.jpg';
    cb(null, `product-${uuidv4()}${safeExt}`);
  },
});

function fileFilter(_req, file, cb) {
  const ext = path.extname(file.originalname).toLowerCase();
  const allowed = ALLOWED_MIME.includes(file.mimetype) && ALLOWED_EXT.includes(ext);
  if (allowed) {
    cb(null, true);
  } else {
    cb(new multer.MulterError('LIMIT_UNEXPECTED_FILE', 'Product image must be JPEG, PNG or WebP.'));
  }
}

const upload = multer({
  storage,
  limits: { fileSize: PRODUCT_MAX_BYTES, files: 5 },
  fileFilter,
}).array('productImages', 5);

/**
 * Middleware: handle up to 5 product images.
 * Sets req.productImages = [ '/uploads/products/{productId}/filename.ext', ... ]
 * on success. Does not error when field is missing.
 */
function productImageUpload(req, res, next) {
  upload(req, res, (err) => {
    if (err instanceof multer.MulterError) {
      if (err.code === 'LIMIT_FILE_SIZE') {
        req.productImageError = Object.assign(
          new Error('Each product image must be under 15 MB.'),
          { code: 'PRODUCT_IMAGE_TOO_LARGE' },
        );
        return next();
      }
      req.productImageError = Object.assign(
        new Error(err.message || 'Product images must be JPEG, PNG or WebP and under 15 MB.'),
        { code: 'PRODUCT_IMAGE_INVALID' },
      );
      return next();
    }
    if (err) return next(err);

    const files = Array.isArray(req.files) ? req.files : [];
    const uploadKey = req.productUploadKey || req.params?.id || req.body?.productId || 'temp';
    const baseUrl = `/uploads/products/${uploadKey}`;

    req.productImages = files.map((f) => `${baseUrl}/${f.filename}`);
    return next();
  });
}

module.exports = productImageUpload;

