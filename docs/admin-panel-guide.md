# LFS Admin Panel — User Guide

This guide describes how to sign in, move around, and use each section of the **Lusaka Fitness Squad (LFS)** site administration area. The admin UI is a single-account panel (one password) protected by a session; there is no separate “users” list in the database for admins.

**Base URL (typical local install):** `http://localhost/lfs-website-php/admin/`  
Adjust the host and folder if your XAMPP site URL differs. All paths below are relative to your site root (e.g. `/admin/dashboard`).

---

## 1. Signing in and signing out

### Login address

- The login page is **not** at `/admin/login`. It uses a configurable path segment. In the current codebase this is **`/admin/door`** (see `AdminConfig::LOGIN_SLUG` in `src/config/AdminConfig.php`). Your deployment may use a different slug if that constant was changed.

### Credentials

- **Email** shown on the form is read-only and comes from config (default: `support@lfszambia.run`).
- **Password** is verified against the **`ADMIN_PASSWORD_HASH`** environment value (set in `.env` or your server config). A bcrypt hash is required; plain text is never stored in the repo.

### Session and security

- After login, a PHP session keeps you signed in. **Idle timeout** is 30 minutes of inactivity by default; you will be asked to sign in again.
- Unauthenticated requests to any `/admin/...` URL (except the login page and `/admin/logout`) redirect to the login page.
- Forms use **CSRF** tokens. If you get errors after a long pause or opening multiple tabs, refresh the page and try again.
- **POST** actions in the admin area are also subject to **rate limiting** to reduce accidental or abusive bursts.

### Signing out

- Use **Logout** in the sidebar footer, or the profile menu → **Logout**. This clears the session and sends you to the public home page. The browser may ask you to confirm.

---

## 2. Layout and navigation

### Sidebar (left)

