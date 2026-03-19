<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/routes/messages.php — Admin contact messages sub-router
 *
 * Mount point: /admin/messages  (add to admin.php: if ($seg0 === 'messages') { ... })
 *
 * Routes:
 *   GET  /admin/messages              → list all messages (paginated)
 *   GET  /admin/messages/{id}         → view a single message (marks as Read)
 *   GET  /admin/messages/{id}/reply   → reply form
 *   POST /admin/messages/{id}/reply   → save reply, send email, set Responded
 *   POST /admin/messages/{id}/status  → update status (Read / Responded)
 *   POST /admin/messages/{id}/delete  → delete a message
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../model/ContactMessage.php';
require_once __DIR__ . '/../../services/ContactMessageService.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../../utility/InputSanitizer.php';

$service = new ContactMessageService();
const MAX_REPLY_CHARS = 5000;

$seg0 = $segments[0] ?? '';           // message ID (or empty → list)
$seg1 = $segments[1] ?? '';           // 'status' | 'delete'
$isMessageId = (bool) preg_match('/^(?:\d+|[a-f0-9-]{36})$/i', $seg0);

/**
 * Send a contact-reply email to a message sender.
 * Returns true on successful delivery handoff to the local MTA.
 */
function sendContactReplyEmail(array $message, string $replyText): bool
{
    $toRaw = trim((string)($message['email'] ?? ''));
    $to    = filter_var($toRaw, FILTER_VALIDATE_EMAIL);
    if ($to === false) {
        return false;
    }

    $name          = trim((string)($message['name'] ?? 'there'));
    $originalSubj  = trim((string)($message['subject'] ?? ''));
    $originalBody  = trim((string)($message['message'] ?? ''));
    $subject       = 'Re: ' . ($originalSubj !== '' ? $originalSubj : 'Your message to Lusaka Fitness Squad');

    $body  = "Hello {$name},\n\n";
    $body .= "Thank you for contacting Lusaka Fitness Squad.\n\n";
    $body .= "Admin reply:\n";
    $body .= $replyText . "\n\n";
    $body .= "------------------------------\n";
    $body .= "Your original message:\n";
    $body .= ($originalBody !== '' ? $originalBody : 'N/A') . "\n\n";
    $body .= "Kind regards,\n";
    $body .= "Lusaka Fitness Squad\n";

    $headers  = "From: noreply@lusakafitnesssquad.com\r\n";
    $headers .= "Reply-To: noreply@lusakafitnesssquad.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($to, $subject, $body, $headers);
}

// ════════════════════════════════════════════════════════════
// GET /admin/messages  →  paginated list
// ════════════════════════════════════════════════════════════
if ($method === 'GET' && $seg0 === '') {
    $flash        = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);

    $messages    = $service->getAll();
    $statusCounts = $service->countByStatus();
    $unread      = $statusCounts['New'] ?? 0;
    $counts      = ['newMessages' => $unread, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];

    $pageTitle  = 'Contact Messages';
    $activePage = 'messages';

    ob_start();
    require __DIR__ . '/../views/messages/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// GET /admin/messages/{id}  →  view a single message
// ════════════════════════════════════════════════════════════
if ($method === 'GET' && $isMessageId && $seg1 === '') {
    $id      = $seg0;
    $message = $service->getById($id);

    if ($message === null) {
        http_response_code(404);
        exit('Message not found.');
    }

    // Auto-advance status from New → Read
    if ($message['status'] === 'New') {
        $service->updateStatus($id, 'Read');
        $message['status'] = 'Read';
    }

    $statusCounts = $service->countByStatus();
    try {
        $replies = $service->getRepliesByMessageId($id);
    } catch (\Throwable) {
        $replies = [];
    }
    $counts       = ['newMessages' => $statusCounts['New'] ?? 0, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];
    $pageTitle    = 'View Message';
    $activePage   = 'messages';

    ob_start();
    require __DIR__ . '/../views/messages/show.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// POST /admin/messages/{id}/status  →  update status
// ════════════════════════════════════════════════════════════
if ($method === 'POST' && $isMessageId && $seg1 === 'status') {
    CsrfMiddleware::verify();

    $id     = $seg0;
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ContactMessage::STATUS, true)) {
        http_response_code(422);
        exit('Invalid status value.');
    }

    $service->updateStatus($id, $status);
    header('Location: /admin/messages');
    exit;
}

