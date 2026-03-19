<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/middleware/CookieMiddleware.php
 *
 * Handles:
 *   • Attaching consent state to $GLOBALS['locals'] for views
 *   • Parsing user preference cookies into view locals
 *   • Cookie validation helpers
 *   • Signed-cookie read helper (HMAC-SHA256)
 *
 * Call CookieMiddleware::attachLocals() early in the front router,
 * before any view rendering, so $consent, $prefs, $showBanner are
 * available in every template.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/CookieConfig.php';

class CookieMiddleware
{
    /* ════════════════════════════════════════════════════════════
       ATTACH LOCALS
       Reads consent + preferences cookies and writes them into
       the $viewLocals array that controllers pass to views.
       Call once per request, before any ob_start() render.
       ════════════════════════════════════════════════════════════ */

    /**
     * Parse cookies and return an array of view-local variables.
     * Merge this into the variable array you extract() before including views.
     *
     * @return array{consent: array, consentGiven: bool, showBanner: bool, prefs: array}
     */
    public static function attachLocals(): array
    {
        /* ── Consent ─────────────────────────────────────────── */
        $consent      = CookieConfig::DEFAULT_CONSENT;
        $consentGiven = false;

        $rawConsent = $_COOKIE[CookieConfig::NAMES['CONSENT']] ?? '';
        if ($rawConsent !== '') {
            $parsed = json_decode($rawConsent, true);
            if (is_array($parsed)) {
                $consent      = array_merge(CookieConfig::DEFAULT_CONSENT, $parsed);
                $consentGiven = true;
            }
        }

        /* ── Preferences ─────────────────────────────────────── */
        $prefs    = [];
        $rawPrefs = $_COOKIE[CookieConfig::NAMES['PREFERENCES']] ?? '';
        if ($rawPrefs !== '') {
            $parsed = json_decode($rawPrefs, true);
            if (is_array($parsed)) $prefs = $parsed;
        }

        return [
            'consent'      => $consent,
            'consentGiven' => $consentGiven,
            'showBanner'   => !$consentGiven,   // PHP views: <?php if ($showBanner): ?>
            'prefs'        => $prefs,
        ];
    }

    /* ════════════════════════════════════════════════════════════
       REQUIRE CONSENT
       Call before rendering a feature that needs a specific
       consent category. Returns false (and sends the response)
       if consent has not been given; returns true if OK to proceed.

       Usage:
         if (!CookieMiddleware::requireConsent('analytics')) return;
       ════════════════════════════════════════════════════════════ */

    public static function requireConsent(string $category): bool
    {
        $locals  = self::attachLocals();
        $consent = $locals['consent'];

        if (!empty($consent[$category])) {
            return true;
        }

        $accept     = $_SERVER['HTTP_ACCEPT']           ?? '';
        $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $wantsJson  = str_contains($accept, 'application/json')
                   || strtolower($xRequested) === 'xmlhttprequest';

        if ($wantsJson) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => "Consent required for category: $category"]);
        } else {
            header('Location: /?consent_required=' . urlencode($category));
        }
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       VALIDATE COOKIE VALUE
       Basic sanity check — non-empty, reasonable length, no
       control/newline characters that could enable header injection.
       ════════════════════════════════════════════════════════════ */

    public static function isValidCookieValue(mixed $value): bool
    {
        if (!is_string($value)) return false;
        $len = strlen($value);
        if ($len === 0 || $len > 4096) return false;
        // Reject raw newlines and ASCII control characters
        if (preg_match('/[\r\n\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) return false;
        return true;
    }

    /* ════════════════════════════════════════════════════════════
       READ SIGNED COOKIE
       Verifies the HMAC-SHA256 signature appended by setSignedCookie().
       Returns the plain value on success, null if missing or tampered.

       Format stored in cookie:  base64url(value) . '.' . base64url(hmac)
       ════════════════════════════════════════════════════════════ */

    public static function readSignedCookie(string $name): ?string
    {
        $raw = $_COOKIE[$name] ?? null;
        if ($raw === null) return null;

        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) return null;

        [$encodedValue, $encodedSig] = $parts;

        $secret       = CookieConfig::secret();
        $expectedHmac = hash_hmac('sha256', $encodedValue, $secret, true);
        $expectedSig  = rtrim(strtr(base64_encode($expectedHmac), '+/', '-_'), '=');

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSig, $encodedSig)) {
            return null;
        }

        $decoded = base64_decode(strtr($encodedValue, '-_', '+/'));
        return $decoded !== false ? $decoded : null;
    }

    /* ════════════════════════════════════════════════════════════
       SET SIGNED COOKIE
       Writes a value with an HMAC-SHA256 signature so it cannot
       be tampered with client-side.
       ════════════════════════════════════════════════════════════ */

    public static function setSignedCookie(string $name, string $value, array $options = []): void
    {
        $secret       = CookieConfig::secret();
        $encodedValue = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $hmac         = hash_hmac('sha256', $encodedValue, $secret, true);
        $encodedSig   = rtrim(strtr(base64_encode($hmac), '+/', '-_'), '=');

        $signed  = $encodedValue . '.' . $encodedSig;
        $options = empty($options) ? CookieConfig::options('auth') : $options;

        setcookie($name, $signed, $options);
    }

    /* ════════════════════════════════════════════════════════════
       CLEAR ALL LFS COOKIES
       Wipes every LFS cookie — used on logout / account delete.
       ════════════════════════════════════════════════════════════ */

    public static function clearAllCookies(): void
    {
        $expired = ['expires' => 1, 'path' => '/'];
        foreach (CookieConfig::NAMES as $name) {
            setcookie($name, '', $expired);
        }
    }
}
