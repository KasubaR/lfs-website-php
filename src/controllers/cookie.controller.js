/* ============================================================
   LFS — Lusaka Fitness Squad
   src/controllers/cookie.controller.js
   
   Handles:
     • POST /cookies/consent   — save accept/reject decision
     • POST /cookies/prefs     — save preference cookie values
     • DELETE /cookies/consent — withdraw consent & clear cookies
     • GET  /cookies/status    — return current consent JSON (API)
   ============================================================ */

'use strict';

const {
  COOKIES,
  COOKIE_NAMES,
  CONSENT_CATEGORIES,
  DEFAULT_CONSENT,
} = require('../config/cookie.config');

/* ════════════════════════════════════════════════════════════
   SAVE CONSENT
   POST /cookies/consent
   Body: { necessary, analytics, preferences, marketing }
         or shorthand: { accept: 'all' | 'necessary' }
   ════════════════════════════════════════════════════════════ */
function saveConsent(req, res) {
  let consent = { ...DEFAULT_CONSENT };

  /* Shorthand: "Accept All" or "Accept Necessary Only" ── */
  if (req.body.accept === 'all') {
    consent = {
      [CONSENT_CATEGORIES.NECESSARY]:   true,
      [CONSENT_CATEGORIES.ANALYTICS]:   true,
      [CONSENT_CATEGORIES.PREFERENCES]: true,
      [CONSENT_CATEGORIES.MARKETING]:   true,
    };
  } else if (req.body.accept === 'necessary') {
    consent = { ...DEFAULT_CONSENT, [CONSENT_CATEGORIES.NECESSARY]: true };
  } else {
    /* Granular: parse each category from body ─────────── */
    /* Necessary is disabled in the form so it is never submitted; we always set it true. */
    Object.values(CONSENT_CATEGORIES).forEach((cat) => {
      if (cat === CONSENT_CATEGORIES.NECESSARY) {
        consent[cat] = true;                 // always on (disabled input not in req.body)
      } else {
        consent[cat] = req.body[cat] === 'true' || req.body[cat] === true;
      }
    });
  }

  /* Set the consent cookie (secure only over HTTPS so it works on http://localhost) */
  const consentOptions = {
    ...COOKIES.consent.options,
    secure: req.secure,
  };
  res.cookie(
    COOKIE_NAMES.CONSENT,
    JSON.stringify(consent),
    consentOptions
  );

  /* If analytics rejected, remove any analytics cookies ── */
  if (!consent[CONSENT_CATEGORIES.ANALYTICS]) {
    res.clearCookie('_ga',  { path: '/' });
    res.clearCookie('_gid', { path: '/' });
    res.clearCookie('_gat', { path: '/' });
  }

  /* ── Response ─────────────────────────────────────────── */
  if (req.xhr || req.headers.accept?.includes('application/json')) {
    return res.json({ ok: true, consent });
  }

  // Redirect back to the page the banner was on
  const redirectTo = req.body.redirect || req.headers.referer || '/';
  return res.redirect(redirectTo);
}

/* ════════════════════════════════════════════════════════════
   SAVE PREFERENCES
   POST /cookies/prefs
   Body: any key-value pairs to persist as user prefs.
   e.g. { theme: 'dark', locale: 'en-ZM' }
   ════════════════════════════════════════════════════════════ */
function savePreferences(req, res) {
  // Merge incoming prefs with existing
  let existingPrefs = {};
  const rawPrefs = req.cookies[COOKIE_NAMES.PREFERENCES];

  if (rawPrefs) {
    try {
      existingPrefs = JSON.parse(rawPrefs);
    } catch {
      existingPrefs = {};
    }
  }

  /* Sanitize — only allow safe string/boolean/number values */
  const ALLOWED_KEYS = ['theme', 'locale', 'fontSize', 'reducedMotion', 'notifications'];
  const incomingPrefs = {};

  ALLOWED_KEYS.forEach((key) => {
    if (req.body[key] !== undefined) {
      incomingPrefs[key] = req.body[key];
    }
  });

  const mergedPrefs = { ...existingPrefs, ...incomingPrefs };

  res.cookie(
    COOKIE_NAMES.PREFERENCES,
    JSON.stringify(mergedPrefs),
    COOKIES.preferences.options
  );

  if (req.xhr || req.headers.accept?.includes('application/json')) {
    return res.json({ ok: true, prefs: mergedPrefs });
  }

  return res.redirect(req.headers.referer || '/');
}

/* ════════════════════════════════════════════════════════════
   WITHDRAW CONSENT
   DELETE /cookies/consent  (or POST with _method=DELETE)
   Clears consent cookie — banner will reappear on next load.
   ════════════════════════════════════════════════════════════ */
function withdrawConsent(req, res) {
  res.clearCookie(COOKIE_NAMES.CONSENT,     { path: '/' });
  res.clearCookie(COOKIE_NAMES.PREFERENCES, { path: '/' });

  // Also wipe common third-party analytics cookies
  ['_ga', '_gid', '_gat', '_fbp', 'fr'].forEach((c) => {
    res.clearCookie(c, { path: '/', domain: req.hostname });
  });

  if (req.xhr || req.headers.accept?.includes('application/json')) {
    return res.json({ ok: true, message: 'Consent withdrawn. Cookies cleared.' });
  }

  return res.redirect(req.body?.redirect || '/');
}

/* ════════════════════════════════════════════════════════════
   GET STATUS  (API helper for front-end)
   GET /cookies/status
   Returns current consent + prefs as JSON.
   ════════════════════════════════════════════════════════════ */
function getStatus(req, res) {
  return res.json({
    ok:           true,
    consentGiven: res.locals.consentGiven,
    consent:      res.locals.consent,
    prefs:        res.locals.prefs,
  });
}

/* ════════════════════════════════════════════════════════════
   EXPORTS
   ════════════════════════════════════════════════════════════ */
module.exports = {
  saveConsent,
  savePreferences,
  withdrawConsent,
  getStatus,
};
