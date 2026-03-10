/* ============================================================
   LFS — Lusaka Fitness Squad
   src/middleware/auth.middleware.js
   
   Handles:
     • Reading the signed auth cookie (lfs_auth)
     • Verifying & decoding the JWT payload
     • Attaching req.user for downstream handlers
     • Protecting routes — redirects or 401s on failure
   
   Usage in routes:
     const { requireAuth, optionalAuth } = require('../middleware/auth.middleware');
   
     router.get('/dashboard', requireAuth, handler);
     router.get('/profile',   optionalAuth, handler); // sets req.user if logged in
   ============================================================ */

'use strict';

const jwt = require('jsonwebtoken');
const { readSignedCookie } = require('./cookie.middleware');
const { COOKIE_NAMES, COOKIES }  = require('../config/cookie.config');

/* ── JWT secret ──────────────────────────────────────────── */
const JWT_SECRET = process.env.JWT_SECRET
  || (process.env.NODE_ENV === 'production'
      ? (() => { throw new Error('[LFS] JWT_SECRET must be set in production'); })()
      : 'dev-jwt-secret-change-me');

/* ════════════════════════════════════════════════════════════
   DECODE COOKIE → USER
   Internal helper. Returns the decoded JWT payload or null.
   ════════════════════════════════════════════════════════════ */
function decodeAuthCookie(req) {
  const token = readSignedCookie(req, COOKIE_NAMES.AUTH);
  if (!token) return null;

  try {
    return jwt.verify(token, JWT_SECRET);
  } catch (err) {
    // expired, tampered, or invalid — treat as unauthenticated
    if (process.env.NODE_ENV !== 'production') {
      console.warn('[LFS Auth] JWT verify failed:', err.message);
    }
    return null;
  }
}

/* ════════════════════════════════════════════════════════════
   OPTIONAL AUTH
   Attaches req.user and res.locals.user if a valid token
   exists, but does NOT block the request if there's none.
   Use on public pages that personalise when logged in.
   ════════════════════════════════════════════════════════════ */
function optionalAuth(req, res, next) {
  const user = decodeAuthCookie(req);
  req.user          = user || null;
  res.locals.user   = user || null;
  res.locals.isAuth = Boolean(user);
  next();
}

/* ════════════════════════════════════════════════════════════
   REQUIRE AUTH
   Blocks unauthenticated requests.
   • API requests → 401 JSON
   • Browser requests → redirect to /admin/login (or /login)
   ════════════════════════════════════════════════════════════ */
function requireAuth(req, res, next) {
  const user = decodeAuthCookie(req);

  if (!user) {
    // JSON / XHR clients
    if (req.xhr || req.headers.accept?.includes('application/json')) {
      return res.status(401).json({ ok: false, message: 'Authentication required.' });
    }

    // Determine login path based on route prefix
    const loginPath = req.path.startsWith('/admin') ? '/admin/login' : '/login';
    return res.redirect(`${loginPath}?redirect=${encodeURIComponent(req.originalUrl)}`);
  }

  req.user          = user;
  res.locals.user   = user;
  res.locals.isAuth = true;
  next();
}

/* ════════════════════════════════════════════════════════════
   REQUIRE ROLE
   Factory — wraps requireAuth + role check.
   
   Usage:
     router.get('/admin', requireRole('admin'), handler);
     router.get('/mod',   requireRole(['admin', 'moderator']), handler);
   ════════════════════════════════════════════════════════════ */
function requireRole(roles) {
  const allowed = Array.isArray(roles) ? roles : [roles];

  return [
    requireAuth,
    function checkRole(req, res, next) {
      if (!allowed.includes(req.user?.role)) {
        if (req.xhr || req.headers.accept?.includes('application/json')) {
          return res.status(403).json({ ok: false, message: 'Forbidden.' });
        }
        return res.status(403).render('pages/error', {
          title:   'Forbidden',
          status:  403,
          message: 'You don\'t have permission to access this page.',
          layout:  'layouts/main',
        });
      }
      next();
    },
  ];
}

/* ════════════════════════════════════════════════════════════
   SIGN TOKEN
   Creates a signed JWT and sets it as the auth cookie.
   Call this after successful login.
   
   payload example: { id: user._id, email: user.email, role: 'admin' }
   ════════════════════════════════════════════════════════════ */
function signAuthCookie(res, payload) {
  const token = jwt.sign(payload, JWT_SECRET, {
    expiresIn: '7d',
    issuer:    'lfs-zambia',
    audience:  'lfs-web',
  });

  res.cookie(
    COOKIE_NAMES.AUTH,
    token,
    COOKIES.auth.options
  );

  return token;
}

/* ════════════════════════════════════════════════════════════
   CLEAR AUTH COOKIE
   Wipes the auth cookie on logout.
   ════════════════════════════════════════════════════════════ */
function clearAuthCookie(res) {
  res.clearCookie(COOKIE_NAMES.AUTH, { path: '/' });
}

/* ════════════════════════════════════════════════════════════
   EXPORTS
   ════════════════════════════════════════════════════════════ */
module.exports = {
  optionalAuth,
  requireAuth,
  requireRole,
  signAuthCookie,
  clearAuthCookie,
  decodeAuthCookie,
};
