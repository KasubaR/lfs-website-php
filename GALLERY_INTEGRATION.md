# Gallery Module — Integration Guide

**Project:** LFS — Lusaka Fitness Squad  
**Module:** Admin Gallery  
**Version:** 1.0.0  
**Last Updated:** March 2026

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Dependencies](#dependencies)
4. [Directory Setup](#directory-setup)
5. [Database Models](#database-models)
6. [Wiring Routes](#wiring-routes)
7. [View Engine & Layout](#view-engine--layout)
8. [Environment Variables](#environment-variables)
9. [Upload Rate Limiting](#upload-rate-limiting)
10. [Image Optimization Pipeline](#image-optimization-pipeline)
11. [Video Hosting — Cloudinary (Recommended)](#video-hosting--cloudinary-recommended)
12. [Static Files](#static-files)
13. [Flash Messages](#flash-messages)
14. [Security Checklist](#security-checklist)
15. [Testing the Module](#testing-the-module)
16. [Troubleshooting](#troubleshooting)

---

## Overview

The Gallery Module gives LFS admins a complete media management system inside the existing admin panel. It adds three EJS views, one route file, and one controller — all designed to slot into the existing `app.js` / `admin.routes.js` pattern without touching any other part of the codebase.

**What it delivers:**
- Album creation, editing, deletion with category/tag/event assignment
- Bulk photo upload with drag & drop, client-side validation, and a per-file progress UI
- Server-side Sharp image pipeline: three WebP size variants auto-generated on upload
- Media management grid with drag-to-reorder, bulk actions, inline caption editing, and a full-screen lightbox
- Featured content system: mark albums and individual media items as featured, slider, or event highlight

---

## File Structure

Place each file at the path shown. No other files need to be moved or renamed.

```
lfs/
├── src/
│   ├── admin/
│   │   ├── controllers/
│   │   │   └── gallery.controller.js        ← NEW
│   │   ├── routes/
│   │   │   ├── admin.routes.js              ← EDIT (add one line)
│   │   │   └── gallery.routes.js            ← NEW
│   │   └── views/
│   │       └── gallery/
│   │           ├── albums.ejs               ← NEW
│   │           ├── upload.ejs               ← NEW
│   │           └── manage.ejs               ← NEW
│   └── app.js                               ← no changes needed
└── public/
    └── uploads/
        └── gallery/                         ← auto-created by controller
```

> **Note:** The `public/uploads/gallery/` directory is created automatically by the controller when the first upload is processed. You do not need to create it manually, but you must ensure `public/` is writable by the Node process.

---

## Dependencies

Install all required packages before starting the server.

```bash
npm install multer sharp uuid
```

| Package | Purpose | Min Version |
|---------|---------|-------------|
| `multer` | Multipart form parsing, temp file storage | `^1.4.5` |
| `sharp` | Image resize, format conversion, compression | `^0.33.0` |
| `uuid` | Collision-safe unique filenames | `^9.0.0` |

**Optional — required only if using Cloudinary for video:**

```bash
npm install cloudinary multer-storage-cloudinary
```

**Already in the project (no action needed):**

| Package | Already used by |
|---------|----------------|
| `express` | `app.js` |
| `express-ejs-layouts` | `app.js` |
| `dotenv` | `server.js` |

---

## Directory Setup

The controller auto-creates subdirectories at runtime, but if you want to pre-create them or add them to `.gitkeep`:

```bash
mkdir -p public/uploads/gallery/temp
mkdir -p public/uploads/gallery/videos
```

Add to `.gitignore` so uploaded media is never committed:

```gitignore
# User-uploaded media
public/uploads/gallery/
```

Keep the directory itself tracked so the path exists on fresh clones:

```bash
touch public/uploads/gallery/.gitkeep
```

---

## Database (Supabase)

Gallery data lives in Supabase. Tables: `albums` and `media` (see `supabase-schema.sql`). The data layer is `src/services/gallery.service.js`; shape and constants are in `src/model/gallery.js`. The gallery controller uses the gallery service — no Mongoose or separate model files for Album/Media.

---

## Wiring Routes

### 1. Register the gallery router inside `admin.routes.js`

Open `src/admin/routes/admin.routes.js` and add one `require` and one `use` call alongside the existing admin routes:

```js
// At the top with other route imports
const galleryRouter = require('./gallery.routes');

// Inside the router definitions (after dashboard, members, events, etc.)
router.use('/gallery', galleryRouter);
```

The gallery routes will then be reachable at `/admin/gallery/*` because `admin.routes.js` is already mounted at `/admin` in `app.js`.

### 2. Verify the admin router mount in `app.js`

No changes are needed. The existing line already covers the new routes:

```js
// app.js — already present, no edit needed
app.use('/admin', adminRouter);
```

---

## View Engine & Layout

### Layout reference

All three gallery views call `layout: 'layouts/admin'` in their controller render calls. Confirm the admin layout file path matches what your project uses. In `app.js` the views array is:

```js
app.set('views', [
  path.join(__dirname, 'views'),
  path.join(__dirname, 'admin', 'views'),   // ← gallery views resolve from here
]);
```

The three gallery views live at `admin/views/gallery/albums.ejs` etc., so EJS will find them as `gallery/albums`, `gallery/upload`, and `gallery/manage` — which is exactly what the controller passes to `res.render()`.

### `activePage` for sidebar highlighting

The admin sidebar in `admin.ejs` already contains the gallery nav item:

```html
<a href="/admin/gallery"
   class="nav-item <%= activePage === 'gallery' ? 'active' : '' %>">
```

All three gallery views set `activePage = 'gallery'` at the top of the template, so the sidebar link highlights correctly on every gallery page with no extra work.

---

## Environment Variables

Add these to your `.env` file. Only `UPLOAD_DIR` is strictly required for local storage. The Cloudinary variables are needed only if you switch to cloud video hosting.

```dotenv
# ── Gallery ────────────────────────────────────────
# Absolute or relative path override for upload storage.
# Defaults to public/uploads/gallery/ if not set.
GALLERY_UPLOAD_DIR=public/uploads/gallery

# Max upload sizes (optional — controller defaults used if absent)
PHOTO_MAX_MB=5
VIDEO_MAX_MB=200

# ── Cloudinary (optional — for video hosting) ──────
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

---

## Upload Rate Limiting

The controller does not apply rate limiting itself — add it in the route file or at the Express app level so it sits in front of the multer middleware.

### Option A — Apply to upload route only (recommended)

```js
// gallery.routes.js — add at the top
const rateLimit = require('express-rate-limit');

const uploadLimiter = rateLimit({
  windowMs:    15 * 60 * 1000,   // 15-minute window
  max:         100,               // max 100 upload requests per window per IP
  message:     { success: false, message: 'Too many uploads. Please wait and try again.' },
  standardHeaders: true,
  legacyHeaders:   false,
});

// Apply before the upload POST route
router.post('/upload', uploadLimiter, galleryController.handleUploadMiddleware, galleryController.processUpload);
```

Install: `npm install express-rate-limit`

### Option B — Apply across all admin routes

Add to `admin.routes.js` or in `app.js` before mounting admin routes.

---

## Image Optimization Pipeline

The Sharp pipeline runs automatically inside `processUpload` every time a photo is received. No configuration is required beyond having Sharp installed.

### What happens on every photo upload

```
Browser sends file
       │
       ▼
Multer saves to  public/uploads/gallery/temp/{uuid}.jpg
       │
       ▼
Sharp reads temp file
       ├─► Resize to 300px  → Convert to WebP @ 75% quality → thumbnails/{uuid}.webp
       ├─► Resize to 900px  → Convert to WebP @ 78% quality → medium/{uuid}.webp
       └─► Resize to 1600px → Convert to WebP @ 80% quality → large/{uuid}.webp
       │
       ▼
Original copied to  originals/{uuid}.jpg  (kept for downloads)
Temp file deleted
       │
       ▼
Media document saved with all four URLs
```

### Adjusting quality settings

Quality values are defined in the `IMAGE_SIZES` constant near the top of `gallery.controller.js`:

```js
const IMAGE_SIZES = {
  thumbnail: { width: 300,  quality: 75 },
  medium:    { width: 900,  quality: 78 },
  large:     { width: 1600, quality: 80 },
};
```

Increase quality for better visual fidelity; decrease it for smaller file sizes. The 75–80% range is the recommended sweet spot for WebP.

---

## Video Hosting — Cloudinary (Recommended)

Local video storage is fine for development but not recommended in production. The controller contains a clearly marked `TODO` comment where Cloudinary upload logic should be inserted.

### Setup

```bash
npm install cloudinary
```

### Replace the local video block in `processUpload`

Find this comment in `gallery.controller.js`:

```js
} else if (isVideo) {
  /* ── Video Storage ──
     TODO: integrate Cloudinary upload_stream.
  */
```

Replace the block below it with:

```js
} else if (isVideo) {
  const cloudinary = require('cloudinary').v2;
  
  const result = await new Promise((resolve, reject) => {
    const stream = cloudinary.uploader.upload_stream(
      {
        resource_type: 'video',
        folder:        `lfs/gallery/${albumId}`,
        public_id:     baseName,
        eager: [
          { streaming_profile: 'full_hd', format: 'm3u8' },  // HLS streaming
        ],
        eager_async: true,
      },
      (err, result) => err ? reject(err) : resolve(result)
    );
    fs.createReadStream(req.file.path).pipe(stream);
  });

  fs.unlink(req.file.path, () {});

  urls = {
    original:  result.secure_url,
    thumbnail: result.secure_url.replace('/upload/', '/upload/so_0/').replace(/\.[^.]+$/, '.jpg'),
    medium:    null,
    large:     null,
  };
}
```

---

## Static Files

Uploaded images are served from the `public/` directory, which `app.js` already exposes at the root URL:

```js
// app.js — already present
app.use(express.static(path.join(__dirname, '..', 'public'), { ... }));
```

A file stored at `public/uploads/gallery/{albumId}/thumbnails/abc.webp` is therefore accessible at `/uploads/gallery/{albumId}/thumbnails/abc.webp` — which is exactly the URL format the controller writes into `Media.urls.thumbnail`.

No additional static-file configuration is required.

---

## Flash Messages

The gallery controller calls `req.flash?.('success', '...')` using optional chaining. This means if you do not have a flash middleware installed, the calls fail silently and the app still works — you just won't see success toasts.

To enable flash messages, install `connect-flash` and `express-session`:

```bash
npm install connect-flash express-session
```

Add to `app.js` **before** the route mounts:

```js
const session     = require('express-session');
const flash       = require('connect-flash');

app.use(session({
  secret:            process.env.SESSION_SECRET || 'lfs-dev-secret',
  resave:            false,
  saveUninitialized: false,
  cookie:            { secure: process.env.NODE_ENV === 'production' },
}));
app.use(flash());

// Make flash available in all templates
app.use((req, res, next) => {
  res.locals.flash = {
    success: req.flash('success')[0],
    error:   req.flash('error')[0],
    warning: req.flash('warning')[0],
  };
  next();
});
```

The admin layout (`admin.ejs`) already contains the flash rendering block, so messages will appear automatically once the middleware is in place.

---

## Security Checklist

All protections listed in the spec are implemented. Verify each one is active before going to production.

| Protection | Where implemented | Status |
|-----------|------------------|--------|
| File type validation (extension) | `fileFilter` in controller | ✅ Built-in |
| MIME type checking | `fileFilter` in controller | ✅ Built-in |
| Photo size limit (5 MB) | `processUpload` body check | ✅ Built-in |
| Video size limit (200 MB) | Multer `limits.fileSize` | ✅ Built-in |
| Bulk upload cap (50 photos) | Client-side JS in `upload.ejs` | ✅ Built-in |
| Upload rate limiting | `gallery.routes.js` (see section above) | ⚠️ Add `express-rate-limit` |
| Admin-only access | Applied by parent `admin.routes.js` auth middleware | ✅ Inherited |
| Temp file cleanup on error | `try/catch` in `processUpload` | ✅ Built-in |
| Unique filenames (no overwrites) | `uuid` in multer storage config | ✅ Built-in |

> **Virus scanning** is listed as optional in the spec. If required, integrate `clamscan` or the ClamAV Node bindings after the Multer step and before the Sharp pipeline.

---

## Testing the Module

### 1. Confirm the server starts cleanly

```bash
npm run dev
```

Watch for any `MODULE_NOT_FOUND` errors — most likely `multer`, `sharp`, or `uuid` not yet installed.

### 2. Navigate to the albums list

```
http://localhost:3000/admin/gallery/albums
```

The page should render the gallery stats cards and an empty album grid with a "Create First Album" call to action.

### 3. Create an album

Click **New Album**, fill in the form (title, category, date, location), and submit. You should be redirected back to the albums grid with the new card visible.

### 4. Upload a photo

Navigate to **Upload**, select the album just created, drop a JPG onto the dropzone. The progress bar should fill and per-file status should show a green tick. After completion the browser redirects to the Manage page.

Verify on disk that three WebP variants were created:

```bash
ls public/uploads/gallery/{albumId}/thumbnails/
ls public/uploads/gallery/{albumId}/medium/
ls public/uploads/gallery/{albumId}/large/
```

### 5. Test security rejections

- Upload a `.txt` file → should be rejected client-side and never reach the server
- Upload a photo renamed to `.mp4` → MIME check in `fileFilter` should reject it with a 400
- Upload a photo over 5 MB → `processUpload` size guard should return `{ success: false, message: "Photo exceeds 5 MB limit." }`

---

## Troubleshooting

**`Error: Cannot find module 'sharp'`**  
Run `npm install sharp`. On some systems Sharp needs a compatible Node version — Sharp 0.33+ requires Node 18+.

**`Error: Cannot find module 'multer'`**  
Run `npm install multer uuid`.

**Images upload but no WebP files appear on disk**  
Check that the process has write permission to `public/uploads/`. On Linux: `chmod -R 755 public/uploads/`.

**Gallery sidebar link is not highlighted**  
Confirm `activePage = 'gallery'` is set at the top of each gallery EJS file (it is set by default — check nothing is overwriting it in the controller render call).

**Flash messages not appearing after create/delete**  
`connect-flash` and `express-session` middleware are not installed. See the Flash Messages section above.

**Videos play but images inside `manage.ejs` are broken**  
The `Media.urls.thumbnail` value stored in the database may be `null` if Sharp failed silently. Check the server log for Sharp errors, and confirm `public/uploads/gallery/{albumId}/thumbnails/` exists and is writable.

**`ENOENT: no such file or directory` on upload**  
The temp directory does not exist yet. The controller creates it automatically, but if the `public/uploads/` parent is missing entirely the recursive `mkdirSync` may fail depending on OS permissions. Create `public/uploads/gallery/temp/` manually once and the controller will handle subdirectories from there.
