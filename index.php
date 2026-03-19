<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap/DatabaseEnv.php';

$lfsRoot = __DIR__;
DatabaseEnv::load($lfsRoot . '/.env');

// Non-database settings (override via real server env as needed)
putenv('APP_ENV=development');
putenv('ADMIN_PASSWORD_HASH=$2y$12$Rlf3PkcaJd3hMctBA.FZn.tECeMJiQk25DYBnIBZIi1OlpW4o26y.'); // hash of password "password"

// Lenco payment API — get keys from https://dashboard.lenco.co (or Lenco docs)
// Set these for checkout/payments to work; leave empty to disable Lenco (shop will still load).
putenv('LENCO_API_SECRET_KEY=');        // e.g. your Lenco API secret key
putenv('LENCO_WEBHOOK_SECRET=');        // optional: for webhook signature verification

/**
 * LFS — Front Controller (project root)
 *
 * DocumentRoot should be this folder. Static assets (css/, js/, images/, uploads/) live here alongside index.php.
 *
 * - Normalises the URL when the app lives in a subfolder
 * - Dispatches to admin, shop, contact, gallery, cookies, api, or the public router
 */

// Important paths
define('APP_ROOT', __DIR__ . '/src');
define('PUBLIC_ROOT', __DIR__);

// Blog list caching (APCu) — see BlogPostService docs
if (!defined('BLOG_LIST_CACHE_TTL')) {
    define('BLOG_LIST_CACHE_TTL', 120);
}

// HTTP method
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Raw request path (without query string)
$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

// Normalise base path if app is in a subfolder (e.g. /lfs-website-php)
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
$path = '/' . ltrim($path, '/');

// Expose base path to views ('' when app is at domain root)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', ($scriptDir === '' || $scriptDir === '/') ? '' : $scriptDir);
}

require_once APP_ROOT . '/utility/helpers.php';

// Turn path into segments
$trimmed  = trim($path, '/');
$segments = $trimmed === '' ? [] : explode('/', $trimmed);
$first    = $segments[0] ?? '';

// API routing: /api/...
if ($first === 'api') {
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/admin/routes/api.php';
    exit;
}

// Admin routing: /admin/...
if ($first === 'admin') {
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/admin/routes/admin.php';
    exit;
}

// Shop routing: /shop/...
if ($first === 'shop') {
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/shop.php';
    exit;
}

// Contact routing: /contact/...
if ($first === 'contact') {
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/contact.php';
    exit;
}

// Gallery routing: /gallery/...
if ($first === 'gallery') {
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/gallery.php';
    exit;
}

// Cookies routing: /cookies/...
if ($first === 'cookies') {
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/cookies.php';
    exit;
}

// Public routing: everything else goes through the main public router
require APP_ROOT . '/routes/index.php';
