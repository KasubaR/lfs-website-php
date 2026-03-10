/* ============================================================
   LFS — Lusaka Fitness Squad
   src/routes/cookie.routes.js
   
   Mount in app.js:
     const cookieRoutes = require('./routes/cookie.routes');
     app.use('/cookies', cookieRoutes);
   
   Routes:
     POST   /cookies/consent   → save consent decision
     POST   /cookies/prefs     → save UI preferences
     POST   /cookies/withdraw  → clear all consent cookies
     GET    /cookies/status    → return current state (JSON)
   ============================================================ */

'use strict';

const express    = require('express');
const rateLimit  = require('express-rate-limit');
const controller = require('../controllers/cookie.controller');

const router = express.Router();

/* ── Rate limit: 20 requests per minute per IP for cookie mutation ── */
const limiter = rateLimit({ windowMs: 60_000, max: 20 });

/* ── Save consent (accept all / necessary / granular) ────── */
router.post('/consent', limiter, controller.saveConsent);

/* ── Save user preferences (theme, locale, etc.) ─────────── */
router.post('/prefs', limiter, controller.savePreferences);

/* ── Withdraw consent & clear cookies ────────────────────── */
router.post('/withdraw', limiter, controller.withdrawConsent);

/* ── Status API — returns consent + prefs as JSON ────────── */
router.get('/status', controller.getStatus);

module.exports = router;
