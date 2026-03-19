<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/routes/gallery.php — Gallery admin routes
 *
 * Mount point: /admin/gallery  (dispatched from admin.php)
 *
 * Expects (set by admin.php before require-ing this file):
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /admin/gallery/
 *               e.g. /admin/gallery/albums         → ['albums']
 *                    /admin/gallery/albums/abc/edit → ['albums', 'abc', 'edit']
 *                    /admin/gallery/media/abc/feature → ['media', 'abc', 'feature']
 *
 * CSRF is verified inline on state-changing form POSTs.
 * Upload endpoints verify CSRF after their middleware runs.
 *
 * Rate-limiting for uploads is enforced in AlbumCoverUpload middleware
 * (or a separate PHP rate-limit helper if needed).
 */

declare(strict_types=1);

require_once __DIR__ . '/../controllers/GalleryController.php';
require_once __DIR__ . '/../middleware/AlbumCoverUpload.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';

$controller = new GalleryController();

$seg0 = $segments[0] ?? '';   // 'albums' | 'upload' | 'media' | 'cover-upload'
$seg1 = $segments[1] ?? '';   // album id  OR  media id  OR  'create'
$seg2 = $segments[2] ?? '';   // 'edit' | 'manage' | 'delete' | 'feature' | 'caption'

/* ════════════════════════════════════════════════════════════
   GET /admin/gallery  →  redirect to albums
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === '') {
    header('Location: /admin/gallery/albums');
    exit;
}

/* ════════════════════════════════════════════════════════════
   ALBUM ROUTES
   ════════════════════════════════════════════════════════════ */

// GET /admin/gallery/albums
if ($method === 'GET' && $seg0 === 'albums' && $seg1 === '') {
    $controller->getAlbums();
    exit;
}

// GET /admin/gallery/albums/create
if ($method === 'GET' && $seg0 === 'albums' && $seg1 === 'create') {
    $controller->getCreateAlbum();
    exit;
}

// GET /admin/gallery/settings
if ($method === 'GET' && $seg0 === 'settings') {
    $controller->getSettings();
    exit;
}

// POST /admin/gallery/albums  (create)
if ($method === 'POST' && $seg0 === 'albums' && $seg1 === '') {
    CsrfMiddleware::verify();
    $controller->createAlbum();
    exit;
}

// POST /admin/gallery/settings
if ($method === 'POST' && $seg0 === 'settings') {
    CsrfMiddleware::verify();
    $controller->updateSettings();
    exit;
}

// GET /admin/gallery/albums/:id/edit
if ($method === 'GET' && $seg0 === 'albums' && $seg1 !== '' && $seg2 === 'edit') {
    $controller->getEditAlbum($seg1);
    exit;
}

// GET /admin/gallery/albums/:id/manage
if ($method === 'GET' && $seg0 === 'albums' && $seg1 !== '' && $seg2 === 'manage') {
    $controller->getManageAlbum($seg1);
    exit;
}

// POST /admin/gallery/albums/:id  (update)
if ($method === 'POST' && $seg0 === 'albums' && $seg1 !== '' && $seg2 === '') {
    CsrfMiddleware::verify();
    $controller->updateAlbum($seg1);
    exit;
}

// POST /admin/gallery/albums/:id/delete
if ($method === 'POST' && $seg0 === 'albums' && $seg1 !== '' && $seg2 === 'delete') {
    CsrfMiddleware::verify();
    $controller->deleteAlbum($seg1);
    exit;
}

// PATCH /admin/gallery/albums/:id/feature
if ($method === 'PATCH' && $seg0 === 'albums' && $seg1 !== '' && $seg2 === 'feature') {
    CsrfMiddleware::verify();
    $controller->toggleAlbumFeatured($seg1);
    exit;
}

/* ════════════════════════════════════════════════════════════
   UPLOAD ROUTES
   ════════════════════════════════════════════════════════════ */

// GET /admin/gallery/upload
if ($method === 'GET' && $seg0 === 'upload') {
    $controller->getUploadPage();
    exit;
}

// POST /admin/gallery/upload  (AJAX file upload — returns JSON)
if ($method === 'POST' && $seg0 === 'upload') {
    CsrfMiddleware::verifyHeader();   // AJAX sends X-CSRF-Token header
    $controller->handleUpload();
    exit;
}

// POST /admin/gallery/cover-upload  (AJAX cover image upload — returns JSON)
if ($method === 'POST' && $seg0 === 'cover-upload') {
    $filename = AlbumCoverUpload::handle();   // returns filename or null on error
    CsrfMiddleware::verifyHeader();

    if ($filename === null) {
        $errorMsg = $_REQUEST['_coverUploadError'] ?? 'Upload failed.';
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'url'     => '/uploads/gallery/covers/' . $filename,
    ]);
    exit;
}

/* ════════════════════════════════════════════════════════════
   MEDIA ITEM ROUTES
   ════════════════════════════════════════════════════════════ */

// PATCH /admin/gallery/media/:id/caption
if ($method === 'PATCH' && $seg0 === 'media' && $seg1 !== '' && $seg2 === 'caption') {
    CsrfMiddleware::verifyHeader();
    $controller->updateCaption($seg1);
    exit;
}

// PATCH /admin/gallery/media/:id/feature
if ($method === 'PATCH' && $seg0 === 'media' && $seg1 !== '' && $seg2 === 'feature') {
    CsrfMiddleware::verifyHeader();
    $controller->toggleMediaFeatured($seg1);
    exit;
}

// PATCH /admin/gallery/media/:id/homepage-slider
if ($method === 'PATCH' && $seg0 === 'media' && $seg1 !== '' && $seg2 === 'homepage-slider') {
    CsrfMiddleware::verifyHeader();
    $controller->toggleMediaHomepageSlider($seg1);
    exit;
}

// PATCH /admin/gallery/media/:id/event-highlight
if ($method === 'PATCH' && $seg0 === 'media' && $seg1 !== '' && $seg2 === 'event-highlight') {
    CsrfMiddleware::verifyHeader();
    $controller->toggleMediaEventHighlight($seg1);
    exit;
}

// DELETE /admin/gallery/media/:id
if ($method === 'DELETE' && $seg0 === 'media' && $seg1 !== '' && $seg2 === '') {
    CsrfMiddleware::verifyHeader();
    $controller->deleteMedia($seg1);
    exit;
}

// POST /admin/gallery/media/reorder
if ($method === 'POST' && $seg0 === 'media' && $seg1 === 'reorder') {
    CsrfMiddleware::verifyHeader();
    $controller->reorderMedia();
    exit;
}

// POST /admin/gallery/media/bulk-delete
if ($method === 'POST' && $seg0 === 'media' && $seg1 === 'bulk-delete') {
    CsrfMiddleware::verifyHeader();
    $controller->bulkDeleteMedia();
    exit;
}

// POST /admin/gallery/media/bulk-feature
if ($method === 'POST' && $seg0 === 'media' && $seg1 === 'bulk-feature') {
    CsrfMiddleware::verifyHeader();
    $controller->bulkFeatureMedia();
    exit;
}

// POST /admin/gallery/media/bulk-move
if ($method === 'POST' && $seg0 === 'media' && $seg1 === 'bulk-move') {
    CsrfMiddleware::verifyHeader();
    $controller->bulkMoveMedia();
    exit;
}

// ── Fallback: 404 ────────────────────────────────────────────
http_response_code(404);
echo 'Gallery route not found.';
