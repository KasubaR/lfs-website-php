# Lenco Payment Integration — LFS Shop

Step-by-step guide to wiring the Lenco Collections API into the existing LFS checkout.

---

## What you're building

```
Customer clicks "Place Order"
       │
       ▼
checkout-lenco.js  POSTs  →  /shop/checkout/place-order
                                    │
                                    ▼
                            OrderController::placeOrder()
                              ├─ Validates customer info
                              ├─ Gets cart totals from SESSION (authoritative)
                              ├─ Creates row in orders table
                              ├─ Calls LencoService::initiateMobileMoneyPayment()
                              ├─ Creates row in payments table
                              ├─ Clears $_SESSION['cart']
                              └─ Returns { ok, transactionId, instructions… }
                                    │
       ◄────────────────────────────┘
       │
       ▼
checkout-lenco.js shows instructions, starts polling every 8 s
       │
       ▼  GET /shop/checkout/verify?txId=xxx
       │
       ▼
OrderController::verifyPayment()
  ├─ Checks payments table first (no API call if already terminal)
  ├─ Calls LencoService::verifyPayment() if not terminal
  ├─ Updates payments + orders tables on status change
  └─ Returns { ok, status, lencoStatus, orderNumber }
       │
       ▼
checkout-lenco.js
  ├─ status = 'completed' → advance to Step 4 confirmation
  └─ status = 'failed'    → show error, re-enable button

Meanwhile, Lenco also POSTs to:
  POST /shop/checkout/webhook  →  OrderController::handleWebhook()
    ├─ Verifies HMAC-SHA256 signature
    ├─ Finds payment record
    ├─ Idempotency check (skips if already terminal)
    ├─ Updates payments + orders tables
    └─ Always returns 200 to Lenco
```

---

## Step 1 — Set environment variables

Database credentials live in **`public/.env`** next to `public/index.php` (copy from `env.example`). If you use the repo root `index.php` entry instead, put `.env` in the **project root**. Only `DB_*` keys are read; see `src/bootstrap/DatabaseEnv.php`.

Add Lenco keys to `public/index.php` (or your server environment) via `putenv()`:

```php
// Lenco API
putenv('LENCO_API_SECRET_KEY=your_actual_lenco_secret_key');
putenv('LENCO_WEBHOOK_SECRET=your_lenco_webhook_hmac_secret');
```

> ⚠️ Never commit real keys. `.env` is gitignored; use different `.env` files per machine without merge conflicts.

---

## Step 2 — Run the database migration

```bash
mysql -u root lfs_db < database/001_orders_and_payments.sql
```

This creates three tables: `orders`, `order_items`, `payments`.

---

## Step 3 — Copy the new PHP files

Copy these files into your project:

| Source file | Destination in your project |
|---|---|
| `src/controllers/OrderController.php` | `src/controllers/OrderController.php` |
| `src/services/LencoService.php` | `src/services/LencoService.php` |
| `src/services/LencoApiException.php` | `src/services/LencoApiException.php` |
| `src/models/OrderModel.php` | `src/models/OrderModel.php` |
| `src/models/PaymentModel.php` | `src/models/PaymentModel.php` |
| `src/views/pages/order-confirmation.php` | `src/views/pages/order-confirmation.php` |
| `public/js/checkout-lenco.js` | `public/js/checkout-lenco.js` |

---

## Step 4 — Update your shop route file

Replace `src/routes/shop.php` with the provided `src/routes/shop.php`.

The key additions are:

```php
require_once __DIR__ . '/../../src/controllers/OrderController.php';
$orderController = new OrderController();

// POST /shop/checkout/place-order
if ($method === 'POST' && $seg0 === 'checkout' && $seg1 === 'place-order') {
    CsrfMiddleware::verify();
    $orderController->placeOrder();
    exit;
}

// GET /shop/checkout/verify?txId=xxx
if ($method === 'GET' && $seg0 === 'checkout' && $seg1 === 'verify') {
    $orderController->verifyPayment();
    exit;
}

// POST /shop/checkout/webhook  (no CSRF — secured by HMAC signature)
if ($method === 'POST' && $seg0 === 'checkout' && $seg1 === 'webhook') {
    $orderController->handleWebhook();
    exit;
}

// GET /shop/order/:orderNumber
if ($method === 'GET' && $seg0 === 'order' && $seg1 !== '') {
    $orderController->getOrderConfirmation($seg1);
    exit;
}
```

---

## Step 5 — Update checkout.php (the view)

### 5a. Replace Step 3 payment panel

In `src/views/pages/checkout.php`, find the comment:

```
<!-- ────────────────────────────────────────────────
     STEP 3 — PICKUP + PAYMENT
```

Replace everything from that comment down to:

```
</div><!-- /.checkout-panel[step 3] -->
```

…with the contents of `src/views/pages/checkout-step3-updated.php`.

### 5b. Add the new JS file

At the bottom of `checkout.php`, change the `$scripts` line from:

```php
$scripts = '<script src="/js/checkout.js"></script>';
```

to:

```php
$scripts = '<script src="/js/checkout.js"></script>'
         . '<script src="/js/checkout-lenco.js"></script>';
```

### 5c. Add a small CSS rule for the error box

In your `checkout.css` (or inline in the view), add:

```css
.checkout-error-msg {
    display: flex;
    align-items: center;
    gap: .5rem;
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    padding: .8rem 1rem;
    border-radius: 8px;
    margin-top: 1rem;
    font-size: .9rem;
}

.lenco-polling-status {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-top: .75rem;
    color: #334155;
    font-size: .85rem;
    font-style: italic;
}
```

---

## Step 6 — Register webhook URL in Lenco dashboard

Log in to your Lenco dashboard and set the webhook URL to:

```
https://your-domain.com/shop/checkout/webhook
```

This must be HTTPS in production.

---

## Step 7 — Verify the checkout flow in dev

1. Add a product to cart → go to checkout
2. Fill in Step 2 (name, email)
3. In Step 3, select MTN or Airtel, enter a test phone number
4. Click "Place Order"
5. Confirm a row appears in `orders` and `payments` tables
6. Confirm `lenco_status` = `pay-offline` (awaiting phone approval)
7. Approve on phone → webhook fires → `payments.status` = `completed`, `orders.status` = `paid`
8. Checkout should advance to Step 4 automatically via polling

---

## File map summary

```
src/
├── controllers/
│   ├── ShopController.php      ← UNCHANGED
│   └── OrderController.php     ← NEW
├── services/
│   ├── LencoService.php        ← NEW
│   └── LencoApiException.php   ← NEW
├── models/
│   ├── OrderModel.php          ← NEW
│   └── PaymentModel.php        ← NEW
├── routes/
│   └── shop.php                ← UPDATED (new routes added)
└── views/pages/
    ├── checkout.php            ← UPDATED (Step 3 replaced + new JS added)
    ├── checkout-step3-updated.php  ← NEW (paste into checkout.php)
    └── order-confirmation.php  ← NEW

public/js/
└── checkout-lenco.js           ← NEW

database/
└── 001_orders_and_payments.sql ← NEW (run once)
```

---

## Security notes

- **Amount is never trusted from the browser.** `OrderController::placeOrder()` always reads the cart total from `$_SESSION['cart']` — the server-authoritative source.
- **Webhook is secured by HMAC-SHA256** (`LencoService::verifyWebhookSignature`), not CSRF, because Lenco doesn't send your CSRF token.
- **Idempotency**: the webhook handler skips any payment already in a terminal state (`completed`, `failed`, `cancelled`), preventing double-processing.
- **PDO prepared statements** throughout — no raw SQL interpolation.