- **Dashboard** — overview and shortcuts.
- **Messages** — contact form submissions; a badge can show new items when wired.
- **Events** — create and edit public events.
- **Gallery** — albums and media for the public gallery.
- **Blog** — posts for the blog.
- **FAQ** — frequently asked questions for the site.
- **Shop** (Products) — merchandise catalog.
- **Orders** — shop orders; badge reflects orders awaiting action (see [Orders](#7-orders-shop)).

- **Go back to site** — opens the public homepage in a new tab.
- **Logout** — ends the admin session.

### Top bar

- **Menu (hamburger)** — toggles the sidebar on small screens; overlay click also closes it.
- **Page title** and optional **breadcrumbs** for nested pages.
- **Search** and **Notifications** in the top bar are mostly **placeholder** in the current app; the notifications dropdown can show an empty state. “View all notifications” and “My profile” may not lead to fully implemented pages yet.
- **Profile** menu — shows the admin display name, links to “View site”, and “Logout”.

### Flash messages

- Green / red / yellow banners at the top of the main area report success, errors, or warnings after form actions. You can dismiss them with the **×** control.

### Visiting `/admin` directly

- `GET /admin` **redirects** to `/admin/dashboard`.

---

## 3. Dashboard

**URL:** `/admin/dashboard`

- **Stats cards** show quick figures where implemented: e.g. new contact messages, upcoming events (next 30 days), pending shop orders, and a revenue placeholder. Some values may be “—” if not yet computed.
- **Quick actions** link to: Add Event, Add Product, Add Blog, Upload to Gallery. See [section 9](#9-quick-url-reference) for direct URLs.
- **Upcoming events** — short list with **View** (public event page) and **Edit** (admin).
- **Sales performance** — chart area (may be placeholder depending on data).
- **Recent activity** — feed from the activity log; **View all** goes to Activity.
- **Pending tasks** — links to orders and draft events; **System alerts** shows status text.

**Activity (full list):** `GET /admin/activity` — more rows from the same activity feed.

---

## 4. Events

**List:** `/admin/events` → redirects to `/admin/events/list`  
**Create:** `/admin/events/create`  
**Edit:** `/admin/events/{id}/edit`

### List

- Open an event to edit, or use dashboard quick action **Add Event** for a new one.

### Create and edit (form)

- **Title** (required) — public name of the event.
- **Slug** — optional; used in URLs like `/events/your-slug`. If left blank on create, it can be auto-generated from the title.
- **Description**, **location** — free text.
- **Recurrence** — one-off, or **weekly** with day-of-week checkboxes. For weekly events, the **event date** field behaves as described on the form (e.g. next occurrence; validation follows the app rules).
- **Event date** — datetime; required for one-off events.
- **Distances & route maps** — one row per distance (e.g. `10K`). Each row can have an optional **route image** (image upload). You can add/remove rows. Images appear on the public event page.
- **Category** — chosen from the configured list.
- **Registration** — *Open to all*, *Members only*, or *No registration*.  
  - **External registration link** — if set, the public “Register” action can use this URL.  
  - **Registration opens / closes** — datetime fields; hidden when registration type is *No registration*.
- **Banner image** — upload a file, **or** paste a URL/path. Affects how the event appears.  
- **Feature on home page hero** — when checked, the event can appear in the home hero (with banner and date rules as on the public site).
- **Event brochure (PDF)** — optional upload (max 25 MB in the UI copy) or URL/path. On edit, you can remove the existing brochure with the checkbox, or replace it with a new file.

**Save:** use **Create event** or **Update event**. The form is multipart; banner, distance route images, and brochure are processed on submit.

**Delete:** performed from the event’s admin flow (POST to delete — use the control provided on the list or edit view as implemented in your build).

Use **Cancel** to return to `/admin/events` (or the back link shown on the form).

---

## 5. Gallery

**Albums:** `/admin/gallery` → `/admin/gallery/albums`  
**Upload:** `/admin/gallery/upload`  
**Settings:** `/admin/gallery/settings`

- **Albums** — create, edit, and delete **albums**; set titles, slugs, visibility, cover images, and similar fields per your forms. You can open **manage** for an album to work with **media** inside it.
- **Upload** page — add images to the gallery (flow may be album-specific; follow the on-screen steps).
- **Media management** (from album manage) — set captions, feature flags, homepage slider, event highlight, delete or bulk actions, and reorder, depending on the UI. AJAX actions send CSRF in headers where required.
- **Settings** — global gallery options as defined on the settings page.

---

## 6. Blog

**List:** `/admin/blog` → `/admin/blog/list`  
**Create:** `/admin/blog/create`  
**Edit:** `/admin/blog/{id}/edit`  
**Delete:** confirmation at `/admin/blog/{id}/delete`, then POST to confirm

- Create posts with the rich form; you can **upload a featured/cover image** for a post.  
- The blog router validates CSRF on create, update, and delete.  
- After edits, the public blog list may be cached for a short period (e.g. a couple of minutes) — if you do not see changes immediately, wait briefly or check cache settings in the project.

---

## 7. Messages (contact form)

**List:** `/admin/messages`  
**Detail:** `/admin/messages/{id}`

- Opening a message that was **New** marks it as **Read** automatically.
- **Reply:** `/admin/messages/{id}/reply` — compose a reply; on success the system can email the visitor and set status to **Responded** if mail sends correctly. If email fails, the UI may show a warning and leave status unchanged.
- You can **change status** or **delete** a message from the provided controls (CSRF-protected).
- Replies are limited to 5000 characters.

Ensure PHP **mail** is configured on the server for outbound replies; otherwise saves may work but email might not.

---

## 8. FAQ

**List:** `/admin/faqs`  
**Create:** `/admin/faqs/create`  
**Edit:** `/admin/faqs/{id}/edit`  
**Delete:** POST to `/admin/faqs/{id}/delete`

- Fields typically include **question**, **answer**, optional **category**, and **sort order**.
- The application enforces a **maximum of 10 FAQs** — creating beyond that may redirect you back to the list.
- Sort order is validated so it does not exceed the number of items.

---

## 9. Orders (shop)

**List:** `/admin/orders`  
**Filter:** add `?status=pending_payment` (or any valid order status) to the URL.  
**Detail:** `/admin/orders/{id}`

- **Order status** values include: *Pending payment*, *Paid*, *Processing*, *Ready for pickup*, *Collected*, *Cancelled*, *Payment failed* (see `Order::ORDER_STATUS` in code for exact slugs used in the database).
- The sidebar **Orders** badge and dashboard “pending” style counts treat orders that still need your attention (e.g. `pending_payment` and `paid`) in a way that matches the `orders` router logic.
- From an order’s detail page, update status via the provided form (CSRF-protected). Payment information may be shown for troubleshooting Lenco (or your payment integration).

---

## 10. Shop (Products)

**List:** `/admin/products`  
**Create:** `/admin/products/create` (POST new product to `/admin/products`)  
**Edit:** `/admin/products/{id}/edit`  
**Delete:** POST to `/admin/products/{id}/delete`

- Add **name, description, price, images**, and any other fields shown on the product form.
- **Image upload** is handled by dedicated middleware; follow size/type guidance on the form.
- The public **Shop** is under `/shop/...` on the main site, not under `/admin/shop` (the admin may redirect `/admin/shop` to `/admin/products`).

---

## 11. API

Server-side routes under `/api/...` are for programmatic use (e.g. mobile or integrations). They are **not** the day-to-day admin UI. See `src/admin/routes/api.php` and related code for allowed endpoints and authentication, if any.

---

## 12. Troubleshooting

| Issue | What to try |
|--------|--------------|
| Redirected to login immediately | Session expired; sign in at `/admin/door` (or your login slug). Check `ADMIN_PASSWORD_HASH` is set. |
| 403 on save | CSRF token mismatch — refresh the page, sign in again, submit once. |
| Upload fails | Check PHP `upload_max_filesize` / `post_max_size`, correct file type (images vs PDF for brochures), and disk permissions on upload directories. |
| Emails from contact replies not received | Configure SMTP/`sendmail` for PHP; check spam folders; read server error logs. |
| Wrong base path (links to `/admin/...` break) | The app can live in a subfolder; ensure the front controller normalizes `BASE_PATH` and you open the site using the same base URL. |

---

## 13. Quick URL reference

| Action | URL |
|--------|-----|
| Login | `GET/POST` `/admin/door` (or configured `LOGIN_SLUG`) |
| Logout | `GET` `/admin/logout` |
| Dashboard | `GET` `/admin/dashboard` |
| Activity | `GET` `/admin/activity` |
| Events list | `GET` `/admin/events/list` |
| New event | `GET` `/admin/events/create` |
| Message list | `GET` `/admin/messages` |
| Gallery albums | `GET` `/admin/gallery/albums` |
| Gallery upload | `GET` `/admin/gallery/upload` |
| Blog list | `GET` `/admin/blog/list` |
| New blog post | `GET` `/admin/blog/create` |
| FAQs | `GET` `/admin/faqs` |
| Products | `GET` `/admin/products` |
| Orders | `GET` `/admin/orders` |

---

*Document generated to match the LFS PHP project structure. If your team changes login slug, environment variables, or routes, update this file accordingly.*

## Exporting a PDF (optional)

To refresh `admin-panel-guide.pdf` after editing this file: on Windows, open the Markdown in an editor with preview and **Print to PDF**, or run `docs/build-admin-panel-pdf.py` (Python 3, `pip install markdown`, Microsoft Edge at the default install path).
