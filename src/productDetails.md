# LFS Shop — Product Detail Page: Implementation Guide

> **Files delivered in this batch**
> | File | Location |
> |------|----------|
> | `productDetails.ejs` | `src/views/shop/productDetails.ejs` |
> | `productDetails.css` | `src/public/css/productDetails.css` |
> | `productDetails.js`  | `src/public/js/productDetails.js` |
> | `helpers.js`         | `src/utils/helpers.js` |

---

## 1. Route & Controller Wiring

### 1.1 Update `shop.controller.js`

The controller already has `getProduct` wired. Two changes are needed:

**a) Import helpers** at the top of `shop.controller.js`:

```js
const {
  formatPrice,
  generateBreadcrumbs,
  checkStockAvailability,
  buildProductJsonLd,
} = require('../utils/helpers');
```

**b) Update `getProduct` to pass `siteUrl`** (needed for JSON-LD and share links):

```js
exports.getProduct = async (req, res, next) => {
  try {
    const product = await productService.findOneBySlug(req.params.slug);
    if (!product) { /* ... 404 handler unchanged ... */ }

    const related = await productService.findRelatedByCategory(product.category, product.id, 4);
    const cart    = getCart(req);
    const { itemCount } = cartTotals(cart);

    res.render('shop/productDetails', {          // ← updated view path
      title:       `${product.name} — LFS Shop`,
      description: product.shortDescription || product.description?.slice(0, 155) || '',
      layout:      'layouts/main',
      product,
      related,
      cartCount:  itemCount,
      formatPrice,
      siteUrl:    process.env.SITE_URL || 'https://www.lfszambia.run',
    });
  } catch (err) {
    next(err);
  }
};
```

### 1.2 Update `shop.routes.js`

No changes required — the route `GET /shop/product/:slug` already calls `shopCtrl.getProduct`.

---

## 2. View Path

The EJS template sits at:

```
src/views/shop/productDetails.ejs
```

Express resolves views relative to the `views` directory set in `app.js`:

```js
app.set('views', [
  path.join(__dirname, 'views'),
  path.join(__dirname, 'admin', 'views'),
]);
```

So `res.render('shop/productDetails', ...)` maps to `src/views/shop/productDetails.ejs`. ✅

---

## 3. Static Assets

### 3.1 CSS

Add **after** `main.css` in `layouts/main.ejs` (or inject via the `styles` block the template already uses):

```html
<!-- shop.css already linked on /shop pages via styles block -->
<!-- productDetails.css is injected automatically by productDetails.ejs: -->
<!-- <% styles = `<link ... href="/css/productDetails.css">` %> -->
```

No changes needed to `main.ejs` — the template uses the `<%- styles %>` slot from `express-ejs-layouts`.

### 3.2 JavaScript

Similarly, `productDetails.js` is injected by the template via `<% scripts = ... %>`. No changes to `main.ejs` needed.

### 3.3 Serve static files

Confirm `app.js` serves from `public/`:

```js
app.use(express.static(path.join(__dirname, '..', 'public')));
```

Place files at:

```
public/css/productDetails.css
public/js/productDetails.js
public/images/products/          ← product images go here
```

---

## 4. Product model

This project uses **Supabase**, not Mongoose. Product data is defined in `supabase-schema.sql` (table `products`). Product data access and camelCase mapping are in `src/services/product.service.js`.

---

## 5. Product Service

`shop.controller.js` imports from `../services/product.service`. The service uses Supabase and exposes:

- `findPublic(opts)` — listing with filters/pagination  
- `findOneBySlug(slug)` — single product by slug  
- `findById(id)` — single product by id  
- `findRelatedByCategory(category, excludeId, limit)` — related products  

No Mongoose or `models/Product` is used.

---

## 6. Session (Required for Cart)

The cart uses `req.session`. Add `express-session` to `app.js` if not present:

```bash
npm install express-session
```

```js
const session = require('express-session');

app.use(session({
  secret:            process.env.SESSION_SECRET || 'lfs-dev-secret',
  resave:            false,
  saveUninitialized: false,
  cookie: {
    secure: process.env.NODE_ENV === 'production',
    maxAge: 7 * 24 * 60 * 60 * 1000,   // 7 days
  },
}));
```

