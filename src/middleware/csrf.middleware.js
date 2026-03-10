/* ============================================================
   LFS — Lusaka Fitness Squad
   src/middleware/csrf.middleware.js — CSRF protection

   Strategy: double-submit cookie
     • generate  — mints a token, sets the lfs_csrf cookie, and
                   exposes it as res.locals.csrfToken for EJS forms.
     • verify    — on state-changing methods (POST/PUT/PATCH/DELETE)
                   checks that the submitted token matches the cookie.

   Token submission:
     HTML forms  →  <input type="hidden" name="_csrf" value="...">
     fetch/XHR   →  X-CSRF-Token request header (read from
                    <meta name="csrf-token"> or the lfs_csrf cookie)

   Mount order (app.js):
     1. cookieMiddleware.init        (cookie-parser)
     2. cookieMiddleware.attachLocals
     3. csrf.generate                ← sets cookie + res.locals
     … routes …

   Admin router (admin.routes.js):
     router.use(csrf.verify)         ← blocks bad POSTs
   ============================================================ */

'use strict';

const crypto = require('crypto');
const { COOKIE_NAMES, COOKIES } = require('../config/cookie.config');

const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

/** Generate a cryptographically random 64-char hex token. */
function makeToken() {
  return crypto.randomBytes(32).toString('hex');
}

/* ════════════════════════════════════════════════════════════
   GENERATE
   Runs on every request. Mints a token if none exists,
   refreshes the cookie, and exposes the token to templates.
   ════════════════════════════════════════════════════════════ */
function generate(req, res, next) {
  let token = req.cookies[COOKIE_NAMES.CSRF];

  // Validate the existing cookie: must be a 64-char hex string.
  if (!token || !/^[0-9a-f]{64}$/.test(token)) {
    token = makeToken();
    res.cookie(COOKIE_NAMES.CSRF, token, COOKIES.csrf.options);
  }

  // Expose to every EJS template via res.locals.csrfToken.
  res.locals.csrfToken = token;
  next();
}

/* ════════════════════════════════════════════════════════════
   VERIFY
   Rejects state-changing requests without a valid token.
   Safe methods (GET, HEAD, OPTIONS) pass through unchanged.
   ════════════════════════════════════════════════════════════ */
function verify(req, res, next) {
  if (SAFE_METHODS.has(req.method)) return next();

  const cookieToken = req.cookies[COOKIE_NAMES.CSRF];

  // Accept token from form body OR request header (for fetch/XHR).
  const submitted = req.body?._csrf || req.headers['x-csrf-token'];

  if (!cookieToken || !submitted || cookieToken !== submitted) {
    // JSON / XHR / multipart clients get a JSON error.
    const isJson = req.xhr
      || (req.headers.accept || '').includes('application/json')
      || (req.headers['content-type'] || '').includes('multipart');

    if (isJson) {
      return res.status(403).json({ ok: false, message: 'Invalid CSRF token.' });
    }

    return res.status(403).render('pages/error', {
      title:   'Forbidden',
      status:  403,
      message: 'Invalid or missing CSRF token. Please go back and try again.',
      layout:  'layouts/main',
    });
  }

  next();
}

module.exports = { generate, verify };
