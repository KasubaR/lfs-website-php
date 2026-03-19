<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/views/auth/login.php
 *
 * Rendered by AuthController::showLogin().
 * Expects:
 *   - $error     string|null — validation error message
 *   - $csrfToken string      — CSRF token from CsrfMiddleware
 *   - AdminConfig::EMAIL     — fixed admin email
 */
?>

<div class="auth-wrap">

  <div class="auth-card">

    <header class="auth-card__header">
      <div class="auth-logo">LFS</div>
      <h1 class="auth-card__title">Admin Sign In</h1>
      <p class="auth-card__subtitle">
        Enter the admin password to access the control panel.
      </p>
    </header>

    <?php if (!empty($error)): ?>
      <div class="auth-card__error">
        <div class="sys-notif sys-notif--error" role="alert">
          <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      </div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/admin/<?= htmlspecialchars(\AdminConfig::LOGIN_SLUG) ?>">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

      <div class="form-group">
        <label class="admin-label" for="adminEmail">Admin email</label>
        <input
          id="adminEmail"
          type="email"
          class="admin-input"
          value="<?= htmlspecialchars(\AdminConfig::EMAIL) ?>"
          readonly
          aria-readonly="true"
        >
      </div>

      <div class="form-group auth-form__password-wrap">
        <label class="admin-label" for="password">
          Password <span class="admin-label__required">*</span>
        </label>
        <input
          id="password"
          name="password"
          type="password"
          class="admin-input"
          autocomplete="current-password"
          required
        >
        <button
          type="button"
          class="auth-form__eye"
          data-toggle-password="password"
          aria-label="Show password"
        >
          <i class="fas fa-eye-slash" aria-hidden="true"></i>
        </button>
      </div>

      <button type="submit" class="admin-btn admin-btn--primary auth-form__submit">
        <i class="fas fa-lock" aria-hidden="true"></i>
        Sign In
      </button>
    </form>

    <p class="auth-card__footer-note">
      <a href="/" class="auth-card__back-link">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        Back to site
      </a>
    </p>

  </div>

</div>