> **Production note:** Replace the default memory store with `connect-redis` or `connect-pg-simple` to avoid session leaks across restarts.

---

## 7. Mount the Shop Router in `app.js`

```js
const shopRouter = require('./routes/shop.routes');
app.use('/shop', shopRouter);
```

Place this **before** `app.use('/', homeRouter)` to avoid the catch-all home router intercepting `/shop/*`.

---

## 8. Using `helpers.js`

Import wherever needed:

```js
const helpers = require('../utils/helpers');

// Format price
helpers.formatPrice(250);           // → "K 250"
helpers.formatPrice(1500);          // → "K 1,500"

// Breadcrumbs (auto from req.path)
helpers.generateBreadcrumbs(req);
// → [{ label:'Home', href:'/' }, { label:'Shop', href:'/shop' }, { label:'Running Kits' }]

// Breadcrumbs (manual override for product detail)
helpers.generateBreadcrumbs(req, [
  { label: 'Shop', href: '/shop' },
  { label: 'Running Kits', href: '/shop?category=running-kits' },
  { label: product.name },
]);

// Stock check
helpers.checkStockAvailability(product);
// → { inStock: true, totalStock: 24, sizeStock: null, status: 'in-stock', statusLabel: 'In Stock' }

helpers.checkStockAvailability(product, 'M');
// → { inStock: true, totalStock: 24, sizeStock: 6, status: 'in-stock', statusLabel: 'In Stock' }

// Category label
helpers.categoryLabel('running-kits');   // → "Running Kits"

// JSON-LD for SEO
helpers.buildProductJsonLd(product, process.env.SITE_URL);
```

---

## 9. Product Images

Place images in:

```
public/images/products/
```

Naming convention:

```
{slug}-1.webp
{slug}-2.webp
placeholder.webp    ← fallback when no image
```

Recommended spec:

| Property      | Value                       |
|---------------|-----------------------------|
| Format        | WebP (JPEG as fallback)     |
| Dimensions    | 800 × 800 px (1:1)          |
| Max file size | < 200 KB per image          |
| Background    | White or light grey         |

Compress with [Squoosh](https://squoosh.app) or:

```bash
cwebp -q 82 input.jpg -o output.webp
```

---

## 10. Environment Variables

Add to `.env`:

```env
SITE_URL=https://www.lfszambia.run
SESSION_SECRET=your-random-secret-here
NODE_ENV=development
```

---

## 11. Feature Summary

| Feature | Implementation |
|---------|---------------|
| Image gallery | Thumbnail click swaps main image; arrows work with keyboard |
| Image zoom | Click main image → lightbox overlay; Escape to close |
| Size selector | Radio-style buttons; disabled + strikethrough for out-of-stock sizes |
| Per-size stock limit | Qty max updates dynamically when size is selected |
| Add to Cart | AJAX POST with loading state, toast confirmation, badge sync |
| Buy Now | Add to cart then redirect to `/shop/cart` |
| Size guide | Modal + tab panel; opens from "Size Guide" link |
| Tabs | Description / Details & Care / Size Guide |
| Related products | 4 cards from same category, scroll-reveal animation |
| Social sharing | Facebook, WhatsApp, Twitter/X links |
| Breadcrumbs | Auto-generated from URL; Schema.org BreadcrumbList markup |
| SEO | `<title>`, `<meta description>`, JSON-LD Product schema |
| Mobile | Single-column layout; images on top; full-width buttons |
| Accessibility | `role`, `aria-label`, `aria-pressed`, focus management |

---

## 12. File Tree

```
src/
├── controllers/
│   └── shop.controller.js        (uses productService + siteUrl + helpers)
├── model/
│   └── order.js                  (shop order/product constants; products in Supabase)
├── routes/
│   └── shop.routes.js
├── services/
│   └── product.service.js        (Supabase product data layer)
├── utility/
│   └── helpers.js
├── views/
│   └── pages/
│       └── productDetails.ejs
└── public/
    ├── css/
    │   └── productDetails.css    (NEW)
    ├── js/
    │   └── productDetails.js     (NEW)
    └── images/
        └── products/
            ├── placeholder.webp  (add manually)
            └── {slug}-1.webp     (add per product)
```
