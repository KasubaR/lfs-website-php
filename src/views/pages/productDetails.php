<?php
/* ============================================================
   LFS — Lusaka Fitness Squad
   views/pages/productDetails.php

   Variables provided by the shop controller:
     $product, $related, $cartCount, $siteUrl, $formatPrice (callable)
   ============================================================ */

if (!isset($formatPrice) || !is_callable($formatPrice)) {
    $formatPrice = function (float $amount): string {
        return 'K ' . number_format($amount, 2);
    };
}

$rawSizes  = $product['sizes'] ?? [];
$allSizes  = count($rawSizes) > 0
  ? $rawSizes
  : (($product['totalStock'] ?? 0) > 0 ? [['size' => 'One Size', 'stock' => $product['totalStock']]] : []);

$inStock = false;
if (!empty($product['sizes']) && is_array($product['sizes'])) {
  foreach ($product['sizes'] as $s) {
    if (($s['stock'] ?? 0) > 0) { $inStock = true; break; }
  }
} else {
  $inStock = ($product['totalStock'] ?? 0) > 0;
}

$onSale      = !empty($product['comparePrice']) && $product['comparePrice'] > $product['price'];
$discountPct = $onSale ? round((($product['comparePrice'] - $product['price']) / $product['comparePrice']) * 100) : null;

$sizesInStock = array_map(fn($s) => $s['size'], array_filter($allSizes, fn($s) => ($s['stock'] ?? 0) > 0));
$firstImage   = $product['thumbnail'] ?? $product['images'][0] ?? '/images/products/placeholder.webp';
$hasImages    = !empty($product['images']) && count($product['images']) > 0;
$sku          = $product['_id'] ?? $product['id'] ?? $product['slug'] ?? '';
$lowStock     = ($product['totalStock'] ?? 0) > 0 && ($product['totalStock'] ?? 0) <= 5;

// Inject page-level CSS into the layout
$styles = '<link rel="stylesheet" href="' . htmlspecialchars(lfs_public_url('/css/shop.css'), ENT_QUOTES, 'UTF-8') . '"><link rel="stylesheet" href="' . htmlspecialchars(lfs_public_url('/css/productDetails.css'), ENT_QUOTES, 'UTF-8') . '">';
?>

<!-- ══════════════════════════════════════════
     BREADCRUMB
     ══════════════════════════════════════════ -->
<nav class="pd-breadcrumb" aria-label="Breadcrumb">
  <div class="pd-breadcrumb__inner">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">

      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a itemprop="item" href="/"><span itemprop="name">Home</span></a>
        <meta itemprop="position" content="1">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
      </li>

      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a itemprop="item" href="/shop"><span itemprop="name">Shop</span></a>
        <meta itemprop="position" content="2">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
      </li>

      <?php if (!empty($product['category'])): ?>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
        <a itemprop="item" href="/shop?category=<?= htmlspecialchars($product['category']) ?>">
          <span itemprop="name"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $product['category']))) ?></span>
        </a>
        <meta itemprop="position" content="3">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
      </li>
      <?php endif; ?>

      <li
        itemprop="itemListElement"
        itemscope
        itemtype="https://schema.org/ListItem"
        aria-current="page"
      >
        <span itemprop="name" class="pd-breadcrumb__current"><?= htmlspecialchars($product['name']) ?></span>
        <meta itemprop="position" content="<?= !empty($product['category']) ? 4 : 3 ?>">
      </li>

    </ol>
  </div>
</nav>

<!-- ══════════════════════════════════════════
     MAIN PRODUCT SECTION
     ══════════════════════════════════════════ -->
<section
  class="pd-section"
  itemscope
  itemtype="https://schema.org/Product"
  aria-label="<?= htmlspecialchars($product['name']) ?> product details"
