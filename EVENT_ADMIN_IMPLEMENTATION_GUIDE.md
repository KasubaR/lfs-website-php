# Event Admin System — Step-by-Step Implementation Guide

This guide walks through implementing the event admin CRUD and wiring public events to Supabase. Follow the steps in order; each step lists the file(s) to change and what to do.

---

## Step 1: Add `slug` to the database

**Goal:** Public event URLs stay `/events/:slug`. The `events` table needs a unique `slug` column.

**File:** `supabase-schema.sql`

1. Open the `events` table definition (around line 117).
2. After `event_date timestamptz not null,` add:
   ```sql
   slug text unique,
   ```
   (Use `slug text` if you prefer nullable for existing rows; then add a unique constraint after backfilling.)
3. After the existing `create index if not exists events_created_at...` line, add:
   ```sql
   create unique index if not exists events_slug on public.events(slug);
   ```
   (If slug is nullable, you can use a non-unique index or skip until slugs are backfilled.)

**Apply the change:**

- In Supabase Dashboard → SQL Editor, run either:
  - The full amended `create table` (only if the table does not exist yet), or
  - An `ALTER TABLE` to add the column and index, for example:
    ```sql
    alter table public.events add column if not exists slug text unique;
    create unique index if not exists events_slug on public.events(slug);
    ```
- If you already have rows, backfill `slug` (e.g. from `title` with a slugify rule) so new/updated events can use it. New events will set slug from the admin form.

---

## Step 2: Event model — make `EVENT_CATEGORIES` require-able

**Goal:** Admin controller can `require('../model/event')` and use `EVENT_CATEGORIES`.

**File:** `src/model/event.js`

1. At the end of the file, ensure you export for CommonJS. If the file only has `export { EVENT_CATEGORIES };`, add:
   ```js
   module.exports = { EVENT_CATEGORIES };
   ```
   (Or remove the ESM export and keep only `module.exports = { EVENT_CATEGORIES };`.)

---

## Step 3: Event service — slug support

**Goal:** Service reads/writes `slug` and supports lookup by slug.

**File:** `src/services/event.service.js`

**3a. Map `slug` in `toEvent`**

- In the object returned by `toEvent`, add:
  ```js
  slug: row.slug ?? null,
  ```
  (Place it after `title` or next to other identity fields.)

**3b. Add `getEventBySlug`**

- Add a new async function:
  ```js
  async function getEventBySlug(slug) {
    const { data, error } = await supabase
      .from('events')
      .select('*')
      .eq('slug', slug)
      .maybeSingle();
    if (error) throw error;
    return toEvent(data);
  }
  ```
- Export it in `module.exports` (add `getEventBySlug` to the list).

**3c. `createEvent` — include slug**

- In the `row` object built for insert, add:
  ```js
  slug: data.slug || null,
  ```
  (If you want to auto-generate from title when empty, require `slugify` from `../utility/helpers` and set something like `slug: data.slug || slugify(data.title)`; ensure uniqueness if needed.)

**3d. `updateEvent` — allow updating slug**

- In the `row` object, add:
  ```js
  if (data.slug !== undefined) row.slug = data.slug;
  ```

---

## Step 4: Admin event controller

**Goal:** Handle list, create, edit, update, delete for events.

**File:** Create `src/admin/controllers/event.controller.js`

1. At the top, require the event service and event model:
   ```js
   const eventService = require('../../services/event.service');
   const { EVENT_CATEGORIES } = require('../../model/event');
   ```

2. **getEvents** — list page:
   - `async (req, res, next) => { try { const events = await eventService.getEvents({ limit: 100 }); res.render('events/list', { layout: 'layouts/admin', pageTitle: 'Events', activePage: 'events', events, EVENT_CATEGORIES, breadcrumbs: [...] }); } catch (err) { next(err); } }`
   - Optionally read `req.query.category` and pass it to `getEvents({ category, limit })` and back to the view for filter state.

3. **getCreateEvent** — new event form:
   - `res.render('events/event-form', { layout: 'layouts/admin', pageTitle: 'New Event', activePage: 'events', event: null, EVENT_CATEGORIES, breadcrumbs: [...], isEdit: false });`

4. **postCreateEvent** — create event:
   - Read `req.body` (title, slug, description, location, eventDate, distance, category, registrationOpen, registrationClose, bannerImage).
   - Validate required fields (e.g. title, slug, eventDate).
   - Call `await eventService.createEvent({ title, slug, description, location, eventDate, distance, category, registrationOpen: registrationOpen || null, registrationClose: registrationClose || null, bannerImage: bannerImage || null });`
   - Redirect to `/admin/events` (or to the new event’s edit page) with a success message if you have flash/toast.

