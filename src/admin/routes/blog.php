<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/routes/blog.php — Blog admin routes
 *
 * Mount point: /admin/blog  (registered in admin/routes/admin.php)
 *
 * Expects from master router:
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /admin/blog/
 *               e.g. /admin/blog/list        → ['list']
 *                    /admin/blog/abc/edit    → ['abc', 'edit']
 *                    /admin/blog/abc/delete  → ['abc', 'delete']
 *
 * Routes:
 *   GET  /admin/blog              → redirect to list
 *   GET  /admin/blog/list         → list all posts
 *   GET  /admin/blog/create       → new post form
 *   POST /admin/blog              → save new post
 *   GET  /admin/blog/:id/edit     → edit form
 *   POST /admin/blog/:id          → save edit
 *   GET  /admin/blog/:id/delete   → delete confirmation page
 *   POST /admin/blog/:id/delete   → perform delete
 */

declare(strict_types=1);

require_once __DIR__ . '/../controllers/BlogController.php';
require_once __DIR__ . '/../middleware/BlogImageUpload.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../../config/AdminConfig.php';

// Defence-in-depth: ensure auth. Parent (admin.php) already ran AuthController::requireAuth().
// Use the same session key and redirect to the actual login slug (e.g. /admin/door).
if (empty($_SESSION[AdminConfig::SESSION_AUTH_KEY])) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin/blog';
    header('Location: /admin/' . AdminConfig::LOGIN_SLUG);
    exit;
}

$controller = new BlogController();

$seg0 = $segments[0] ?? '';
$seg1 = $segments[1] ?? '';

// GET /admin/blog  → list
if ($method === 'GET' && $seg0 === '') {
    header('Location: /admin/blog/list');
    exit;
}

// GET /admin/blog/list
if ($method === 'GET' && $seg0 === 'list') {
    $controller->list();
    exit;
}

// GET /admin/blog/create
if ($method === 'GET' && $seg0 === 'create') {
    $controller->getCreate();
    exit;
}

// POST /admin/blog  (create)
if ($method === 'POST' && $seg0 === '') {
    $uploadResult      = BlogImageUpload::handle();
    $uploadedImagePath = $uploadResult['path']  ?? null;
    $uploadError       = $uploadResult['error'] ?? null;
    CsrfMiddleware::verify();
    $controller->postCreate($uploadedImagePath, $uploadError);
    exit;
}

// GET /admin/blog/:id/edit
if ($method === 'GET' && $seg1 === 'edit' && $seg0 !== '') {
    $controller->getEdit($seg0);
    exit;
}

// GET /admin/blog/:id/delete  (confirmation page)
if ($method === 'GET' && $seg1 === 'delete' && $seg0 !== '') {
    $controller->getDelete($seg0);
    exit;
}

// POST /admin/blog/:id/delete
if ($method === 'POST' && $seg1 === 'delete' && $seg0 !== '') {
    CsrfMiddleware::verify();
    $controller->postDelete($seg0);
    exit;
}

// POST /admin/blog/:id  (update) — must come after delete check
if ($method === 'POST' && $seg0 !== '' && $seg1 === '') {
    $uploadResult      = BlogImageUpload::handle();
    $uploadedImagePath = $uploadResult['path']  ?? null;
    $uploadError       = $uploadResult['error'] ?? null;
    CsrfMiddleware::verify();
    $controller->postUpdate($seg0, $uploadedImagePath, $uploadError);
    exit;
}

// Fallback 404
http_response_code(404);
echo 'Admin blog route not found.';
