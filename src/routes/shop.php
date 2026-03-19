<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/routes/shop.php  — UPDATED VERSION
 *
 * Add these routes below your existing ones.
 * The full file is shown; changed/added sections are marked with ← NEW
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/controllers/ShopController.php';
require_once __DIR__ . '/../../src/controllers/OrderController.php';   // ← NEW
require_once __DIR__ . '/../../src/middleware/CsrfMiddleware.php';

// Ensure CSRF token exists for cart/checkout POSTs (and cookie for AJAX)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
CsrfMiddleware::generate();

$controller      = new ShopController();
$orderController = new OrderController();                               // ← NEW
$seg0            = $segments[0] ?? '';
$seg1            = $segments[1] ?? '';
$seg2            = $segments[2] ?? '';                                  // ← NEW: needed for /checkout/verify etc.

/* ════════════════════════════════════════════════════════════
   EXISTING ROUTES (unchanged)
   ════════════════════════════════════════════════════════════ */

// GET /shop
if ($method === 'GET' && $seg0 === '') {
    $controller->getShop();
    exit;
}

// GET /shop/cart
if ($method === 'GET' && $seg0 === 'cart' && $seg1 === '') {
    $controller->getCart();
    exit;
}

// GET /shop/checkout  ← existing page render
if ($method === 'GET' && $seg0 === 'checkout' && $seg1 === '') {
    $controller->getCheckout();
    exit;
}

// GET /shop/product/:slug
if ($method === 'GET' && $seg0 === 'product' && $seg1 !== '') {
    $controller->getProduct($seg1);
    exit;
}

// POST /shop/cart/add
if ($method === 'POST' && $seg0 === 'cart' && $seg1 === 'add') {
    CsrfMiddleware::verify();
    $controller->addToCart();
    exit;
}

// POST /shop/cart/update
if ($method === 'POST' && $seg0 === 'cart' && $seg1 === 'update') {
    CsrfMiddleware::verify();
    $controller->updateCart();
    exit;
}

// POST /shop/cart/remove
if ($method === 'POST' && $seg0 === 'cart' && $seg1 === 'remove') {
    CsrfMiddleware::verify();
    $controller->removeFromCart();
    exit;
}

/* ════════════════════════════════════════════════════════════
   ← NEW: PAYMENT ROUTES
   ════════════════════════════════════════════════════════════ */

// POST /shop/checkout/place-order  — create order + initiate Lenco payment
if ($method === 'POST' && $seg0 === 'checkout' && $seg1 === 'place-order') {
    CsrfMiddleware::verify();
    $orderController->placeOrder();
    exit;
}

// GET /shop/checkout/verify?txId=xxx  — AJAX status poll
if ($method === 'GET' && $seg0 === 'checkout' && $seg1 === 'verify') {
    $orderController->verifyPayment();
    exit;
}

// POST /shop/checkout/webhook  — Lenco payment notification
// Note: NO CSRF check — Lenco doesn't send our CSRF token.
//       Security is provided by HMAC-SHA256 signature verification inside the controller.
if ($method === 'POST' && $seg0 === 'checkout' && $seg1 === 'webhook') {
    $orderController->handleWebhook();
    exit;
}

// GET /shop/order/:orderNumber  — order confirmation page
if ($method === 'GET' && $seg0 === 'order' && $seg1 !== '') {
    $orderController->getOrderConfirmation($seg1);
    exit;
}

/* ════════════════════════════════════════════════════════════
   FALLBACK 404
   ════════════════════════════════════════════════════════════ */
http_response_code(404);
echo 'Shop route not found.';