5. **getEditEvent** — edit form:
   - `const event = await eventService.getEventById(req.params.id);` if (!event) return res.status(404).render(...) or redirect.
   - `res.render('events/event-form', { layout: 'layouts/admin', pageTitle: 'Edit Event', activePage: 'events', event, EVENT_CATEGORIES, breadcrumbs: [...], isEdit: true });`

6. **postUpdateEvent** — update event:
   - Same body fields as create. Call `await eventService.updateEvent(req.params.id, { title, slug, description, location, eventDate, distance, category, registrationOpen, registrationClose, bannerImage });`
   - Redirect to `/admin/events` or back to edit.

7. **postDeleteEvent** — delete event:
   - `await eventService.deleteEvent(req.params.id);` then redirect to `/admin/events`.

Use the same layout and variable names as your other admin pages (e.g. `layout: 'layouts/admin'`, `activePage: 'events'`).

---

## Step 5: Admin event routes

**Goal:** Wire admin event URLs to the controller.

**File:** Create `src/admin/routes/events.routes.js`

1. Create an Express router: `const express = require('express'); const router = express.Router(); const eventController = require('../controllers/event.controller');`

2. Define routes:
   - `router.get('/', (req, res) => res.redirect('/admin/events/list'));`  
     or render list directly: `router.get('/', eventController.getEvents);`
   - `router.get('/list', eventController.getEvents);`  (if you use a separate /list path)
   - `router.get('/create', eventController.getCreateEvent);`
   - `router.post('/', eventController.postCreateEvent);`
   - `router.get('/:id/edit', eventController.getEditEvent);`
   - `router.post('/:id', eventController.postUpdateEvent);`
   - `router.post('/:id/delete', eventController.postDeleteEvent);`

3. Export: `module.exports = router;`

**File:** `src/admin/routes/admin.routes.js`

1. Near the top, add: `const eventsRouter = require('./events.routes');`
2. After the gallery mount (e.g. `router.use('/gallery', galleryRouter);`), add: `router.use('/events', eventsRouter);`

Ensure the `/events` mount comes before any catch-all so `/admin/events`, `/admin/events/create`, `/admin/events/:id/edit`, etc. are handled correctly.

---

## Step 6: Admin list view

**Goal:** Show all events with links to create, edit, and delete.

**File:** Create `src/admin/views/events/list.ejs`

1. Set locals at the top (same pattern as gallery):
   - `pageTitle = 'Events'; activePage = 'events'; breadcrumbs = [{ label: 'Admin', url: '/admin' }, { label: 'Events' }];`

2. Optional: a small stats row (e.g. “Total events”, “Upcoming”) using `events.length` and filtering by `eventDate >= now`.

3. Toolbar: link “New event” to `/admin/events/create`. Optionally a search input or category filter that submits to the same page with query params.

4. Table or card list over `events`:
   - Columns/cells: title, category, event_date (formatted), location, slug.
   - For each row: “Edit” link to `/admin/events/<%= event.id %>/edit`, and a “Delete” form: `method="POST" action="/admin/events/<%= event.id %>/delete"` with a submit button (and optional CSRF if you use it).

5. Use the same admin layout and CSS classes as [src/admin/views/gallery/albums.ejs](src/admin/views/gallery/albums.ejs) (e.g. stat-card, form styles) so the UI is consistent.

---

## Step 7: Admin event form view

**Goal:** One form used for both create and edit.

**File:** Create `src/admin/views/events/event-form.ejs`

1. At the top, set:
   - `isEdit = typeof event !== 'undefined' && event !== null;`
   - `pageTitle = isEdit ? 'Edit Event' : 'New Event'; activePage = 'events';`
   - `formAction = isEdit ? '/admin/events/' + event.id : '/admin/events';`
   - `method="POST"` for the form.

2. Form fields (same naming as controller expects):
   - **Title** — `input type="text" name="title"` required, value from `event.title`.
   - **Slug** — `input type="text" name="slug"` required (or optional if you auto-generate), value from `event.slug`. Hint: “URL-friendly, e.g. lfs-sunrise-10k-2026”.
   - **Description** — `textarea name="description"`, value from `event.description`.
   - **Location** — `input name="location"`, value from `event.location`.
   - **Event date** — `input type="datetime-local" name="eventDate"` (or two inputs: date + time), value from `event.eventDate` (format for datetime-local).
   - **Distance** — `input name="distance"` (e.g. “10K”, “21.1K”), value from `event.distance`.
   - **Category** — `<select name="category">` with options from `EVENT_CATEGORIES`, selected from `event.category`.
   - **Registration open** — `input type="datetime-local"` or `date` name="registrationOpen", value from `event.registrationOpen`.
   - **Registration close** — same for `registrationClose`.
   - **Banner image URL** — `input name="bannerImage"`, value from `event.bannerImage`.

