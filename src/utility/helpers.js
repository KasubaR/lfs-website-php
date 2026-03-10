/* ============================================================
   LFS — Lusaka Fitness Squad
   src/utils/helpers.js

   Shared helper functions used across controllers, views, and
   client-side scripts (via EJS locals or require()).

   Exports:
     formatPrice(amount)
     generateBreadcrumbs(req, overrides?)
     checkStockAvailability(product, size?)
     slugify(text)
     truncate(str, maxLen)
     categoryLabel(slug)
   ============================================================ */

'use strict';

/* ════════════════════════════════════════════════════════════
   PRICE FORMATTING
   ════════════════════════════════════════════════════════════ */

/**
 * Format a number as Zambian Kwacha.
 * e.g.  250    → "K 250"
 *       1500   → "K 1,500"
 *       2999.5 → "K 2,999.50"
 *
 * @param {number} amount
 * @param {boolean} [showCents=false] — show decimal places when true
 * @returns {string}
 */
function formatPrice(amount, showCents = false) {
  if (amount == null || isNaN(amount)) return 'K —';

  const num = Number(amount);
  const opts = {
    minimumFractionDigits: showCents ? 2 : 0,
    maximumFractionDigits: showCents ? 2 : 0,
  };

  return `K ${num.toLocaleString('en-ZM', opts)}`;
}

/**
 * Format a price range.
 * @param {number} min
 * @param {number} max
 * @returns {string}  e.g. "K 250 – K 500"
 */
function formatPriceRange(min, max) {
  if (min === max) return formatPrice(min);
  return `${formatPrice(min)} – ${formatPrice(max)}`;
}

/* ════════════════════════════════════════════════════════════
   BREADCRUMB GENERATION
   ════════════════════════════════════════════════════════════ */

/**
 * Generate a breadcrumb trail from the current request path.
 *
 * Returns an array of { label, href, active } objects.
 * The last item is always marked active: true (current page).
 *
 * @param {import('express').Request} req
 * @param {Array<{label: string, href?: string}>} [overrides]
 *   Pass explicit segments to override auto-detection.
 *   e.g. [{ label: 'Shop', href: '/shop' }, { label: 'LFS Running Shirt' }]
 * @returns {Array<{label: string, href: string|null, active: boolean}>}
 */
function generateBreadcrumbs(req, overrides = null) {
  /** Static label map for known path segments */
  const labelMap = {
    shop:        'Shop',
    product:     null,          // skip "product" segment — not user-friendly
    cart:        'Cart',
    gallery:     'Gallery',
    contact:     'Contact',
    admin:       'Admin',
    about:       'About',
    events:      'Events',
    news:        'News',
    cookies:     'Cookie Settings',
  };

  const crumbs = [{ label: 'Home', href: '/', active: false }];

  if (overrides) {
    overrides.forEach((o, i) => {
      crumbs.push({
        label:  o.label,
        href:   o.href || null,
        active: i === overrides.length - 1,
      });
    });
    // Ensure last crumb is marked active
    if (crumbs.length > 1) crumbs[crumbs.length - 1].active = true;
    return crumbs;
  }

  // Auto-generate from URL path
  const segments = req.path.split('/').filter(Boolean);
  let builtPath  = '';

  segments.forEach((seg, i) => {
    builtPath += `/${seg}`;

    // Skip "product" literal segment — it's a URL namespace, not meaningful to users
    if (seg === 'product') return;

    const isLast  = i === segments.length - 1;
    const mapped  = labelMap[seg.toLowerCase()];

    // If mapped to null → skip
    if (mapped === null) return;

    const label = mapped || toTitleCase(seg.replace(/-/g, ' '));

    crumbs.push({
      label,
      href:   isLast ? null : builtPath,
      active: isLast,
    });
  });

  // Mark the true last item active
  if (crumbs.length > 1) {
    crumbs.forEach((c) => { c.active = false; });
    crumbs[crumbs.length - 1].active = true;
  }

  return crumbs;
}

/* ════════════════════════════════════════════════════════════
   STOCK AVAILABILITY
   ════════════════════════════════════════════════════════════ */

/**
 * Check whether a product (or a specific size) is in stock.
 *
 * @param {object} product   Product object from service (Supabase / app shape)
 * @param {string} [size]    Optional — check stock for a specific size
 * @returns {{
 *   inStock:     boolean,
 *   totalStock:  number,
 *   sizeStock:   number|null,   // stock for requested size, or null
 *   status:      'in-stock'|'low-stock'|'out-of-stock',
 *   statusLabel: string,
 * }}
 */
