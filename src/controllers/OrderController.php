<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/controllers/OrderController.php
 *
 * Handles:
 *   POST /shop/checkout/place-order   — create order + initiate Lenco payment
 *   GET  /shop/checkout/verify        — poll payment status (AJAX)
 *   POST /shop/checkout/webhook       — Lenco webhook (registered in Lenco dashboard)
 *   GET  /shop/order/:orderNumber     — order confirmation page
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/LencoService.php';
require_once __DIR__ . '/../services/LencoApiException.php';
require_once __DIR__ . '/../model/OrderModel.php';
require_once __DIR__ . '/../model/PaymentModel.php';

class OrderController
{
    private LencoService $lenco;
    private OrderModel   $orders;
    private PaymentModel $payments;

    public function __construct()
    {
        $this->lenco    = new LencoService();
        $this->orders   = new OrderModel();
        $this->payments = new PaymentModel();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /* ════════════════════════════════════════════════════════════
       PLACE ORDER — POST /shop/checkout/place-order
       Body (JSON): { customerInfo, paymentMethod, provider, customerPhone }
       ════════════════════════════════════════════════════════════ */

    public function placeOrder(): void
    {
        $body = $this->parseJsonBody();

        // ── 1. Load cart from session ──────────────────────────────
        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) {
            $this->jsonError('Your cart is empty.', 400);
            return;
        }

        // ── 2. Validate customer info ──────────────────────────────
        $customerInfo = $body['customerInfo'] ?? [];
        $errors = $this->validateCustomerInfo($customerInfo);
        if ($errors) {
            $this->jsonError(implode(' ', $errors), 400);
            return;
        }

        // ── 3. Validate payment method ─────────────────────────────
        $paymentMethod = $body['paymentMethod'] ?? '';
        $provider      = strtolower($body['provider']      ?? '');
        $customerPhone = trim($body['customerPhone'] ?? '');

        if (!in_array($paymentMethod, ['mobile_money'], true)) {
            $this->jsonError('Invalid payment method.', 400);
            return;
        }
        if ($paymentMethod === 'mobile_money') {
            if (!in_array($provider, ['airtel', 'mtn'], true)) {
                $this->jsonError('Please select a valid provider: airtel or mtn.', 400);
                return;
            }
            if (!$this->isValidPhone($customerPhone)) {
                $this->jsonError('Please enter a valid mobile money phone number (e.g. +260971234567).', 400);
                return;
            }
        }

        // ── 4. Compute totals from session cart (authoritative) ────
        $subtotal = 0.0;
        foreach ($cart as $item) {
            $subtotal += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0);
        }
        if ($subtotal <= 0) {
            $this->jsonError('Cart total must be greater than zero.', 400);
            return;
        }

        // ── 5. Create order record ─────────────────────────────────
        $orderNumber = $this->generateOrderNumber();

        try {
            $orderId = $this->orders->create([
                'orderNumber'  => $orderNumber,
                'customerName' => $customerInfo['name'],
                'customerEmail'=> $customerInfo['email'],
                'customerPhone'=> $customerInfo['phone'] ?? '',
                'notes'        => $customerInfo['notes'] ?? '',
                'items'        => $cart,
                'subtotal'     => $subtotal,
                'total'        => $subtotal,
                'status'       => 'pending_payment',
            ]);
        } catch (\Throwable $e) {
            error_log('[OrderController] Failed to create order: ' . $e->getMessage());
            $this->jsonError('Could not create your order. Please try again.', 500);
            return;
        }

        // ── 6. Initiate Lenco payment ──────────────────────────────
        $orderData = [
            'orderNumber' => $orderNumber,
            'totals'      => ['total' => $subtotal],
            'currency'    => 'ZMW',
            'country'     => 'ZM',
        ];

        try {
            $lencoResult = $this->lenco->initiateMobileMoneyPayment(
                $orderData,
                $customerPhone,
                $provider
            );
        } catch (LencoApiException $e) {
            error_log('[OrderController] Lenco initiation failed: ' . $e->getMessage());
            // Mark order as failed so it can be retried
            $this->orders->updateStatus($orderId, 'payment_failed');
            $this->jsonError(
                $e->getMessage() ?: 'Could not connect to payment provider. Please try again.',
                502
            );
            return;
        }

