<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/config/Database.php — MySQL PDO connection (replaces supabase.js)
 *
 * Provides a singleton PDO instance used by all services.
 * Credentials are read from environment variables (set in .env via your
 * bootstrap, or directly in the server/cPanel environment).
 *
 * Required env vars:
 *   DB_HOST      — e.g. localhost
 *   DB_PORT      — e.g. 3306
 *   DB_NAME      — e.g. lfs_db
 *   DB_USER      — e.g. lfs_user
 *   DB_PASS      — database password
 *
 * Optional:
 *   DB_CHARSET   — defaults to utf8mb4
 *
 * Usage:
 *   require_once __DIR__ . '/../config/Database.php';
 *   $pdo = Database::connect();
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    /**
     * Return the shared PDO instance, creating it on first call.
     *
     * @throws RuntimeException if required env vars are missing in production.
     * @throws PDOException     if the connection fails.
     */
    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host    = self::env('DB_HOST', 'localhost');
        $port    = self::env('DB_PORT', '3306');
        $name    = self::env('DB_NAME', '');
        $user    = self::env('DB_USER', '');
        $pass    = self::env('DB_PASS', '');
        $charset = self::env('DB_CHARSET', 'utf8mb4');

        $isProd = (self::env('APP_ENV', 'development') === 'production');

        if ($isProd && ($name === '' || $user === '')) {
            throw new RuntimeException('[LFS] DB_NAME and DB_USER must be set in production.');
        }

        if ($name === '') {
            error_log('[LFS] DB_NAME not set — using default "lfs_db". Set DB_NAME in your .env.');
            $name = 'lfs_db';
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host, $port, $name, $charset
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Keep connections alive and reconnect silently
            PDO::ATTR_PERSISTENT         => false,
            // Ensure MySQL uses UTC for datetime comparisons
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Mask credentials in the error message before logging
            $safeMsg = preg_replace('/:[^@]+@/', ':***@', $e->getMessage());
            error_log('[LFS] Database connection failed: ' . $safeMsg);
            throw $e;
        }

        return self::$instance;
    }

    /**
     * Explicitly close the connection (e.g. in long-running CLI scripts).
     * Normal PHP requests close automatically at script end.
     */
    public static function disconnect(): void
    {
        self::$instance = null;
    }

    // ─── Private helpers ──────────────────────────────────────

    /**
     * Read an env var from $_ENV/getenv(), treating empty strings as "unset".
     * This avoids Apache/cPanel empty env placeholders overriding putenv() values.
     */
    private static function env(string $key, string $default = ''): string
    {
        $envVal = $_ENV[$key] ?? null;
        if (is_string($envVal) && trim($envVal) !== '') {
            return $envVal;
        }

        $getenvVal = getenv($key);
        if (is_string($getenvVal) && trim($getenvVal) !== '') {
            return $getenvVal;
        }

        return $default;
    }
}
