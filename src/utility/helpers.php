<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/utility/helpers.php
 *
 * Shared helper functions used across controllers, views, and templates.
 *
 * Functions:
 *   lfs_formatPrice(amount, showCents)
 *   lfs_formatPriceRange(min, max)
 *   lfs_generateBreadcrumbs(overrides?)
 *   lfs_checkStockAvailability(product, size?)
 *   lfs_getMaxQty(product, size)
 *   lfs_slugify(text)
 *   lfs_truncate(str, maxLen)
 *   lfs_toTitleCase(str)
 *   lfs_categoryLabel(slug)
 *   lfs_buildProductJsonLd(product, siteUrl)
 *   lfs_public_url(path)   — static asset URL (handles subfolder installs)
 *
 * All functions are prefixed with `lfs_` to avoid collisions with PHP
 * built-ins or other includes. Import into controllers with:
 *
 *   require_once __DIR__ . '/../utility/helpers.php';
 */

declare(strict_types=1);

require_once __DIR__ . '/../model/Product.php';

/* ════════════════════════════════════════════════════════════
   PUBLIC ASSET URLS
   ════════════════════════════════════════════════════════════ */

/**
 * URL path for a static asset (e.g. '/css/main.css').
 * Prepends BASE_PATH when the app is installed in a subfolder.
 */
function lfs_public_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $base = '';
    if (defined('BASE_PATH')) {
        $bp = (string) BASE_PATH;
        if ($bp !== '' && $bp !== '/') {
            $base = rtrim($bp, '/');
        }
    }
    return $base . $path;
}

/* ════════════════════════════════════════════════════════════
   PRICE FORMATTING
   ════════════════════════════════════════════════════════════ */

/**
 * Format a number as Zambian Kwacha.
 *   250    → "K 250"
 *   1500   → "K 1,500"
 *   2999.5 → "K 2,999.50"  (when $showCents = true)
 *
 * @param  int|float|null $amount
 * @param  bool           $showCents  Show decimal places when true
 * @return string
 */
function lfs_formatPrice(int|float|null $amount, bool $showCents = false): string
{
    if ($amount === null || !is_numeric($amount)) return 'K —';

    $num      = (float)$amount;
    $decimals = $showCents ? 2 : 0;

    return 'K ' . number_format($num, $decimals);
}

/**
 * Format a price range.
 *   e.g. "K 250 – K 500"
 */
function lfs_formatPriceRange(int|float $min, int|float $max): string
{
    if ($min === $max) return lfs_formatPrice($min);
    return lfs_formatPrice($min) . ' – ' . lfs_formatPrice($max);
}

/* ════════════════════════════════════════════════════════════
   TIME / DATE
   ════════════════════════════════════════════════════════════ */

/**
 * Human-readable relative time from a datetime string.
 *   e.g. "Just now", "5 min ago", "2 hours ago", "3 days ago"
 *
 * @param  string $datetime  Any strtotime()-parseable string (e.g. ISO 8601 or MySQL datetime)
 * @return string
 */
function lfs_timeAgo(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) return '';

    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return (int) floor($diff / 60) . ' min ago';
    if ($diff < 86400) return (int) floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return (int) floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return (int) floor($diff / 604800) . ' weeks ago';
    if ($diff < 31536000) return (int) floor($diff / 2592000) . ' months ago';
    return (int) floor($diff / 31536000) . ' years ago';
}

/* ════════════════════════════════════════════════════════════
   BREADCRUMB GENERATION
   ════════════════════════════════════════════════════════════ */

/**
 * Generate a breadcrumb trail from the current request URI.
 *
 * Returns an array of ['label' => string, 'href' => string|null, 'active' => bool].
 * The last item is always active.
 *
 * @param  array<array{label: string, href?: string}>|null $overrides
 *   Pass explicit segments to override auto-detection.
 *   e.g. [['label' => 'Shop', 'href' => '/shop'], ['label' => 'LFS Running Shirt']]
 * @return array<array{label: string, href: string|null, active: bool}>
 */