>
  <div class="pd-section__inner">

    <!-- ══ LEFT — Image Gallery ══ -->
    <div class="pd-gallery" aria-label="Product images">

      <!-- Main image -->
      <div class="pd-gallery__main" id="pd-main-img-wrap">
        <img
          src="<?= htmlspecialchars($firstImage) ?>"
          alt="<?= htmlspecialchars($product['name']) ?>"
          class="pd-gallery__main-img"
          id="pd-main-img"
          width="800"
          height="800"
          loading="eager"
          itemprop="image"
          data-zoom-src="<?= htmlspecialchars($firstImage) ?>"
        >

        <!-- Sale ribbon -->
        <?php if ($onSale): ?>
          <div class="pd-gallery__ribbon pd-gallery__ribbon--sale">
            −<?= $discountPct ?>%
          </div>
        <?php endif; ?>

        <!-- Out of stock overlay -->
        <?php if (!$inStock): ?>
          <div class="pd-gallery__sold-out" aria-label="Sold out">
            <span>Sold Out</span>
          </div>
        <?php endif; ?>

        <!-- Zoom hint -->
        <div class="pd-gallery__zoom-hint" aria-hidden="true">
          <i class="fas fa-search-plus"></i>
        </div>
      </div>

      <!-- Thumbnails -->
      <?php if ($hasImages && count($product['images']) > 1): ?>
      <div class="pd-gallery__thumbs" role="list" aria-label="Product image thumbnails">
        <?php foreach ($product['images'] as $i => $img): ?>
          <button
            type="button"
            class="pd-gallery__thumb <?= $i === 0 ? 'is-active' : '' ?>"
            data-src="<?= htmlspecialchars($img) ?>"
            data-index="<?= $i ?>"
            aria-label="View image <?= $i + 1 ?>"
            aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"
            role="listitem"
          >
            <img
              src="<?= htmlspecialchars($img) ?>"
              alt="<?= htmlspecialchars($product['name']) ?> — image <?= $i + 1 ?>"
              loading="lazy"
              width="100"
              height="100"
            >
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Share buttons -->
      <?php
      $productUrl  = htmlspecialchars($siteUrl . '/shop/product/' . $product['slug']);
      $fbShareUrl  = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($siteUrl . '/shop/product/' . $product['slug']);
      $waShareUrl  = 'https://wa.me/?text=' . rawurlencode($product['name'] . ' — ' . $siteUrl . '/shop/product/' . $product['slug']);
      $twShareUrl  = 'https://twitter.com/intent/tweet?text=' . rawurlencode('Check out ' . $product['name'] . ' from @lfszambia') . '&url=' . rawurlencode($siteUrl . '/shop/product/' . $product['slug']);
      ?>
      <div class="pd-share" aria-label="Share this product">
        <span class="pd-share__label text-label">Share</span>
        <div class="pd-share__btns">
          <a
            href="<?= $fbShareUrl ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="pd-share__btn pd-share__btn--fb"
            aria-label="Share on Facebook"
          >
            <i class="fab fa-facebook-f" aria-hidden="true"></i>
          </a>
          <a
            href="<?= $waShareUrl ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="pd-share__btn pd-share__btn--wa"
            aria-label="Share on WhatsApp"
          >
            <i class="fab fa-whatsapp" aria-hidden="true"></i>
          </a>
          <a
            href="<?= $twShareUrl ?>"
            target="_blank"
            rel="noopener noreferrer"
            class="pd-share__btn pd-share__btn--tw"
            aria-label="Share on Twitter / X"
          >
            <i class="fab fa-x-twitter" aria-hidden="true"></i>
          </a>
        </div>
      </div>

    </div><!-- /.pd-gallery -->

    <!-- ══ RIGHT — Product Info ══ -->
    <div class="pd-info">

      <!-- Category + badges row -->
      <div class="pd-info__meta">
        <a href="/shop?category=<?= htmlspecialchars($product['category'] ?? '') ?>" class="badge" aria-label="Category: <?= htmlspecialchars($product['category'] ?? '') ?>">
          <?= htmlspecialchars(str_replace('-', ' ', $product['category'] ?? '')) ?>
        </a>
        <?php if (!empty($product['featured'])): ?>
          <span class="badge gold"><i class="fas fa-star" aria-hidden="true"></i> Featured</span>
        <?php endif; ?>
        <?php if ($onSale): ?>
          <span class="badge red">SALE</span>
        <?php endif; ?>
      </div>

      <!-- Name -->
      <h1 class="pd-info__name" itemprop="name"><?= htmlspecialchars($product['name']) ?></h1>

      <!-- SKU -->
      <p class="pd-info__sku text-label">
        SKU: <span itemprop="sku"><?= htmlspecialchars(strtoupper(substr($sku, -8))) ?></span>
        &nbsp;·&nbsp;
        <span itemprop="brand" itemscope itemtype="https://schema.org/Brand">
          <span itemprop="name">LFS Zambia</span>
        </span>
      </p>

      <!-- Price -->
      <div class="pd-info__price-row" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
        <meta itemprop="priceCurrency" content="ZMW">
        <meta itemprop="price" content="<?= $product['price'] ?>">
        <meta itemprop="availability" content="<?= $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>">
        <meta itemprop="url" content="<?= htmlspecialchars($siteUrl) ?>/shop/product/<?= htmlspecialchars($product['slug']) ?>">

        <span class="pd-info__price pd-info__price--current">
          <?= $formatPrice($product['price']) ?>
        </span>

        <?php if ($onSale): ?>
          <span class="pd-info__price pd-info__price--compare" aria-label="Was <?= $formatPrice($product['comparePrice']) ?>">
            <?= $formatPrice($product['comparePrice']) ?>
          </span>
          <span class="badge red pd-info__discount">Save <?= $discountPct ?>%</span>
        <?php endif; ?>
      </div>

      <!-- Stock status -->
      <div class="pd-info__stock" role="status" aria-live="polite">
        <?php if (!$inStock): ?>
          <span class="pd-info__stock-indicator pd-info__stock-indicator--out">
            <i class="fas fa-times-circle" aria-hidden="true"></i>
            Out of Stock
          </span>
        <?php elseif ($lowStock): ?>
          <span class="pd-info__stock-indicator pd-info__stock-indicator--low">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            Only <?= (int)$product['totalStock'] ?> left
          </span>
        <?php else: ?>
          <span class="pd-info__stock-indicator pd-info__stock-indicator--in">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            In Stock
          </span>
        <?php endif; ?>
      </div>

      <!-- Short description -->
      <?php if (!empty($product['shortDescription'])): ?>
        <p class="pd-info__short-desc" itemprop="description">
          <?= htmlspecialchars($product['shortDescription']) ?>
        </p>
      <?php endif; ?>

      <!-- Meta: gender, tags -->
      <div class="pd-info__meta-secondary">
        <?php if (!empty($product['gender'])): ?>
          <p class="pd-info__meta-line">
            <span class="text-label">Gender:</span>
            <span><?= htmlspecialchars(ucfirst($product['gender'])) ?></span>
          </p>
        <?php endif; ?>

        <?php if (!empty($product['tags']) && count($product['tags'])): ?>
          <p class="pd-info__meta-line">
            <span class="text-label">Tags:</span>
            <span><?= htmlspecialchars(implode(', ', $product['tags'])) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <!-- Divider -->
      <div class="flag-divider" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
      </div>

      <!-- ── PURCHASE FORM ── -->
      <form
        class="pd-form"
        id="pd-purchase-form"
        data-product-id="<?= htmlspecialchars($product['_id'] ?? $product['id'] ?? '') ?>"
        data-product-name="<?= htmlspecialchars($product['name']) ?>"
        data-product-slug="<?= htmlspecialchars($product['slug']) ?>"
        data-max-stock="<?= (int)($product['totalStock'] ?? 0) ?>"
        novalidate
      >

        <!-- Size selector -->
        <?php if (count($allSizes) > 0): ?>
        <div class="pd-form__group" id="pd-size-group">
          <div class="pd-form__label-row">
            <label class="pd-form__label" for="pd-size-select">
              Select Size <span class="pd-form__required" aria-hidden="true">*</span>
            </label>
            <button type="button" class="pd-form__size-guide" aria-label="View size guide">
              <i class="fas fa-ruler-horizontal" aria-hidden="true"></i>
              Size Guide
            </button>
          </div>

          <div class="pd-size-grid" role="group" aria-labelledby="pd-size-label" id="pd-size-btns">
            <?php foreach ($allSizes as $sizeEntry):
              $available = ($sizeEntry['stock'] ?? 0) > 0;
            ?>
              <button
                type="button"
                class="pd-size-btn <?= !$available ? 'is-disabled' : '' ?>"
                data-size="<?= htmlspecialchars($sizeEntry['size']) ?>"
                data-stock="<?= (int)($sizeEntry['stock'] ?? 0) ?>"
                aria-label="Size <?= htmlspecialchars($sizeEntry['size']) ?><?= !$available ? ' — out of stock' : '' ?>"
                aria-pressed="false"
                <?= !$available ? 'disabled' : '' ?>
              >
                <?= htmlspecialchars($sizeEntry['size']) ?>
                <?php if (!$available): ?>
                  <span class="pd-size-btn__line" aria-hidden="true"></span>
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>

          <!-- Hidden input carries selected value -->
          <input type="hidden" name="size" id="pd-selected-size" value="" required>
          <p class="pd-form__error" id="pd-size-error" role="alert" aria-live="assertive" hidden>
            Please select a size before adding to cart.
          </p>
        </div>
        <?php endif; ?>

        <!-- Quantity -->
        <div class="pd-form__group">
          <label class="pd-form__label" for="pd-qty">Quantity</label>
          <div class="pd-qty" aria-label="Quantity selector">
            <button
              type="button"
              class="pd-qty__btn pd-qty__btn--minus"
              id="pd-qty-minus"
              aria-label="Decrease quantity"
            >
              <i class="fas fa-minus" aria-hidden="true"></i>
            </button>
            <input
              type="number"
              class="pd-qty__input"
              id="pd-qty"
              name="qty"
              value="1"
              min="1"
              max="<?= (int)($product['totalStock'] ?? 1) ?: 1 ?>"
              aria-label="Quantity"
              readonly
            >
            <button
              type="button"
              class="pd-qty__btn pd-qty__btn--plus"
              id="pd-qty-plus"
              aria-label="Increase quantity"
            >
              <i class="fas fa-plus" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="pd-form__actions">
          <?php if ($inStock): ?>
            <button
              type="submit"
              class="btn btn-primary pd-form__btn-cart"
              id="pd-add-cart"
              aria-label="Add <?= htmlspecialchars($product['name']) ?> to cart"
            >
              <i class="fas fa-shopping-bag" aria-hidden="true"></i>
              Add to Cart
            </button>

            <button
              type="button"
              class="btn btn-orange pd-form__btn-buy"
              id="pd-buy-now"
              aria-label="Buy <?= htmlspecialchars($product['name']) ?> now"
            >
              Buy Now
            </button>
          <?php else: ?>
            <button class="btn pd-form__btn-cart" disabled type="button" aria-disabled="true">
              <i class="fas fa-ban" aria-hidden="true"></i>
              Out of Stock
            </button>
          <?php endif; ?>
        </div>

      </form><!-- /.pd-form -->

      <!-- Delivery / policy snippets -->
      <div class="pd-policy-row" aria-label="Delivery and returns policy">
        <div class="pd-policy-item">
          <i class="fas fa-truck" aria-hidden="true"></i>
          <span>Lusaka delivery available</span>
        </div>
        <div class="pd-policy-item">
          <i class="fas fa-undo-alt" aria-hidden="true"></i>
          <span>Easy exchanges</span>
        </div>
        <div class="pd-policy-item">
          <i class="fas fa-shield-alt" aria-hidden="true"></i>
          <span>Authentic LFS gear</span>
        </div>
      </div>

    </div><!-- /.pd-info -->
  </div><!-- /.pd-section__inner -->
