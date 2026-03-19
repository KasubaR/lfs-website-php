<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/routes/cookies.php — Cookie consent & preferences
 *
 * Mount point: /cookies  (dispatched from the front router)
 *
 * Expects from front router:
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /cookies/
 *               e.g. /cookies/consent  → ['consent']
 *
 * Routes:
 *   POST /cookies/consent   → save consent decision
 *   POST /cookies/prefs     → save UI preferences
 *   POST /cookies/withdraw  → clear all consent cookies
 *   GET  /cookies/status    → return current state (JSON)
 *
 * Rate limiting is handled at the web server level (nginx / .htaccess)
 * or via a lightweight PHP rate-limit helper if needed.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/controllers/CookieController.php';

$controller = new CookieController();
$seg0       = $segments[0] ?? '';

// POST /cookies/consent
if ($method === 'POST' && $seg0 === 'consent') {
    $controller->saveConsent();
    exit;
}

// POST /cookies/prefs
if ($method === 'POST' && $seg0 === 'prefs') {
    $controller->savePreferences();
    exit;
}

// POST /cookies/withdraw
if ($method === 'POST' && $seg0 === 'withdraw') {
    $controller->withdrawConsent();
    exit;
}

// GET /cookies/status  (JSON API)
if ($method === 'GET' && $seg0 === 'status') {
    $controller->getStatus();
    exit;
}

// ── Fallback: 404 ────────────────────────────────────────────
http_response_code(404);
echo 'Cookie route not found.';
