/* ============================================================
   LFS — Lusaka Fitness Squad
   app.js — Express application factory
   
   Responsibilities:
     • Express instance creation & configuration
     • Middleware stack (security, logging, body parsing, static)
     • View engine setup (EJS + express-ejs-layouts)
     • Route mounting
     • Error handling (404 + 500)
   
   server.js imports this and calls app.listen().
   Separating app from server makes testing easier.
   ============================================================ */

'use strict';

const path = require('path');
const express = require('express');
const layouts = require('express-ejs-layouts');
const session = require('express-session');

/* ── Optional middleware (install as needed) ──────────────── */
// const helmet      = require('helmet');
// const compression = require('compression');
// const morgan      = require('morgan');

/* ── Cookie & auth middleware ──────────────────────────────── */
const cookieMiddleware = require('./middleware/cookie.middleware');
const csrf             = require('./middleware/csrf.middleware');
const cookieRoutes     = require('./routes/cookie.routes');

/* ── Route modules ────────────────────────────────────────── */
const homeRouter = require('./routes/index');
const contactRouter = require('./routes/contact.routes');
const galleryPublicRouter = require('./routes/gallery.public.routes');
const adminRouter = require('./admin/routes/admin.routes');
// const eventsRouter  = require('./routes/events');
const shopRouter    = require('./routes/shop.routes');
// const newsRouter    = require('./routes/news');
// const galleryRouter = require('./routes/gallery');

/* ════════════════════════════════════════════════════════════
   CREATE APP
   ════════════════════════════════════════════════════════════ */
const app = express();

/* ════════════════════════════════════════════════════════════
   SECURITY & UTILITY MIDDLEWARE
   ════════════════════════════════════════════════════════════ */

// Helmet — sets sensible security headers
// app.use(helmet({
//   contentSecurityPolicy: {
//     directives: {
//       defaultSrc:  ["'self'"],
//       scriptSrc:   ["'self'", "'unsafe-inline'", 'cdn.tailwindcss.com', 'cdnjs.cloudflare.com'],
//       styleSrc:    ["'self'", "'unsafe-inline'", 'fonts.googleapis.com', 'cdnjs.cloudflare.com'],
//       fontSrc:     ["'self'", 'fonts.gstatic.com', 'cdnjs.cloudflare.com'],
//       imgSrc:      ["'self'", 'data:', 'images.unsplash.com'],
//       connectSrc:  ["'self'"],
//     },
//   },
// }));

// Gzip compression
// app.use(compression());

// HTTP request logger (dev format)
// app.use(morgan('dev'));

/* ════════════════════════════════════════════════════════════
   SESSION (cart storage)
   ════════════════════════════════════════════════════════════ */
app.use(session({
  secret: process.env.SESSION_SECRET || 'lfs-session-secret-change-in-production',
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    maxAge: 7 * 24 * 60 * 60 * 1000, // 7 days
  },
}));

/* ════════════════════════════════════════════════════════════
   BODY PARSERS
   ════════════════════════════════════════════════════════════ */
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

/* ════════════════════════════════════════════════════════════
   STATIC FILES
   Serve everything in /public at the root URL path.
   e.g.  public/css/main.css  →  /css/main.css
         public/js/main.js    →  /js/main.js
         public/img/logo.png  →  /img/logo.png
   ════════════════════════════════════════════════════════════ */
const PUBLIC_ROOT = path.join(__dirname, '..', 'public');

app.use(
  express.static(PUBLIC_ROOT, {
    maxAge: process.env.NODE_ENV === 'production' ? '7d' : 0,
    etag: true,
  })
);

// Favicon — serve SVG logo to avoid 404s when browsers request /favicon.ico
app.get('/favicon.ico', (req, res) => {
  res.sendFile(path.join(PUBLIC_ROOT, 'images', 'LFS-logo.svg'));
});

/* ════════════════════════════════════════════════════════════
   VIEW ENGINE — EJS + express-ejs-layouts
   
   Directory layout expected:
     views/
       layouts/main.ejs     ← wrapper (<%- body %> slot)
       pages/home.ejs       ← page content
       partials/navbar.ejs
       partials/footer.ejs
   ════════════════════════════════════════════════════════════ */
app.set('view engine', 'ejs');
app.set('views', [
  path.join(__dirname, 'views'),
  path.join(__dirname, 'admin', 'views'),
]);

// express-ejs-layouts — enables layout wrapping
app.use(layouts);
app.set('layout', 'layouts/main');           // default layout for all pages
app.set('layout extractScripts', true);      // allows <%- scripts %> blocks in pages
app.set('layout extractStyles', true);       // allows <%- styles %>  blocks in pages

/* ════════════════════════════════════════════════════════════
   TEMPLATE LOCALS
   Variables available in every EJS template automatically.
   ════════════════════════════════════════════════════════════ */
app.use((req, res, next) => {
  res.locals.siteName = 'LFS — Lusaka Fitness Squad';
  res.locals.siteUrl = process.env.SITE_URL || 'https://www.lfszambia.run';
  res.locals.currentYear = new Date().getFullYear();
  res.locals.currentPath = req.path;         // useful for active nav states
  // Cart count badge available in every template
  const cart = req.session?.cart || [];
  res.locals.cartCount = cart.reduce((sum, item) => sum + item.qty, 0);
  next();
});

/* ── Cookie consent & preferences (after body parsers, before routes) ── */
app.use(cookieMiddleware.init);
app.use(cookieMiddleware.attachLocals);

/* ── CSRF token — generate on every request, verify on admin routes ── */
app.use(csrf.generate);

/* ════════════════════════════════════════════════════════════
   ROUTES — more specific paths first so /admin/* is not caught by /
   ════════════════════════════════════════════════════════════ */
app.use('/cookies', cookieRoutes);
app.use('/contact', contactRouter);
app.use('/gallery', galleryPublicRouter);
app.use('/shop', shopRouter);
app.use('/admin', adminRouter);
app.use('/', homeRouter);

/* ════════════════════════════════════════════════════════════
   404 HANDLER
   Catches any request that didn't match a route above.
   ════════════════════════════════════════════════════════════ */
app.use((req, res) => {
  res.status(404).render('pages/404', {
    title: 'Page Not Found',
    description: 'The page you\'re looking for doesn\'t exist.',
    layout: 'layouts/main',
  });
});

/* ════════════════════════════════════════════════════════════
   GLOBAL ERROR HANDLER
   Express error middleware must have 4 parameters.
   ════════════════════════════════════════════════════════════ */
// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  const status = err.status || err.statusCode || 500;

  // Log full error in development
  if (process.env.NODE_ENV !== 'production') {
    console.error(`[LFS Error] ${status} — ${req.method} ${req.url}`);
    console.error(err.stack || err.message);
  } else {
    // Minimal log in production (no stack traces to console)
    console.error(`[LFS Error] ${status} — ${err.message}`);
  }

  res.status(status).render('pages/error', {
    title: `Error ${status}`,
    description: 'Something went wrong. Please try again shortly.',
    status,
    message: process.env.NODE_ENV !== 'production' ? err.message : 'An unexpected error occurred.',
    layout: 'layouts/main',
  });
});

module.exports = app;
