<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/routes/contact.php — Contact page router
 *
 * Mount point: /contact  (registered in public/index.php)
 *
 * Routes:
 *   GET  /contact        → render the contact page (with FAQs)
 *   POST /contact        → validate, save message, redirect with success flag
 *
 * Expects from the front controller:
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /contact/
 *
 * Security:
 *   - CSRF token verified on POST via CsrfMiddleware::verify()
 *   - All user input sanitised / validated before DB write
 *   - POST/Redirect/GET pattern prevents double-submit on reload
 */

declare(strict_types=1);

// ── Bootstrap: shared services ───────────────────────────────
require_once APP_ROOT . '/config/Database.php';
require_once APP_ROOT . '/middleware/CsrfMiddleware.php';
require_once APP_ROOT . '/model/ContactMessage.php';
require_once APP_ROOT . '/model/Faq.php';
require_once APP_ROOT . '/services/ContactMessageService.php';
require_once APP_ROOT . '/services/FaqService.php';
require_once APP_ROOT . '/utility/InputSanitizer.php';

// ── Session (needed for CSRF token + flash messages) ─────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CSRF token (available to GET view) ───────────────────────
CsrfMiddleware::generate();
$csrfToken = CsrfMiddleware::token();

// ── Flash message from a previous POST/redirect ──────────────
$submitted   = false;
$flashErrors = [];

if (isset($_SESSION['contact_success'])) {
    $submitted = true;
    unset($_SESSION['contact_success']);
}

if (isset($_SESSION['contact_errors'])) {
    $flashErrors = $_SESSION['contact_errors'];
    unset($_SESSION['contact_errors']);
}

// ════════════════════════════════════════════════════════════
// POST /contact — process form submission
// ════════════════════════════════════════════════════════════
if ($method === 'POST') {

    // ── 0. Rate limit — max 5 submissions per IP per 10 minutes ──
    $rateLimitKey = 'contact_submit_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $attempts     = $_SESSION[$rateLimitKey] ?? ['count' => 0, 'since' => time()];

    if (time() - $attempts['since'] > 600) {
        $attempts = ['count' => 0, 'since' => time()];
    }

    if ($attempts['count'] >= 5) {
        http_response_code(429);
        $_SESSION['contact_errors'] = ['_general' => 'Too many submissions. Please wait a few minutes before trying again.'];
        header('Location: /contact#contact');
        exit;
    }

    $attempts['count']++;
    $_SESSION[$rateLimitKey] = $attempts;

    // ── 1. CSRF ───────────────────────────────────────────────
    try {
        CsrfMiddleware::verify();
    } catch (RuntimeException $e) {
        http_response_code(403);
        exit('403 Forbidden – CSRF token mismatch. Please go back and try again.');
    }

    // ── 2. Collect & sanitise ─────────────────────────────────
    $firstName = InputSanitizer::text($_POST['firstName'] ?? '', 60);
    $lastName  = InputSanitizer::text($_POST['lastName']  ?? '', 60);
    $email     = InputSanitizer::email($_POST['email'] ?? '');
    $phone     = InputSanitizer::phone($_POST['phone'] ?? '');
    $satellite = InputSanitizer::text($_POST['satellite'] ?? '', 60);
    $message   = InputSanitizer::textarea($_POST['message'] ?? '', 5000);

    // ── 3. Validate ───────────────────────────────────────────
    $errors = [];

    if ($firstName === '') {
        $errors['firstName'] = 'First name is required.';
    } elseif (mb_strlen($firstName) > 60) {
        $errors['firstName'] = 'First name must be 60 characters or fewer.';
    }

    if ($lastName === '') {
        $errors['lastName'] = 'Last name is required.';
    } elseif (mb_strlen($lastName) > 60) {
        $errors['lastName'] = 'Last name must be 60 characters or fewer.';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 254) {
        $errors['email'] = 'Email address is too long.';
    }

    if ($phone !== '' && !preg_match('/\d{6,}/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    } elseif ($phone !== '' && mb_strlen($phone) > 30) {
        $errors['phone'] = 'Phone number must be 30 characters or fewer.';
    }

    $allowedSatellites = ['', 'arcades', 'avondale', 'chamba-valley', 'woodies', 'north-side', 'south-side'];
    if (!in_array($satellite, $allowedSatellites, true)) {
        $errors['satellite'] = 'Please select a valid satellite.';
    }

    if ($message === '') {
        $errors['message'] = 'Message is required.';
    } elseif (mb_strlen($message) > 5000) {
        $errors['message'] = 'Message must be 5,000 characters or fewer.';
    }

    // ── 4a. Errors → flash + redirect back ───────────────────
    if (!empty($errors)) {
        $_SESSION['contact_errors'] = $errors;
        // Preserve submitted values so the form can be re-populated
        $_SESSION['contact_old'] = compact('firstName', 'lastName', 'email', 'phone', 'satellite', 'message');
        header('Location: /contact#contact');
        exit;
    }

    // ── 4b. Persist to DB ─────────────────────────────────────
    try {
        $service = new ContactMessageService();
        $service->create([
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'email'     => $email,
            'phone'     => $phone     !== '' ? $phone     : null,
            'satellite' => $satellite !== '' ? $satellite : null,
            'message'   => $message,
        ]);
    } catch (Throwable $e) {
        // Log internally; show a generic error to the user.
        error_log('[ContactMessageService] ' . $e->getMessage());
        $_SESSION['contact_errors'] = ['_general' => 'Sorry, there was a problem sending your message. Please try again later.'];
        header('Location: /contact#contact');
        exit;
    }

    // ── 5. PRG — redirect with success flag ───────────────────
    // Rotate the CSRF token so the now-used token cannot be replayed.
    CsrfMiddleware::regenerate();
    $_SESSION['contact_success'] = true;
    header('Location: /contact#contact');
    exit;
}

// ════════════════════════════════════════════════════════════
// GET /contact — render the page
// ════════════════════════════════════════════════════════════

// ── Load FAQs from DB (fallback to empty array on failure) ────
$faqs = [];
try {
    $faqService = new FaqService();
    $faqs = $faqService->getAll();
} catch (Throwable $e) {
    error_log('[FaqService] ' . $e->getMessage());
    // $faqs stays [] — view will render gracefully
}

// ── Restore previously submitted values (after a failed POST) ─
$old = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_old']);

// ── Errors passed back from failed POST ──────────────────────
$errors = $flashErrors; // already unset from session above

// ── Render ────────────────────────────────────────────────────
$pageTitle    = 'Contact Us';
$activePage   = 'contact';
$bodyClass    = 'page-contact';

ob_start();
require APP_ROOT . '/views/pages/contact-us.php';
$content = ob_get_clean();
require APP_ROOT . '/views/layouts/main.php';
exit;