function lfs_generateBreadcrumbs(?array $overrides = null): array
{
    /** Known path-segment → label (null = skip segment) */
    $labelMap = [
        'shop'    => 'Shop',
        'product' => null,          // skip — not user-friendly
        'cart'    => 'Cart',
        'gallery' => 'Gallery',
        'contact' => 'Contact',
        'admin'   => 'Admin',
        'about'   => 'About',
        'events'  => 'Events',
        'news'    => 'News',
        'cookies' => 'Cookie Settings',
    ];

    $crumbs = [['label' => 'Home', 'href' => '/', 'active' => false]];

    if ($overrides !== null) {
        $last = count($overrides) - 1;
        foreach ($overrides as $i => $o) {
            $crumbs[] = [
                'label'  => $o['label'],
                'href'   => $o['href'] ?? null,
                'active' => $i === $last,
            ];
        }
        if (count($crumbs) > 1) {
            $crumbs[count($crumbs) - 1]['active'] = true;
        }
        return $crumbs;
    }

    // Auto-generate from REQUEST_URI (strip query string)
    $path     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $segments = array_filter(explode('/', $path), fn ($s) => $s !== '');
    $segments = array_values($segments);
    $builtPath = '';
    $count     = count($segments);

    foreach ($segments as $i => $seg) {
        $builtPath .= '/' . $seg;
        $lower      = strtolower($seg);
        $isLast     = ($i === $count - 1);

        // null in map = skip entirely
        if (array_key_exists($lower, $labelMap) && $labelMap[$lower] === null) continue;

        $label = $labelMap[$lower] ?? lfs_toTitleCase(str_replace('-', ' ', $seg));

        $crumbs[] = [
            'label'  => $label,
            'href'   => $isLast ? null : $builtPath,
            'active' => $isLast,
        ];
    }

    // Ensure only the last crumb is active
    if (count($crumbs) > 1) {
        foreach ($crumbs as &$c) $c['active'] = false;
        unset($c);
        $crumbs[count($crumbs) - 1]['active'] = true;
    }

    return $crumbs;
}

/* ════════════════════════════════════════════════════════════
   STOCK AVAILABILITY
   ════════════════════════════════════════════════════════════ */

/**
 * Check whether a product (or a specific size) is in stock.
 *
 * @param  array|null  $product  Product array from service (camelCase shape)
 * @param  string|null $size     Optional — check stock for a specific size
 * @return array{inStock: bool, totalStock: int, sizeStock: int|null, status: string, statusLabel: string}
 */
function lfs_checkStockAvailability(?array $product, ?string $size = null): array
{
    $empty = [
        'inStock'     => false,
        'totalStock'  => 0,
        'sizeStock'   => null,
        'status'      => 'out-of-stock',
        'statusLabel' => 'Out of Stock',
    ];

    if ($product === null) return $empty;

    // Total stock across all sizes
    $totalStock = (int)($product['totalStock'] ?? 0);
    $sizes      = is_array($product['sizes'] ?? null) ? $product['sizes'] : [];
    if (!empty($sizes)) {
        $totalStock = (int)array_sum(array_column($sizes, 'stock'));
    }

    // Size-specific stock
    $sizeStock = null;
    if ($size !== null && !empty($sizes)) {
        foreach ($sizes as $s) {
            if (($s['size'] ?? '') === $size) {
                $sizeStock = (int)($s['stock'] ?? 0);
                break;
            }
        }
        if ($sizeStock === null) $sizeStock = 0;
    }

    $effective = $sizeStock !== null ? $sizeStock : $totalStock;
    $inStock   = $effective > 0;

    if (!$inStock) {
        $status      = 'out-of-stock';
        $statusLabel = 'Out of Stock';
    } elseif ($effective <= 5) {
        $status      = 'low-stock';
        $statusLabel = "Only $effective left";
    } else {
        $status      = 'in-stock';
        $statusLabel = 'In Stock';
    }

    return compact('inStock', 'totalStock', 'sizeStock', 'status', 'statusLabel');
}

/**
 * Get the maximum purchasable quantity for a product + size.
 */
function lfs_getMaxQty(array $product, string $size): int
{
    $result = lfs_checkStockAvailability($product, $size);
    return max(0, $result['sizeStock'] !== null ? $result['sizeStock'] : $result['totalStock']);
}

