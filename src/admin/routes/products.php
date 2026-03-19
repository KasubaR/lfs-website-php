<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/routes/products.php — Product admin routes
 *
 * Mount point: /admin/products  (dispatched from admin.php)
 *
 * Expects (set by admin.php before require-ing this file):
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /admin/products/
 *               e.g. /admin/products              → ['']
 *                    /admin/products/create        → ['create']
 *                    /admin/products/abc123/edit   → ['abc123', 'edit']
 *                    /admin/products/abc123/delete → ['abc123', 'delete']
 *
 * Image uploads are handled by ProductImageUpload middleware before
 * the controller, which also calls CsrfMiddleware::verify() internally.
 */

declare(strict_types=1);

require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../middleware/ProductImageUpload.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';

$controller = new ProductController();

$seg0 = $segments[0] ?? '';   // '' | 'create' | product id
$seg1 = $segments[1] ?? '';   // 'edit' | 'delete' | ''

/* ════════════════════════════════════════════════════════════
   LIST
   GET /admin/products
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === '') {
    $controller->getProducts();
    exit;
}

/* ════════════════════════════════════════════════════════════
   CREATE
   GET  /admin/products/create
   POST /admin/products
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === 'create') {
    $controller->getCreateProduct();
    exit;
}

if ($method === 'POST' && $seg0 === '') {
    ProductImageUpload::handle();   // sets $_FILES; sets _imageUploadError on failure
    CsrfMiddleware::verify();
    $controller->postCreateProduct();
    exit;
}

/* ════════════════════════════════════════════════════════════
   EDIT / UPDATE
   GET  /admin/products/:id/edit
   POST /admin/products/:id
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 !== '' && $seg1 === 'edit') {
    $controller->getEditProduct($seg0);
    exit;
}

if ($method === 'POST' && $seg0 !== '' && $seg1 === '') {
    ProductImageUpload::handle();
    CsrfMiddleware::verify();
    $controller->postUpdateProduct($seg0);
    exit;
}

/* ════════════════════════════════════════════════════════════
   DELETE
   POST /admin/products/:id/delete
   ════════════════════════════════════════════════════════════ */
if ($method === 'POST' && $seg0 !== '' && $seg1 === 'delete') {
    CsrfMiddleware::verify();
    $controller->postDeleteProduct($seg0);
    exit;
}

// ── Fallback: 404 ────────────────────────────────────────────
http_response_code(404);
echo 'Products route not found.';
