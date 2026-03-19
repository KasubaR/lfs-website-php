<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/routes/gallery.php — Public gallery (read-only)
 *
 * Mount point: /gallery  (dispatched from the front router)
 *
 * Expects from front router:
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /gallery/
 *               e.g. /gallery          → ['']
 *                    /gallery/abc123   → ['abc123']
 *
 * Routes:
 *   GET /gallery        → album listing grid
 *   GET /gallery/:id    → single album with media
 *
 * ⚠️  The listing must match '' BEFORE the :id catch-all so that
 *    /gallery (no trailing slash) is handled correctly.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/controllers/GalleryPublicController.php';

$controller = new GalleryPublicController();
$seg0       = $segments[0] ?? '';

// GET /gallery
if ($method === 'GET' && $seg0 === '') {
    $controller->getIndex();
    exit;
}

// GET /gallery/:id
if ($method === 'GET' && $seg0 !== '') {
    $controller->getAlbum($seg0);
    exit;
}

// ── Fallback: 404 ────────────────────────────────────────────
http_response_code(404);
echo 'Gallery route not found.';
