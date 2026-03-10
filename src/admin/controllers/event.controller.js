/* ============================================================
   LFS — Lusaka Fitness Squad
   admin/controllers/event.controller.js

   Event admin: list, create, edit, update, delete.
   Uses event.service (Supabase) and EVENT_CATEGORIES from model.
   ============================================================ */

'use strict';

const path = require('path');
const fs = require('fs');
const eventService = require('../../services/event.service');
const { EVENT_CATEGORIES } = require('../../model/event');

/** Web root for resolving banner paths (e.g. /images/events/…). On cPanel set PUBLIC_ROOT e.g. /home/lfszambia/public_html */
const IS_PROD = process.env.NODE_ENV === 'production';
const DEFAULT_PUBLIC_ROOT = path.join(__dirname, '..', '..', '..', 'public');
const PUBLIC_ROOT = (IS_PROD && process.env.PUBLIC_ROOT) ? process.env.PUBLIC_ROOT : DEFAULT_PUBLIC_ROOT;

/* ════════════════════════════════════════════════════════════
   LIST EVENTS — GET /admin/events, GET /admin/events/list
   ════════════════════════════════════════════════════════════ */

async function getEvents(req, res, next) {
  try {
    const { category, fromDate, toDate } = req.query;
    const opts = { limit: 100 };
    if (category) opts.category = category;
    if (fromDate) opts.fromDate = fromDate;
    if (toDate) opts.toDate = toDate;

    let events = [];
    let eventsError = null;
    try {
      events = await eventService.getEvents(opts);
    } catch (err) {
      eventsError = err.message || 'Could not load events. Check Supabase connection (SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY in .env) and network.';
      console.error('[LFS Admin] getEvents:', err);
    }

    res.render('events/list', {
      layout:       'layouts/admin',
      pageTitle:    'Events',
      activePage:   'events',
      events,
      eventsError,
      EVENT_CATEGORIES,
      filterCategory: category || '',
      filterFromDate: fromDate || '',
      filterToDate:   toDate || '',
      breadcrumbs:   [{ label: 'Admin', url: '/admin' }, { label: 'Events' }],
    });
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   CREATE (GET) — GET /admin/events/create
   ════════════════════════════════════════════════════════════ */

async function getCreateEvent(req, res, next) {
  try {
    res.render('events/event-form', {
      layout:       'layouts/admin',
      pageTitle:    'New Event',
      activePage:   'events',
      event:        null,
      EVENT_CATEGORIES,
      isEdit:       false,
      breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: 'New Event' }],
    });
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   CREATE (POST) — POST /admin/events
   ════════════════════════════════════════════════════════════ */

async function postCreateEvent(req, res, next) {
  try {
    if (req.bannerUploadError) {
      return res.render('events/event-form', {
        layout:       'layouts/admin',
        pageTitle:    'New Event',
        activePage:   'events',
        event:        req.body,
        EVENT_CATEGORIES,
        isEdit:       false,
        breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: 'New Event' }],
        error:        req.bannerUploadError.message || 'Banner must be a JPEG, PNG or WebP image under 5 MB.',
      });
    }

    const {
      title,
      slug,
      description,
      location,
      eventDate,
      distance,
      category,
      series,
      registrationOpen,
      registrationClose,
      bannerImage: bannerImageBody,
    } = req.body;

    if (!title || !eventDate) {
      return res.render('events/event-form', {
        layout:       'layouts/admin',
        pageTitle:    'New Event',
        activePage:   'events',
        event:        req.body,
        EVENT_CATEGORIES,
        isEdit:       false,
        breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: 'New Event' }],
        error:        'Title and event date are required.',
      });
    }

    const bannerImage = req.file
      ? '/images/events/' + req.file.filename
      : (bannerImageBody || null);

    await eventService.createEvent({
      title:               title.trim(),
      slug:                slug ? String(slug).trim() : undefined,
      description:         description ?? '',
      location:             location ?? '',
      eventDate:           eventDate,
      distance:             distance ?? '',
      category:             category ?? '',
      series:              series ?? null,
      registrationOpen:    registrationOpen || null,
      registrationClose:   registrationClose || null,
      bannerImage,
    });

    res.redirect('/admin/events');
  } catch (err) {
    const isBannerUploadErr = err.code === 'BANNER_FILE_TOO_LARGE' || err.code === 'BANNER_FILE_INVALID';
    const isValidationErr = err.code === eventService.SLUG_TAKEN_CODE || err.code === eventService.DATE_ORDER_INVALID_CODE || err.code === eventService.INVALID_BANNER_URL_CODE;
    if (isBannerUploadErr || isValidationErr) {
      const fallbackMsg = err.code === eventService.DATE_ORDER_INVALID_CODE
        ? 'Registration and event dates must be in order: open < close < event date.'
        : err.code === eventService.INVALID_BANNER_URL_CODE
          ? 'Banner image must be a valid http or https URL.'
          : err.code === 'BANNER_FILE_TOO_LARGE' || err.code === 'BANNER_FILE_INVALID'
            ? (err.message || 'Banner must be a JPEG, PNG or WebP image under 5 MB.')
            : 'This slug is already in use. Choose another.';
      return res.render('events/event-form', {
        layout:       'layouts/admin',
        pageTitle:    'New Event',
        activePage:   'events',
        event:        req.body,
        EVENT_CATEGORIES,
        isEdit:       false,
        breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: 'New Event' }],
        error:        err.message || fallbackMsg,
      });
    }
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   EDIT (GET) — GET /admin/events/:id/edit
   ════════════════════════════════════════════════════════════ */

