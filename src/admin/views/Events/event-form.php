<?php
/**
 * admin/views/events/event-form.php — Create / Edit Event
 *
 * Variables from controller:
 *   $pageTitle       string
 *   $activePage      string
 *   $breadcrumbs     array
 *   $event           null (create) or array {
 *                      id, title, slug, description, location,
 *                      eventDate, distance, category, recurrenceType, recurrenceDays,
 *                      registrationOpen, registrationClose, bannerImage, featureOnHome
 *                    }
 *   $eventCategories array of category strings
 *   $isEdit          bool
 *   $error           string|null  validation message
 *   $csrfToken       string
 */

$isEdit = $isEdit ?? ($event !== null);
$ev     = $event  ?? [];

$pageTitle  = $pageTitle  ?? ($isEdit ? 'Edit Event' : 'New Event');
$activePage = 'events';
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Admin',  'url' => '/admin'],
    ['label' => 'Events', 'url' => '/admin/events'],
    ['label' => $isEdit ? ($ev['title'] ?? 'Edit') : 'New Event'],
];

$cats = $eventCategories ?? [];

/**
 * Format a datetime string to datetime-local input value (YYYY-MM-DDTHH:mm).
 * Uses UTC so server timezone doesn't shift values on round-trip.
 */
function toDateTimeLocal(?string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    if ($ts === false) return '';
    return gmdate('Y-m-d\TH:i', $ts);
}
?>

<?php if (!empty($error)): ?>
  <div class="gallery-error-banner" role="alert" style="margin-bottom:1rem;">
    <i class="fas fa-triangle-exclamation"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
<?php endif; ?>