</section>

<!-- ══════════════════════════════════════════
     PRODUCT DESCRIPTION TABS
     ══════════════════════════════════════════ -->
<section class="pd-tabs" aria-label="Product details tabs">
  <div class="pd-tabs__inner">

    <div class="pd-tabs__nav" role="tablist" aria-label="Product information tabs">
      <button
        class="pd-tabs__tab is-active"
        role="tab"
        aria-selected="true"
        aria-controls="tab-description"
        id="btn-description"
        type="button"
      >Description</button>

      <button
        class="pd-tabs__tab"
        role="tab"
        aria-selected="false"
        aria-controls="tab-details"
        id="btn-details"
        type="button"
      >Details &amp; Care</button>

      <button
        class="pd-tabs__tab"
        role="tab"
        aria-selected="false"
        aria-controls="tab-sizing"
        id="btn-sizing"
        type="button"
      >Size Guide</button>
    </div>

    <!-- Description panel -->
    <div
      class="pd-tabs__panel is-active"
      id="tab-description"
      role="tabpanel"
      aria-labelledby="btn-description"
    >
      <?php if (!empty($product['description'])): ?>
        <div class="pd-tabs__content" itemprop="description">
          <?= nl2br(htmlspecialchars($product['description'])) ?>
        </div>
      <?php else: ?>
        <p class="pd-tabs__empty">No description available for this product.</p>
      <?php endif; ?>
    </div>

    <!-- Details & Care panel -->
    <div
      class="pd-tabs__panel"
      id="tab-details"
      role="tabpanel"
      aria-labelledby="btn-details"
      hidden
    >
      <div class="pd-details-grid">
        <div class="pd-details-item">
          <span class="pd-details-item__icon"><i class="fas fa-tshirt" aria-hidden="true"></i></span>
          <div>
            <p class="pd-details-item__label">Material</p>
            <p class="pd-details-item__value">100% Polyester breathable fabric</p>
          </div>
        </div>
        <div class="pd-details-item">
          <span class="pd-details-item__icon"><i class="fas fa-running" aria-hidden="true"></i></span>
          <div>
            <p class="pd-details-item__label">Recommended Use</p>
            <p class="pd-details-item__value">Training runs, races, community events</p>
          </div>
        </div>
        <div class="pd-details-item">
          <span class="pd-details-item__icon"><i class="fas fa-tint" aria-hidden="true"></i></span>
          <div>
            <p class="pd-details-item__label">Care Instructions</p>
            <p class="pd-details-item__value">Machine wash cold · Do not tumble dry · Do not iron print</p>
          </div>
        </div>
        <div class="pd-details-item">
          <span class="pd-details-item__icon"><i class="fas fa-globe-africa" aria-hidden="true"></i></span>
          <div>
            <p class="pd-details-item__label">Origin</p>
            <p class="pd-details-item__value">Official LFS Zambia merchandise</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Size Guide panel -->
    <div
      class="pd-tabs__panel"
      id="tab-sizing"
      role="tabpanel"
      aria-labelledby="btn-sizing"
      hidden
    >
      <div class="pd-size-guide">
        <p class="pd-size-guide__note">
          Measurements are in centimetres. If between sizes, size up for a relaxed fit.
        </p>
        <div class="pd-size-guide__table-wrap">
          <table class="pd-size-guide__table" aria-label="Size guide measurements">
            <thead>
              <tr>
                <th scope="col">Size</th>
                <th scope="col">Chest (cm)</th>
                <th scope="col">Waist (cm)</th>
                <th scope="col">Hips (cm)</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>S</td><td>86–91</td><td>71–76</td><td>91–96</td></tr>
              <tr><td>M</td><td>91–97</td><td>76–81</td><td>96–102</td></tr>
              <tr><td>L</td><td>97–102</td><td>81–86</td><td>102–107</td></tr>
              <tr><td>XL</td><td>107–112</td><td>91–97</td><td>112–117</td></tr>
              <tr><td>XXL</td><td>117–122</td><td>97–107</td><td>122–127</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /.pd-tabs__inner -->
