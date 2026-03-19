<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/config/AdminConfig.php — Single-admin account configuration
 *
 * Password hash is read from the ADMIN_PASSWORD_HASH environment variable.
 * To generate a hash locally run:
 *   php -r "echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 12]);"
 *
 * Then add to your public/index.php putenv() block (or .env / vhost config):
 *   putenv('ADMIN_PASSWORD_HASH=$2y$12$...');
 */

declare(strict_types=1);

class AdminConfig
{
    /** The single admin email — shown read-only on the login form. */
    public const EMAIL = 'support@lfszambia.run';

    /**
     * Session idle timeout in seconds (default: 30 minutes).
     * After this period of inactivity the admin is forced to re-login.
     */
    public const SESSION_TIMEOUT = 1800;

    /** $_SESSION keys used by the auth guard and controller. */
    public const SESSION_AUTH_KEY   = 'admin_authenticated';
    public const SESSION_ACTIVE_KEY = 'admin_last_active';

    /**
     * The secret login slug (relative to /admin/).
     * Change this to something non-obvious before deploying.
     * Do not commit the production value to source control.
     */
    public const LOGIN_SLUG = 'door';

    /**
     * Returns the BCrypt password hash for the admin account.
     *
     * Reads ADMIN_PASSWORD_HASH from the environment. Falls back to an
     * intentionally invalid hash so that login always fails gracefully when
     * the env var is not configured — password_verify() will not crash.
     *
     * @return string BCrypt hash string
     */
    public static function passwordHash(): string
    {
        $hash = getenv('ADMIN_PASSWORD_HASH');

        return (is_string($hash) && $hash !== '')
            ? $hash
            : '$2y$12$invalid.placeholder.hash.that.never.matches.anything..';
    }
}
