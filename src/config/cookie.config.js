/* ============================================================
   LFS — Lusaka Fitness Squad
   src/config/cookie.config.js — Global cookie settings
   
   Central place for all cookie defaults, security options,
   and expiration constants. Import this wherever cookies
   are set or validated.
   ============================================================ */

'use strict';

/* ── Environment helpers ─────────────────────────────────── */
const isProd = process.env.NODE_ENV === 'production';

/* ════════════════════════════════════════════════════════════
   EXPIRATION CONSTANTS
   All durations in milliseconds for consistency.
   ════════════════════════════════════════════════════════════ */
const DURATION = {
  MINUTE:   60 * 1000,
  HOUR:     60 * 60 * 1000,
  DAY:      24 * 60 * 60 * 1000,
  WEEK:     7  * 24 * 60 * 60 * 1000,
  MONTH:    30 * 24 * 60 * 60 * 1000,
  YEAR:     365 * 24 * 60 * 60 * 1000,
};

/* ════════════════════════════════════════════════════════════
   COOKIE NAMES
   Single source of truth — never hardcode names elsewhere.
   ════════════════════════════════════════════════════════════ */
const COOKIE_NAMES = {
  AUTH:        'lfs_auth',         // JWT session token
  CONSENT:     'lfs_consent',      // cookie consent decision
  PREFERENCES: 'lfs_prefs',        // user UI preferences
  CSRF:        'lfs_csrf',         // CSRF protection token
};

/* ════════════════════════════════════════════════════════════
   BASE OPTIONS
   Shared security defaults applied to every cookie.
   Override per-cookie where necessary.
   ════════════════════════════════════════════════════════════ */
const BASE_OPTIONS = {
  httpOnly: true,              // JS cannot read — prevents XSS theft
  secure:   isProd,            // HTTPS only in production
  sameSite: 'lax',             // CSRF protection; 'strict' breaks OAuth
  path:     '/',               // available across entire site
};

/* ════════════════════════════════════════════════════════════
   COOKIE-SPECIFIC CONFIGS
   Spread BASE_OPTIONS then override as needed.
   ════════════════════════════════════════════════════════════ */
const COOKIES = {

  /* ── Authentication / Session ────────────────────────── */
  auth: {
    name:    COOKIE_NAMES.AUTH,
    options: {
      ...BASE_OPTIONS,
      maxAge:   DURATION.WEEK,   // 7 days rolling session
      signed:   true,            // requires cookieParser(secret)
      sameSite: 'lax',
    },
  },

  /* ── Cookie Consent ──────────────────────────────────── */
  consent: {
    name:    COOKIE_NAMES.CONSENT,
    options: {
      ...BASE_OPTIONS,
      httpOnly: false,           // front-end must read this to hide banner
      maxAge:   DURATION.YEAR,   // remember decision for 1 year
      signed:   false,
    },
  },

  /* ── User Preferences (theme, locale, etc.) ─────────── */
  preferences: {
    name:    COOKIE_NAMES.PREFERENCES,
    options: {
      ...BASE_OPTIONS,
      httpOnly: false,           // front-end reads prefs on load
      maxAge:   DURATION.YEAR,
      signed:   false,
    },
  },

  /* ── CSRF Token ──────────────────────────────────────── */
  csrf: {
    name:    COOKIE_NAMES.CSRF,
    options: {
      ...BASE_OPTIONS,
      httpOnly: false,           // JS needs to read & send in header
      sameSite: 'strict',        // stricter for CSRF token
      maxAge:   DURATION.DAY,
      signed:   false,
    },
  },

};

/* ════════════════════════════════════════════════════════════
   COOKIE-PARSER SECRET
   Used to sign/verify cookies when signed: true.
   Falls back to a default in development only.
   ════════════════════════════════════════════════════════════ */
const COOKIE_SECRET = process.env.COOKIE_SECRET
  || (isProd
      ? (() => { throw new Error('[LFS] COOKIE_SECRET must be set in production'); })()
      : 'dev-cookie-secret-change-me');

/* ════════════════════════════════════════════════════════════
   CONSENT CATEGORIES
   Granular consent levels users can accept/reject.
   ════════════════════════════════════════════════════════════ */
const CONSENT_CATEGORIES = {
  NECESSARY:   'necessary',    // always on — cannot be rejected
  ANALYTICS:   'analytics',    // usage statistics
  PREFERENCES: 'preferences',  // saves UI choices
  MARKETING:   'marketing',    // third-party tracking
};

const DEFAULT_CONSENT = {
  [CONSENT_CATEGORIES.NECESSARY]:   true,
  [CONSENT_CATEGORIES.ANALYTICS]:   false,
  [CONSENT_CATEGORIES.PREFERENCES]: false,
  [CONSENT_CATEGORIES.MARKETING]:   false,
};

/* ════════════════════════════════════════════════════════════
   EXPORTS
   ════════════════════════════════════════════════════════════ */
module.exports = {
  DURATION,
  COOKIE_NAMES,
  COOKIES,
  COOKIE_SECRET,
  CONSENT_CATEGORIES,
  DEFAULT_CONSENT,
};
