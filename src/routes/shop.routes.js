/* ============================================================
   LFS — Lusaka Fitness Squad
   routes/shop.routes.js

   Mounts under /shop in app.js:
     app.use('/shop', shopRouter);

   Routes:
     GET  /shop                     → shop listing
     GET  /shop/product/:slug       → product detail
     GET  /shop/cart                → view cart
     POST /shop/cart/add            → add to cart (AJAX-friendly)
     POST /shop/cart/update         → update item quantity
     POST /shop/cart/remove         → remove item
   ============================================================ */

'use strict';

const express      = require('express');
const router       = express.Router();
const shopCtrl     = require('../controllers/shop.controller');

/* ── Rate limiter (optional — install express-rate-limit) ── */
// const rateLimit = require('express-rate-limit');
// const cartLimiter = rateLimit({ windowMs: 60_000, max: 30, standardHeaders: true });

/* ════════════════════════════════════════════════════════════
   PUBLIC SHOP PAGES
   ════════════════════════════════════════════════════════════ */

/**
 * GET /shop
 * Main product listing with filter + pagination support.
 * Query params: category, gender, size, sort, page, minPrice, maxPrice
 */
router.get('/', shopCtrl.getShop);

/**
 * GET /shop/cart
 * View current session cart.
 * ⚠️  Must be defined BEFORE /product/:slug so "cart" isn't matched as a slug.
 */
router.get('/cart', shopCtrl.getCart);

/**
 * GET /shop/product/:slug
 * Product detail page.
 */
router.get('/product/:slug', shopCtrl.getProduct);

/* ════════════════════════════════════════════════════════════
   CART MUTATIONS (POST — AJAX-friendly, falls back to redirect)
   ════════════════════════════════════════════════════════════ */

/**
 * POST /shop/cart/add
 * Body: { productId, size, qty? }
 * Returns JSON when called via fetch/XHR; redirects otherwise.
 */
router.post('/cart/add', /* cartLimiter, */ shopCtrl.addToCart);

/**
 * POST /shop/cart/update
 * Body: { key, qty }  — set qty to 0 to remove.
 */
router.post('/cart/update', shopCtrl.updateCart);

/**
 * POST /shop/cart/remove
 * Body: { key }
 */
router.post('/cart/remove', shopCtrl.removeFromCart);

module.exports = router;