</section>

<!-- ══════════════════════════════════════════
     RELATED PRODUCTS
     ══════════════════════════════════════════ -->
<?php if (!empty($related) && count($related) > 0): ?>
<section class="pd-related" aria-labelledby="related-heading">
  <div class="pd-related__inner">

    <div class="pd-related__header">
      <span class="badge">More from LFS</span>
      <h2 class="text-display-md mt-2" id="related-heading">You May Also Like</h2>
    </div>

    <div class="pd-related__grid" role="list">
      <?php foreach ($related as $rel):
        $relInStock = false;
        if (!empty($rel['sizes']) && is_array($rel['sizes'])) {
          foreach ($rel['sizes'] as $s) {
            if (($s['stock'] ?? 0) > 0) { $relInStock = true; break; }
          }
        } else {
          $relInStock = ($rel['totalStock'] ?? 0) > 0;
        }
        $relImg      = $rel['thumbnail'] ?? $rel['images'][0] ?? '/images/products/placeholder.webp';
        $relSizeNames = array_map(
          fn($s) => $s['size'],
          array_filter($rel['sizes'] ?? [], fn($s) => ($s['stock'] ?? 0) > 0)
        );
      ?>
      <article class="pd-related__card" data-reveal role="listitem">
        <a href="/shop/product/<?= htmlspecialchars($rel['slug']) ?>" class="pd-related__img-link" aria-label="<?= htmlspecialchars($rel['name']) ?>">
          <div class="pd-related__img-wrap">
            <img
              src="<?= htmlspecialchars($relImg) ?>"
              alt="<?= htmlspecialchars($rel['name']) ?>"
              class="pd-related__img"
              loading="lazy"
              width="400"
              height="400"
            >
            <?php if (!$relInStock): ?>
              <span class="pd-related__sold-out">Sold Out</span>
            <?php endif; ?>
          </div>
        </a>
        <div class="pd-related__body">
          <span class="text-label text-green"><?= htmlspecialchars(str_replace('-', ' ', $rel['category'] ?? '')) ?></span>
          <h3 class="pd-related__name">
            <a href="/shop/product/<?= htmlspecialchars($rel['slug']) ?>"><?= htmlspecialchars($rel['name']) ?></a>
          </h3>
          <?php if (count($relSizeNames) > 0): ?>
          <div class="pd-related__sizes" aria-label="Sizes available">
            <?php foreach (array_slice($relSizeNames, 0, 4) as $s): ?>
              <span class="product-card__size-chip"><?= htmlspecialchars($s) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="pd-related__price-row">
            <span class="pd-related__price"><?= $formatPrice($rel['price']) ?></span>
            <a href="/shop/product/<?= htmlspecialchars($rel['slug']) ?>" class="btn btn-outline pd-related__btn">
              View
            </a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <div class="pd-related__footer">
      <a href="/shop" class="btn btn-outline">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        Back to Shop
      </a>
    </div>

  </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     SIZE GUIDE MODAL
     ══════════════════════════════════════════ -->
