/* ============================================================
   LFS — Lusaka Fitness Squad
   src/middleware/cookie.middleware.js
   
   Handles:
     • Parsing signed & unsigned cookies via cookie-parser
     • Attaching consent state to res.locals (available in EJS)
     • Parsing user preference cookies into res.locals
     • Cookie validation helpers
   
   Mount BEFORE routes in app.js:
     const cookieMiddleware = require('./middleware/cookie.middleware');
     app.use(cookieMiddleware.init);
     app.use(cookieMiddleware.attachLocals);
   ============================================================ */

'use strict';

const cookieParser = require('cookie-parser');
const {
  COOKIE_SECRET,
  COOKIE_NAMES,
  CONSENT_CATEGORIES,
  DEFAULT_CONSENT,
} = require('../config/cookie.config');

/* ════════════════════════════════════════════════════════════
   INIT — cookie-parser with signing secret
   This must be the first middleware so subsequent handlers
   can access req.cookies and req.signedCookies.
   ════════════════════════════════════════════════════════════ */
const init = cookieParser(COOKIE_SECRET);

/* ════════════════════════════════════════════════════════════
   ATTACH LOCALS
   Reads consent + preferences cookies and exposes them to
   every EJS template via res.locals.
   ════════════════════════════════════════════════════════════ */
function attachLocals(req, res, next) {
  /* ── Consent ─────────────────────────────────────────── */
  const consentRaw = req.cookies[COOKIE_NAMES.CONSENT];
  let consent = { ...DEFAULT_CONSENT };
  let consentGiven = false;

  if (consentRaw) {
    try {
      const parsed = JSON.parse(consentRaw);
      consent = { ...DEFAULT_CONSENT, ...parsed };
      consentGiven = true;
    } catch {
      // malformed cookie — treat as no consent given
      consentGiven = false;
    }
  }

  res.locals.consent      = consent;
  res.locals.consentGiven = consentGiven;
  res.locals.showBanner   = !consentGiven;   // EJS: <% if (showBanner) { %>

  /* ── Preferences ─────────────────────────────────────── */
  const prefsRaw = req.cookies[COOKIE_NAMES.PREFERENCES];
  let prefs = {};

  if (prefsRaw) {
    try {
      prefs = JSON.parse(prefsRaw);
    } catch {
      prefs = {};
    }
  }

  res.locals.prefs = prefs;

  next();
}

/* ════════════════════════════════════════════════════════════
   HAS CONSENT
   Middleware factory — protect routes / features that need
   a specific consent category.
   
   Usage:
     router.get('/analytics-page',
       requireConsent(CONSENT_CATEGORIES.ANALYTICS),
       handler
     );
   ════════════════════════════════════════════════════════════ */
function requireConsent(category) {
  return function checkConsent(req, res, next) {
    const consent = res.locals.consent || DEFAULT_CONSENT;

    if (consent[category]) {
      return next();
    }

    // Consent not given — respond with 403 or redirect
    if (req.xhr || req.headers.accept?.includes('application/json')) {
      return res.status(403).json({
        ok:      false,
        message: `Consent required for category: ${category}`,
      });
    }

    return res.redirect('/?consent_required=' + encodeURIComponent(category));
  };
}

/* ════════════════════════════════════════════════════════════
   VALIDATE COOKIE VALUE
   Utility — verify a raw cookie value meets basic rules
   (non-empty, reasonable length, no injection chars).
   ════════════════════════════════════════════════════════════ */
function isValidCookieValue(value) {
  if (typeof value !== 'string') return false;
  if (value.length === 0 || value.length > 4096) return false;
  // Reject values with raw newlines or control characters
  if (/[\r\n\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/.test(value)) return false;
  return true;
}

/* ════════════════════════════════════════════════════════════
   READ SIGNED COOKIE
   Safe wrapper — returns null instead of throwing when a
   signed cookie is missing or tampered with.
   ════════════════════════════════════════════════════════════ */
function readSignedCookie(req, name) {
  const value = req.signedCookies?.[name];
  // cookie-parser sets tampered cookies to false
  if (value === false || value === undefined) return null;
  return value;
}

/* ════════════════════════════════════════════════════════════
   CLEAR ALL LFS COOKIES
   Wipes every LFS cookie — used on logout or account delete.
   ════════════════════════════════════════════════════════════ */
function clearAllCookies(res) {
  Object.values(COOKIE_NAMES).forEach((name) => {
    res.clearCookie(name, { path: '/' });
  });
}

/* ════════════════════════════════════════════════════════════
   EXPORTS
   ════════════════════════════════════════════════════════════ */
module.exports = {
  init,
  attachLocals,
  requireConsent,
  isValidCookieValue,
  readSignedCookie,
  clearAllCookies,
};
