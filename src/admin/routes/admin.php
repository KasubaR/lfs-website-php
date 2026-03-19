<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/routes/admin.php — Admin master router
 *
 * Mount point: /admin  (registered in project index.php front controller)
 *
 * Expects from the front controller:
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /admin/
 *               e.g. /admin/dashboard       → ['dashboard']
 *                    /admin/gallery/albums  → ['gallery', 'albums']
 *
 * Auth guard (AuthController::requireAuth) runs first on every request.
 * The login slug (/admin/door) and /admin/logout bypass the guard.
 *
 * CSRF is verified here for standard form POSTs.
 * Multipart POST routes (events, products, gallery uploads) handle
 * CSRF themselves after their upload middleware runs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../../services/EventService.php';
require_once __DIR__ . '/../../config/AdminConfig.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../middleware/AdminRateLimiter.php';

// ── Session bootstrap ────────────────────────────────────────
// Must run before the auth guard reads $_SESSION.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Top-level segment ────────────────────────────────────────
$seg0 = $segments[0] ?? '';

// ── Auth guard ───────────────────────────────────────────────
// Passes 'door' and 'logout' through unauthenticated.
// Redirects everything else to the login page when not authenticated.
AuthController::requireAuth($seg0);

// ── CSRF token ───────────────────────────────────────────────
// Generate after the guard so that unauthenticated redirects are cheap.
CsrfMiddleware::generate();
$csrfToken = CsrfMiddleware::token();

// ── Rate limiting for admin POSTs ────────────────────────────
// Protect all authenticated admin POST routes (excluding the login slug
// which is handled above) from accidental or malicious write floods.
if ($method === 'POST' && $seg0 !== AdminConfig::LOGIN_SLUG) {
    AdminRateLimiter::enforceForPost();
}

// ── Placeholder data ─────────────────────────────────────────
// Replace with real DB queries as each module is wired up.
$defaultStats = [
    'totalMembers'   => 0,
    'newMessages' => 0,
    'upcomingEvents' => 0,
    'pendingOrders'  => 0,
    'monthlyRevenue' => 0,
    'galleryUploads' => 0,
];

$defaultAdminUser = [
    'name'  => 'Admin User',
    'email' => AdminConfig::EMAIL,
    'role'  => 'admin',
];

// ── Helper: dispatch to a sub-router ─────────────────────────
$dispatchSubRouter = function (string $file, int $skip = 1) use (&$segments, $method, $csrfToken): void {
    $segments = array_slice($segments, $skip);
    require $file;
    exit;
};

// ════════════════════════════════════════════════════════════
// AUTH ROUTES  (no login required — already bypassed above)
// ════════════════════════════════════════════════════════════

// GET  /admin/door  →  show login form
if ($method === 'GET' && $seg0 === AdminConfig::LOGIN_SLUG) {
    AuthController::showLogin();
    // showLogin() calls exit; this line is unreachable.
}

// POST /admin/door  →  process credentials
if ($method === 'POST' && $seg0 === AdminConfig::LOGIN_SLUG) {
    AuthController::login();
}

// GET  /admin/logout  →  destroy session and redirect home
if ($method === 'GET' && $seg0 === 'logout') {
    AuthController::logout();
}

// ════════════════════════════════════════════════════════════
// AUTHENTICATED ROUTES  (guard already enforced above)
// ════════════════════════════════════════════════════════════

// GET /admin  →  redirect to dashboard
if ($method === 'GET' && $seg0 === '') {
    header('Location: /admin/dashboard');
    exit;
}

// GET /admin/shop  →  redirect to products
if ($method === 'GET' && $seg0 === 'shop') {
    header('Location: /admin/products');
    exit;
}

// GET /admin/dashboard
if ($method === 'GET' && $seg0 === 'dashboard') {
    $eventService   = new EventService();
    $upcomingEvents = $eventService->getUpcomingEvents(5);

    $newMessages = 0;
    $pendingOrders  = 0;
    try {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../model/ContactMessage.php';
        require_once __DIR__ . '/../../model/Order.php';
        require_once __DIR__ . '/../../model/OrderModel.php';

        $pdo = Database::connect();

        $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'New'");
        if ($stmt) $newMessages = (int) $stmt->fetchColumn();

        // "Pending" = orders that still need admin action (awaiting payment or ready to process)
        $orderModel    = new OrderModel();
        $pendingOrders = $orderModel->countByStatus('pending_payment')
                       + $orderModel->countByStatus('paid');
    } catch (Throwable) {}

    $stats = array_merge($defaultStats, [
        'newMessages' => $newMessages,
        'upcomingEvents' => is_array($upcomingEvents) ? count($upcomingEvents) : 0,
        'pendingOrders'  => $pendingOrders,
    ]);

    $pageTitle      = 'Dashboard';
    $activePage     = 'dashboard';
    $adminUser      = $defaultAdminUser;
    $counts         = ['pendingMembers' => 0, 'pendingOrders' => $pendingOrders, 'pendingGallery' => 0, 'newMessages' => $newMessages];
    $notifications  = ['unread' => 0, 'items' => []];
    $recentActivity = [];
    try {
        require_once __DIR__ . '/../../services/ActivityService.php';
        $recentActivity = (new ActivityService())->getRecentActivity(8);
    } catch (Throwable) {
        // keep empty feed so dashboard still renders
    }
    $pendingTasks   = ['orders' => $pendingOrders, 'events' => 0, 'gallery' => 0, 'memberships' => 0];
    $chartData      = ['members' => [], 'events' => [], 'sales' => [], 'gallery' => []];

    ob_start();
    require __DIR__ . '/../views/dashboard/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// GET /admin/activity  →  View all activity feed
if ($method === 'GET' && $seg0 === 'activity') {
    $recentActivity = [];
    try {
        require_once __DIR__ . '/../../services/ActivityService.php';
        $recentActivity = (new ActivityService())->getRecentActivity(50);
    } catch (Throwable) {
        // keep empty
    }
    $pageTitle   = 'Activity';
    $activePage  = 'dashboard';
    $counts      = ['pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0, 'newMessages' => 0];
    $breadcrumbs = [['label' => 'Admin', 'url' => '/admin/dashboard'], ['label' => 'Activity']];

    ob_start();
    require __DIR__ . '/../views/activity/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// Gallery sub-router
if ($seg0 === 'gallery') {
    $dispatchSubRouter(__DIR__ . '/gallery.php');
}

// Events sub-router
if ($seg0 === 'events') {
    $dispatchSubRouter(__DIR__ . '/events.php');
}

// Products sub-router
if ($seg0 === 'products') {
    $dispatchSubRouter(__DIR__ . '/products.php');
}

// Blog sub-router
if ($seg0 === 'blog') {
    $dispatchSubRouter(__DIR__ . '/blog.php');
}

// Contact messages sub-router
if ($seg0 === 'messages') {
    $dispatchSubRouter(__DIR__ . '/messages.php');
}

// FAQs sub-router
if ($seg0 === 'faqs') {
    $dispatchSubRouter(__DIR__ . '/faqs.php');
}

// Orders sub-router
if ($seg0 === 'orders') {
    $dispatchSubRouter(__DIR__ . '/orders.php');
}

// ── Fallback: 404 ────────────────────────────────────────────
http_response_code(404);
echo 'Admin route not found.';