<div style="max-width:720px;">
  <form method="POST"
        action="<?= $isEdit ? '/admin/events/' . htmlspecialchars($ev['id']) : '/admin/events' ?>"
        id="eventForm"
        enctype="multipart/form-data">

    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

    <!-- Title -->
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label" for="title">Title <span style="color:var(--flag-red);">*</span></label>
      <input type="text" id="title" name="title" required
             value="<?= htmlspecialchars($ev['title'] ?? '') ?>"
             placeholder="e.g. LFS Saturday LSD Run"
             style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
    </div>

    <!-- Slug -->
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label" for="slug">Slug</label>
      <input type="text" id="slug" name="slug"
             value="<?= htmlspecialchars($ev['slug'] ?? '') ?>"
             placeholder="e.g. lfs-saturday-lsd-run (leave blank to auto-generate from title)"
             style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
      <small style="display:block; margin-top:0.35rem; color:var(--text-dim); font-size:0.8rem;">
        URL-friendly; used in /events/:slug. Auto-generated from title if empty on create.
      </small>
    </div>

    <!-- Description -->
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label" for="description">Description</label>
      <textarea id="description" name="description" rows="4" placeholder="Event description"
                style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem; resize:vertical;"
      ><?= htmlspecialchars($ev['description'] ?? '') ?></textarea>
    </div>

    <!-- Location -->
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label" for="location">Location</label>
      <input type="text" id="location" name="location"
             value="<?= htmlspecialchars($ev['location'] ?? '') ?>"
             placeholder="e.g. Arcades, Lusaka"
             style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
    </div>

    <!-- Recurrence -->
    <?php
      $recType = $ev['recurrenceType'] ?? 'none';
      $recDays = array_filter(array_map('trim', explode(',', $ev['recurrenceDays'] ?? '')));
      $allDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
      $dayLabels = ['monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu',
                    'friday'=>'Fri','saturday'=>'Sat','sunday'=>'Sun'];
    ?>
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label">Recurrence</label>
      <div style="display:flex; gap:1.25rem; margin-top:0.4rem;">
        <label style="display:flex; align-items:center; gap:0.45rem; cursor:pointer; font-size:0.9rem; color:var(--off-white);">
          <input type="radio" name="recurrenceType" value="none" id="rec_none"
                 <?= $recType !== 'weekly' ? 'checked' : '' ?>>
          One-off event
        </label>
        <label style="display:flex; align-items:center; gap:0.45rem; cursor:pointer; font-size:0.9rem; color:var(--off-white);">
          <input type="radio" name="recurrenceType" value="weekly" id="rec_weekly"
                 <?= $recType === 'weekly' ? 'checked' : '' ?>>
          Repeats weekly
        </label>
      </div>

      <!-- Day checkboxes — shown only for weekly -->
      <div id="recurrence_days_wrap" style="margin-top:0.85rem; display:<?= $recType === 'weekly' ? 'flex' : 'none' ?>; flex-wrap:wrap; gap:0.5rem;">
        <?php foreach ($allDays as $d): ?>
        <label style="display:flex; align-items:center; gap:0.35rem; cursor:pointer;
                       padding:0.4rem 0.8rem; background:var(--black-soft);
                       border:1px solid var(--border-mid); border-radius:6px;
                       font-size:0.82rem; color:var(--off-white); user-select:none;"
               class="rec-day-label" data-day="<?= $d ?>">
          <input type="checkbox" name="recurrence_days[]" value="<?= $d ?>"
                 <?= in_array($d, $recDays, true) ? 'checked' : '' ?>>
          <?= $dayLabels[$d] ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Event date -->
    <div class="form-group" style="margin-bottom:1.25rem;" id="eventDate_wrap">
      <label class="form-label" for="eventDate" id="eventDate_label">
        Event date <span style="color:var(--flag-red);" id="eventDate_required">*</span>
      </label>
      <input type="datetime-local" id="eventDate" name="eventDate"
             value="<?= toDateTimeLocal($ev['eventDate'] ?? null) ?>"
             style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
      <small id="eventDate_hint" style="display:none; margin-top:0.35rem; color:var(--text-dim); font-size:0.8rem;">
        Next occurrence date (optional for recurring events).
      </small>
    </div>

    <!-- Distance -->
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label" for="distance">Distance</label>
      <input type="text" id="distance" name="distance"
             value="<?= htmlspecialchars($ev['distance'] ?? '') ?>"
             placeholder="e.g. 10K, 21.1K, 18K"
             style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
    </div>

    <script>
    (function () {
      const radios   = document.querySelectorAll('input[name="recurrenceType"]');
      const daysWrap = document.getElementById('recurrence_days_wrap');
      const dateInput = document.getElementById('eventDate');
      const dateReq   = document.getElementById('eventDate_required');
      const dateHint  = document.getElementById('eventDate_hint');

      function toggle() {
        const isWeekly = document.getElementById('rec_weekly').checked;
        daysWrap.style.display  = isWeekly ? 'flex' : 'none';
        dateInput.required      = !isWeekly;
        dateReq.style.display   = isWeekly ? 'none' : '';
        dateHint.style.display  = isWeekly ? 'block' : 'none';
      }

      radios.forEach(r => r.addEventListener('change', toggle));
      toggle();

      /* Highlight checked day labels */
      daysWrap.addEventListener('change', function (e) {
        if (e.target.type !== 'checkbox') return;
        e.target.closest('label').style.borderColor =
          e.target.checked ? 'var(--flag-green)' : 'var(--border-mid)';
      });
      /* Init highlight on load */
      daysWrap.querySelectorAll('input[type=checkbox]:checked').forEach(cb => {
        cb.closest('label').style.borderColor = 'var(--flag-green)';
      });
    }());
    </script>

    <!-- Category -->
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label" for="category">Category</label>
      <select id="category" name="category"
              style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;">
        <option value="">— Select —</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>"
            <?= ($ev['category'] ?? '') === $c ? 'selected' : '' ?>>
            <?= htmlspecialchars($c) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Registration type -->
    <?php $regType = $ev['registrationType'] ?? 'open'; ?>
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label">Registration</label>
      <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:0.4rem;">
        <?php foreach (['open' => 'Open to all', 'members' => 'Members only', 'none' => 'No registration'] as $val => $lbl): ?>
        <label style="display:flex; align-items:center; gap:0.45rem; cursor:pointer; font-size:0.9rem; color:var(--off-white);">
          <input type="radio" name="registrationType" value="<?= $val ?>" id="reg_<?= $val ?>"
                 <?= $regType === $val ? 'checked' : '' ?>>
          <?= $lbl ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- External registration link -->
    <div class="form-group" style="margin-bottom:1.25rem;">
      <label class="form-label" for="registrationLink">External registration link</label>
      <input type="url" id="registrationLink" name="registrationLink"
             value="<?= htmlspecialchars($ev['registrationLink'] ?? '') ?>"
             placeholder="https://squidal.com/… or any external URL"
             style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
      <small style="display:block; margin-top:0.35rem; color:var(--text-dim); font-size:0.8rem;">
        If set, the Register button will link here instead of the contact page.
      </small>
    </div>

    <!-- Registration open / close dates — hidden when type is 'none' -->
    <div id="reg_dates_wrap">
      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="registrationOpen">Registration opens</label>
        <input type="datetime-local" id="registrationOpen" name="registrationOpen"
               value="<?= toDateTimeLocal($ev['registrationOpen'] ?? null) ?>"
               style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
      </div>
      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" for="registrationClose">Registration closes</label>
        <input type="datetime-local" id="registrationClose" name="registrationClose"
               value="<?= toDateTimeLocal($ev['registrationClose'] ?? null) ?>"
               style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem;" />
      </div>
    </div>

    <script>
    (function () {
      const radios  = document.querySelectorAll('input[name="registrationType"]');
      const wrap    = document.getElementById('reg_dates_wrap');
      function toggle() {
        wrap.style.display = document.getElementById('reg_none').checked ? 'none' : '';
      }
      radios.forEach(r => r.addEventListener('change', toggle));
      toggle();
    }());
    </script>

    <!-- Banner image: upload or URL -->
    <div class="form-group event-form__banner-group" style="margin-bottom:1.25rem;">
      <?php if ($isEdit && !empty($ev['bannerImage'])): ?>
        <div class="event-form__banner-preview">
          <p class="event-form__banner-current-label">Current banner:</p>
          <img src="<?= htmlspecialchars($ev['bannerImage']) ?>" alt="Current banner" class="event-form__banner-image" />
        </div>
      <?php endif; ?>
      <label class="form-label" for="bannerImageFile">Banner image</label>
      <input type="file" id="bannerImageFile" name="bannerImageFile"
             accept="image/jpeg,image/png,image/webp" class="event-form__file" />
      <p class="event-form__banner-hint">Upload an image or paste a URL below. Leave both empty for no banner.</p>
      <label class="form-label" for="bannerImage">Or banner image URL</label>
      <input type="text" id="bannerImage" name="bannerImage" class="admin-input"
             value="<?= htmlspecialchars($ev['bannerImage'] ?? '') ?>"
             placeholder="https://… or /images/…" />
      <div class="form-group" style="margin-top:1rem;">
        <label style="display:flex; align-items:flex-start; gap:0.55rem; cursor:pointer; font-size:0.9rem; color:var(--off-white); line-height:1.4;">
          <input type="checkbox" name="featureOnHome" value="1"
            <?= !empty($ev['featureOnHome']) ? 'checked' : '' ?>
            style="margin-top:0.2rem; flex-shrink:0;" />
          <span>Feature on home page hero (banner + title shown on the hero; multiple events allowed—their slides and labels rotate first, then gallery images; requires a banner and an upcoming event date)</span>
        </label>
      </div>
    </div>

    <!-- Actions -->
    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
      <button type="submit"
              style="padding:0.6rem 1.25rem; background:var(--flag-green); color:#fff; border:none; border-radius:8px; font-family:var(--font-body); font-size:0.9rem; font-weight:600; cursor:pointer;">
        <?= $isEdit ? 'Update event' : 'Create event' ?>
      </button>
      <a href="/admin/events"
         style="padding:0.6rem 1rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--text-dim); text-decoration:none; font-size:0.9rem;">
        Cancel
      </a>
    </div>

  </form>
</div>
