<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/routes/faqs.php — Admin FAQ sub-router
 *
 * Mount point: /admin/faqs  (add to admin.php: if ($seg0 === 'faqs') { ... })
 *
 * Routes:
 *   GET  /admin/faqs              → list all FAQs
 *   GET  /admin/faqs/create       → show create form
 *   POST /admin/faqs/create       → save new FAQ
 *   GET  /admin/faqs/{id}/edit    → show edit form
 *   POST /admin/faqs/{id}/edit    → save updated FAQ
 *   POST /admin/faqs/{id}/delete  → delete FAQ
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../model/Faq.php';
require_once __DIR__ . '/../../services/FaqService.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';

$faqService = new FaqService();

$seg0 = $segments[0] ?? '';   // 'create' | numeric ID | ''
$seg1 = $segments[1] ?? '';   // 'edit' | 'delete'

// ── Shared validation helper ──────────────────────────────────
$validateFaqInput = static function (array $post): array {
    $errors   = [];
    $question = trim($post['question'] ?? '');
    $answer   = trim($post['answer']   ?? '');
    $category = trim($post['category'] ?? '');
    $sort     = (int) ($post['sort_order'] ?? 0);

    if ($question === '') {
        $errors['question'] = 'Question is required.';
    } elseif (mb_strlen($question) > 500) {
        $errors['question'] = 'Question must be 500 characters or fewer.';
    }

    if ($answer === '') {
        $errors['answer'] = 'Answer is required.';
    }

    if ($category !== '' && mb_strlen($category) > 100) {
        $errors['category'] = 'Category must be 100 characters or fewer.';
    }

    return [
        'errors'   => $errors,
        'question' => $question,
        'answer'   => $answer,
        'category' => $category !== '' ? $category : null,
        'sort_order' => $sort,
    ];
};

// ════════════════════════════════════════════════════════════
// GET /admin/faqs  →  list
// ════════════════════════════════════════════════════════════
if ($method === 'GET' && $seg0 === '') {
    $faqs     = $faqService->getAll();
    $faqCount = count($faqs);

    $newMessages = 0;
    try {
        require_once __DIR__ . '/../../services/ContactMessageService.php';
        $msgService   = new \ContactMessageService();
        $statusCounts = $msgService->countByStatus();
        $newMessages = $statusCounts['New'] ?? 0;
    } catch (Throwable) {}
    $counts = ['newMessages' => $newMessages, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];

    $pageTitle  = 'FAQs';
    $activePage = 'faqs';

    ob_start();
    require __DIR__ . '/../views/faqs/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// GET  /admin/faqs/create  →  show form
// POST /admin/faqs/create  →  save new FAQ
// ════════════════════════════════════════════════════════════
if ($seg0 === 'create') {
    if ($faqService->getCount() >= 10) {
        header('Location: /admin/faqs');
        exit;
    }

    $errors = [];
    $faq    = [
        'question'   => '',
        'answer'     => '',
        'category'   => '',
        'sort_order' => $faqService->getNextSortOrder(),
    ];

    if ($method === 'POST') {
        CsrfMiddleware::verify();
        $parsed = $validateFaqInput($_POST);
        $errors = $parsed['errors'];

        $count = $faqService->getCount();

        if ($count >= 10) {
            header('Location: /admin/faqs');
            exit;
        }

        $maxOrder = $count + 1;
        if ($parsed['sort_order'] > $maxOrder) {
            $errors['sort_order'] = "Sort order cannot exceed {$maxOrder} (total FAQs + 1).";
        }

        if (empty($errors)) {
            $faqService->create($parsed);
            header('Location: /admin/faqs');
            exit;
        }

        // Re-populate form on error
        $faq = array_merge($faq, $parsed);
    }

    $newMessages = 0;
    try {
        require_once __DIR__ . '/../../services/ContactMessageService.php';
        $msgService = new \ContactMessageService();
        $newMessages = ($msgService->countByStatus())['New'] ?? 0;
    } catch (Throwable) {}
    $counts = ['newMessages' => $newMessages, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];
    $pageTitle  = 'Create FAQ';
    $activePage = 'faqs';

    ob_start();
    require __DIR__ . '/../views/faqs/form.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// GET  /admin/faqs/{id}/edit  →  show form pre-filled
// POST /admin/faqs/{id}/edit  →  save changes
// ════════════════════════════════════════════════════════════
if (ctype_digit($seg0) && $seg1 === 'edit') {
    $id  = (int) $seg0;
    $faq = $faqService->getById($id);

    if ($faq === null) {
        http_response_code(404);
        exit('FAQ not found.');
    }

    $errors = [];

    if ($method === 'POST') {
        CsrfMiddleware::verify();
        $parsed = $validateFaqInput($_POST);
        $errors = $parsed['errors'];

        $count = $faqService->getCount();
        if ($parsed['sort_order'] > $count) {
            $errors['sort_order'] = "Sort order cannot exceed {$count} (total number of FAQs).";
        }

        if (empty($errors)) {
            $faqService->update($id, $parsed);
            header('Location: /admin/faqs');
            exit;
        }

        $faq = array_merge($faq, $parsed);
    }

    $newMessages = 0;
    try {
        require_once __DIR__ . '/../../services/ContactMessageService.php';
        $msgService = new \ContactMessageService();
        $newMessages = ($msgService->countByStatus())['New'] ?? 0;
    } catch (Throwable) {}
    $counts = ['newMessages' => $newMessages, 'pendingMembers' => 0, 'pendingOrders' => 0, 'pendingGallery' => 0];
    $pageTitle  = 'Edit FAQ';
    $activePage = 'faqs';

    ob_start();
    require __DIR__ . '/../views/faqs/form.php';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layouts/admin.php';
    exit;
}

// ════════════════════════════════════════════════════════════
// POST /admin/faqs/{id}/delete  →  delete
// ════════════════════════════════════════════════════════════
if ($method === 'POST' && ctype_digit($seg0) && $seg1 === 'delete') {
    CsrfMiddleware::verify();
    $faqService->delete((int) $seg0);
    header('Location: /admin/faqs');
    exit;
}

// ── Fallback ─────────────────────────────────────────────────
http_response_code(404);
echo 'FAQ route not found.';