// ════════════════════════════════════════════════════════════
// POST /admin/messages/{id}/delete  →  delete a message
// ════════════════════════════════════════════════════════════
if ($method === 'POST' && $isMessageId && $seg1 === 'delete') {
    CsrfMiddleware::verify();

    $service->delete($seg0);
    header('Location: /admin/messages');
    exit;
}

// ════════════════════════════════════════════════════════════
// GET /admin/messages/{id}/reply  →  reply form
// ════════════════════════════════════════════════════════════
if ($method === 'GET' && $isMessageId && $seg1 === 'reply') {
    $id      = $seg0;
    $message = $service->getById($id);

    if ($message === null) {
        http_response_code(404);
        exit('Message not found.');
    }

    $statusCounts = $service->countByStatus();
    $counts       = ['newMessages' => $statusCounts['New'] ?? 0, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];
    $pageTitle    = 'Reply to Message';
    $activePage   = 'messages';
    $flash        = [];

    ob_start();
    require __DIR__ . '/../views/messages/reply.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// POST /admin/messages/{id}/reply  →  save reply + send email
// ════════════════════════════════════════════════════════════
if ($method === 'POST' && $isMessageId && $seg1 === 'reply') {
    CsrfMiddleware::verify();

    $id      = $seg0;
    $message = $service->getById($id);

    if ($message === null) {
        http_response_code(404);
        exit('Message not found.');
    }

    $replyText = InputSanitizer::textarea($_POST['reply_message'] ?? '', MAX_REPLY_CHARS);

    if ($replyText === '') {
        // Re-render form with validation error
        $statusCounts = $service->countByStatus();
        $counts       = ['newMessages' => $statusCounts['New'] ?? 0, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];
        $pageTitle    = 'Reply to Message';
        $activePage   = 'messages';
        $flash        = ['error' => 'Reply message cannot be empty.'];

        ob_start();
        require __DIR__ . '/../views/messages/reply.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
        exit;
    }

    $replyLength = function_exists('mb_strlen')
        ? mb_strlen($replyText, 'UTF-8')
        : strlen($replyText);
    if ($replyLength > MAX_REPLY_CHARS) {
        $statusCounts = $service->countByStatus();
        $counts       = ['newMessages' => $statusCounts['New'] ?? 0, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];
        $pageTitle    = 'Reply to Message';
        $activePage   = 'messages';
        $flash        = ['error' => 'Reply is too long. Maximum allowed length is ' . MAX_REPLY_CHARS . ' characters.'];

        ob_start();
        require __DIR__ . '/../views/messages/reply.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
        exit;
    }

    try {
        // 1) Persist reply first.
        $service->createReply($id, $replyText);
    } catch (\Throwable $e) {
        error_log('[LFS Admin Messages] Reply save failed for message id=' . $id . '; reason=' . $e->getMessage());
        $_SESSION['admin_flash'] = ['error' => 'Reply could not be saved: ' . $e->getMessage()];
        header('Location: /admin/messages');
        exit;
    }

    // 2) Send email via helper.
    $mailSent = sendContactReplyEmail($message, $replyText);
    if (!$mailSent) {
        // Keep saved reply, but do NOT mark as Responded when send fails.
        error_log('[LFS Admin Messages] Reply mail delivery failed for message id=' . $id);
        $_SESSION['admin_flash'] = ['warning' => 'Reply was saved, but email delivery failed. Status was not changed.'];
        header('Location: /admin/messages');
        exit;
    }

    // 3) Mark message as responded only after successful send.
    try {
        $service->updateStatus($id, 'Responded');
        $_SESSION['admin_flash'] = ['success' => 'Reply sent and message marked as Responded.'];
    } catch (\Throwable $e) {
        error_log('[LFS Admin Messages] Reply status update failed for message id=' . $id . '; reason=' . $e->getMessage());
        $_SESSION['admin_flash'] = ['warning' => 'Reply emailed successfully, but status update failed.'];
    }

    header('Location: /admin/messages');
    exit;
}

// ── Fallback ─────────────────────────────────────────────────
http_response_code(404);
echo 'Messages route not found.';
