/* ============================================================
   LFS — Lusaka Fitness Squad
   src/controllers/shop.controller.js

   Handles:
     • GET /shop            — product listing with filters + pagination
     • GET /shop/product/:slug — product detail page
     • POST /shop/cart/add  — add to cart (session-based)
     • GET /shop/cart       — view cart
     • POST /shop/cart/update — update cart item qty
     • POST /shop/cart/remove — remove cart item
   ============================================================ */

'use strict';

const productService = require('../services/product.service');
const { getCategoryOptions, getGenderOptions } = require('../model/product');
const {
  formatPrice: formatPriceHelper,
  generateBreadcrumbs,
  checkStockAvailability,
  buildProductJsonLd,
} = require('../utility/helpers');

/* ════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════ */

/**
 * Retrieve or initialise the session cart.
 * Cart structure: [{ productId, slug, name, price, image, size, qty }]
 */
function getCart(req) {
  if (!req.session) {
    console.warn('[LFS Shop] No session found — cart will not persist. Ensure express-session is configured.');
    return [];
  }
  if (!req.session.cart) req.session.cart = [];
  return req.session.cart;
}

/** Save cart back to session. */
function saveCart(req, cart) {
  if (req.session) req.session.cart = cart;
}

/** Compute cart summary totals. */
function cartTotals(cart) {
  const itemCount = cart.reduce((sum, item) => sum + item.qty, 0);
  const subtotal  = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
  return { itemCount, subtotal };
}

/** Format price as "K X,XXX" (Zambian Kwacha). Uses shared helper when available. */
function formatPrice(amount) {
  return formatPriceHelper(amount);
}

/* ════════════════════════════════════════════════════════════
   SHOP INDEX — GET /shop
   ════════════════════════════════════════════════════════════ */

/**
 * Render the main shop listing page.
 * Supports query params: category, gender, size, sort, page, minPrice, maxPrice
 */
exports.getShop = async (req, res, next) => {
  try {
    const {
      category = '',
      gender   = '',
      size     = '',
      sort     = 'latest',
      page     = 1,
      minPrice = '',
      maxPrice = '',
    } = req.query;

    const filters = {
      ...(category  && { category }),
      ...(gender    && { gender }),
      ...(size      && { size }),
      ...(minPrice  && { minPrice: Number(minPrice) }),
      ...(maxPrice  && { maxPrice: Number(maxPrice) }),
      sort,
      page:  Number(page),
      limit: 12,
    };

    const { products, total, pages } = await productService.findPublic(filters);

    const cart = getCart(req);
    const { itemCount } = cartTotals(cart);

    res.render('pages/shop', {
      title:       'Shop LFS Merchandise',
      description: 'High-quality running gear and regalia for Lusaka Fitness Squad members.',
      layout:      'layouts/main',

      /* Data */
      products,
      total,
      pages,
      currentPage: Number(page),

      /* Active filter state (for re-populating form) */
      filters: {
        category,
        gender,
        size,
        sort,
        minPrice,
        maxPrice,
      },

      /* Filter options from product model (single source of truth) */
      categoryOptions: getCategoryOptions(),
      genderOptions:   getGenderOptions(),

      /* Cart badge */
      cartCount: itemCount,

      /* Utility */
      formatPrice,
    });

  } catch (err) {
    next(err);
  }
};

/* ════════════════════════════════════════════════════════════
   PRODUCT DETAIL — GET /shop/product/:slug
   ════════════════════════════════════════════════════════════ */

exports.getProduct = async (req, res, next) => {
  try {
    const product = await productService.findOneBySlug(req.params.slug);

    if (!product) {
      return res.status(404).render('pages/404', {
        title: 'Product Not Found',
        description: 'That product does not exist or is no longer available.',
        layout: 'layouts/main',
      });
    }

    const related = await productService.findRelatedByCategory(product.category, product.id, 4);

    const cart = getCart(req);
    const { itemCount } = cartTotals(cart);

    const siteUrl = process.env.SITE_URL || 'https://www.lfszambia.run';

    res.render('pages/productDetails', {
      title:       `${product.name} — LFS Shop`,
      description: product.shortDescription || product.description?.slice(0, 155) || '',
      layout:      'layouts/main',
      product,
      related,
      cartCount:   itemCount,
      formatPrice,
      generateBreadcrumbs,
      checkStockAvailability,
      buildProductJsonLd,
      siteUrl,
    });

  } catch (err) {
    next(err);
  }
};

