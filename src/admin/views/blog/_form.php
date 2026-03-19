<?php
/**
 * admin/views/blog/_form.php — Shared create/edit form partial
 *
 * Included by create.php ($isEdit = false) and edit.php ($isEdit = true).
 *
 * Variables expected:
 *   $post        null|array   post data (null on create)
 *   $postId      null|string  post ID (null on create)
 *   $isEdit      bool
 *   $categories  array
 *   $csrfToken   string
 *   $error       string|null
 *
 * Sets $extraStyles and $extraScripts for Quill + DOMPurify (picked up by admin layout).
 */

$isEdit  = $isEdit  ?? ($post !== null);
$p       = $post    ?? [];
$postId  = $postId  ?? ($p['id'] ?? null);
$cats    = $categories ?? [];

// Quill loaded via $extraStyles / $extraScripts — admin layout outputs these
$extraStyles  = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css">'
              . '<style>
                  .ql-toolbar.ql-snow { background:var(--black-soft); border-color:var(--border-mid) !important; border-radius:8px 8px 0 0; }
                  .ql-container.ql-snow { background:var(--black-soft); border-color:var(--border-mid) !important; border-radius:0 0 8px 8px; min-height:320px; }
                  .ql-editor { color:var(--off-white); font-family:var(--font-body); font-size:0.95rem; min-height:300px; }
                  .ql-editor.ql-blank::before { color:var(--text-dim); font-style:normal; }
                  .ql-snow .ql-stroke { stroke:var(--text-dim) !important; }
                  .ql-snow .ql-fill  { fill:var(--text-dim)   !important; }
                  .ql-snow .ql-picker-label { color:var(--text-dim) !important; }
                  .ql-snow .ql-picker-options { background:var(--black-soft); border-color:var(--border-mid); }
                  .tag-chip { display:inline-flex; align-items:center; gap:0.3rem; padding:0.25rem 0.6rem; background:rgba(74,124,89,0.15); border:1px solid rgba(74,124,89,0.4); border-radius:20px; font-size:0.8rem; color:var(--green-bright,#7ecb93); }
                  .tag-chip__remove { background:none; border:none; cursor:pointer; color:inherit; padding:0; line-height:1; font-size:0.85rem; }
                  .blog-form-grid { display:grid; grid-template-columns:1fr 260px; gap:1.5rem; align-items:start; }
                  @media(max-width:900px) { .blog-form-grid { grid-template-columns:1fr; } }
                  .blog-sidebar-card { background:var(--black-soft); border:1px solid var(--border-mid); border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1rem; }
                  .blog-sidebar-card__title { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin:0 0 0.85rem; font-weight:600; }
                  .blog-field { margin-bottom:1.1rem; }
                  .blog-field:last-child { margin-bottom:0; }
                  .blog-label { display:block; font-size:0.8rem; color:var(--text-dim); margin-bottom:0.35rem; font-weight:500; }
                  .blog-input { width:100%; padding:0.55rem 0.8rem; background:var(--black-mid,#1a1a1a); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.88rem; box-sizing:border-box; }
                  .blog-input:focus { outline:none; border-color:var(--flag-green); }
                  .blog-toggle { display:flex; align-items:center; gap:0.6rem; cursor:pointer; }
                  .blog-toggle input[type=checkbox] { width:1rem; height:1rem; accent-color:var(--flag-green); cursor:pointer; }
                </style>';

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>'
              . '<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>'
              . '<script>
(function () {
  /* Sanitise HTML before use in Quill to prevent stored XSS (admin panel) */
  function sanitiseForQuill(html) {
    if (typeof DOMPurify === "undefined") return "";
    return DOMPurify.sanitize(html || "", { USE_PROFILES: { html: true } });
  }

  /* ── Quill rich text editor ─────────────────────── */
  const quill = new Quill("#blog-editor", {
    theme: "snow",
    placeholder: "Write your post content here…",
    modules: {
      toolbar: [
        [{ header: [1, 2, 3, false] }],
        ["bold", "italic", "underline", "strike"],
        [{ list: "ordered" }, { list: "bullet" }],
        ["blockquote", "code-block"],
        ["link", "image"],
        [{ align: [] }],
        ["clean"]
      ]
    }
  });

  /* Pre-fill content on edit — sanitise to prevent XSS from stored HTML */
  const existingContent = document.getElementById("content-hidden").value;
  if (existingContent) {
    quill.root.innerHTML = sanitiseForQuill(existingContent);
  }

  /* Copy Quill HTML to hidden input on form submit (sanitise for defense in depth) */
  document.getElementById("blog-form").addEventListener("submit", function () {
    document.getElementById("content-hidden").value = sanitiseForQuill(quill.root.innerHTML);
  });

  /* ── Tag chip input ─────────────────────────────── */
  const tagInput  = document.getElementById("tag-input");
  const tagsHidden = document.getElementById("tags-hidden");
  const tagWrap   = document.getElementById("tag-chips");

  function renderTags(tags) {
    tagWrap.innerHTML = "";
    tags.forEach(function (t, i) {
      const chip = document.createElement("span");
      chip.className = "tag-chip";
      chip.innerHTML = htmlEsc(t) +
        "<button type=\"button\" class=\"tag-chip__remove\" aria-label=\"Remove tag " + htmlEsc(t) + "\">×</button>";
      chip.querySelector("button").addEventListener("click", function () {
        tags.splice(i, 1);
        renderTags(tags);
        syncTags(tags);
      });
      tagWrap.appendChild(chip);
    });
  }

  function syncTags(tags) {
    tagsHidden.value = tags.join(",");
  }

  function htmlEsc(s) {
    return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
  }

  /* Initialise from existing value */
  let currentTags = tagsHidden.value
    ? tagsHidden.value.split(",").map(s => s.trim()).filter(Boolean)
    : [];
  renderTags(currentTags);

  tagInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter" || e.key === ",") {
      e.preventDefault();
      const val = tagInput.value.trim().replace(/,$/, "");
      if (val && !currentTags.includes(val)) {
        currentTags.push(val);
        renderTags(currentTags);
        syncTags(currentTags);
      }
      tagInput.value = "";
    }
  });

  /* ── Publish mode toggle ────────────────────────── */
  const statusSelect   = document.getElementById("blog-status");
  const scheduledWrap  = document.getElementById("scheduled-wrap");

  function toggleScheduled() {
    const val = statusSelect ? statusSelect.value : "";
    if (scheduledWrap) {
      scheduledWrap.style.display = val === "schedule" ? "" : "none";
    }
  }
  if (statusSelect) {
    statusSelect.addEventListener("change", toggleScheduled);
    toggleScheduled();
  }

  /* ── Featured image preview ─────────────────────── */
  const imgFile    = document.getElementById("featuredImageFile");
  const imgPreview = document.getElementById("featured-img-preview");
  if (imgFile && imgPreview) {
    imgFile.addEventListener("change", function () {
      const file = this.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function (e) {
        imgPreview.src   = e.target.result;
        imgPreview.style.display = "block";
      };
      reader.readAsDataURL(file);
    });
  }

  /* ── Preview button ─────────────────────────────── */
  document.getElementById("btn-preview")?.addEventListener("click", function () {
    const title   = document.getElementById("blog-title").value || "(No title)";
    const excerpt = document.getElementById("blog-excerpt").value || "";
    const body    = quill.root.innerHTML;
    const safeBody = typeof DOMPurify !== "undefined"
      ? DOMPurify.sanitize(body || "", { USE_PROFILES: { html: true } })
      : body || "";
    const win     = window.open("", "_blank");
    win.document.write(
      "<!DOCTYPE html><html><head><meta charset=\"UTF-8\">" +
      "<title>Preview: " + title.replace(/</g,"&lt;") + "</title>" +
      "<style>body{font-family:system-ui,sans-serif;max-width:780px;margin:2.5rem auto;padding:1rem 1.5rem;color:#111;line-height:1.7}" +
      "h1{margin-bottom:0.5rem}p.excerpt{color:#555;font-size:1.05rem;border-left:3px solid #4a7c59;padding-left:1rem;margin-bottom:2rem}" +
      "img{max-width:100%;border-radius:6px}" +
      ".preview-banner{background:#4a7c59;color:#fff;padding:0.5rem 1rem;font-size:0.8rem;margin-bottom:1.5rem;border-radius:4px}" +
      "</style></head><body>" +
      "<div class=\"preview-banner\">⚠ Preview — not yet published</div>" +
      "<h1>" + title.replace(/</g,"&lt;") + "</h1>" +
      (excerpt ? "<p class=\"excerpt\">" + excerpt.replace(/</g,"&lt;") + "</p>" : "") +
      safeBody +
      "</body></html>"
    );
    win.document.close();
  });

})();
</script>';
?>

<?php if (!empty($error)): ?>
  <div class="gallery-error-banner" role="alert" style="margin-bottom:1.25rem;">
    <i class="fas fa-triangle-exclamation"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
<?php endif; ?>

<form id="blog-form" method="POST"
      action="<?= $isEdit ? '/admin/blog/' . htmlspecialchars($postId ?? '') : '/admin/blog' ?>"
      enctype="multipart/form-data">

  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

  <div class="blog-form-grid">

    <!-- ══════════════════════════════════════════════
         MAIN COLUMN
    ════════════════════════════════════════════════ -->
    <div>

      <!-- Title -->
      <div class="blog-field" style="margin-bottom:1.25rem;">
        <label class="form-label" for="blog-title">
          Title <span style="color:var(--flag-red);">*</span>
        </label>
        <input type="text" id="blog-title" name="title" required
               value="<?= htmlspecialchars($p['title'] ?? '') ?>"
               placeholder="Post title"
               style="width:100%; padding:0.65rem 0.9rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:1rem; font-weight:600; box-sizing:border-box;" />
      </div>

      <!-- Slug -->
      <div class="blog-field" style="margin-bottom:1.25rem;">
        <label class="form-label" for="blog-slug">Slug</label>
        <input type="text" id="blog-slug" name="slug"
               value="<?= htmlspecialchars($p['slug'] ?? '') ?>"
               placeholder="auto-generated from title if left blank"
               class="blog-input" />
        <small style="display:block; margin-top:0.3rem; color:var(--text-dim); font-size:0.78rem;">
          Used in /blog/:slug — must be URL-safe.
        </small>
      </div>

      <!-- Rich text content -->
      <div class="blog-field" style="margin-bottom:1.25rem;">
        <label class="form-label" style="margin-bottom:0.5rem;">Content</label>
        <div id="blog-editor"></div>
        <input type="hidden" id="content-hidden" name="content"
               value="<?= htmlspecialchars($p['content'] ?? '') ?>">
      </div>

      <!-- Excerpt -->
      <div class="blog-field" style="margin-bottom:1.25rem;">
        <label class="form-label" for="blog-excerpt">Excerpt</label>
        <textarea id="blog-excerpt" name="excerpt" rows="3"
                  placeholder="Short summary shown in post listings…"
                  style="width:100%; padding:0.6rem 0.85rem; background:var(--black-soft); border:1px solid var(--border-mid); border-radius:8px; color:var(--off-white); font-family:var(--font-body); font-size:0.9rem; resize:vertical; box-sizing:border-box;"
        ><?= htmlspecialchars($p['excerpt'] ?? '') ?></textarea>
      </div>

    </div><!-- /main column -->


    <!-- ══════════════════════════════════════════════
         SIDEBAR
    ════════════════════════════════════════════════ -->
    <div>

      <!-- Publish actions card -->
      <div class="blog-sidebar-card">
        <p class="blog-sidebar-card__title">Publish</p>

        <!-- Status -->
        <?php
          $currentStatus = $p['status'] ?? 'draft';
          // Map 'draft' with a future publish_date to 'schedule' for the UI
          $uiStatus = $currentStatus;
          if ($currentStatus === 'draft' && !empty($p['publishDate'])) {
              $uiStatus = 'schedule';
          }
        ?>
        <div class="blog-field">
          <label class="blog-label" for="blog-status">Status</label>
          <select id="blog-status" name="status" class="blog-input">
            <option value="draft"     <?= $uiStatus === 'draft'     ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $uiStatus === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="schedule"  <?= $uiStatus === 'schedule'  ? 'selected' : '' ?>>Scheduled</option>
          </select>
          <!-- JS reads "schedule" as a publishMode, maps to status=draft in PHP -->
          <input type="hidden" name="publishMode" id="publish-mode-hidden" value="">
        </div>

        <!-- Schedule date (hidden unless Scheduled) -->
        <div class="blog-field" id="scheduled-wrap" style="display:none;">
          <label class="blog-label" for="blog-publish-date">Publish date &amp; time</label>
          <input type="datetime-local" id="blog-publish-date" name="publishDate"
                 value="<?= !empty($p['publishDate']) ? date('Y-m-d\TH:i', strtotime($p['publishDate'])) : '' ?>"
                 class="blog-input" />
        </div>

        <!-- Actions -->
        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.85rem;">
          <button type="submit" name="action" value="publish"
                  onclick="document.getElementById('publish-mode-hidden').value=document.getElementById('blog-status').value"
                  style="padding:0.6rem 1rem; background:var(--flag-green); color:#fff; border:none; border-radius:8px; font-family:var(--font-body); font-size:0.88rem; font-weight:600; cursor:pointer; text-align:center;">
            <?= $isEdit ? 'Update Post' : 'Publish Post' ?>
          </button>
          <button type="submit" name="action" value="draft"
                  onclick="document.getElementById('blog-status').value='draft'; document.getElementById('publish-mode-hidden').value='draft';"
                  style="padding:0.6rem 1rem; background:var(--black-mid,#1a1a1a); color:var(--off-white); border:1px solid var(--border-mid); border-radius:8px; font-family:var(--font-body); font-size:0.88rem; cursor:pointer; text-align:center;">
            Save as Draft
          </button>
          <button type="button" id="btn-preview"
                  style="padding:0.6rem 1rem; background:transparent; color:var(--text-dim); border:1px solid var(--border-mid); border-radius:8px; font-family:var(--font-body); font-size:0.88rem; cursor:pointer; text-align:center;">
            <i class="fas fa-eye" aria-hidden="true"></i> Preview
          </button>
          <a href="/admin/blog/list"
             style="padding:0.6rem 1rem; text-align:center; color:var(--text-dim); font-size:0.88rem; text-decoration:none;">
            Cancel
          </a>
        </div>
      </div><!-- /publish card -->

      <!-- Meta card -->
      <div class="blog-sidebar-card">
        <p class="blog-sidebar-card__title">Meta</p>

        <!-- Category -->
        <div class="blog-field">
          <label class="blog-label" for="blog-category">Category</label>
          <select id="blog-category" name="category" class="blog-input">
            <option value="">— Select —</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"
                <?= ($p['category'] ?? '') === $c ? 'selected' : '' ?>>
                <?= htmlspecialchars($c) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Author -->
        <div class="blog-field">
          <label class="blog-label" for="blog-author">Author</label>
          <input type="text" id="blog-author" name="author"
                 value="<?= htmlspecialchars($p['author'] ?? 'LFS Admin') ?>"
                 class="blog-input" />
        </div>

        <!-- Tags -->
        <div class="blog-field">
          <label class="blog-label">Tags</label>
          <div id="tag-chips" style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:0.45rem; min-height:1rem;"></div>
          <input type="hidden" id="tags-hidden" name="tags"
                 value="<?= htmlspecialchars(is_array($p['tags'] ?? null) ? implode(',', $p['tags']) : ($p['tags'] ?? '')) ?>">
          <input type="text" id="tag-input" placeholder="Add tag, press Enter or ,"
                 class="blog-input" autocomplete="off" />
          <small style="display:block; margin-top:0.3rem; color:var(--text-dim); font-size:0.75rem;">
            Press Enter or comma to add.
          </small>
        </div>

        <!-- Featured -->
        <div class="blog-field">
          <label class="blog-toggle">
            <input type="checkbox" name="featured" value="1"
                   <?= !empty($p['featured']) ? 'checked' : '' ?>>
            <span style="font-size:0.85rem; color:var(--off-white);">Featured post</span>
          </label>
          <small style="display:block; margin-top:0.3rem; color:var(--text-dim); font-size:0.75rem; padding-left:1.4rem;">
            Highlighted on the blog index.
          </small>
        </div>
      </div><!-- /meta card -->

      <!-- Featured image card -->
      <div class="blog-sidebar-card">
        <p class="blog-sidebar-card__title">Featured Image</p>

        <!-- Current image preview -->
        <?php $currentImg = $p['featuredImage'] ?? ''; ?>
        <?php if ($isEdit && $currentImg): ?>
          <img src="<?= htmlspecialchars($currentImg) ?>" alt="Current featured image"
               style="width:100%; border-radius:6px; margin-bottom:0.75rem; object-fit:cover; max-height:140px;" />
        <?php endif; ?>

        <!-- Upload new -->
        <img id="featured-img-preview" src="" alt=""
             style="display:none; width:100%; border-radius:6px; margin-bottom:0.75rem; object-fit:cover; max-height:140px;" />

        <div class="blog-field">
          <label class="blog-label" for="featuredImageFile">Upload image</label>
          <input type="file" id="featuredImageFile" name="featuredImageFile"
                 accept="image/jpeg,image/png,image/webp"
                 style="font-size:0.82rem; color:var(--text-dim);" />
          <small style="display:block; margin-top:0.3rem; color:var(--text-dim); font-size:0.75rem;">
            JPEG, PNG or WebP · max 10 MB
          </small>
        </div>

        <div class="blog-field">
          <label class="blog-label" for="featuredImage">Or image URL</label>
          <input type="text" id="featuredImage" name="featuredImage"
                 value="<?= htmlspecialchars($currentImg) ?>"
                 placeholder="https://… or /images/…"
                 class="blog-input" />
        </div>
      </div><!-- /image card -->

    </div><!-- /sidebar -->

  </div><!-- /grid -->

</form>