<div
  class="pd-modal"
  id="size-guide-modal"
  role="dialog"
  aria-modal="true"
  aria-labelledby="size-guide-title"
  aria-hidden="true"
>
  <div class="pd-modal__backdrop js-sg-close"></div>
  <div class="pd-modal__panel">
    <div class="pd-modal__header">
      <h2 class="pd-modal__title" id="size-guide-title">Size Guide</h2>
      <button class="pd-modal__close js-sg-close" type="button" aria-label="Close size guide">
        <i class="fas fa-times" aria-hidden="true"></i>
      </button>
    </div>
    <div class="pd-modal__body">
      <p class="pd-size-guide__note">
        All measurements are in centimetres. For a relaxed/race fit, go one size up.
      </p>
      <div class="pd-size-guide__table-wrap">
        <table class="pd-size-guide__table" aria-label="Full size guide">
          <thead>
            <tr>
              <th scope="col">Size</th>
              <th scope="col">Chest</th>
              <th scope="col">Waist</th>
              <th scope="col">Hips</th>
              <th scope="col">Height</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>S</td><td>86–91</td><td>71–76</td><td>91–96</td><td>165–170</td></tr>
            <tr><td>M</td><td>91–97</td><td>76–81</td><td>96–102</td><td>170–175</td></tr>
            <tr><td>L</td><td>97–102</td><td>81–86</td><td>102–107</td><td>175–180</td></tr>
            <tr><td>XL</td><td>107–112</td><td>91–97</td><td>112–117</td><td>180–185</td></tr>
            <tr><td>XXL</td><td>117–122</td><td>97–107</td><td>122–127</td><td>185–190</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     TOAST — cart confirmation
     ══════════════════════════════════════════ -->
