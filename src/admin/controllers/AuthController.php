<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/controllers/AuthController.php — Admin authentication controller
 *
 * Handles login form display, credential verification, and logout.
 * All auth state is stored in $_SESSION; no database users table is required.
 *
 * Public API (called from src/admin/routes/admin.php):
 *
 *   AuthController::requireAuth(string $seg0)
 *       Guard: enforces authentication on every admin route.
 *       Pass the first URL segment so login/logout are let through.
 *
 *   AuthController::showLogin()
 *       GET /admin/door — renders the login form via the admin layout.
 *
 *   AuthController::login()
 *       POST /admin/door — verifies password, sets session, redirects.
 *
 *   AuthController::logout()
 *       GET /admin/logout — destroys session, redirects to home.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/AdminConfig.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';

class AuthController
{
    /* ════════════════════════════════════════════════════════════
       AUTH GUARD
       ════════════════════════════════════════════════════════════ */

    /**
     * Enforces authentication on all admin routes.
     *
     * Call this near the top of admin.php before any route dispatch.
     * Passes through the LOGIN_SLUG and 'logout' slugs unauthenticated;
     * everything else requires a valid, non-expired session.
     *
     * @param string $seg0 First URL segment after /admin/
     */
    public static function requireAuth(string $seg0): void
    {
        // These paths are always publicly reachable.
        if (in_array($seg0, [AdminConfig::LOGIN_SLUG, 'logout'], true)) {
            return;
        }

        self::ensureSession();

        if (!self::isAuthenticated()) {
            header('Location: /admin/' . AdminConfig::LOGIN_SLUG);
            exit;
        }

        // Slide the idle-timeout window forward on each authenticated request.
        $_SESSION[AdminConfig::SESSION_ACTIVE_KEY] = time();
    }

    /* ════════════════════════════════════════════════════════════
       SHOW LOGIN  —  GET /admin/door
       ════════════════════════════════════════════════════════════ */

