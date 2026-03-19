<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/routes/api.php — Admin JSON API
 *
 * Mount point: /api  (dispatched from public/index.php)
 * Segments on entry: everything after /api/
 *   e.g. /api/admin/stats     → ['admin', 'stats']
 *        /api/admin/activity  → ['admin', 'activity']
 *
 * All endpoints:
 *   - Require an active admin session (same check as AuthController).
 *   - Return JSON with Content-Type: application/json.
 *   - Never render HTML.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/AdminConfig.php';

// ── Session ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth guard ────────────────────────────────────────────────
// Mirror the check in AuthController::requireAuth() without loading
// the full controller (avoids view dependencies).
if (empty($_SESSION[AdminConfig::SESSION_AUTH_KEY])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Unauthenticated.']);
    exit;
}

// ── Routing helpers ───────────────────────────────────────────
$seg0 = $segments[0] ?? '';   // 'admin'
$seg1 = $segments[1] ?? '';   // 'stats' | 'activity' | …

/** Emit JSON and stop. */
function api_json(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Only /api/admin/* is defined so far.
if ($seg0 !== 'admin') {
    api_json(['ok' => false, 'message' => 'Not found.'], 404);
}

// ════════════════════════════════════════════════════════════
// GET /api/admin/stats
// Returns live metric counts for the dashboard stat cards.
// ════════════════════════════════════════════════════════════
if ($seg1 === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {

    $pendingOrders  = 0;
    $newMessages = 0;
    $upcomingEvents = 0;

    try {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../model/Order.php';
        require_once __DIR__ . '/../../model/OrderModel.php';
        require_once __DIR__ . '/../../services/EventService.php';

        $pdo = Database::connect();

        // Unread contact messages
        $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'New'");
        if ($stmt) $newMessages = (int) $stmt->fetchColumn();

        // Pending orders = still need admin action
        $orderModel    = new OrderModel();
        $pendingOrders = $orderModel->countByStatus('pending_payment')
                       + $orderModel->countByStatus('paid');

        // Upcoming events
        $eventService   = new EventService();
        $upcomingList   = $eventService->getUpcomingEvents(100);
        $upcomingEvents = is_array($upcomingList) ? count($upcomingList) : 0;

    } catch (Throwable) {}

    api_json([
        'ok'             => true,
        'pendingOrders'  => $pendingOrders,
        'newMessages' => $newMessages,
        'upcomingEvents' => $upcomingEvents,
        'totalMembers'   => 0,   // not yet tracked
        'monthlyRevenue' => 0,   // not yet tracked
        'galleryUploads' => 0,   // not yet tracked
    ]);
}

// ════════════════════════════════════════════════════════════
// GET /api/admin/activity?limit=N
// Returns recent activity items for the dashboard feed.
// ════════════════════════════════════════════════════════════
if ($seg1 === 'activity' && $_SERVER['REQUEST_METHOD'] === 'GET') {

    $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
    $items = [];

    try {
        require_once __DIR__ . '/../../services/ActivityService.php';
        $items = (new ActivityService())->getRecentActivity($limit);
    } catch (Throwable) {}

    api_json(['ok' => true, 'items' => $items]);
}

// ── Fallback ─────────────────────────────────────────────────
api_json(['ok' => false, 'message' => 'Not found.'], 404);