<div class="lfs-toast" id="cart-toast" role="alert" aria-live="assertive" aria-atomic="true">
  <i class="fas fa-check-circle" aria-hidden="true"></i>
  <span id="cart-toast-msg">Item added to cart</span>
  <div class="pd-toast__actions">
    <a href="/shop/cart" class="pd-toast__link">View Cart</a>
    <span aria-hidden="true"> · </span>
    <button type="button" class="pd-toast__link" id="pd-toast-dismiss">Continue Shopping</button>
  </div>
</div>

<!-- ══════════════════════════════════════════
     STRUCTURED DATA — JSON-LD
     ══════════════════════════════════════════ -->
<?php
// Build image array with absolute URLs
$jsonImages = [];
foreach ((!empty($product['images']) ? $product['images'] : [$firstImage]) as $img) {
  $jsonImages[] = str_starts_with($img, 'http') ? $img : $siteUrl . $img;
}

$jsonLd = [
  '@context'    => 'https://schema.org',
  '@type'       => 'Product',
  'name'        => $product['name'],
  'description' => $product['description'] ?? $product['shortDescription'] ?? '',
  'url'         => $siteUrl . '/shop/product/' . $product['slug'],
  'image'       => $jsonImages,
  'sku'         => strtoupper(substr($sku, -8)),
  'brand'       => ['@type' => 'Brand', 'name' => 'LFS — Lusaka Fitness Squad'],
  'offers'      => [
    '@type'        => 'Offer',
    'url'          => $siteUrl . '/shop/product/' . $product['slug'],
    'price'        => $product['price'],
    'priceCurrency'=> 'ZMW',
    'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    'seller'       => ['@type' => 'Organization', 'name' => 'LFS — Lusaka Fitness Squad'],
  ],
];
?>
<script type="application/ld+json">
<?= json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- Page JS -->
<?php $scripts = '<script src="' . htmlspecialchars(lfs_public_url('/js/productDetails.js'), ENT_QUOTES, 'UTF-8') . '"></script>'; ?>
