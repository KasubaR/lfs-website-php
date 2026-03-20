<?php
/**
 * LFS SHOP — partials/productCard.php
 *
 * Expected variables:
 *   $product      array   — product data from service/DB
 *   $formatPrice  callable — function(int $cents): string → 'K X,XXX'
 *                            Falls back to a built-in formatter if not provided.
 *
 * $product keys used:
 *   _id, slug, name, category, gender, thumbnail, images[],
 *   sizes[] (each: ['size'=>string, 'stock'=>int]),
 *   totalStock, price, comparePrice, featured
 */

// Built-in price formatter fallback
if (!isset($formatPrice) || !is_callable($formatPrice)) {
    $formatPrice = function (int|float $amount): string {
        return 'K ' . number_format($amount, 0, '.', ',');
    };
}

$inStock      = false;
if (!empty($product['sizes'])) {
    foreach ($product['sizes'] as $s) {
        if (($s['stock'] ?? 0) > 0) { $inStock = true; break; }
    }
} else {
    $inStock = ($product['totalStock'] ?? 0) > 0;
}

$onSale      = !empty($product['comparePrice']) && $product['comparePrice'] > $product['price'];
$firstImage  = $product['thumbnail'] ?? $product['images'][0] ?? '/images/products/placeholder.webp';
$productUrl  = '/shop/product/' . htmlspecialchars($product['slug']);

$sizesInStock = [];
foreach ($product['sizes'] ?? [] as $s) {
    if (($s['stock'] ?? 0) > 0) {
        $sizesInStock[] = $s['size'];
    }
}

$categoryLabel = str_replace('-', ' ', $product['category'] ?? '');
?>

<article
  class="product-card"
  data-product-id="<?= htmlspecialchars($product['_id'] ?? '') ?>"
  data-product-slug="<?= htmlspecialchars($product['slug'] ?? '') ?>"
  data-category="<?= htmlspecialchars($product['category'] ?? '') ?>"
  data-gender="<?= htmlspecialchars($product['gender'] ?? '') ?>"
  data-total-stock="<?= (int)($product['totalStock'] ?? 0) ?>"
  itemscope
  itemtype="https://schema.org/Product"
  aria-label="<?= htmlspecialchars($product['name'] ?? '') ?>"
>

<!-- ── Image wrapper ── -->
<div class="product-card__img-wrap" tabindex="-1" aria-hidden="true">
    <img
      src="<?= htmlspecialchars($firstImage) ?>"
      alt="<?= htmlspecialchars($product['name'] ?? '') ?>"
      class="product-card__img"
      loading="lazy"
      width="400"
      height="400"
      itemprop="image"
    >

    <?php if ($onSale): ?>
      <span class="product-card__badge product-card__badge--sale">SALE</span>
    <?php endif; ?>

    <?php if (!$inStock): ?>
      <span class="product-card__badge product-card__badge--sold-out">Sold Out</span>
    <?php endif; ?>

    <?php if (!empty($product['featured'])): ?>
      <span class="product-card__badge product-card__badge--featured">
        <i class="fas fa-star" aria-hidden="true"></i> Featured
      </span>
    <?php endif; ?>

    <!-- Quick view overlay -->
    <button
      class="product-card__quick-view"
      data-product-id="<?= htmlspecialchars($product['_id'] ?? '') ?>"
      data-slug="<?= htmlspecialchars($product['slug'] ?? '') ?>"
      aria-label="Quick view <?= htmlspecialchars($product['name'] ?? '') ?>"
      type="button"
    >
      <i class="fas fa-eye" aria-hidden="true"></i>
      Quick View
    </button>
  </div>

  <!-- ── Body ── -->
  <div class="product-card__body">

    <!-- Category label -->
    <span class="product-card__category text-label" aria-label="Category: <?= htmlspecialchars($product['category'] ?? '') ?>">
      <?= htmlspecialchars($categoryLabel) ?>
    </span>

    <!-- Name -->
    <h3 class="product-card__name" itemprop="name">
      <a href="<?= $productUrl ?>"><?= htmlspecialchars($product['name'] ?? '') ?></a>
    </h3>

    <!-- Sizes available -->
    <?php if (!empty($sizesInStock)): ?>
    <div class="product-card__sizes" aria-label="Available sizes">
      <?php foreach (array_slice($sizesInStock, 0, 5) as $size): ?>
        <span class="product-card__size-chip"><?= htmlspecialchars($size) ?></span>
      <?php endforeach; ?>
      <?php if (count($sizesInStock) > 5): ?>
        <span class="product-card__size-chip product-card__size-chip--more">+<?= count($sizesInStock) - 5 ?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Price row -->
    <div class="product-card__price-row" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
      <meta itemprop="priceCurrency" content="ZMW">
      <meta itemprop="price" content="<?= htmlspecialchars($product['price'] ?? '') ?>">
      <meta itemprop="availability" content="<?= $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>">
      <meta itemprop="url" content="<?= $productUrl ?>">

      <span class="product-card__price product-card__price--current">
        <?= $formatPrice($product['price'] ?? 0) ?>
      </span>

      <?php if ($onSale): ?>
        <span class="product-card__price product-card__price--compare" aria-label="Was <?= $formatPrice($product['comparePrice']) ?>">
          <?= $formatPrice($product['comparePrice']) ?>
        </span>
      <?php endif; ?>

      <!-- Stock status -->
      <?php if (!$inStock): ?>
        <span class="product-card__stock product-card__stock--out">Out of Stock</span>
      <?php elseif (($product['totalStock'] ?? 0) <= 5 && ($product['totalStock'] ?? 0) > 0): ?>
        <span class="product-card__stock product-card__stock--low">Only <?= (int) $product['totalStock'] ?> left</span>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="product-card__actions">

      <?php if ($inStock): ?>
        <button
          class="btn btn-primary product-card__btn-cart product-card__btn-cart--icon js-quick-add"
          data-product-id="<?= htmlspecialchars($product['_id'] ?? '') ?>"
          data-slug="<?= htmlspecialchars($product['slug'] ?? '') ?>"
          data-sizes="<?= htmlspecialchars(json_encode($sizesInStock)) ?>"
          type="button"
          aria-label="Add <?= htmlspecialchars($product['name'] ?? '') ?> to cart"
        >
          <i class="fas fa-shopping-bag" aria-hidden="true"></i>
        </button>
      <?php else: ?>
        <button class="btn btn-outline product-card__btn-cart" disabled type="button">
          <i class="fas fa-ban" aria-hidden="true"></i>
          Out of Stock
        </button>
      <?php endif; ?>

      <a
        href="<?= $productUrl ?>"
        class="btn btn-outline product-card__btn-detail"
        aria-label="View details for <?= htmlspecialchars($product['name'] ?? '') ?>"
      >
        View Details
      </a>

    </div>
  </div><!-- /.product-card__body -->

</article>