3. Buttons: “Save” (submit) and “Cancel” (link to `/admin/events`).

4. Use the same layout and form styling as [src/admin/views/gallery/album-form.ejs](src/admin/views/gallery/album-form.ejs) (form-group, form-label, input styles).

---

## Step 8: Public GET /events — use Supabase

**Goal:** Events list page is driven by Supabase, not stub data.

**File:** `src/routes/index.js`

1. At the top (with other requires), add: `const eventService = require('../services/event.service');`

2. Find the `router.get('/events', ...)` that currently renders with `EVENTS_ALL`.

3. Replace the handler with an async function that:
   - Calls `const events = await eventService.getEvents({ limit: 100 });`
   - Renders `pages/events` with `{ title, description, page: 'events', events, styles, scripts }`.
   - Uses `try/catch` and `next(err)` for errors.

4. You can keep the stub array elsewhere for reference or remove it once the home page is updated (Step 10).

---

## Step 9: Public GET /events/:slug — use Supabase

**Goal:** Event detail page is driven by Supabase using slug.

**File:** `src/routes/index.js`

1. Find the `router.get('/events/:slug', ...)` that currently looks up from `EVENTS_ALL` by slug.

2. Replace with an async handler that:
   - Calls `const event = await eventService.getEventBySlug(req.params.slug);`
   - If `!event`, respond with 404 (e.g. `res.status(404).render('pages/404', ...)`) or `res.redirect('/events');`
   - Otherwise `res.render('pages/event-details', { title: event.title, description: event.description || '', page: 'events', event, styles, scripts });`
   - Use try/catch and next(err).

---

## Step 10: Home page events from Supabase

**Goal:** Any “upcoming events” on the home page use Supabase instead of the stub array.

**File:** `src/routes/index.js`

1. Find the route that renders the home page (e.g. `router.get('/', ...)`).

2. If it passes an `events` (or `EVENTS`) variable to the view, replace the stub with:
   - `const events = await eventService.getUpcomingEvents(3);` (or `getEvents({ fromDate: now, limit: 3 })`), and pass that to the view.

3. Make the home route async and use try/catch/next(err).

---

## Step 11: Test the flow

1. **Database:** In Supabase, ensure the `events` table has the `slug` column and index. Optionally insert one row with a slug for testing.

2. **Admin:**
   - Open `/admin/events` — you should see the list (empty or with existing events).
   - Click “New event”, fill title, slug, date, etc., submit — event should be created and list should show it.
   - Click “Edit” on an event — change fields and save — event should update.
   - Use “Delete” — event should be removed.

3. **Public:**
   - Open `/events` — list should show the same events from Supabase.
   - Open `/events/your-test-slug` — detail page should render; optional fields (startTime, terrain, etc.) may be blank and the existing template should still work.

4. **Home:** If the home page shows upcoming events, confirm they come from Supabase and look correct.

---

## Checklist

- [ ] Step 1: `slug` column and index added in schema and applied in Supabase
- [ ] Step 2: `event.js` exports `EVENT_CATEGORIES` via `module.exports`
- [ ] Step 3: `event.service.js` — toEvent(slug), getEventBySlug, create/update slug
- [ ] Step 4: `src/admin/controllers/event.controller.js` created with all actions
- [ ] Step 5: `src/admin/routes/events.routes.js` created and mounted in admin.routes.js
- [ ] Step 6: `src/admin/views/events/list.ejs` created
- [ ] Step 7: `src/admin/views/events/event-form.ejs` created
- [ ] Step 8: GET /events uses eventService.getEvents
- [ ] Step 9: GET /events/:slug uses eventService.getEventBySlug
- [ ] Step 10: Home page events from eventService
- [ ] Step 11: Manual test of admin CRUD and public list/detail

---

## Optional follow-ups

- **Slug from title:** In the admin form or in the controller, auto-generate `slug` from `title` (e.g. using `slugify` from helpers) when slug is empty; ensure uniqueness (e.g. append id or date).
- **Validation:** Add server-side validation (e.g. express-validator) for create/update body.
- **Flash messages:** Add a flash middleware and set success/error messages on redirect for create/update/delete.
- **Rich event fields:** If you want startTime, entryFee, highlights, schedule on the public detail page, add columns or jsonb to `events` and extend the admin form and `event.service` mapping.
