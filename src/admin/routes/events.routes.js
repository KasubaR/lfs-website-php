/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/routes/events.routes.js — Event admin routes

   Mount point: /admin/events (from admin.routes.js)
   ============================================================ */

'use strict';

const express = require('express');
const router = express.Router();
const eventController = require('../controllers/event.controller');
const eventBannerUpload = require('../middleware/eventBannerUpload');
const csrf = require('../../middleware/csrf.middleware');

/**
 * GET /admin/events
 * Redirect to list.
 */
router.get('/', (req, res) => res.redirect('/admin/events/list'));

/**
 * GET /admin/events/list
 * List all events (with optional category/date filters).
 */
router.get('/list', eventController.getEvents);

/**
 * GET /admin/events/create
 * New event form.
 */
router.get('/create', eventController.getCreateEvent);

/**
 * POST /admin/events
 * Create event (with optional banner file upload). CSRF verified after multer so req.body._csrf is present.
 */
router.post('/', eventBannerUpload, csrf.verify, eventController.postCreateEvent);

/**
 * GET /admin/events/:id/edit
 * Edit event form.
 */
router.get('/:id/edit', eventController.getEditEvent);

/**
 * POST /admin/events/:id
 * Update event (with optional banner file upload). CSRF verified after multer so req.body._csrf is present.
 */
router.post('/:id', eventBannerUpload, csrf.verify, eventController.postUpdateEvent);

/**
 * POST /admin/events/:id/delete
 * Delete event.
 */
router.post('/:id/delete', eventController.postDeleteEvent);

module.exports = router;
