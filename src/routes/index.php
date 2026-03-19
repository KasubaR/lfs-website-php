<?php
declare(strict_types=1);
/**
 * LFS — Lusaka Fitness Squad
 * src/routes/index.php — Main public page routes
 *
 * Mount point: / (top-level, dispatched from the front router)
 *
 * Expects from front router:
 *   $method   = $_SERVER['REQUEST_METHOD']
 *   $segments = URL parts after /
 *               e.g. /           → ['']
 *                    /events     → ['events']
 *                    /events/my-slug → ['events', 'my-slug']
 *                    /about      → ['about']
 *
 * Routes:
 *   GET /         → home page
 *   GET /events   → events listing
 *   GET /events/:slug → event detail
 *   GET /about    → about page
 */

require_once __DIR__ . '/../../src/services/GalleryService.php';
require_once __DIR__ . '/../../src/services/EventService.php';
require_once __DIR__ . '/../../src/services/ProductService.php';
require_once __DIR__ . '/../../src/services/BlogPostService.php';
require_once __DIR__ . '/../../src/model/BlogPost.php';

$galleryService   = new GalleryService();
$eventService    = new EventService();
$productService  = new ProductService();
$blogPostService = new BlogPostService();

$seg0 = $segments[0] ?? '';
$seg1 = $segments[1] ?? '';

/* ════════════════════════════════════════════════════════════
   SHARED CONSTANTS
   ════════════════════════════════════════════════════════════ */
const GALLERY_PREVIEW_FALLBACK_FOLDER = '21.02.2026-LSD';
const HOMEPAGE_PREVIEW_LIMIT = 6;
const IMAGE_EXTS_INDEX = ['webp', 'jpg', 'jpeg', 'png'];

/* ════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════ */

/**
 * Read fallback photos from public/images/21.02.2026-LSD when DB is unavailable.
 * Returns [['urls' => ['medium'=>..., 'large'=>..., 'original'=>...], 'albumId'=>'', 'caption'=>...], ...]
 */
function getHomepageFallbackMedia(): array
{
    $publicRoot  = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : realpath(__DIR__ . '/../../public');
    $folderPath  = rtrim((string)$publicRoot, '/') . '/images/' . GALLERY_PREVIEW_FALLBACK_FOLDER;
    $baseUrl     = '/images/' . GALLERY_PREVIEW_FALLBACK_FOLDER;

    if (!is_dir($folderPath)) return [];

    $files = array_filter(
        scandir($folderPath) ?: [],
        // Avoid typed arrow function signatures for older PHP compatibility.
        fn ($f) =>
            $f !== '.' && $f !== '..'
            && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), IMAGE_EXTS_INDEX, true)
    );
    sort($files);
    $files = array_slice(array_values($files), 0, HOMEPAGE_PREVIEW_LIMIT);

    return array_map(fn ($f) => [
        'urls'    => ['medium' => "$baseUrl/$f", 'large' => "$baseUrl/$f", 'original' => "$baseUrl/$f"],
        'albumId' => '',
        'caption' => 'LFS — 21.02.2026 LSD',
    ], $files);
}

/** Map event category to home-page card tag colour. */
function eventTagColor(string $category): string
{
    return match ($category) {
        'LSD'           => 'green',
        'Road Race'     => 'orange',
        'Training',
        'Training Camp' => 'red',
        'Social'        => 'gold',
        default         => '',
    };
}

/** Map a service event array to the home-view shape. */
function mapEventForHome(array $e): array
{
    $date    = !empty($e['eventDate']) ? new DateTime($e['eventDate']) : null;
    $dateStr = $date
        ? $date->format('D, j F Y')   // e.g. "Sat, 1 March 2025"
        : 'TBA';

    return [
        'title'     => $e['title'],
        'date'      => $dateStr,
        'location'  => $e['location'] ?: 'TBA',
        'distance'  => $e['distance'] ?: '—',
        'tag'       => $e['category'] ?: 'Event',
        'tagColor'  => eventTagColor($e['category'] ?? ''),
        'link'      => '/events/' . ($e['slug'] ?: $e['id']),
    ];
}

/** Format a numeric price as Zambian Kwacha, e.g. "K 350.00" */
function formatPriceIndex(float $amount): string
{
    return 'K ' . number_format($amount, 2);
}

