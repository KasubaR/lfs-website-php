<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap/DatabaseEnv.php';
DatabaseEnv::load(__DIR__ . '/.env');

// Non-database settings (override via real server env as needed)
putenv('APP_ENV=development');
putenv('ADMIN_PASSWORD_HASH=$2y$12$Rlf3PkcaJd3hMctBA.FZn.tECeMJiQk25DYBnIBZIi1OlpW4o26y.');//hash of password "password"    

/**
 * LFS — Front Controller
 *
 * Main entry point for the PHP app.
 * - Figures out the current path and HTTP method
 * - Normalises the base path (when the project is in a subfolder)
 * - Dispatches to either the public router or the admin router
 */

// Important paths
define('APP_ROOT', __DIR__ . '/src');
define('PUBLIC_ROOT', __DIR__ . '/public');

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

// Turn path into segments
$trimmed  = trim($path, '/');
$segments = $trimmed === '' ? [] : explode('/', $trimmed);
$first    = $segments[0] ?? '';

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