function checkStockAvailability(product, size = null) {
  if (!product) {
    return {
      inStock:     false,
      totalStock:  0,
      sizeStock:   null,
      status:      'out-of-stock',
      statusLabel: 'Out of Stock',
    };
  }

  /* Total stock across all sizes */
  let totalStock = product.totalStock ?? 0;
  if (product.sizes?.length > 0) {
    totalStock = product.sizes.reduce((sum, s) => sum + (s.stock || 0), 0);
  }

  /* Size-specific stock */
  let sizeStock = null;
  if (size && product.sizes?.length > 0) {
    const entry = product.sizes.find((s) => s.size === size);
    sizeStock   = entry ? entry.stock : 0;
  }

  const effectiveStock = sizeStock !== null ? sizeStock : totalStock;
  const inStock        = effectiveStock > 0;

  let status, statusLabel;

  if (!inStock) {
    status      = 'out-of-stock';
    statusLabel = 'Out of Stock';
  } else if (effectiveStock <= 5) {
    status      = 'low-stock';
    statusLabel = `Only ${effectiveStock} left`;
  } else {
    status      = 'in-stock';
    statusLabel = 'In Stock';
  }

  return { inStock, totalStock, sizeStock, status, statusLabel };
}

/**
 * Get the maximum purchasable quantity for a product + size.
 * @param {object} product
 * @param {string} size
 * @returns {number}
 */
function getMaxQty(product, size) {
  const { sizeStock, totalStock } = checkStockAvailability(product, size);
  return Math.max(0, sizeStock !== null ? sizeStock : totalStock);
}

/* ════════════════════════════════════════════════════════════
   STRING UTILITIES
   ════════════════════════════════════════════════════════════ */

/**
 * Convert a string to a URL-safe slug.
 * "LFS Running Shirt 2024" → "lfs-running-shirt-2024"
 * @param {string} text
 * @returns {string}
 */
function slugify(text) {
  return String(text)
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')    // remove non-word chars (except - and space)
    .replace(/[\s_]+/g, '-')     // spaces/underscores → hyphens
    .replace(/--+/g, '-')        // collapse multiple hyphens
    .replace(/^-+|-+$/g, '');    // trim leading/trailing hyphens
}

/**
 * Truncate a string to maxLen characters, appending "…" if cut.
 * @param {string} str
 * @param {number} maxLen
 * @returns {string}
 */
function truncate(str, maxLen = 160) {
  if (!str || str.length <= maxLen) return str ?? '';
  return str.slice(0, maxLen).replace(/\s+\S*$/, '') + '…';
}

/**
 * Title-case a string.
 * "running kits" → "Running Kits"
 * @param {string} str
 * @returns {string}
 */
function toTitleCase(str) {
  return String(str).replace(
    /\w\S*/g,
    (word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
  );
}

/* ════════════════════════════════════════════════════════════
   CATEGORY LABELS (from product model — single source of truth)
   ════════════════════════════════════════════════════════════ */

const productModel = require('../model/product');

/**
 * Get the human-readable label for a product category slug.
 * @param {string} slug
 * @returns {string}
 */
function categoryLabel(slug) {
  const labels = productModel.PRODUCT_CATEGORY_LABELS || {};
  return labels[slug] || toTitleCase((slug || '').replace(/-/g, ' '));
}

/**
 * All available category options (for building filter UIs).
 * Delegates to product model.
 * @returns {Array<{value: string, label: string}>}
 */
function getCategoryOptions() {
  return productModel.getCategoryOptions();
}

/* ════════════════════════════════════════════════════════════
   STRUCTURED DATA (JSON-LD)
   ════════════════════════════════════════════════════════════ */

/**
 * Build a Schema.org Product JSON-LD object for SEO.
 * @param {object} product   Lean product document
 * @param {string} siteUrl   Base URL, e.g. "https://www.lfszambia.run"
 * @returns {object}
 */
function buildProductJsonLd(product, siteUrl = 'https://www.lfszambia.run') {
  const inStock = (product.sizes?.some((s) => s.stock > 0)) ?? (product.totalStock > 0);

  return {
    '@context':   'https://schema.org',
    '@type':      'Product',
    name:         product.name,
    description:  product.description || product.shortDescription || '',
    url:          `${siteUrl}/shop/product/${product.slug}`,
    image:        product.images?.map((img) =>
                    img.startsWith('http') ? img : `${siteUrl}${img}`
                  ) || [],
    sku:          product._id?.toString() || product.slug,
    brand: {
      '@type': 'Brand',
      name:    'LFS — Lusaka Fitness Squad',
    },
    offers: {
      '@type':        'Offer',
      url:            `${siteUrl}/shop/product/${product.slug}`,
      price:          product.price,
      priceCurrency:  'ZMW',
      priceValidUntil: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      availability:   inStock
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
      seller: {
        '@type': 'Organization',
        name:    'LFS — Lusaka Fitness Squad',
      },
    },
  };
}

/* ════════════════════════════════════════════════════════════
   EXPORTS
   ════════════════════════════════════════════════════════════ */

module.exports = {
  formatPrice,
  formatPriceRange,
  generateBreadcrumbs,
  checkStockAvailability,
  getMaxQty,
  slugify,
  truncate,
  toTitleCase,
  categoryLabel,
  getCategoryOptions,
  buildProductJsonLd,
};
