<?php
declare(strict_types=1);

$lfsRoot = dirname(__DIR__);
require_once $lfsRoot . '/src/bootstrap/DatabaseEnv.php';
DatabaseEnv::load($lfsRoot . '/.env');

// Non-database settings (keep out of .env if you prefer; override via real server env as needed)
putenv('APP_ENV=development');
putenv('ADMIN_PASSWORD_HASH=$2y$12$Rlf3PkcaJd3hMctBA.FZn.tECeMJiQk25DYBnIBZIi1OlpW4o26y.');

// Lenco payment API — get keys from https://dashboard.lenco.co (or Lenco docs)
// Set these for checkout/payments to work; leave empty to disable Lenco (shop will still load).
putenv('LENCO_API_SECRET_KEY=');        // e.g. your Lenco API secret key
putenv('LENCO_WEBHOOK_SECRET=');        // optional: for webhook signature verification

/**
 * LFS — Front Controller (public/index.php)
 *
 * When Apache's DocumentRoot is set to the public/ folder, this file
 * becomes the main entry point for all requests.
 */

// Important paths
define('APP_ROOT', dirname(__DIR__) . '/src');
define('PUBLIC_ROOT', __DIR__);

// Blog list caching (APCu) — see BlogPostService docs
// Cache unfiltered blog lists for 2 minutes to reduce DB load.
// Set to 0 or omit to disable in higher-churn environments.
if (!defined('BLOG_LIST_CACHE_TTL')) {
    define('BLOG_LIST_CACHE_TTL', 120);
}

// HTTP method
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Raw request path (without query string)
$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

// Turn path into segments (no subfolder trimming needed when public/ is DocumentRoot)
$trimmed  = trim($path, '/');
$segments = $trimmed === '' ? [] : explode('/', $trimmed);
$first    = $segments[0] ?? '';

// Expose base path to views ('' when app is at domain root)
if (!defined('BASE_PATH')) {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    define('BASE_PATH', ($scriptDir === '' || $scriptDir === '/') ? '' : $scriptDir);
}

// API routing: /api/...
if ($first === 'api') {
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/admin/routes/api.php';
    exit;
}

// Admin routing: /admin/...
if ($first === 'admin') {
    // Pass everything after "admin" to the admin router
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/admin/routes/admin.php';
    exit;
}

// Shop routing: /shop/...
if ($first === 'shop') {
    // Pass everything after "shop" to the shop router
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/shop.php';
    exit;
}

// Contact routing: /contact/...
if ($first === 'contact') {
    // Pass everything after "contact" to the contact router
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/contact.php';
    exit;
}

// Gallery routing: /gallery/...
if ($first === 'gallery') {
    // Pass everything after "gallery" to the gallery router
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/gallery.php';
    exit;
}

// Cookies routing: /cookies/...
if ($first === 'cookies') {
    // Pass everything after "cookies" to the cookies router
    $segments = array_slice($segments, 1);
    require APP_ROOT . '/routes/cookies.php';
    exit;
}

// Public routing: everything else goes through the main public router
require APP_ROOT . '/routes/index.php';