async function getEditEvent(req, res, next) {
  try {
    const event = await eventService.getEventById(req.params.id);

    if (!event) {
      return res.status(404).redirect('/admin/events');
    }

    res.render('events/event-form', {
      layout:       'layouts/admin',
      pageTitle:    'Edit Event',
      activePage:   'events',
      event,
      EVENT_CATEGORIES,
      isEdit:       true,
      breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: event.title }],
    });
  } catch (err) {
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   UPDATE (POST) — POST /admin/events/:id
   ════════════════════════════════════════════════════════════ */

async function postUpdateEvent(req, res, next) {
  try {
    const { id } = req.params;

    if (req.bannerUploadError) {
      const event = await eventService.getEventById(id).catch(() => null);
      return res.render('events/event-form', {
        layout:       'layouts/admin',
        pageTitle:    'Edit Event',
        activePage:   'events',
        event:        { ...(event || {}), ...req.body },
        EVENT_CATEGORIES,
        isEdit:       true,
        breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: req.body.title || (event && event.title) || 'Edit' }],
        error:        req.bannerUploadError.message || 'Banner must be a JPEG, PNG or WebP image under 5 MB.',
      });
    }

    const {
      title,
      slug,
      description,
      location,
      eventDate,
      distance,
      category,
      series,
      registrationOpen,
      registrationClose,
      bannerImage: bannerImageBody,
    } = req.body;

    if (!title || !eventDate) {
      return res.render('events/event-form', {
        layout:       'layouts/admin',
        pageTitle:    'Edit Event',
        activePage:   'events',
        event:        req.body,
        EVENT_CATEGORIES,
        isEdit:       true,
        breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: title || 'Edit' }],
        error:        'Title and event date are required.',
      });
    }

    let bannerImage = req.file ? '/images/events/' + req.file.filename : (bannerImageBody || null);

    if (req.file) {
      const existing = await eventService.getEventById(id).catch(() => null);
      if (existing && existing.bannerImage && String(existing.bannerImage).startsWith('/images/events/')) {
        const oldPath = path.join(PUBLIC_ROOT, existing.bannerImage.replace(/^\//, ''));
        fs.unlink(oldPath, () => {});
      }
    }

    const updated = await eventService.updateEvent(id, {
      title:               title.trim(),
      slug:                slug ? String(slug).trim() : undefined,
      description:         description ?? '',
      location:             location ?? '',
      eventDate:            eventDate,
      distance:             distance ?? '',
      category:             category ?? '',
      series:              series ?? null,
      registrationOpen:    registrationOpen || null,
      registrationClose:   registrationClose || null,
      bannerImage,
    });

    if (!updated) {
      return res.redirect('/admin/events');
    }

    res.redirect('/admin/events');
  } catch (err) {
    const isBannerUploadErr = err.code === 'BANNER_FILE_TOO_LARGE' || err.code === 'BANNER_FILE_INVALID';
    const isValidationErr = err.code === eventService.SLUG_TAKEN_CODE || err.code === eventService.DATE_ORDER_INVALID_CODE || err.code === eventService.INVALID_BANNER_URL_CODE;
    if (isBannerUploadErr || isValidationErr) {
      const event = await eventService.getEventById(id).catch(() => null);
      const fallbackMsg = err.code === eventService.DATE_ORDER_INVALID_CODE
        ? 'Registration and event dates must be in order: open < close < event date.'
        : err.code === eventService.INVALID_BANNER_URL_CODE
          ? 'Banner image must be a valid http or https URL.'
          : err.code === 'BANNER_FILE_TOO_LARGE' || err.code === 'BANNER_FILE_INVALID'
            ? (err.message || 'Banner must be a JPEG, PNG or WebP image under 5 MB.')
            : 'This slug is already in use. Choose another.';
      return res.render('events/event-form', {
        layout:       'layouts/admin',
        pageTitle:    'Edit Event',
        activePage:   'events',
        event:        { ...(event || {}), ...req.body },
        EVENT_CATEGORIES,
        isEdit:       true,
        breadcrumbs:  [{ label: 'Admin', url: '/admin' }, { label: 'Events', url: '/admin/events' }, { label: req.body.title || (event && event.title) || 'Edit' }],
        error:        err.message || fallbackMsg,
      });
    }
    next(err);
  }
}

/* ════════════════════════════════════════════════════════════
   DELETE — POST /admin/events/:id/delete
   ════════════════════════════════════════════════════════════ */

async function postDeleteEvent(req, res, next) {
  try {
    const { id } = req.params;
    const event = await eventService.getEventById(id).catch(() => null);
    await eventService.deleteEvent(id);
    if (event && event.bannerImage && String(event.bannerImage).startsWith('/images/events/')) {
      const filePath = path.join(PUBLIC_ROOT, event.bannerImage.replace(/^\//, ''));
      fs.unlink(filePath, () => {});
    }
    res.redirect('/admin/events');
  } catch (err) {
    next(err);
  }
}

module.exports = {
  getEvents,
  getCreateEvent,
  postCreateEvent,
  getEditEvent,
  postUpdateEvent,
  postDeleteEvent,
};
