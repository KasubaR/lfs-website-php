<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/controllers/ShopController.php
 *
 * Handles:
 *   GET  /shop                 — product listing with filters + pagination
 *   GET  /shop/product/:slug   — product detail page
 *   POST /shop/cart/add        — add to cart (session-based)
 *   GET  /shop/cart            — view cart
 *   POST /shop/cart/update     — update cart item qty
 *   POST /shop/cart/remove     — remove cart item
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/ProductService.php';
require_once __DIR__ . '/../model/Product.php';

class ShopController
{
    private ProductService $productService;

    /** Site URL used for JSON-LD structured data. */
    private string $siteUrl;

    public function __construct()
    {
        $this->productService = new ProductService();
        $this->siteUrl        = $_ENV['SITE_URL'] ?? getenv('SITE_URL') ?: 'https://www.lfszambia.run';

        // Session must be started by the front router before any controller runs
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /* ════════════════════════════════════════════════════════════
       SHOP INDEX — GET /shop
       ════════════════════════════════════════════════════════════ */

    public function getShop(): void
    {
        $category = $_GET['category'] ?? '';
        $gender   = $_GET['gender']   ?? '';
        $size     = $_GET['size']     ?? '';
        $sort     = $_GET['sort']     ?? 'latest';
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $minPrice = $_GET['minPrice'] ?? '';
        $maxPrice = $_GET['maxPrice'] ?? '';

        $filters = ['sort' => $sort, 'page' => $page, 'limit' => 12];
        if ($category !== '') $filters['category'] = $category;
        if ($gender   !== '') $filters['gender']   = $gender;
        if ($size     !== '') $filters['size']      = $size;
        if ($minPrice !== '') $filters['minPrice']  = (float)$minPrice;
        if ($maxPrice !== '') $filters['maxPrice']  = (float)$maxPrice;

        ['products' => $products, 'total' => $total, 'pages' => $pages]
            = $this->productService->findPublic($filters);

        // Ensure pagination vars expected by the view exist
        $currentPage = $page;
        $pages       = max(1, (int)($pages ?? 1));

        $title       = 'Shop LFS Merchandise';
        $description = 'High-quality running gear and regalia for Lusaka Fitness Squad members.';
        $bodyClass   = 'page-no-hero';
        $cartCount   = $this->cartItemCount();
        $filters     = compact('category', 'gender', 'size', 'sort', 'minPrice', 'maxPrice');

        ob_start();
        require __DIR__ . '/../../src/views/pages/shop.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
    }

    /* ════════════════════════════════════════════════════════════
       PRODUCT DETAIL — GET /shop/product/:slug
       ════════════════════════════════════════════════════════════ */

    public function getProduct(string $slug): void
    {
        $product = $this->productService->findOneBySlug($slug);

        if (!$product) {
            http_response_code(404);
            $title       = 'Product Not Found';
            $description = 'That product does not exist or is no longer available.';
            ob_start();
            require __DIR__ . '/../../src/views/pages/404.php';
            $content = ob_get_clean();
            require __DIR__ . '/../../src/views/layouts/main.php';
            return;
        }

        $related   = $this->productService->findRelatedByCategory($product['category'], $product['id'], 4);
        $cartCount = $this->cartItemCount();
        $siteUrl   = $this->siteUrl;
        $formatPrice = fn(float $amount): string => $this->formatPrice($amount);

        $title       = htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . ' — LFS Shop';
        $description = $product['shortDescription']
            ?? substr($product['description'] ?? '', 0, 155)
            ?: '';
        $bodyClass   = 'page-no-hero';

        ob_start();
        require __DIR__ . '/../../src/views/pages/productDetails.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
    }

    /* ════════════════════════════════════════════════════════════
       ADD TO CART — POST /shop/cart/add
       Body: productId, size, qty (default 1)
       ════════════════════════════════════════════════════════════ */

    public function addToCart(): void
    {
        $input = $this->parseCartAddInput();
        $productId = trim($input['productId'] ?? '');
        $size      = trim($input['size']      ?? '');
        $qty       = max(1, (int)($input['qty'] ?? 1));

        if ($productId === '' || $size === '') {
            $this->cartErrorResponse(400, 'Product and size are required.', '/shop');
            return;
        }

        $product = $this->productService->findById($productId);

        if (!$product) {
            $this->cartErrorResponse(404, 'Product not found.', '/shop');
            return;
        }

        // Find matching size entry
        $sizeEntry = null;
        foreach ($product['sizes'] as $s) {
            if (($s['size'] ?? '') === $size) { $sizeEntry = $s; break; }
        }

        if ($sizeEntry === null || (int)($sizeEntry['stock'] ?? 0) <= 0) {
            $this->cartErrorResponse(400, "Size $size is out of stock.", '/shop/product/' . $product['slug']);
            return;
        }

        $cart    = $this->loadCart();
        $maxQty  = (int)$sizeEntry['stock'];
        $cartKey = $productId . '::' . $size;

        // Find existing cart entry for this product+size
        $found = false;
        foreach ($cart as &$item) {
            if ($item['key'] === $cartKey) {
                $item['qty'] = min($item['qty'] + $qty, $maxQty);
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $cart[] = [
                'key'       => $cartKey,
                'productId' => (string)$product['id'],
                'slug'      => $product['slug'],
                'name'      => $product['name'],
                'price'     => (float)$product['price'],
                'image'     => $product['thumbnail'] ?: '/images/products/placeholder.webp',
                'size'      => $size,
                'qty'       => min($qty, $maxQty),
            ];
        }

        $this->saveCart($cart);
        ['itemCount' => $itemCount, 'subtotal' => $subtotal] = $this->cartTotals($cart);

        if ($this->wantsJson()) {
            $this->jsonResponse([
                'ok'        => true,
                'message'   => 'Item added to cart.',
                'itemCount' => $itemCount,
                'subtotal'  => $this->formatPrice($subtotal),
            ]);
            return;
        }

        header('Location: /shop/cart');
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       VIEW CART — GET /shop/cart
       ════════════════════════════════════════════════════════════ */

    public function getCart(): void
    {
        $cart = $this->loadCart();
        ['itemCount' => $itemCount, 'subtotal' => $subtotal] = $this->cartTotals($cart);

        $title       = 'Your Cart — LFS Shop';
        $description = 'Review your LFS merchandise cart.';
        $bodyClass   = 'page-no-hero';
        $cartCount   = $itemCount;
        $subtotalFmt = $this->formatPrice($subtotal);

        ob_start();
        require __DIR__ . '/../../src/views/pages/shop-cart.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
    }

    /* ════════════════════════════════════════════════════════════
       CHECKOUT PAGE — GET /shop/checkout
       ════════════════════════════════════════════════════════════ */

    public function getCheckout(): void
    {
        $cart = $this->loadCart();
        ['itemCount' => $itemCount, 'subtotal' => $subtotalAmount] = $this->cartTotals($cart);

        // Shape cart items for checkout view
        $cartItems = array_map(function (array $item): array {
            $price   = (float)($item['price'] ?? 0);
            $qty     = (int)($item['qty']   ?? 0);
            $image   = $item['image'] ?: '/images/products/placeholder.webp';
            return [
                'key'       => $item['key']       ?? '',
                'productId' => $item['productId'] ?? '',
                'name'      => $item['name']      ?? '',
                'size'      => $item['size']      ?? '',
                'qty'       => $qty,
                'price'     => $price,
                'subtotal'  => $price * $qty,
                'image'     => $image,
            ];
        }, $cart);

        $cartCount = $itemCount;
        $subtotal  = $this->formatPrice($subtotalAmount);
        $total     = $subtotal; // no shipping yet

        // Ensure CSRF token exists for checkout POSTs
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];

        $title       = 'Checkout — LFS Shop';
        $description = 'Complete your order for official LFS regalia and gear.';
        $bodyClass   = 'page-no-hero';

        ob_start();
        require __DIR__ . '/../../src/views/pages/checkout.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
    }

    /* ════════════════════════════════════════════════════════════
       UPDATE CART — POST /shop/cart/update
       Body: key, qty
       ════════════════════════════════════════════════════════════ */

    public function updateCart(): void
    {
        $key    = $_POST['key'] ?? '';
        $newQty = (int)($_POST['qty'] ?? 0);
        $cart   = $this->loadCart();

        foreach ($cart as $i => $item) {
            if ($item['key'] === $key) {
                if ($newQty <= 0) {
                    array_splice($cart, $i, 1);
                } else {
                    $cart[$i]['qty'] = $newQty;
                }
                break;
            }
        }

        $this->saveCart($cart);

        if ($this->wantsJson()) {
            ['itemCount' => $itemCount, 'subtotal' => $subtotal] = $this->cartTotals($cart);
            $this->jsonResponse([
                'ok'        => true,
                'itemCount' => $itemCount,
                'subtotal'  => $this->formatPrice($subtotal),
            ]);
            return;
        }

        header('Location: /shop/cart');
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       REMOVE FROM CART — POST /shop/cart/remove
       Body: key
       ════════════════════════════════════════════════════════════ */

    public function removeFromCart(): void
    {
        $key  = $_POST['key'] ?? '';
        $cart = $this->loadCart();

        foreach ($cart as $i => $item) {
            if ($item['key'] === $key) {
                array_splice($cart, $i, 1);
                break;
            }
        }

        $this->saveCart($cart);

        if ($this->wantsJson()) {
            ['itemCount' => $itemCount, 'subtotal' => $subtotal] = $this->cartTotals($cart);
            $this->jsonResponse([
                'ok'        => true,
                'itemCount' => $itemCount,
                'subtotal'  => $this->formatPrice($subtotal),
            ]);
            return;
        }

        header('Location: /shop/cart');
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Retrieve the session cart array.
     * Cart structure: [['key', 'productId', 'slug', 'name', 'price', 'image', 'size', 'qty'], ...]
     *
     * @return array<array>
     */
    private function loadCart(): array
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }

    /** Persist cart back to the session. */
    private function saveCart(array $cart): void
    {
        $_SESSION['cart'] = array_values($cart);
    }

    /** Compute cart summary: total item count and subtotal amount. */
    private function cartTotals(array $cart): array
    {
        $itemCount = 0;
        $subtotal  = 0.0;
        foreach ($cart as $item) {
            $qty        = (int)($item['qty']   ?? 0);
            $price      = (float)($item['price'] ?? 0.0);
            $itemCount += $qty;
            $subtotal  += $price * $qty;
        }
        return ['itemCount' => $itemCount, 'subtotal' => $subtotal];
    }

    /** Shortcut: total item count for cart badge in nav. */
    private function cartItemCount(): int
    {
        return $this->cartTotals($this->loadCart())['itemCount'];
    }

    /**
     * Parse add-to-cart input from JSON body (AJAX) or $_POST (form submit).
     *
     * @return array{productId?: string, size?: string, qty?: int}
     */
    private function parseCartAddInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    /**
     * Format a price as Zambian Kwacha: "K 1,234.00"
     */
    private function formatPrice(float $amount): string
    {
        return 'K ' . number_format($amount, 2);
    }

    /** True when the request expects a JSON response. */
    private function wantsJson(): bool
    {
        $accept     = $_SERVER['HTTP_ACCEPT']           ?? '';
        $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json')
            || strtolower($xRequested) === 'xmlhttprequest';
    }

    /** Output JSON and terminate. */
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a cart error — JSON for AJAX requests, redirect for form posts.
     */
    private function cartErrorResponse(int $status, string $message, string $redirectTo): void
    {
        if ($this->wantsJson()) {
            $this->jsonResponse(['ok' => false, 'message' => $message], $status);
        } else {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}
