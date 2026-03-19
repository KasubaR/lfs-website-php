<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/controllers/CookieController.php
 *
 * Handles cookie consent and preferences endpoints.
 *
 *   POST   /cookies/consent   — save accept/reject decision
 *   POST   /cookies/prefs     — save preference cookie values
 *   POST   /cookies/withdraw  — withdraw consent & clear cookies
 *                               (HTML forms can't send DELETE; use POST)
 *   GET    /cookies/status    — return current consent JSON (API)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/CookieConfig.php';

class CookieController
{
    /* ════════════════════════════════════════════════════════════
       SAVE CONSENT
       POST /cookies/consent
       Body: accept=all | accept=necessary
             OR granular: necessary=1 analytics=1 preferences=0 marketing=0
       ════════════════════════════════════════════════════════════ */

    public function saveConsent(): void
    {
        $consent = CookieConfig::DEFAULT_CONSENT;

        $accept = $_POST['accept'] ?? '';

        if ($accept === 'all') {
            $consent = [
                'necessary'   => true,
                'analytics'   => true,
                'preferences' => true,
                'marketing'   => true,
            ];
        } elseif ($accept === 'necessary') {
            $consent = array_merge(CookieConfig::DEFAULT_CONSENT, ['necessary' => true]);
        } else {
            // Granular: necessary is always true (disabled input not submitted)
            foreach (CookieConfig::CONSENT_CATEGORIES as $key => $cat) {
                $consent[$cat] = ($cat === 'necessary')
                    ? true
                    : ($_POST[$cat] === 'true' || $_POST[$cat] === '1' || $_POST[$cat] === true);
            }
        }

        // Set the consent cookie
        setcookie(
            CookieConfig::NAMES['CONSENT'],
            json_encode($consent),
            CookieConfig::options('consent')
        );

        // If analytics rejected, clear GA cookies
        if (empty($consent['analytics'])) {
            foreach (['_ga', '_gid', '_gat'] as $ga) {
                setcookie($ga, '', ['expires' => 1, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'] ?? '']);
            }
        }

        if ($this->wantsJson()) {
            $this->jsonResponse(['ok' => true, 'consent' => $consent]);
            return;
        }

        $redirectTo = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');
        header('Location: ' . $redirectTo);
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       SAVE PREFERENCES
       POST /cookies/prefs
       Body: theme, locale, fontSize, reducedMotion, notifications
       ════════════════════════════════════════════════════════════ */

    public function savePreferences(): void
    {
        // Merge with existing prefs cookie
        $existingPrefs = [];
        $rawPrefs      = $_COOKIE[CookieConfig::NAMES['PREFERENCES']] ?? '';
        if ($rawPrefs !== '') {
            $decoded = json_decode($rawPrefs, true);
            if (is_array($decoded)) $existingPrefs = $decoded;
        }

        $allowedKeys    = ['theme', 'locale', 'fontSize', 'reducedMotion', 'notifications'];
        $incomingPrefs  = [];
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $incomingPrefs[$key] = $_POST[$key];
            }
        }

        $mergedPrefs = array_merge($existingPrefs, $incomingPrefs);

        setcookie(
            CookieConfig::NAMES['PREFERENCES'],
            json_encode($mergedPrefs),
            CookieConfig::options('preferences')
        );

        if ($this->wantsJson()) {
            $this->jsonResponse(['ok' => true, 'prefs' => $mergedPrefs]);
            return;
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       WITHDRAW CONSENT
       POST /cookies/withdraw
       Clears consent cookie — banner reappears on next load.
       ════════════════════════════════════════════════════════════ */

    public function withdrawConsent(): void
    {
        $expired = ['expires' => 1, 'path' => '/'];

        setcookie(CookieConfig::NAMES['CONSENT'],     '', $expired);
        setcookie(CookieConfig::NAMES['PREFERENCES'], '', $expired);

        // Clear common third-party analytics cookies
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        foreach (['_ga', '_gid', '_gat', '_fbp', 'fr'] as $c) {
            setcookie($c, '', ['expires' => 1, 'path' => '/', 'domain' => $domain]);
        }

        if ($this->wantsJson()) {
            $this->jsonResponse(['ok' => true, 'message' => 'Consent withdrawn. Cookies cleared.']);
            return;
        }

        header('Location: ' . ($_POST['redirect'] ?? '/'));
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       GET STATUS  (API helper for front-end)
       GET /cookies/status
       Returns current consent + prefs as JSON.
       ════════════════════════════════════════════════════════════ */

    public function getStatus(): void
    {
        // Parse consent cookie
        $consent     = CookieConfig::DEFAULT_CONSENT;
        $consentGiven = false;
        $rawConsent  = $_COOKIE[CookieConfig::NAMES['CONSENT']] ?? '';
        if ($rawConsent !== '') {
            $decoded = json_decode($rawConsent, true);
            if (is_array($decoded)) {
                $consent      = $decoded;
                $consentGiven = true;
            }
        }

        // Parse prefs cookie
        $prefs   = [];
        $rawPrefs = $_COOKIE[CookieConfig::NAMES['PREFERENCES']] ?? '';
        if ($rawPrefs !== '') {
            $decoded = json_decode($rawPrefs, true);
            if (is_array($decoded)) $prefs = $decoded;
        }

        $this->jsonResponse([
            'ok'           => true,
            'consentGiven' => $consentGiven,
            'consent'      => $consent,
            'prefs'        => $prefs,
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /** True when the request expects a JSON response. */
    private function wantsJson(): bool
    {
        $accept      = $_SERVER['HTTP_ACCEPT']           ?? '';
        $xRequested  = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json')
            || strtolower($xRequested) === 'xmlhttprequest';
    }

    /** Output a JSON response and terminate. */
    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
