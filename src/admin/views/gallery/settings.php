<?php
/**
 * admin/views/gallery/settings.php — Gallery settings (banner)
 *
 * Vars:
 *   $pageTitle     string
 *   $activePage    string
 *   $breadcrumbs   array
 *   $csrfToken     string
 *   $bannerImage   string|null
 *   $error         string|null
 */
?>

<?php if (!empty($error ?? null)): ?>
  <div class="gallery-error-banner" role="alert">
    <i class="fas fa-triangle-exclamation"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
<?php endif; ?>

<div class="admin-panel">
  <div class="admin-panel__header">
    <h1 class="admin-panel__title">Gallery Settings</h1>
  </div>
  <div class="admin-panel__body">
  <form method="POST" action="/admin/gallery/settings" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

    <div class="admin-form__field">
      <label class="form-label" for="bannerImageFile">Gallery banner image</label>

      <?php if (!empty($bannerImage)): ?>
        <div class="settings-banner-preview">
          <p class="settings-banner-preview__label">Current banner:</p>
          <img src="<?= htmlspecialchars($bannerImage) ?>"
               alt="Current gallery banner"
               class="settings-banner-preview__img">
        </div>
      <?php endif; ?>

      <input type="file"
             id="bannerImageFile"
             name="bannerImageFile"
             accept="image/jpeg,image/png,image/webp"
             class="admin-input">
      <p class="form-hint">Upload an image or paste a URL below. Leave both empty for no banner.</p>
    </div>

    <div class="admin-form__field">
      <label class="form-label" for="bannerImageUrl">Or banner image URL</label>
      <input type="text"
             id="bannerImageUrl"
             name="bannerImageUrl"
             class="admin-input"
             value="<?= htmlspecialchars($bannerImage ?? '') ?>"
             placeholder="/images/gallery/… or https://…">
    </div>

    <div class="admin-form__actions">
      <button type="submit" class="btn-action btn-action--primary">
        <i class="fas fa-save"></i> Save settings
      </button>
      <a href="/admin/gallery/albums" class="btn-action btn-action--ghost">
        Cancel
      </a>
    </div>
  </form>
  </div>
</div>