/* ════════════════════════════════════════════════════════════
   SAMPLE FALLBACK DATA
   Shown when DB is empty; replace with live data as project matures.
   ════════════════════════════════════════════════════════════ */
$POSTS_FALLBACK = [
    ['title' => 'LFS Closes 2024 With Record Participation',    'excerpt' => 'Over 500 runners crossed the finish line at our Year-End Fun Run — the biggest turnout in LFS history.',                      'date' => 'Dec 10, 2024', 'category' => 'News',       'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?q=80&w=800&auto=format&fit=crop', 'link' => '/news/year-end-2024'],
    ['title' => '2025 Membership Is Now Open',                  'excerpt' => 'The 2025 membership window is officially open. Renew or register before end of April to stay connected.',                      'date' => 'Jan 3, 2025',  'category' => 'Membership', 'image' => 'https://images.unsplash.com/photo-1552674605-d1f74c4f719b?q=80&w=800&auto=format&fit=crop', 'link' => '/news/membership-2025'],
    ['title' => 'New Satellite Captains Announced for 2025',    'excerpt' => 'LFS leadership has confirmed satellite captains across all six locations for the new season. Meet your new captains.',          'date' => 'Jan 15, 2025', 'category' => 'Community',  'image' => 'https://images.unsplash.com/photo-1517649763962-0c623066013b?q=80&w=800&auto=format&fit=crop', 'link' => '/news/captains-2025'],
];