/* ════════════════════════════════════════════════════════════
   STRING & DATE UTILITIES
   ════════════════════════════════════════════════════════════ */

/**
 * Format a date string as a short, human-readable blog date.
 *   e.g. "2024-01-15 13:45:00" → "15 Jan 2024"
 *
 * Returns "—" when input is null/empty or invalid.
 */
if (!function_exists('blogFormatDate')) {
    function blogFormatDate(?string $d): string
    {
        if (!$d) {
            return '—';
        }
        $ts = strtotime($d);
        if ($ts === false) {
            return '—';
        }
        return date('j M Y', $ts);
    }
}

/**
 * Convert a string to a URL-safe slug.
 *   "LFS Running Shirt 2024" → "lfs-running-shirt-2024"
 */
function lfs_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^\w\s\-]/u', '', $text);   // remove non-word chars (keep - and space)
    $text = preg_replace('/[\s_]+/', '-', $text);        // spaces/underscores → hyphens
    $text = preg_replace('/\-{2,}/', '-', $text);        // collapse multiple hyphens
    return trim($text, '-');
}

/**
 * Truncate a string to $maxLen characters, appending "…" if cut.
 * Breaks at a word boundary.
 */
function lfs_truncate(string $str, int $maxLen = 160): string
{
    if (mb_strlen($str) <= $maxLen) return $str;
    $cut = mb_substr($str, 0, $maxLen);
    // Break at last whitespace to avoid cutting mid-word
    $cut = preg_replace('/\s+\S*$/u', '', $cut);
    return $cut . '…';
}

/**
 * Title-case a string.
 *   "running kits" → "Running Kits"
 */
function lfs_toTitleCase(string $str): string
{
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

/* ════════════════════════════════════════════════════════════
   CATEGORY LABELS
   ════════════════════════════════════════════════════════════ */

/**
 * Get the human-readable label for a product category slug.
 *   'running-kits' → 'Running Kits'
 */
function lfs_categoryLabel(string $slug): string
{
    return Product::CATEGORY_LABELS[$slug]
        ?? lfs_toTitleCase(str_replace('-', ' ', $slug));
}

/* ════════════════════════════════════════════════════════════
   STRUCTURED DATA (JSON-LD)
   ════════════════════════════════════════════════════════════ */

/**
 * Build a Schema.org Product JSON-LD array for SEO.
 * Encode with json_encode() and echo inside a <script type="application/ld+json"> tag.
 *
 * @param  array  $product  Product array from service (camelCase shape)
 * @param  string $siteUrl  Base URL, e.g. "https://www.lfszambia.run"
 * @return array
 */
function lfs_buildProductJsonLd(array $product, string $siteUrl = 'https://www.lfszambia.run'): array
{
    $sizes   = is_array($product['sizes'] ?? null) ? $product['sizes'] : [];
    $inStock = !empty($sizes)
        ? (bool)array_filter($sizes, fn ($s) => (int)($s['stock'] ?? 0) > 0)
        : ((int)($product['totalStock'] ?? 0) > 0);

    $images = array_map(
        fn ($img): string => str_starts_with((string)$img, 'http') ? (string)$img : $siteUrl . $img,
        is_array($product['images'] ?? null) ? $product['images'] : []
    );

    // Price valid for 30 days from now
    $priceValidUntil = date('Y-m-d', strtotime('+30 days'));

    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $product['name'],
        'description' => $product['description'] ?? $product['shortDescription'] ?? '',
        'url'         => $siteUrl . '/shop/product/' . $product['slug'],
        'image'       => $images,
        'sku'         => (string)($product['id'] ?? $product['slug']),
        'brand'       => [
            '@type' => 'Brand',
            'name'  => 'LFS — Lusaka Fitness Squad',
        ],
        'offers' => [
            '@type'            => 'Offer',
            'url'              => $siteUrl . '/shop/product/' . $product['slug'],
            'price'            => (float)($product['price'] ?? 0),
            'priceCurrency'    => 'ZMW',
            'priceValidUntil'  => $priceValidUntil,
            'availability'     => $inStock
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'seller' => [
                '@type' => 'Organization',
                'name'  => 'LFS — Lusaka Fitness Squad',
            ],
        ],
    ];
}
