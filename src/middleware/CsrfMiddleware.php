<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/middleware/CsrfMiddleware.php — CSRF protection
 *
 * Strategy: double-submit cookie
 *   generate()  — mints a token, sets the lfs_csrf cookie, and stores
 *                 the token in $_SESSION['csrf_token'] for views.
 *   verify()    — on state-changing requests (POST/PUT/PATCH/DELETE)
 *                 checks that the submitted token matches the session token.
 *   verifyHeader() — same check but only looks at the X-CSRF-Token header
 *                    (for AJAX/fetch endpoints that don't have a form body).
 *
 * Token submission:
 *   HTML forms  → <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
 *   fetch/XHR   → X-CSRF-Token request header
 *
 * Front router bootstrap order:
 *   1. session_start()
 *   2. CookieMiddleware::attachLocals()
 *   3. CsrfMiddleware::generate()     ← sets cookie + session token
 *   … routes …
 *
 * Admin routes:
 *   CsrfMiddleware::verify()          ← call before controller on POST routes
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/CookieConfig.php';

class CsrfMiddleware
{
    private const SAFE_METHODS  = ['GET', 'HEAD', 'OPTIONS'];
    private const SESSION_KEY   = 'csrf_token';
    private const FORM_FIELD    = '_csrf';
    private const HEADER_NAME   = 'HTTP_X_CSRF_TOKEN';   // $_SERVER key for X-CSRF-Token
    private const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    /* ════════════════════════════════════════════════════════════
       GENERATE
       Call once per request, before any route handler.
       Mints a token if none exists, keeps existing valid token,
       and writes it to both the cookie and the session.
       ════════════════════════════════════════════════════════════ */

    public static function generate(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $cookieName = CookieConfig::NAMES['CSRF'];

        // Re-use existing valid token (64-char hex)
        $existing = $_COOKIE[$cookieName] ?? '';
        if (preg_match(self::TOKEN_PATTERN, $existing)) {
            $_SESSION[self::SESSION_KEY] = $existing;
            return;
        }

        // Mint a new token
        $token = bin2hex(random_bytes(32));   // 64 hex chars

        setcookie($cookieName, $token, CookieConfig::options('csrf'));
        $_COOKIE[$cookieName]        = $token;   // make immediately available this request
        $_SESSION[self::SESSION_KEY] = $token;
    }

    /* ════════════════════════════════════════════════════════════
       VERIFY
       Rejects state-changing requests with missing/invalid tokens.
       Accepts the token from the POST body (_csrf) OR the header.
       Safe methods (GET, HEAD, OPTIONS) pass through.
       ════════════════════════════════════════════════════════════ */

    public static function verify(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, self::SAFE_METHODS, true)) return;

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        $submitted    = $_POST[self::FORM_FIELD] ?? $_SERVER[self::HEADER_NAME] ?? '';

        if ($sessionToken === '' || $submitted === '' || !hash_equals($sessionToken, $submitted)) {
            self::abort();
        }
    }

    /* ════════════════════════════════════════════════════════════
       VERIFY HEADER
       Like verify() but only checks the X-CSRF-Token header.
       Use for AJAX/fetch endpoints where no form body is present.
       ════════════════════════════════════════════════════════════ */

    public static function verifyHeader(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, self::SAFE_METHODS, true)) return;

        $sessionToken  = $_SESSION[self::SESSION_KEY] ?? '';
        $headerToken   = $_SERVER[self::HEADER_NAME]  ?? '';

        if ($sessionToken === '' || $headerToken === '' || !hash_equals($sessionToken, $headerToken)) {
            self::abort();
        }
    }

    /* ════════════════════════════════════════════════════════════
       REGENERATE
       Invalidates the current token and mints a fresh one.
       Call after every successful state-changing POST so that
       captured tokens cannot be replayed across requests.
       ════════════════════════════════════════════════════════════ */

    public static function regenerate(): void
    {
        // Drop the session token so generate() cannot reuse it.
        unset($_SESSION[self::SESSION_KEY]);

        // Drop the in-memory cookie value so generate() skips the
        // "re-use existing valid token" branch and always mints fresh.
        $cookieName = CookieConfig::NAMES['CSRF'];
        unset($_COOKIE[$cookieName]);

        self::generate();
    }

    /* ════════════════════════════════════════════════════════════
       GET TOKEN
       Returns the current CSRF token for inline use in templates
       (e.g. when ob_start buffering has already started).
       ════════════════════════════════════════════════════════════ */

    public static function token(): string
    {
        return $_SESSION[self::SESSION_KEY] ?? '';
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /** Send a 403 response and halt execution. */
    private static function abort(): void
    {
        $accept      = $_SERVER['HTTP_ACCEPT']           ?? '';
        $xRequested  = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE']          ?? '';

        $isJson = str_contains($accept, 'application/json')
               || strtolower($xRequested) === 'xmlhttprequest'
               || str_contains($contentType, 'multipart');

        http_response_code(403);

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $title   = 'Forbidden';
        $status  = 403;
        $message = 'Invalid or missing CSRF token. Please go back and try again.';

        ob_start();
        require __DIR__ . '/../../src/views/pages/error.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
        exit;
    }
}
