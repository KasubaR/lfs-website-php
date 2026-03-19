<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/views/auth/login.php — Admin login form (page body only)
 *
 * Injected into $content and wrapped by src/admin/views/layouts/admin.php.
 * Do NOT add <html>, <head>, <body>, <style>, or <script> tags here.
 *
 * Variables supplied by AuthController::showLogin():
 *   string  $csrfToken  — CSRF token for the hidden input
 *   ?string $error      — auth error message, or null when none
 */
?>

<div class="auth-wrap">
  <div class="auth-card">

    <!-- ── Logo / heading ─────────────────────────────────── -->
    <div class="auth-card__header">
      <div class="auth-logo" aria-hidden="true">LFS</div>
      <h2 class="auth-card__title">Admin Panel</h2>
      <p class="auth-card__subtitle">Lusaka Fitness Squad</p>
    </div>

    <!-- ── Error banner ───────────────────────────────────── -->
    <?php if ($error !== null): ?>
      <div class="sys-notif sys-notif--error auth-card__error" role="alert">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- ── Login form ─────────────────────────────────────── -->
    <form
      class="auth-form"
      method="POST"
      action="/admin/<?= htmlspecialchars(AdminConfig::LOGIN_SLUG) ?>"
      novalidate
      autocomplete="on"
    >
      <!-- CSRF protection -->
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>" />

      <!-- Email — read-only identity hint -->
      <div class="form-group">
        <label class="form-label" for="admin-email">Email</label>
        <input
          class="form-control"
          id="admin-email"
          type="email"
          name="email"
          value="<?= htmlspecialchars(AdminConfig::EMAIL) ?>"
          readonly
          aria-readonly="true"
          autocomplete="username"
        />
      </div>

      <!-- Password -->
      <div class="form-group auth-form__password-wrap">
        <label class="form-label" for="admin-password">Password</label>
        <input
          class="form-control"
          id="admin-password"
          type="password"
          name="password"
          required
          autofocus
          autocomplete="current-password"
          placeholder="Enter admin password"
          aria-required="true"
        />
        <!-- Show / hide toggle — no inline JS; uses data-target attribute -->
        <button
          type="button"
          class="auth-form__eye"
          data-toggle-password="admin-password"
          aria-label="Toggle password visibility"
        >
          <i class="fas fa-eye" aria-hidden="true"></i>
        </button>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn btn--primary auth-form__submit">
        <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
        Sign in
      </button>

    </form><!-- /.auth-form -->

    <!-- ── Back link ──────────────────────────────────────── -->
    <p class="auth-card__footer-note">
      <a href="/" class="auth-card__back-link">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        Back to site
      </a>
    </p>

  </div><!-- /.auth-card -->
</div><!-- /.auth-wrap -->