    /**
     * Renders the login form inside the admin layout.
     * Already-authenticated admins are bounced to the dashboard.
     */
    public static function showLogin(): void
    {
        self::ensureSession();

        if (self::isAuthenticated()) {
            header('Location: /admin/dashboard');
            exit;
        }

        CsrfMiddleware::generate();

        // Pull and immediately clear any pending error stored by login().
        $error = $_SESSION['admin_login_error'] ?? null;
        unset($_SESSION['admin_login_error']);

        // Variables for the layout and the view.
        $pageTitle   = 'Admin Login';
        $activePage  = '';
        $csrfToken   = CsrfMiddleware::token();

        // CSS and JS injected by the layout — keeps the view file free of
        // inline <style> and <script> tags as required by the spec.
        $extraStyles  = self::loginStyles();
        $extraScripts = self::loginScripts();

        ob_start();
        require __DIR__ . '/../views/auth/login.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/admin.php';
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       LOGIN  —  POST /admin/door
       ════════════════════════════════════════════════════════════ */

    /**
     * Validates the submitted password.
     * On success: regenerates session ID, sets auth keys, redirects to dashboard.
     * On failure: stores error in session (PRG pattern), redirects back to form.
     */
    public static function login(): void
    {
        self::ensureSession();
        CsrfMiddleware::verify();

        $submitted = $_POST['password'] ?? '';

        if (
            is_string($submitted)
            && $submitted !== ''
            && password_verify($submitted, AdminConfig::passwordHash())
        ) {
            // Rotate session ID to mitigate session-fixation attacks.
            session_regenerate_id(true);

            $_SESSION[AdminConfig::SESSION_AUTH_KEY]   = true;
            $_SESSION[AdminConfig::SESSION_ACTIVE_KEY] = time();
            unset($_SESSION['admin_login_error']);

            header('Location: /admin/dashboard');
            exit;
        }

        // Wrong password — store error and redirect back (Post/Redirect/Get).
        $_SESSION['admin_login_error'] = 'Invalid password. Please try again.';
        header('Location: /admin/' . AdminConfig::LOGIN_SLUG);
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       LOGOUT  —  GET /admin/logout
       ════════════════════════════════════════════════════════════ */

    /**
     * Destroys the admin session completely and redirects to the public home page.
     */
    public static function logout(): void
    {
        self::ensureSession();

        // Clear admin-specific keys first.
        unset(
            $_SESSION[AdminConfig::SESSION_AUTH_KEY],
            $_SESSION[AdminConfig::SESSION_ACTIVE_KEY],
            $_SESSION['admin_login_error']
        );

        // Fully destroy the session.
        session_unset();
        session_destroy();

        // Expire the session cookie in the browser.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        header('Location: /');
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Returns true only when the session flag is set AND the last-active
     * timestamp is still within the idle-timeout window.
     */
    private static function isAuthenticated(): bool
    {
        if (empty($_SESSION[AdminConfig::SESSION_AUTH_KEY])) {
            return false;
        }

        $lastActive = (int)($_SESSION[AdminConfig::SESSION_ACTIVE_KEY] ?? 0);

        if ((time() - $lastActive) > AdminConfig::SESSION_TIMEOUT) {
            // Timed out — clean up so the next guard call sees a clean slate.
            unset(
                $_SESSION[AdminConfig::SESSION_AUTH_KEY],
                $_SESSION[AdminConfig::SESSION_ACTIVE_KEY]
            );
            return false;
        }

        return true;
    }

    /** Start a session if one is not already active. */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Returns a <script> block string for the login page.
     *
     * Delivered via $extraScripts so it lands at the bottom of the layout
     * <body>, keeping the view file free of embedded <script> tags.
     */
    private static function loginScripts(): string
    {
        return <<<'JS'
<script>
// Password show/hide toggle — wired to data-toggle-password attribute.
(function () {
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-toggle-password');
      var field    = document.getElementById(targetId);
      if (!field) return;
      var isHidden = field.type === 'password';
      field.type   = isHidden ? 'text' : 'password';
      var icon     = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-eye',       !isHidden);
        icon.classList.toggle('fa-eye-slash',  isHidden);
      }
      btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });
  });
})();
</script>
JS;
    }

    /**
     * Returns a <style> block string for the login page.
     *
     * Delivered via $extraStyles so it lands inside the layout <head>,
     * keeping the view file free of embedded <style> tags.
     *
     * Relies entirely on CSS custom properties already declared in admin.css;
     * only layout rules that are unique to the login page are added here.
     */
    private static function loginStyles(): string
    {
        return <<<'CSS'
<style>
/* ── Login page layout overrides ─────────────────────────────
   Hide the sidebar/topbar and make the main area a single,
   centered column so the auth card sits in the true viewport
   centre on all screen sizes.
   All colour/typography tokens come from admin.css.
──────────────────────────────────────────────────────────────── */
.admin-sidebar,
.admin-topbar {
  display: none !important;
}

.admin-layout {
  grid-template-columns: 1fr !important;
}

.admin-main {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 2rem 1rem;
  margin-left: 0 !important;
  background: var(--bg-base, #0f0f0f);
}

/* Card */
.auth-wrap    { width: 100%; max-width: 420px; }

.auth-card {
  background   : var(--bg-card, #1a1a1a);
  border       : 1px solid var(--border-subtle, rgba(255,255,255,.08));
  border-radius: var(--radius-lg, 12px);
  padding      : 2.5rem 2rem;
  box-shadow   : 0 8px 32px rgba(0,0,0,.5);
}

/* Header */
.auth-card__header   { text-align: center; margin-bottom: 1.75rem; }

.auth-logo {
  font-family : 'Bebas Neue', sans-serif;
  font-size   : 2.4rem;
  letter-spacing: .1em;
  color       : var(--brand-primary, #e8ff4a);
  line-height : 1;
  margin-bottom: .5rem;
}

.auth-card__title {
  font-size  : 1.25rem;
  font-weight: 700;
  color      : var(--text-primary, #f5f5f5);
  margin     : 0 0 .25rem;
}

.auth-card__subtitle {
  font-size: .85rem;
  color    : var(--text-dim, #888);
  margin   : 0;
}

/* Error banner spacing */
.auth-card__error { margin-bottom: 1.25rem; }

/* Form */
.auth-form .form-group { margin-bottom: 1.1rem; }

.auth-form__password-wrap { position: relative; }

.auth-form__eye {
  position  : absolute;
  right     : .75rem;
  bottom    : .6rem;
  background: none;
  border    : none;
  cursor    : pointer;
  color     : var(--text-dim, #888);
  padding   : .25rem;
  line-height: 1;
  transition: color .15s;
}
.auth-form__eye:hover { color: var(--text-primary, #f5f5f5); }

/* Full-width submit */
.auth-form__submit { margin-top: .5rem; gap: .5rem; width: 100%; justify-content: center; }

/* Footer link */
.auth-card__footer-note {
  text-align: center;
  margin    : 1.25rem 0 0;
  font-size : .82rem;
}

.auth-card__back-link {
  color      : var(--text-dim, #888);
  text-decoration: none;
  display    : inline-flex;
  align-items: center;
  gap        : .35rem;
  transition : color .15s;
}
.auth-card__back-link:hover { color: var(--text-primary, #f5f5f5); }
</style>
CSS;
    }
}
