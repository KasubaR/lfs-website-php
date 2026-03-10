# Cookie Implementation — Integration Guide

## Files Created

```
src/
├── config/
│    └── cookie.config.js          ← brand tokens, names, durations, consent categories
├── controllers/
│    └── cookie.controller.js      ← saveConsent, savePreferences, withdrawConsent, getStatus
├── middleware/
│    ├── cookie.middleware.js       ← init (cookie-parser), attachLocals, requireConsent
│    └── auth.middleware.js         ← requireAuth, requireRole, signAuthCookie, clearAuthCookie
├── routes/
│    └── cookie.routes.js          ← POST /cookies/consent, POST /cookies/prefs, etc.
├── public/js/
│    └── cookie-banner.js          ← client-side banner logic (no dependencies)
├── views/partials/
│    └── cookie-banner.ejs         ← server-rendered banner with no-JS fallback
└── .env.example                   ← copy to .env and fill in secrets
```

---

## 1. Wire into `app.js`

Add these lines in the order shown:

```js
// ── Cookie & auth middleware ──────────────────────────────
const cookieMiddleware = require('./middleware/cookie.middleware');
const cookieRoutes     = require('./routes/cookie.routes');

// After body parsers, before routes:
app.use(cookieMiddleware.init);           // mounts cookie-parser with secret
app.use(cookieMiddleware.attachLocals);   // adds consent/prefs to res.locals

// Routes
app.use('/cookies', cookieRoutes);
```

---

## 2. Add the banner to your layout

In `views/layouts/main.ejs`, just before `</body>`:

```ejs
<%- include('../partials/cookie-banner') %>
<script src="/js/cookie-banner.js"></script>
```

---

## 3. Protect routes with auth

```js
const { requireAuth, requireRole, signAuthCookie, clearAuthCookie } = require('../middleware/auth.middleware');

// Protect a route
router.get('/dashboard', requireAuth, (req, res) => {
  res.render('pages/dashboard', { user: req.user });
});

// Role-based
router.get('/admin', requireRole('admin'), handler);

// Login handler — sign the cookie on success
router.post('/login', async (req, res) => {
  const user = await User.findByCredentials(req.body.email, req.body.password);
  if (!user) return res.redirect('/login?error=invalid');

  signAuthCookie(res, { id: user._id, email: user.email, role: user.role });
  res.redirect('/dashboard');
});

// Logout
router.post('/logout', (req, res) => {
  clearAuthCookie(res);
  res.redirect('/');
});
```

---

## 4. React to consent changes in the browser

```js
// Load analytics only after consent is given
window.addEventListener('lfs:consent', (e) => {
  if (e.detail.analytics) {
    // Load GA or other analytics here
  }
});
```

---

## 5. Withdraw consent (privacy settings page)

```html
<button onclick="window.LFSCookies.withdraw()">Withdraw Cookie Consent</button>
```

---

## 6. Environment variables

Copy `.env.example` to `.env` and set real values:

```
COOKIE_SECRET=<long-random-string>
JWT_SECRET=<another-long-random-string>
```

Generate secure secrets:
```bash
node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"
```
