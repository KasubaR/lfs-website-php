<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/middleware/AuthMiddleware.php
 *
 * Handles:
 *   • Reading & verifying the signed auth cookie (lfs_auth)
 *   • Decoding the JWT payload
 *   • optionalAuth()  — attaches $user to view locals, never blocks
 *   • requireAuth()   — redirects or 401s unauthenticated requests
 *   • requireRole()   — requireAuth + role check
 *   • signAuthCookie() — mints JWT + sets signed cookie on login
 *   • clearAuthCookie() — clears cookie on logout
 *
 * PHP JWT library used: firebase/php-jwt (install via Composer):
 *   composer require firebase/php-jwt
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/CookieConfig.php';
require_once __DIR__ . '/CookieMiddleware.php';

// firebase/php-jwt — loaded via Composer autoload in index.php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class AuthMiddleware
{
    /** JWT signing algorithm. */
    private const ALGO = 'HS256';

    /** JWT claims. */
    private const ISSUER   = 'lfs-zambia';
    private const AUDIENCE = 'lfs-web';

    /** Cookie name holding the signed JWT. */
    private const COOKIE = 'AUTH';   // key in CookieConfig::NAMES

    /* ════════════════════════════════════════════════════════════
       JWT SECRET
       ════════════════════════════════════════════════════════════ */

    private static function jwtSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';

        if ($secret === '') {
            $isProd = CookieConfig::isProd();
            if ($isProd) {
                throw new RuntimeException('[LFS] JWT_SECRET must be set in production.');
            }
            return 'dev-jwt-secret-change-me';
        }

        return $secret;
    }

    /* ════════════════════════════════════════════════════════════
       DECODE AUTH COOKIE → USER ARRAY
       Internal helper. Returns decoded payload array or null.
       ════════════════════════════════════════════════════════════ */

    public static function decodeAuthCookie(): ?array
    {
        $token = CookieMiddleware::readSignedCookie(CookieConfig::NAMES[self::COOKIE]);
        if ($token === null) return null;

        try {
            $decoded = JWT::decode($token, new Key(self::jwtSecret(), self::ALGO));
            return (array)$decoded;
        } catch (ExpiredException | SignatureInvalidException $e) {
            // Silently treat as unauthenticated
            return null;
        } catch (Throwable $e) {
            if (!CookieConfig::isProd()) {
                error_log('[LFS Auth] JWT verify failed: ' . $e->getMessage());
            }
            return null;
        }
    }

    /* ════════════════════════════════════════════════════════════
       OPTIONAL AUTH
       Returns the user array (or null) and does NOT abort.
       Use by extracting the returned array into view variables.

       Usage (in router):
         $authLocals = AuthMiddleware::optionalAuth();
         // $authLocals['user'], ['isAuth'] now available
       ════════════════════════════════════════════════════════════ */

    public static function optionalAuth(): array
    {
        $user = self::decodeAuthCookie();
        return [
            'user'   => $user,
            'isAuth' => $user !== null,
        ];
    }

    /* ════════════════════════════════════════════════════════════
       REQUIRE AUTH
       Aborts with 401/redirect if unauthenticated.
       Returns the user array on success.

       Usage (in router):
         $user = AuthMiddleware::requireAuth();
         // continues only if authenticated
       ════════════════════════════════════════════════════════════ */

    public static function requireAuth(): array
    {
        $user = self::decodeAuthCookie();

        if ($user === null) {
            $accept     = $_SERVER['HTTP_ACCEPT']           ?? '';
            $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            $wantsJson  = str_contains($accept, 'application/json')
                       || strtolower($xRequested) === 'xmlhttprequest';

            if ($wantsJson) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Authentication required.']);
                exit;
            }

            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $isAdmin    = str_starts_with($requestUri, '/admin');
            $loginPath  = $isAdmin ? '/admin/login' : '/login';

            header('Location: ' . $loginPath . '?redirect=' . urlencode($requestUri));
            exit;
        }

        return $user;
    }

    /* ════════════════════════════════════════════════════════════
       REQUIRE ROLE
       requireAuth + role check. Returns user array on success.

       Usage:
         $user = AuthMiddleware::requireRole('admin');
         $user = AuthMiddleware::requireRole(['admin', 'moderator']);
       ════════════════════════════════════════════════════════════ */

    public static function requireRole(string|array $roles): array
    {
        $user    = self::requireAuth();   // exits if not authenticated
        $allowed = is_array($roles) ? $roles : [$roles];
        $role    = $user['role'] ?? '';

        if (!in_array($role, $allowed, true)) {
            $accept     = $_SERVER['HTTP_ACCEPT']           ?? '';
            $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            $wantsJson  = str_contains($accept, 'application/json')
                       || strtolower($xRequested) === 'xmlhttprequest';

            if ($wantsJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Forbidden.']);
                exit;
            }

            http_response_code(403);
            $title   = 'Forbidden';
            $status  = 403;
            $message = "You don't have permission to access this page.";
            ob_start();
            require __DIR__ . '/../../src/views/pages/error.php';
            $content = ob_get_clean();
            require __DIR__ . '/../../src/views/layouts/main.php';
            exit;
        }

        return $user;
    }

    /* ════════════════════════════════════════════════════════════
       SIGN AUTH COOKIE
       Mints a JWT and sets it as a signed cookie on login.

       @param array $payload  e.g. ['id' => $id, 'email' => $email, 'role' => 'admin']
       ════════════════════════════════════════════════════════════ */

    public static function signAuthCookie(array $payload): string
    {
        $now = time();

        $claims = array_merge($payload, [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'exp' => $now + CookieConfig::DURATION['WEEK'],  // 7 days
        ]);

        $token = JWT::encode($claims, self::jwtSecret(), self::ALGO);

        CookieMiddleware::setSignedCookie(
            CookieConfig::NAMES[self::COOKIE],
            $token,
            CookieConfig::options('auth')
        );

        return $token;
    }

    /* ════════════════════════════════════════════════════════════
       CLEAR AUTH COOKIE
       Wipes the auth cookie on logout.
       ════════════════════════════════════════════════════════════ */

    public static function clearAuthCookie(): void
    {
        setcookie(CookieConfig::NAMES[self::COOKIE], '', [
            'expires'  => 1,
            'path'     => '/',
            'secure'   => CookieConfig::isProd(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
