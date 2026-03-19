<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/routes/events.php — Event admin routes
 *
 * Mount point: /admin/events  (registered in admin/routes/admin.php)
 *
 * This file is a plain PHP router segment.
 * It expects $method and $segments to be set by the front router:
 *
 *   $method   = $_SERVER['REQUEST_METHOD']          // 'GET' | 'POST'
 *   $segments = exploded URL parts after /admin/events/
 *               e.g. /admin/events/list   → ['list']
 *                    /admin/events/abc/edit → ['abc', 'edit']
 *
 * CSRF verification is done here before handing off to the controller.
 * Banner upload is processed here for POST routes that need it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../controllers/EventController.php';
require_once __DIR__ . '/../middleware/EventBannerUpload.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';

$controller = new EventController();

// Segment 0: first path part after /admin/events/
// Segment 1: second part (e.g. 'edit', 'delete')
$seg0 = $segments[0] ?? '';
$seg1 = $segments[1] ?? '';

// ── GET /admin/events  →  redirect to list ───────────────────
if ($method === 'GET' && $seg0 === '') {
    header('Location: /admin/events/list');
    exit;
}

// ── GET /admin/events/list ───────────────────────────────────
if ($method === 'GET' && $seg0 === 'list') {
    $controller->getEvents();
    exit;
}

// ── GET /admin/events/create ─────────────────────────────────
if ($method === 'GET' && $seg0 === 'create') {
    $controller->getCreateEvent();
    exit;
}

// ── POST /admin/events  (create) ─────────────────────────────
if ($method === 'POST' && $seg0 === '') {
    EventBannerUpload::handle();   // sets _bannerUploadError on failure
    CsrfMiddleware::verify();      // aborts with 403 on token mismatch
    $controller->postCreateEvent();
    exit;
}

// ── GET /admin/events/:id/edit ───────────────────────────────
if ($method === 'GET' && $seg1 === 'edit' && $seg0 !== '') {
    $controller->getEditEvent($seg0);
    exit;
}

// ── POST /admin/events/:id/delete ────────────────────────────
if ($method === 'POST' && $seg1 === 'delete' && $seg0 !== '') {
    CsrfMiddleware::verify();
    $controller->postDeleteEvent($seg0);
    exit;
}

// ── POST /admin/events/:id  (update) ─────────────────────────
if ($method === 'POST' && $seg0 !== '' && $seg1 === '') {
    EventBannerUpload::handle();
    CsrfMiddleware::verify();
    $controller->postUpdateEvent($seg0);
    exit;
}

// ── Fallback: 404 ────────────────────────────────────────────
http_response_code(404);
echo 'Admin route not found.';
