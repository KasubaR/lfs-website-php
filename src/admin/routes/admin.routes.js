/* ============================================================
   LFS Admin — admin.routes.js
   Routes for /admin (dashboard, etc.)
   ============================================================ */

'use strict';

const express        = require('express');
const router         = express.Router();
const galleryRouter  = require('./gallery.routes');
const eventsRouter   = require('./events.routes');
const productsRouter = require('./products.routes');
const csrf           = require('../../middleware/csrf.middleware');
const eventService   = require('../../services/event.service');

/* Verify CSRF token on state-changing admin requests. For multipart event create/update, body is parsed by multer in events router — verify runs there after upload. */
router.use((req, res, next) => {
  const isMultipartEventPost = req.method === 'POST'
    && req.headers['content-type']
    && req.headers['content-type'].indexOf('multipart/form-data') === 0
    && (req.path === '/events' || /^\/events\/[^/]+$/.test(req.path));
  if (isMultipartEventPost) return next();
  return csrf.verify(req, res, next);
});

/* ════════════════════════════════════════════════════════════
   PLACEHOLDER DATA
   Replace with real DB queries as the project matures.
   ════════════════════════════════════════════════════════════ */
const defaultStats = {
  totalMembers: 1240,
  activeMembers: 892,
  upcomingEvents: 6,
  pendingOrders: 12,
  monthlyRevenue: 45000,
  galleryUploads: 34,
};

const defaultAdminUser = {
  name: 'Admin User',
  email: 'admin@lfszambia.run',
  role: 'admin',
};

/* ════════════════════════════════════════════════════════════
   GET /admin
   Redirect to dashboard.
   ════════════════════════════════════════════════════════════ */
router.get('/', (req, res) => {
  res.redirect('/admin/dashboard');
});

/* ════════════════════════════════════════════════════════════
   GET /admin/dashboard
   Render dashboard with stats and upcoming events.
   ════════════════════════════════════════════════════════════ */
router.get('/dashboard', async (req, res, next) => {
  try {
    const upcomingEvents = await eventService.getUpcomingEvents(5);
    const stats = Object.assign({}, defaultStats, {
      upcomingEvents: Array.isArray(upcomingEvents) ? upcomingEvents.length : 0,
    });

    res.render('dashboard/index', {
      layout: 'layouts/admin',
      title: 'Dashboard',
      pageTitle: 'Dashboard',
      activePage: 'dashboard',
      adminUser: defaultAdminUser,
      stats,
      upcomingEvents,
      recentActivity: [],
      pendingTasks: { orders: 0, events: 0, gallery: 0, memberships: 0 },
      notifications: { unread: 0, items: [] },
      counts: { pendingMembers: 0, pendingOrders: 0, pendingGallery: 0 },
      chartData: { members: [], events: [], sales: [], gallery: [] },
    });
  } catch (err) {
    next(err);
  }
});

/* ════════════════════════════════════════════════════════════
   GET /admin/shop
   Backwards-compatible alias → products admin.
   ════════════════════════════════════════════════════════════ */
router.get('/shop', (req, res) => {
  res.redirect('/admin/products');
});

/* ── Gallery (albums, upload, media management) ───────────── */
router.use('/gallery', galleryRouter);

/* ── Events (list, create, edit, delete) ───────────────────── */
router.use('/events', eventsRouter);

/* ── Products (shop admin CRUD) ────────────────────────────── */
router.use('/products', productsRouter);

module.exports = router;