        // ── 7. Create payment record ───────────────────────────────
        try {
            $this->payments->create([
                'orderNumber'        => $orderNumber,
                'paymentMethod'      => 'mobile_money',
                'amount'             => $subtotal,
                'currency'           => $lencoResult['currency'] ?? 'ZMW',
                'status'             => $lencoResult['internalStatus'],
                'customerInfo'       => $customerInfo,
                'lencoTransactionId' => $lencoResult['transactionId'],
                'lencoReference'     => $lencoResult['lencoReference'],
                'lencoProvider'      => $provider,
                'lencoStatus'        => $lencoResult['status'],
                'lencoResponse'      => $lencoResult['rawResponse'] ?? [],
                'transactionId'      => $lencoResult['reference'],
                'paymentInstructions'=> $lencoResult['paymentInstructions'],
                'expiresAt'          => $lencoResult['expiresAt'],
                'metadata'           => [
                    'provider'      => $provider,
                    'customerPhone' => $customerPhone,
                ],
            ]);
        } catch (\Throwable $e) {
            // Log but don't abort — order and Lenco payment are already live
            error_log('[OrderController] Failed to save payment record: ' . $e->getMessage());
        }

        // ── 8. Clear cart ──────────────────────────────────────────
        $_SESSION['cart'] = [];