/* ════════════════════════════════════════════════════════════
   ADD TO CART — POST /shop/cart/add
   Body: { productId, size, qty }
   Returns JSON for AJAX or redirects for non-JS fallback.
   ════════════════════════════════════════════════════════════ */

exports.addToCart = async (req, res, next) => {
  try {
    const { productId, size, qty = 1 } = req.body;

    if (!productId || !size) {
      if (req.xhr || req.headers.accept?.includes('application/json')) {
        return res.status(400).json({ ok: false, message: 'Product and size are required.' });
      }
      return res.redirect('/shop');
    }

    const product = await productService.findById(productId);

    if (!product) {
      if (req.xhr || req.headers.accept?.includes('application/json')) {
        return res.status(404).json({ ok: false, message: 'Product not found.' });
      }
      return res.redirect('/shop');
    }

    /* Check size stock */
    const sizeEntry = product.sizes?.find((s) => s.size === size);
    if (!sizeEntry || sizeEntry.stock <= 0) {
      if (req.xhr || req.headers.accept?.includes('application/json')) {
        return res.status(400).json({ ok: false, message: `Size ${size} is out of stock.` });
      }
      return res.redirect(`/shop/product/${product.slug}`);
    }

    const cart       = getCart(req);
    const qtyParsed  = Math.max(1, parseInt(qty, 10) || 1);
    const cartKey    = `${productId}::${size}`;

    const existing = cart.find((i) => i.key === cartKey);

    if (existing) {
      existing.qty = Math.min(existing.qty + qtyParsed, sizeEntry.stock);
    } else {
      cart.push({
        key:       cartKey,
        productId: String(product._id),
        slug:      product.slug,
        name:      product.name,
        price:     product.price,
        image:     product.thumbnail || '/images/products/placeholder.webp',
        size,
        qty:       Math.min(qtyParsed, sizeEntry.stock),
      });
    }

    saveCart(req, cart);
    const { itemCount, subtotal } = cartTotals(cart);

    if (req.xhr || req.headers.accept?.includes('application/json')) {
      return res.json({
        ok:        true,
        message:   'Item added to cart.',
        itemCount,
        subtotal:  formatPrice(subtotal),
      });
    }

    res.redirect('/shop/cart');

  } catch (err) {
    next(err);
  }
};

/* ════════════════════════════════════════════════════════════
   VIEW CART — GET /shop/cart
   ════════════════════════════════════════════════════════════ */

exports.getCart = (req, res) => {
  const cart = getCart(req);
  const { itemCount, subtotal } = cartTotals(cart);

  res.render('pages/shop-cart', {
    title:       'Your Cart — LFS Shop',
    description: 'Review your LFS merchandise cart.',
    layout:      'layouts/main',
    cart,
    itemCount,
    subtotal:    formatPrice(subtotal),
    cartCount:   itemCount,
    formatPrice,
  });
};

/* ════════════════════════════════════════════════════════════
   UPDATE CART — POST /shop/cart/update
   Body: { key, qty }
   ════════════════════════════════════════════════════════════ */

exports.updateCart = (req, res) => {
  const { key, qty } = req.body;
  const cart = getCart(req);

  const item = cart.find((i) => i.key === key);
  if (item) {
    const newQty = parseInt(qty, 10);
    if (newQty <= 0) {
      const idx = cart.indexOf(item);
      cart.splice(idx, 1);
    } else {
      item.qty = newQty;
    }
    saveCart(req, cart);
  }

  if (req.xhr || req.headers.accept?.includes('application/json')) {
    const { itemCount, subtotal } = cartTotals(cart);
    return res.json({ ok: true, itemCount, subtotal: formatPrice(subtotal) });
  }

  res.redirect('/shop/cart');
};

/* ════════════════════════════════════════════════════════════
   REMOVE FROM CART — POST /shop/cart/remove
   Body: { key }
   ════════════════════════════════════════════════════════════ */

exports.removeFromCart = (req, res) => {
  const { key } = req.body;
  const cart = getCart(req);

  const idx = cart.findIndex((i) => i.key === key);
  if (idx !== -1) cart.splice(idx, 1);
  saveCart(req, cart);

  if (req.xhr || req.headers.accept?.includes('application/json')) {
    const { itemCount, subtotal } = cartTotals(cart);
    return res.json({ ok: true, itemCount, subtotal: formatPrice(subtotal) });
  }

  res.redirect('/shop/cart');
};
