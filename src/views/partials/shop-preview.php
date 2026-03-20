<?php
/**
 * LFS SHOP PREVIEW PARTIAL — partials/shop-preview.php
 *
 * Reusable "LFS Store — Official Regalia & Gear" section.
 * Drop into any page that needs a product preview strip.
 *
 * Variables (all optional):
 *   $products    array|null  — Live product rows from ProductService / ShopController.
 *                              Each item must have: name, sub|category, price, image|thumbnail,
 *                              badge (optional), badgeColor (optional), slug (optional).
 *                              When empty or not provided, an empty state is shown.
 *   $sectionId   string      — id attribute on <section>. Default: "shop".
 *   $limit       int         — Max products to show. Default: 4.
 *   $bg          string      — Background CSS value. Default: "var(--off-white)".
 */

$sectionId = $sectionId ?? 'shop';
$limit     = (int)($limit ?? 4);
$bg        = $bg ?? 'var(--off-white)';

// Normalise live product rows to the same shape as defaults
$normalise = function (array $p): array {
    $img = $p['thumbnail'] ?? ($p['images'][0] ?? ($p['image'] ?? ''));
    // Price may already be formatted (e.g. \"K 350.00\" from home route)
    // or be a raw numeric value from ProductService.
    $rawPrice = $p['price'] ?? null;
    if ($rawPrice !== null && $rawPrice !== '') {
        $price = is_numeric($rawPrice)
            ? 'K ' . number_format((float)$rawPrice, 0, '.', ',')
            : (string)$rawPrice;
    } else {
        $price = '';
    }
    $comparePrice = isset($p['comparePrice']) && $p['comparePrice'] !== '' && $p['comparePrice'] !== null
        ? (float)$p['comparePrice'] : null;
    $rawPriceNum  = $rawPrice !== null && $rawPrice !== '' && is_numeric($rawPrice) ? (float)$rawPrice : null;
    $onSale       = $comparePrice !== null && $rawPriceNum !== null && $comparePrice > $rawPriceNum;
    $wasPrice     = $onSale ? 'K ' . number_format($comparePrice, 0, '.', ',') : null;

    return [
        'name'       => $p['name']       ?? '',
        'sub'        => $p['sub']        ?? ($p['category'] ?? ''),
        'price'      => $price,
        'onSale'     => $onSale,
        'wasPrice'   => $wasPrice,
        'badge'      => $p['badge']      ?? ((!empty($p['featured'])) ? 'Featured' : null),
        'badgeColor' => $p['badgeColor'] ?? ((!empty($p['featured'])) ? 'gold' : null),
        'image'      => $img,
        'slug'       => $p['slug']       ?? null,
    ];
};

// Use live products only; no placeholder products — show empty state when none
$productsData = !empty($products)
    ? array_map($normalise, array_slice($products, 0, $limit))
    : [];
?>

<!-- ══════════════════════════════════════════════
     SHOP PREVIEW PARTIAL
     ══════════════════════════════════════════════ -->
<section id="<?= htmlspecialchars($sectionId) ?>"
         class="shop-preview"
         style="background:<?= htmlspecialchars($bg) ?>">

  <div class="shop-preview__header" data-reveal>
    <div class="shop-preview__heading">
      <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl">
        Official Regalia<br>&amp; Gear
      </h2>
    </div>
    <?php if (!empty($productsData)): ?>
    <a href="/shop" class="btn btn-primary shop-preview__cta">
      Browse Full Collection <i class="fas fa-arrow-right" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
  </div>

  <?php if (empty($productsData)): ?>
  <div class="shop-preview__empty">
    <h3 class="shop-preview__empty-title">No products yet</h3>
    <p class="shop-preview__empty-text">
      Official LFS regalia and gear are coming soon. Check back later or visit the shop to see what we have in store.
    </p>
  </div>
  <?php else: ?>
  <div class="shop-preview__grid">
    <?php foreach ($productsData as $idx => $p):
      $href = !empty($p['slug']) ? '/shop/product/' . htmlspecialchars($p['slug']) : '/shop';
    ?>
    <article class="shop-preview__card" data-reveal data-reveal-delay="<?= $idx ?>">

      <a href="<?= $href ?>" class="shop-preview__img-wrap" tabindex="-1" aria-hidden="true">
        <img
          src="<?= htmlspecialchars($p['image']) ?>"
          alt="<?= htmlspecialchars($p['name']) ?>"
          class="shop-preview__img"
          loading="lazy"
          width="400"
          height="400"
        >
        <?php if (!empty($p['badge'])): ?>
          <span class="shop-preview__badge shop-preview__badge--<?= htmlspecialchars($p['badgeColor'] ?? 'default') ?>">
            <?= htmlspecialchars($p['badge']) ?>
          </span>
        <?php endif; ?>
        <div class="shop-preview__hover-cta" aria-hidden="true">
          <i class="fas fa-eye"></i> Quick View
        </div>
      </a>

      <div class="shop-preview__body">
        <div class="shop-preview__sub"><?= htmlspecialchars($p['sub']) ?></div>
        <h3 class="shop-preview__name">
          <a href="<?= $href ?>"><?= htmlspecialchars($p['name']) ?></a>
        </h3>
        <div class="shop-preview__footer">
          <span class="shop-preview__price-wrap">
            <?php if (!empty($p['onSale']) && !empty($p['wasPrice'])): ?>
              <span class="shop-preview__price shop-preview__price--compare" aria-label="Was <?= htmlspecialchars($p['wasPrice']) ?>"><?= htmlspecialchars($p['wasPrice']) ?></span>
            <?php endif; ?>
            <span class="shop-preview__price shop-preview__price--current"><?= htmlspecialchars($p['price']) ?></span>
          </span>
          <a href="<?= $href ?>" class="shop-preview__btn" aria-label="View <?= htmlspecialchars($p['name']) ?>">
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
          </a>
        </div>
      </div>

    </article>
    <?php endforeach; ?>
  </div>

  <div class="shop-preview__bottom" data-reveal>
    <p class="shop-preview__tagline">
      <i class="fas fa-shield-halved" aria-hidden="true"></i>
      Official LFS Regalia &mdash; quality gear for every runner.
    </p>
  </div>
  <?php endif; ?>

</section>
