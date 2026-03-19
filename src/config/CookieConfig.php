<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/config/CookieConfig.php — Global cookie settings
 *
 * Central place for all cookie defaults, security options,
 * and expiration constants. Include this wherever cookies
 * are set or validated.
 *
 * Usage:
 *   require_once __DIR__ . '/../config/CookieConfig.php';
 *
 *   // Set a consent cookie
 *   setcookie(
 *       CookieConfig::NAMES['CONSENT'],
 *       'all',
 *       CookieConfig::options('consent')
 *   );
 */

declare(strict_types=1);

class CookieConfig
{
    /* ════════════════════════════════════════════════════════════
       EXPIRATION CONSTANTS  (all in seconds — PHP setcookie uses seconds)
       ════════════════════════════════════════════════════════════ */
    public const DURATION = [
        'MINUTE' =>      60,
        'HOUR'   =>    3600,
        'DAY'    =>   86400,
        'WEEK'   =>  604800,
        'MONTH'  => 2592000,   // 30 days
        'YEAR'   => 31536000,  // 365 days
    ];

    /* ════════════════════════════════════════════════════════════
       COOKIE NAMES — single source of truth
       ════════════════════════════════════════════════════════════ */
    public const NAMES = [
        'AUTH'        => 'lfs_auth',      // session token
        'CONSENT'     => 'lfs_consent',   // cookie consent decision
        'PREFERENCES' => 'lfs_prefs',     // user UI preferences
        'CSRF'        => 'lfs_csrf',      // CSRF protection token
    ];

    /* ════════════════════════════════════════════════════════════
       CONSENT CATEGORIES
       ════════════════════════════════════════════════════════════ */
    public const CONSENT_CATEGORIES = [
        'NECESSARY'   => 'necessary',    // always on — cannot be rejected
        'ANALYTICS'   => 'analytics',    // usage statistics
        'PREFERENCES' => 'preferences',  // saves UI choices
        'MARKETING'   => 'marketing',    // third-party tracking
    ];

    public const DEFAULT_CONSENT = [
        'necessary'   => true,
        'analytics'   => false,
        'preferences' => false,
        'marketing'   => false,
    ];

    /* ════════════════════════════════════════════════════════════
       COOKIE OPTIONS
       Returns an options array for setcookie() (PHP 7.3+ style).
       ════════════════════════════════════════════════════════════ */

    /**
     * Base security defaults applied to every cookie.
     * httpOnly: true prevents JS access — mitigates XSS theft.
     * samesite: Lax protects against CSRF while allowing normal nav.
     * secure:   true in production (HTTPS only).
     */
    private static function base(): array
    {
        return [
            'path'     => '/',
            'secure'   => self::isProd(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * Return the options array for a named cookie type.
     * Pass directly to setcookie() as the third argument.
     *
     * @param  string $type  'auth' | 'consent' | 'preferences' | 'csrf'
     * @return array<string, mixed>
     */
    public static function options(string $type): array
    {
        return match ($type) {

            // 7-day rolling session; httpOnly so JS cannot read it
            'auth' => array_merge(self::base(), [
                'expires'  => time() + self::DURATION['WEEK'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]),

            // Consent banner decision — front-end JS must read this to hide the banner
            'consent' => array_merge(self::base(), [
                'expires'  => time() + self::DURATION['YEAR'],
                'httponly' => false,
                'samesite' => 'Lax',
            ]),

            // User UI preferences — front-end reads on load
            'preferences' => array_merge(self::base(), [
                'expires'  => time() + self::DURATION['YEAR'],
                'httponly' => false,
                'samesite' => 'Lax',
            ]),

            // CSRF token — JS must read & send in X-CSRF-Token header
            'csrf' => array_merge(self::base(), [
                'expires'  => time() + self::DURATION['DAY'],
                'httponly' => false,
                'samesite' => 'Strict',   // stricter: token must not leak cross-site
            ]),

            default => self::base(),
        };
    }

    /** True when running in production (APP_ENV=production). */
    public static function isProd(): bool
    {
        return ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development') === 'production';
    }

    /**
     * Cookie signing secret — used by CsrfMiddleware and auth cookies.
     * Throws in production if the env var is missing.
     */
    public static function secret(): string
    {
        $secret = $_ENV['COOKIE_SECRET'] ?? getenv('COOKIE_SECRET') ?: '';

        if ($secret === '') {
            if (self::isProd()) {
                throw new RuntimeException('[LFS] COOKIE_SECRET must be set in production.');
            }
            return 'dev-cookie-secret-change-me';
        }

        return $secret;
    }
}