        // ── 9. Respond to frontend ─────────────────────────────────
        $this->jsonResponse([
            'ok'                  => true,
            'orderNumber'         => $orderNumber,
            'transactionId'       => $lencoResult['transactionId'],
            'reference'           => $lencoResult['reference'],
            'lencoStatus'         => $lencoResult['status'],
            'paymentInstructions' => $lencoResult['paymentInstructions'],
            'paymentUrl'          => $lencoResult['paymentUrl'],
            'expiresAt'           => $lencoResult['expiresAt'],
            'message'             => $lencoResult['paymentInstructions']
                                     ?? 'Check your phone to approve the payment.',
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       VERIFY PAYMENT — GET /shop/checkout/verify?txId=xxx
       Used by frontend polling. Returns current payment status.
       ════════════════════════════════════════════════════════════ */

    public function verifyPayment(): void
    {
        $txId = trim($_GET['txId'] ?? '');
        if ($txId === '') {
            $this->jsonError('Missing transaction ID.', 400);
            return;
        }

        // Check local DB first — if already terminal, skip Lenco call
        $payment = $this->payments->findByTransactionId($txId);
        if ($payment && in_array($payment['status'], PaymentModel::TERMINAL_STATUSES, true)) {
            $this->jsonResponse([
                'ok'          => true,
                'status'      => $payment['status'],
                'lencoStatus' => $payment['lenco_status'],
                'orderNumber' => $payment['order_number'],
            ]);
            return;
        }

        // Ask Lenco
        try {
            $reference = $payment['transaction_id'] ?? $txId;
            $result    = $this->lenco->verifyPayment($reference, true);

            // Persist status change
            if ($payment && $result['status'] !== ($payment['lenco_status'] ?? '')) {
                $extra = ['lencoStatus' => $result['status']];
                if ($result['internalStatus'] === 'completed') {
                    $extra['completedAt'] = date('Y-m-d H:i:s');
                    $this->orders->updateStatus(
                        $payment['order_number'],
                        'paid',
                        byOrderNumber: true
                    );
                }
                $this->payments->updateStatus($payment['id'], $result['internalStatus'], $extra);
            }

            $this->jsonResponse([
                'ok'          => true,
                'status'      => $result['internalStatus'],
                'lencoStatus' => $result['status'],
                'orderNumber' => $payment['order_number'] ?? null,
            ]);
        } catch (LencoApiException $e) {
            // Return cached DB state on API failure
            $this->jsonResponse([
                'ok'      => false,
                'status'  => $payment['status'] ?? 'pending',
                'message' => 'Could not reach payment provider.',
            ], 503);
        }
    }

    /* ════════════════════════════════════════════════════════════
       WEBHOOK — POST /shop/checkout/webhook
       Lenco POSTs payment status updates here.
       Register this URL in the Lenco dashboard.
       ════════════════════════════════════════════════════════════ */

    public function handleWebhook(): void
    {
        // Always respond 200 so Lenco doesn't retry on our processing errors
        header('Content-Type: application/json');
        http_response_code(200);

        $rawBody = file_get_contents('php://input');
        if ($rawBody === '' || $rawBody === false) {
            echo json_encode(['ok' => false, 'message' => 'Empty body']);
            return;
        }

        // Normalise headers to lowercase
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }

        if (!$this->lenco->verifyWebhookSignature($rawBody, $headers)) {
            error_log('[OrderController] Webhook signature failed');
            echo json_encode(['ok' => false, 'message' => 'Invalid signature']);
            return;
        }

        $rawPayload = json_decode($rawBody, true);
        if (!is_array($rawPayload)) {
            echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
            return;
        }

        try {
            $this->processWebhookPayload($rawPayload);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[OrderController] Webhook processing error: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'message' => 'Internal error']);
        }
    }

    /* ════════════════════════════════════════════════════════════
       ORDER CONFIRMATION — GET /shop/order/:orderNumber
       ════════════════════════════════════════════════════════════ */

    public function getOrderConfirmation(string $orderNumber): void
    {
        $order = $this->orders->findByOrderNumber($orderNumber);
        if (!$order) {
            http_response_code(404);
            echo 'Order not found.';
            return;
        }

        $cartCount = 0; // Cart cleared after order
        $title       = 'Order Confirmed — LFS Shop';
        $description = 'Your LFS order has been placed successfully.';
        $bodyClass   = 'page-no-hero';

        ob_start();
        require __DIR__ . '/../../src/views/pages/order-confirmation.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE — WEBHOOK PROCESSING
       ════════════════════════════════════════════════════════════ */

    private function processWebhookPayload(array $rawPayload): void
    {
        $data = $this->lenco->parseWebhookPayload($rawPayload);

        error_log('[OrderController] Webhook received: txId=' . ($data['transactionId'] ?? 'unknown')
            . ' status=' . ($data['status'] ?? 'unknown'));

        // Find payment
        $payment = null;
        if ($data['transactionId']) {
            $payment = $this->payments->findByTransactionId($data['transactionId']);
        }
        if (!$payment && $data['lencoReference']) {
            $payment = $this->payments->findByLencoReference($data['lencoReference']);
        }
        if (!$payment && $data['reference']) {
            $payment = $this->payments->findByTransactionId($data['reference']);
        }

        if (!$payment) {
            error_log('[OrderController] Webhook: no payment found for txId=' . ($data['transactionId'] ?? ''));
            return;
        }

        // Idempotency — skip if already terminal
        if (in_array($payment['status'], PaymentModel::TERMINAL_STATUSES, true)) {
            return;
        }

        $internalStatus = $this->lenco->mapLencoStatus($data['status'] ?? 'pending');
        $extra = [
            'lencoStatus'       => $data['status'],
            'webhookReceived'   => 1,
            'webhookPayload'    => $rawPayload,
            'webhookReceivedAt' => date('Y-m-d H:i:s'),
        ];

        if ($internalStatus === 'completed') {
            $extra['completedAt'] = $data['completedAt'] ?? date('Y-m-d H:i:s');
        }
        if ($internalStatus === 'failed') {
            $extra['failureReason'] = $data['failureReason'];
            $extra['failedAt']      = $data['failedAt'] ?? date('Y-m-d H:i:s');
        }

        $this->payments->updateStatus($payment['id'], $internalStatus, $extra);

        // Update order status to match
        if ($internalStatus === 'completed') {
            $this->orders->updateStatus($payment['order_number'], 'paid', byOrderNumber: true);
        } elseif ($internalStatus === 'failed') {
            $this->orders->updateStatus($payment['order_number'], 'payment_failed', byOrderNumber: true);
        }
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE — HELPERS
       ════════════════════════════════════════════════════════════ */

    private function validateCustomerInfo(array $info): array
    {
        $errors = [];
        if (empty(trim($info['name'] ?? ''))) {
            $errors[] = 'Full name is required.';
        }
        if (empty($info['email']) || !filter_var($info['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        return $errors;
    }

    private function isValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/[\s\-()]/', '', $phone));
    }

    private function generateOrderNumber(): string
    {
        // Format: LFS-YYYYMMDD-XXXXX (e.g. LFS-20250601-A3F7C)
        return 'LFS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }

    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function jsonError(string $message, int $status = 400): void
    {
        $this->jsonResponse(['ok' => false, 'message' => $message], $status);
    }
}