/* ════════════════════════════════════════════════════════════
   GET /   — Home page
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === '') {
    // Fetch all three in parallel (as close as PHP can get — sequential with error isolation)
    $galleryPreview = [];
    $events         = [];
    $homeProducts   = [];

    try { $galleryPreview = $galleryService->getHomepageMedia(HOMEPAGE_PREVIEW_LIMIT); } catch (Throwable) {}
    if (empty($galleryPreview)) $galleryPreview = getHomepageFallbackMedia();

    $heroSlides = [];
    try { $heroSlides = $galleryService->getHomepageSliderMedia(8); } catch (Throwable) {}

    try {
        $rawEvents = $eventService->getUpcomingEvents(5);
        $events    = array_map('mapEventForHome', $rawEvents);
    } catch (Throwable) {}

    try {
        ['products' => $rawProducts] = $productService->getProducts(['limit' => 8], ['admin' => false]);
        if (!empty($rawProducts)) {
            $homeProducts = array_map(function (array $prod): array {
                $image = $prod['thumbnail'] ?? '';
                if ((!$image || $image === '/images/products/placeholder.webp')
                    && !empty($prod['images'])) {
                    $first = $prod['images'][0];
                    $image = is_string($first) ? $first : ($first['url'] ?? $first['src'] ?? $image);
                }
                if (!$image) {
                    $image = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=2124&auto=format&fit=crop';
                }
                return [
                    'name'         => $prod['name'],
                    'sub'          => $prod['shortDescription'] ?: ($prod['description'] ?? ''),
                    'category'     => $prod['category'] ?? '',
                    'price'        => (float)($prod['price'] ?? 0),
                    'comparePrice' => isset($prod['comparePrice']) && $prod['comparePrice'] !== null && $prod['comparePrice'] !== '' ? (float)$prod['comparePrice'] : null,
                    'slug'         => $prod['slug'] ?? null,
                    'badge'        => !empty($prod['featured']) ? 'Featured' : null,
                    'badgeColor'   => !empty($prod['featured']) ? 'gold'     : null,
                    'image'        => $image,
                    'thumbnail'    => $image,
                ];
            }, $rawProducts);
        }
    } catch (Throwable) {}

    // When no products, shop-preview partial shows empty state (no fallback placeholders)
    // When no published posts, home News section shows empty state (no fallback placeholders)

    $posts = [];
    try {
        ['posts' => $rawPosts] = $blogPostService->getPosts(['status' => 'published', 'limit' => 3]);
        if (!empty($rawPosts)) {
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            $posts = array_map(function (array $p) use ($base): array {
                $date   = $p['publishDate'] ?? null;
                $dateStr = $date && (($t = strtotime($date)) !== false) ? date('j M Y', $t) : '—';
                return [
                    'title'    => $p['title'] ?? '',
                    'excerpt'  => $p['excerpt'] ?? '',
                    'date'     => $dateStr,
                    'category' => $p['category'] ?? 'News',
                    'image'    => $p['featuredImage'] ?? '',
                    'link'     => $base . '/news/' . ($p['slug'] ?? ''),
                ];
            }, $rawPosts);
        }
    } catch (Throwable $e) {
        error_log('[LFS] Home news posts error: ' . $e->getMessage());
    }

    $heroImage  = '/images/LSD07.02.2026-3.jpg';
    $title      = 'Home';
    $description = "Zambia's biggest running community. Train. Run. Compete. Together. Join LFS today.";
    $page       = 'home';
    $products   = $homeProducts;
    // Only preload hero image when it is actually the first slide (no gallery slider media)
    $heroPreload = empty($heroSlides)
        ? '<link rel="preload" as="image" href="' . htmlspecialchars($heroImage, ENT_QUOTES) . '">'
        : '';
    $extraStyles  = $heroPreload . '<link rel="stylesheet" href="/css/home.css">';
    $extraScripts = '<script src="/js/home.js" defer></script>';

    ob_start();
    require __DIR__ . '/../../src/views/pages/home.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../src/views/layouts/main.php';
    exit;
}

/* ════════════════════════════════════════════════════════════
   GET /events  — Events listing
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === 'events' && $seg1 === '') {
    try {
        $events = $eventService->getEvents(['limit' => 100]);
    } catch (Throwable $e) {
        error_log('[LFS] Events listing error: ' . $e->getMessage());
        $events = [];
    }

    $title        = 'Events & Races';
    $description  = 'Upcoming and past LFS events — road races, LSD runs, training camps, and community events in Lusaka, Zambia.';
    $page         = 'events';
    $extraStyles  = '<link rel="stylesheet" href="/css/events.css">';
    $extraScripts = '<script src="/js/events.js"></script>';

    ob_start();
    require __DIR__ . '/../../src/views/pages/events.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../src/views/layouts/main.php';
    exit;
}

/* ════════════════════════════════════════════════════════════
   GET /events/:slug  — Event detail
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === 'events' && $seg1 !== '') {
    try {
        $event = $eventService->getEventBySlug($seg1);
    } catch (Throwable $e) {
        error_log('[LFS] Event detail error: ' . $e->getMessage());
        $event = null;
    }

    if (!$event) {
        header('Location: /events');
        exit;
    }

    $title        = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');
    $description  = htmlspecialchars($event['description'] ?? '', ENT_QUOTES, 'UTF-8');
    $page         = 'events';
    $extraStyles  = '<link rel="stylesheet" href="/css/events.css">';
    $extraScripts = '<script src="/js/events.js"></script>';

    ob_start();
    require __DIR__ . '/../../src/views/pages/event-details.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../src/views/layouts/main.php';
    exit;
}

/* ════════════════════════════════════════════════════════════
   GET /news  — News & blog listing
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === 'news' && ($seg1 ?? '') === '') {
    $activeCategory = trim((string) ($_GET['category'] ?? ''));
    $searchQuery    = trim((string) ($_GET['q'] ?? ''));
    $currentPage    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage        = 9;
    $offset         = ($currentPage - 1) * $perPage;

    $opts = [
        'status' => 'published',
        'limit'  => $perPage,
        'offset' => $offset,
    ];
    if ($activeCategory !== '') $opts['category'] = $activeCategory;
    if ($searchQuery !== '')   $opts['search']   = $searchQuery;

    try {
        ['posts' => $rawPosts, 'total' => $total] = $blogPostService->getPosts($opts);
    } catch (Throwable $e) {
        error_log('[LFS] News listing error: ' . $e->getMessage());
        $rawPosts = [];
        $total    = 0;
    }

    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    $posts      = array_map(function (array $p): array {
        return $p + [
            'image'        => $p['featuredImage'] ?? '',
            'date'         => $p['publishDate'] ?? null,
            'published_at' => $p['publishDate'] ?? null,
        ];
    }, $rawPosts);

    $featured = ($currentPage === 1 && $activeCategory === '' && $searchQuery === '' && isset($posts[0]))
        ? $posts[0]
        : null;

    $title        = 'News & Updates — LFS';
    $description  = 'Stories from the track, the trail, and the community. Club news, race reports, training tips, and announcements from Lusaka Fitness Squad.';
    $page         = 'news';
    $categories   = BlogPost::CATEGORIES;
    $extraStyles  = '<link rel="stylesheet" href="/css/blog.css">';
    $extraScripts = '<script src="/js/blog.js" defer></script>';

    ob_start();
    require __DIR__ . '/../../src/views/pages/news.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../src/views/layouts/main.php';
    exit;
}

/* ════════════════════════════════════════════════════════════
   GET /news/:slug  — Single blog post
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === 'news' && ($seg1 ?? '') !== '') {
    $slug = $seg1;
    try {
        $post = $blogPostService->getPostBySlug($slug);
    } catch (Throwable $e) {
        error_log('[LFS] News post error: ' . $e->getMessage());
        $post = null;
    }

    if (!$post || ($post['status'] ?? '') !== 'published') {
        http_response_code(404);
        $title       = 'Page Not Found';
        $description = '';
        $page        = 'news';
        ob_start();
        require __DIR__ . '/../../src/views/pages/404.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
        exit;
    }

    $blogPostService->incrementViews($post['id']);

    $postForView = $post + [
        'image'        => $post['featuredImage'] ?? '',
        'date'         => $post['publishDate'] ?? null,
        'published_at' => $post['publishDate'] ?? null,
    ];

    $relatedPosts = [];
    $prevPost     = null;
    $nextPost     = null;

    try {
        if (!empty($post['category'])) {
            ['posts' => $relatedRaw] = $blogPostService->getPosts([
                'status'   => 'published',
                'category' => $post['category'],
                'limit'    => 4,
            ]);
            $relatedPosts = array_slice(array_map(function (array $p): array {
                return $p + [
                    'image'        => $p['featuredImage'] ?? '',
                    'date'         => $p['publishDate'] ?? null,
                    'published_at' => $p['publishDate'] ?? null,
                ];
            }, array_filter($relatedRaw, fn ($p) => ($p['id'] ?? '') !== $post['id'])), 0, 3);
        }

        ['posts' => $allByDate] = $blogPostService->getPosts([
            'status' => 'published',
            'limit'  => 100,
            'offset' => 0,
        ]);
        $ids = array_column($allByDate, 'id');
        $pos = array_search($post['id'], $ids, true);
        if ($pos !== false && $pos > 0) {
            $prev = $allByDate[$pos - 1];
            $prevPost = $prev + [
                'image'        => $prev['featuredImage'] ?? '',
                'date'         => $prev['publishDate'] ?? null,
                'published_at' => $prev['publishDate'] ?? null,
            ];
        }
        if ($pos !== false && $pos < count($allByDate) - 1) {
            $next = $allByDate[$pos + 1];
            $nextPost = $next + [
                'image'        => $next['featuredImage'] ?? '',
                'date'         => $next['publishDate'] ?? null,
                'published_at' => $next['publishDate'] ?? null,
            ];
        }
    } catch (Throwable $e) {
        error_log('[LFS] News related/prev-next error: ' . $e->getMessage());
    }

    $title        = htmlspecialchars($post['title'] ?? 'News', ENT_QUOTES, 'UTF-8');
    $description  = htmlspecialchars(strip_tags($post['excerpt'] ?? $post['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $page         = 'news';
    $extraStyles  = '<link rel="stylesheet" href="/css/blog.css">';
    $extraScripts = '<script src="/js/blog.js" defer></script>';

    $post         = $postForView;
    ob_start();
    require __DIR__ . '/../../src/views/pages/news-post.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../src/views/layouts/main.php';
    exit;
}

/* ════════════════════════════════════════════════════════════
   GET /about  — About page
   ════════════════════════════════════════════════════════════ */
if ($method === 'GET' && $seg0 === 'about') {
    $galleryPreview = [];
    try {
        $galleryPreview = $galleryService->getHomepageMedia(HOMEPAGE_PREVIEW_LIMIT);
    } catch (Throwable) {}
    if (empty($galleryPreview)) $galleryPreview = getHomepageFallbackMedia();

    $title       = 'About';
    $description = "Learn about Lusaka Fitness Squad — Zambia's biggest running community. Our history, mission, values, leadership, and satellites.";
    $page        = 'about';

    ob_start();
    require __DIR__ . '/../../src/views/pages/about.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../src/views/layouts/main.php';
    exit;
}

// ── Fallback: 404 ────────────────────────────────────────────
http_response_code(404);
$title       = 'Page Not Found';
$description = '';
ob_start();
require __DIR__ . '/../../src/views/pages/404.php';
$content = ob_get_clean();
require __DIR__ . '/../../src/views/layouts/main.php';
