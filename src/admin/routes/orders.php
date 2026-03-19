<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/routes/orders.php — Admin orders sub-router
 *
 * Mount point: /admin/orders  (dispatched from admin.php)
 *
 * Routes:
 *   GET  /admin/orders              → paginated list (filter by status)
 *   GET  /admin/orders/{id}         → order detail + payment info
 *   POST /admin/orders/{id}/status  → update order status
 */

declare(strict_types=1);

require_once __DIR__ . '/../../model/Order.php';
require_once __DIR__ . '/../../model/OrderModel.php';
require_once __DIR__ . '/../../model/PaymentModel.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';

$orderModel   = new OrderModel();
$paymentModel = new PaymentModel();

$seg0 = $segments[0] ?? '';    // '' | numeric order id
$seg1 = $segments[1] ?? '';    // 'status' | ''

// ── Shared helper ────────────────────────────────────────────
// "Pending" for badge/counts = orders that still need admin action.
$pendingOrdersCount = function () use ($orderModel): int {
    return $orderModel->countByStatus('pending_payment')
         + $orderModel->countByStatus('paid');
};

// ════════════════════════════════════════════════════════════
// GET /admin/orders  →  paginated list
// ════════════════════════════════════════════════════════════
if ($method === 'GET' && $seg0 === '') {
    $perPage = 50;
    $page    = max(1, (int)($_GET['page']   ?? 1));
    $status  = $_GET['status'] ?? '';

    $opts = ['limit' => $perPage, 'offset' => ($page - 1) * $perPage];
    if ($status !== '') $opts['status'] = $status;

    $orderList = $orderModel->getAll($opts);
    $total     = $orderModel->countByStatus($status !== '' ? $status : null);
    $pages     = (int) ceil($total / $perPage);

    // Per-status counts for the tab badges
    $statusCounts = [];
    foreach (Order::ORDER_STATUS as $s) {
        $statusCounts[$s] = $orderModel->countByStatus($s);
    }

    $counts = [
        'pendingOrders'  => $pendingOrdersCount(),
        'newMessages' => 0,
        'pendingMembers' => 0,
        'pendingGallery' => 0,
    ];

    $pageTitle   = 'Orders';
    $activePage  = 'orders';
    $breadcrumbs = [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Orders']];
    $filters     = ['status' => $status];
    $formatPrice = fn ($v) => 'ZMW ' . number_format((float)$v, 2);

    ob_start();
    require __DIR__ . '/../views/orders/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// GET /admin/orders/{id}  →  order detail
// ════════════════════════════════════════════════════════════
if ($method === 'GET' && ctype_digit($seg0) && $seg1 === '') {
    $order = $orderModel->findById((int) $seg0);

    if ($order === null) {
        http_response_code(404);
        exit('Order not found.');
    }

    // Latest payment attempt for this order (may be null if none yet)
    $payment = $paymentModel->findByOrderNumber($order['order_number']);

    $counts = [
        'pendingOrders'  => $pendingOrdersCount(),
        'newMessages' => 0,
        'pendingMembers' => 0,
        'pendingGallery' => 0,
    ];

    $pageTitle   = 'Order ' . $order['order_number'];
    $activePage  = 'orders';
    $breadcrumbs = [
        ['label' => 'Admin',  'url' => '/admin'],
        ['label' => 'Orders', 'url' => '/admin/orders'],
        ['label' => $order['order_number']],
    ];
    $formatPrice = fn ($v) => 'ZMW ' . number_format((float)$v, 2);

    ob_start();
    require __DIR__ . '/../views/orders/show.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// POST /admin/orders/{id}/status  →  update status
// ════════════════════════════════════════════════════════════
if ($method === 'POST' && ctype_digit($seg0) && $seg1 === 'status') {
    CsrfMiddleware::verify();

    $id        = (int) $seg0;
    $newStatus = $_POST['status'] ?? '';

    if (!in_array($newStatus, Order::ORDER_STATUS, true)) {
        http_response_code(422);
        exit('Invalid status value.');
    }

    $orderModel->updateStatus($id, $newStatus);
    header('Location: /admin/orders/' . $id);
    exit;
}

// ── Fallback ─────────────────────────────────────────────────
http_response_code(404);
echo 'Orders route not found.';
