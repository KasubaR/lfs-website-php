<?php /* pages/gallery-album.php — Single album with media grid + lightbox
   Locals: $album[], $media[]
*/ ?>
<?php
  $alb       = $album ?? [];
  $aDate     = !empty($alb['date']) ? new DateTime($alb['date']) : null;
  $dateStr   = $aDate ? $aDate->format('j F Y') : null;   // e.g. "21 February 2026"
  $itemCount = is_array($media ?? null) ? count($media) : (int)($alb['mediaCount'] ?? 0);
?>

<!-- ══════════════════════════════════════════════
     1. HERO
     ══════════════════════════════════════════════ -->
<div class="event-detail-hero">

  <?php if (!empty($alb['coverImage'])): ?>
  <div class="event-detail-hero__bg">
    <img src="<?= htmlspecialchars($alb['coverImage'], ENT_QUOTES, 'UTF-8') ?>"
         alt="<?= htmlspecialchars($alb['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
         loading="eager">
    <div class="event-detail-hero__overlay"></div>
  </div>
  <?php else: ?>
  <div class="event-detail-hero__bg event-detail-hero__bg--placeholder"></div>
  <?php endif ?>

  <div class="event-detail-hero__content">
    <nav class="events-breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="/">Home</a></li>
        <li><i class="fas fa-chevron-right" aria-hidden="true"></i></li>
        <li><a href="/gallery">Gallery</a></li>
        <li><i class="fas fa-chevron-right" aria-hidden="true"></i></li>
        <li><?= htmlspecialchars($alb['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
      </ol>
    </nav>

    <div class="event-detail-hero__inner">
      <div class="event-detail-hero__text">
        <h1 class="font-['Bebas_Neue'] text-5xl md:text-7xl leading-tight text-white">
          <?= htmlspecialchars($alb['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <div class="event-detail-hero__meta mt-4">
          <?php if ($dateStr): ?>
          <div class="event-detail-hero__meta-item">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            <span><?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <?php endif ?>
          <?php if (!empty($alb['location'])): ?>
          <div class="event-detail-hero__meta-item">
            <i class="fas fa-map-pin" aria-hidden="true"></i>
            <span><?= htmlspecialchars($alb['location'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <?php endif ?>
          <?php if (!empty($alb['category'])): ?>
          <div class="event-detail-hero__meta-item">
            <i class="fas fa-tag" aria-hidden="true"></i>
            <span><?= htmlspecialchars($alb['category'], ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <?php endif ?>
          <?php if ($itemCount > 0): ?>
          <div class="event-detail-hero__meta-item">
            <i class="fas fa-images" aria-hidden="true"></i>
            <span><?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?></span>
          </div>
          <?php endif ?>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════
     2. BODY — media grid
     ══════════════════════════════════════════════ -->
<div class="event-detail-body">
  <div class="event-detail-body__inner">
    <div class="event-detail-main">

      <?php if (!empty($media)): ?>
        <div class="gallery-grid" role="list" aria-label="Album photos and videos">
          <?php foreach ($media as $idx => $item):
            $imgUrl = $item['urls']['medium']
                   ?? $item['urls']['thumbnail']
                   ?? $item['urls']['large']
                   ?? null;
            $isVideo   = ($item['type'] ?? '') === 'video';
            $caption   = htmlspecialchars($item['caption'] ?? '', ENT_QUOTES, 'UTF-8');
            $ariaLabel = 'View ' . ($item['caption'] ? $caption : ($isVideo ? 'video' : 'photo')) . ' ' . ($idx + 1);

            // Dynamic layout based on aspect ratio:
            // - Portrait (taller than wide) → tall tile
            // - Extra-wide landscape       → wide tile
            $sizeClass   = '';
            $ratioSource = $imgUrl ?? ($item['urls']['large'] ?? $item['urls']['original'] ?? null);
            if ($ratioSource && str_starts_with($ratioSource, '/') && defined('PUBLIC_ROOT')) {
              $fsPath = rtrim(PUBLIC_ROOT, '/') . $ratioSource;
              if (is_file($fsPath)) {
                [$w, $h] = @getimagesize($fsPath) ?: [0, 0];
                if ($w > 0 && $h > 0) {
                  $ratio = $w / $h;
                  if ($ratio >= 1.5) {
                    $sizeClass = 'gallery-grid__item--wide';
                  } elseif ($ratio <= 0.8) {
                    $sizeClass = 'gallery-grid__item--tall';
                  }
                }
              }
            }
          ?>
            <div class="gallery-grid__item gallery-grid__item--clickable <?= $sizeClass ?>" role="listitem"
                 onclick="openLightbox(<?= $idx ?>)" tabindex="0"
                 onkeydown="if(event.key==='Enter'||event.key===' ')openLightbox(<?= $idx ?>)"
                 aria-label="<?= htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') ?>">

              <?php if ($isVideo): ?>
                <?php if ($imgUrl): ?>
                  <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>"
                       alt="<?= $caption ?: 'Video thumbnail' ?>"
                       loading="lazy">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center"
                       style="background:var(--black-mid); color:var(--white-dim);">
                    <i class="fas fa-play" style="font-size:2rem;"></i>
                  </div>
                <?php endif ?>
                <div class="gallery-grid__play-badge"><i class="fas fa-play"></i></div>
              <?php else: ?>
                <?php if ($imgUrl): ?>
                  <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>"
                       alt="<?= $caption ?>"
                       loading="lazy">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center"
                       style="background:var(--black-mid); color:var(--white-dim);">
                    <i class="fas fa-image" style="font-size:2rem;"></i>
                  </div>
                <?php endif ?>
              <?php endif ?>

            </div>
          <?php endforeach ?>
        </div>

      <?php else: ?>
        <p class="text-sm text-[#6b7280]">
          No photos or videos have been added to this album yet.
        </p>
      <?php endif ?>

    </div><!-- /.event-detail-main -->
  </div><!-- /.event-detail-body__inner -->
</div><!-- /.event-detail-body -->


<!-- ══════════════════════════════════════════════
     LIGHTBOX
     ══════════════════════════════════════════════ -->
<div id="lbOverlay" class="lb-overlay is-hidden" role="dialog" aria-modal="true" aria-label="Image preview">
  <button class="lb-close" onclick="closeLightbox()" aria-label="Close"><i class="fas fa-xmark"></i></button>
  <button class="lb-nav lb-nav--prev" onclick="lbPrev()" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
  <button class="lb-nav lb-nav--next" onclick="lbNext()" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
  <div class="lb-content" id="lbContent"></div>
  <div class="lb-meta" id="lbMeta">
    <span class="lb-counter" id="lbCounter"></span>
    <span class="lb-caption" id="lbCaption"></span>
  </div>
</div>

<?php
  // Build lightbox data array for JS
  $lbData = array_map(fn (array $m): array => [
      'type'    => $m['type']    ?? 'image',
      'caption' => $m['caption'] ?? '',
      'large'   => $m['urls']['large']    ?? $m['urls']['medium'] ?? $m['urls']['original'] ?? '',
      'video'   => $m['urls']['original'] ?? '',
  ], $media ?? []);
  $extraScripts = '<script>var LB_MEDIA = '
      . json_encode($lbData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)
      . ';</script><script src="' . htmlspecialchars(lfs_public_url('/js/gallery-lightbox.js'), ENT_QUOTES, 'UTF-8') . '"></script>';
?>
