<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/middleware/AdminRateLimiter.php
 *
 * Simple per-admin rate limiter for write operations (POST requests).
 * Uses APCu as a fixed-window counter:
 *
 *   - Window: 60 seconds
 *   - Limit : 60 POST requests per window per admin
 *
 * When APCu is not available, the limiter becomes a no-op.
 */

declare(strict_types=1);

class AdminRateLimiter
{
    private const WINDOW_SECONDS = 60;
    private const MAX_POSTS_PER_WINDOW = 60;

    /**
     * Enforce a POST rate limit per authenticated admin user.
     * Sends HTTP 429 and exits when the limit is exceeded.
     */
    public static function enforceForPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        if (!function_exists('apcu_fetch') || !function_exists('apcu_inc')) {
            // APCu not available — fail open rather than breaking admin flows.
            return;
        }

        // Identify the current admin. Prefer a stable user id/email; fall back to session id.
        $user = $_SESSION['admin_user'] ?? [];
        $id   = (string)($user['id'] ?? $user['email'] ?? session_id());

        $bucket = (int) floor(time() / self::WINDOW_SECONDS);
        $key    = 'lfs_admin_post_' . sha1($id . '|' . $bucket);

        $success = false;
        $count   = apcu_inc($key, 1, $success);
        if (!$success) {
            // First hit in this window
            apcu_store($key, 1, self::WINDOW_SECONDS);
            $count = 1;
        }

        if ($count > self::MAX_POSTS_PER_WINDOW) {
            http_response_code(429);
            header('Retry-After: ' . self::WINDOW_SECONDS);
            echo 'Too Many Requests: admin write rate limit exceeded. Please wait a moment and try again.';
            exit;
        }
    }
}

