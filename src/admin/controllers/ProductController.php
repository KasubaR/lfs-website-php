<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/controllers/ProductController.php
 *
 * Product admin: list, create, edit, update, delete.
 * Depends on:
 *   - ProductService   (src/services/ProductService.php)
 *   - Product model    (src/model/Product.php) for CATEGORIES / GENDER_OPTIONS constants
 */

declare(strict_types=1);

require_once __DIR__ . '/../../services/ProductService.php';
require_once __DIR__ . '/../../model/Product.php';

class ProductController
{
    private ProductService $productService;

    /** Absolute path to the public web root — used when renaming temp upload dirs. */
    private string $publicRoot;
    private string $uploadRoot;

    public function __construct()
    {
        $this->productService = new ProductService();
        $this->publicRoot     = defined('PUBLIC_ROOT')
            ? PUBLIC_ROOT
            : realpath(__DIR__ . '/../../../public');
        $this->uploadRoot     = rtrim($this->publicRoot, '/') . '/uploads/products';
    }

    /* ════════════════════════════════════════════════════════════
       LIST — GET /admin/products
       ════════════════════════════════════════════════════════════ */

    public function getProducts(): void
    {
        $page     = max(1, (int)($_GET['page']     ?? 1));
        $category = $_GET['category'] ?? '';
        $search   = $_GET['search']   ?? '';

        $opts = [
            'sort'  => 'latest',
            'page'  => $page,
            'limit' => 20,
            'admin' => true,
        ];
        if ($category !== '') $opts['category'] = $category;

        ['products' => $products, 'total' => $total, 'pages' => $pages]
            = $this->productService->getProducts($opts);

        // In-memory search filter (mirrors JS implementation)
        $term = strtolower(trim($search));
        if ($term !== '') {
            $products = array_values(array_filter($products, function (array $p) use ($term): bool {
                return str_contains(strtolower($p['name']        ?? ''), $term)
                    || str_contains(strtolower($p['description'] ?? ''), $term);
            }));
        }

        $this->render('products/list', [
            'pageTitle'          => 'Products',
            'activePage'         => 'products',
            'products'           => $products,
            'total'              => $total,
            'pages'              => $pages,
            'currentPage'        => $page,
            'filters'            => ['category' => $category, 'search' => $search],
            'PRODUCT_CATEGORIES' => Product::CATEGORIES,
            'formatPrice'        => fn ($v) => 'ZMW ' . number_format((float)$v, 2),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       CREATE (GET) — GET /admin/products/create
       ════════════════════════════════════════════════════════════ */

    public function getCreateProduct(): void
    {
        $this->render('products/form', [
            'pageTitle'          => 'New Product',
            'activePage'         => 'products',
            'product'            => null,
            'PRODUCT_CATEGORIES' => Product::CATEGORIES,
            'GENDER_OPTIONS'     => Product::GENDER_OPTIONS,
            'isEdit'             => false,
            'error'              => null,
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       CREATE (POST) — POST /admin/products
       ════════════════════════════════════════════════════════════ */

    public function postCreateProduct(): void
    {
        // Image upload error set by ProductImageUpload middleware
        if (!empty($_REQUEST['_imageUploadError'])) {
            $this->renderFormWithError(null, false, 'New Product', $_POST, $_REQUEST['_imageUploadError']);
            return;
        }

        $body = $_POST;

        // Required field validation
        if (empty($body['name']) || !isset($body['price']) || $body['price'] === ''
            || empty($body['category']) || empty($body['gender'])) {
            $this->renderFormWithError(null, false, 'New Product', $body, 'Name, price, category and gender are required.');
            return;
        }

        $numericPrice = (float)$body['price'];
        if ($numericPrice < 0 || !is_finite($numericPrice)) {
            $this->renderFormWithError(null, false, 'New Product', $body, 'Price must be a non-negative number.');
            return;
        }

        $data = $this->buildProductData($body);

        // Merge uploaded image URLs from middleware
        $uploadedImages = (array)($_REQUEST['_productImages'] ?? []);
        if (!empty($uploadedImages)) {
            $data['images']    = $uploadedImages;
            $data['thumbnail'] = $uploadedImages[0];
        }

        try {
            $created = $this->productService->createProduct($data);

            // Rename temp upload folder to the real product id
            $uploadKey = $_REQUEST['_productUploadKey'] ?? null;
            if ($created && !empty($created['id']) && $uploadKey && str_starts_with($uploadKey, 'tmp-')) {
                $this->renameUploadDir((string)$uploadKey, (string)$created['id'], $data, $created['id']);
            }

            header('Location: /admin/products');
            exit;
        } catch (Throwable $e) {
            $this->renderFormWithError(null, false, 'New Product', $body,
                $e->getMessage() ?: 'Could not create product. Please try again.');
        }
    }

    /* ════════════════════════════════════════════════════════════
       EDIT (GET) — GET /admin/products/:id/edit
       ════════════════════════════════════════════════════════════ */

    public function getEditProduct(string $id): void
    {
        $product = $this->productService->getProductById($id, ['admin' => true]);
        if (!$product) {
            header('Location: /admin/products');
            exit;
        }

        $this->render('products/form', [
            'pageTitle'          => 'Edit Product',
            'activePage'         => 'products',
            'product'            => $product,
            'PRODUCT_CATEGORIES' => Product::CATEGORIES,
            'GENDER_OPTIONS'     => Product::GENDER_OPTIONS,
            'isEdit'             => true,
            'error'              => null,
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       UPDATE (POST) — POST /admin/products/:id
       ════════════════════════════════════════════════════════════ */

    public function postUpdateProduct(string $id): void
    {
        if (!empty($_REQUEST['_imageUploadError'])) {
            $existing = $this->safeGetById($id);
            $merged   = array_merge($existing ?? [], $_POST);
            $this->renderFormWithError($merged, true, $merged['name'] ?? 'Edit', $merged,
                $_REQUEST['_imageUploadError']);
            return;
        }

        $existing = $this->productService->getProductById($id, ['admin' => true]);
        if (!$existing) {
            header('Location: /admin/products');
            exit;
        }

        $body = $_POST;

        if (empty($body['name']) || !isset($body['price']) || $body['price'] === ''
            || empty($body['category']) || empty($body['gender'])) {
            $merged = array_merge($existing, $body);
            $this->renderFormWithError($merged, true, 'Edit Product', $merged,
                'Name, price, category and gender are required.');
            return;
        }

        $numericPrice = (float)$body['price'];
        if ($numericPrice < 0 || !is_finite($numericPrice)) {
            $merged = array_merge($existing, $body);
            $this->renderFormWithError($merged, true, 'Edit Product', $merged,
                'Price must be a non-negative number.');
            return;
        }

        $data = $this->buildProductData($body);

        // Append newly uploaded images to existing ones
        $uploadedImages = (array)($_REQUEST['_productImages'] ?? []);
        if (!empty($uploadedImages)) {
            $existingImages  = is_array($existing['images'] ?? null) ? $existing['images'] : [];
            $data['images']  = array_merge($existingImages, $uploadedImages);
            $data['thumbnail'] = $existing['thumbnail'] ?? $uploadedImages[0];
        }

        try {
            $this->productService->updateProduct($id, $data);
            header('Location: /admin/products');
            exit;
        } catch (Throwable $e) {
            $merged = array_merge($existing, $body);
            $this->renderFormWithError($merged, true, 'Edit Product', $merged,
                $e->getMessage() ?: 'Could not update product. Please try again.');
        }
    }

    /* ════════════════════════════════════════════════════════════
       DELETE — POST /admin/products/:id/delete
       Soft-delete: sets isActive = false.
       ════════════════════════════════════════════════════════════ */

    public function postDeleteProduct(string $id): void
    {
        try {
            $this->productService->updateProduct($id, ['isActive' => false]);
        } catch (Throwable $e) {
            error_log('[LFS Admin] ProductController::postDeleteProduct — ' . $e->getMessage());
        }

        header('Location: /admin/products');
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Render an admin view through the admin layout.
     */
    private function render(string $view, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        $csrfToken = $_SESSION['csrf_token'] ?? '';

        ob_start();
        require __DIR__ . '/../views/' . $view . '.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/admin.php';
    }

    /**
     * Re-render the product form with an error message.
     */
    private function renderFormWithError(
        ?array $product,
        bool   $isEdit,
        string $pageTitle,
        array  $formData,
        string $error
    ): void {
        $this->render('products/form', [
            'pageTitle'          => $pageTitle,
            'activePage'         => 'products',
            'product'            => $product ?? $formData,
            'PRODUCT_CATEGORIES' => Product::CATEGORIES,
            'GENDER_OPTIONS'     => Product::GENDER_OPTIONS,
            'isEdit'             => $isEdit,
            'error'              => $error,
            'breadcrumbs'        => [
                ['label' => 'Admin',    'url' => '/admin'],
                ['label' => 'Products', 'url' => '/admin/products'],
                ['label' => $pageTitle],
            ],
        ]);
    }

    /**
     * Build the sanitised data array from raw POST body.
     */
    private function buildProductData(array $body): array
    {
        return [
            'name'             => trim($body['name']),
            'slug'             => !empty($body['slug']) ? $this->slugify($body['slug']) : $this->slugify($body['name']),
            'price'            => (float)$body['price'],
            'comparePrice'     => ($body['comparePrice'] ?? '') !== '' ? (float)$body['comparePrice'] : null,
            'description'      => $body['description']      ?? '',
            'shortDescription' => $body['shortDescription'] ?? '',
            'category'         => $body['category'],
            'gender'           => $body['gender'],
            'tags'             => $this->parseTags($body['tags'] ?? ''),
            'sizes'            => $this->parseSizes($body['sizes'] ?? ''),
            'totalStock'       => ($body['totalStock'] ?? '') !== '' ? (int)$body['totalStock'] : 0,
            'featured'         => $this->normaliseCheckbox($body['featured'] ?? null),
            'isActive'         => $this->normaliseCheckbox($body['isActive'] ?? null),
            'sortOrder'        => ($body['sortOrder'] ?? '') !== '' ? (int)$body['sortOrder'] : 0,
        ];
    }

    /**
     * Parse a comma-separated tags string into a lowercase trimmed array.
     *   "Race, 10K , Sunrise" → ['race', '10k', 'sunrise']
     */
    private function parseTags(string $raw): array
    {
        if ($raw === '') return [];
        return array_values(array_filter(
            array_map(fn ($t) => strtolower(trim($t)), explode(',', $raw))
        ));
    }

    /**
     * Parse a sizes string like "S:10, M:8, L:4" into structured rows.
     *   → [['size' => 'S', 'stock' => 10], ...]
     */
    private function parseSizes(string $raw): array
    {
        if ($raw === '') return [];
        $result = [];
        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);
            if ($entry === '') continue;
            [$size, $stockStr] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, '0');
            $stock = max(0, (int)$stockStr);
            if ($size !== '') {
                $result[] = ['size' => $size, 'stock' => $stock];
            }
        }
        return $result;
    }

    /**
     * Normalise a checkbox POST value to bool.
     * HTML checkboxes submit 'on' when checked and are absent when unchecked.
     */
    private function normaliseCheckbox(mixed $value): bool
    {
        return $value === 'on' || $value === 'true' || $value === true || $value === '1';
    }

    /**
     * Convert a string to a URL-safe slug.
     *   "LFS Running Jersey!" → "lfs-running-jersey"
     */
    private function slugify(string $str): string
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9\s\-]/', '', $str);
        $str = preg_replace('/[\s\-]+/', '-', $str);
        return trim($str, '-');
    }

    /**
     * Rename a temp upload directory to the real product id after insert,
     * and patch the image URLs in the DB.
     */
    private function renameUploadDir(string $oldKey, string $newKey, array &$data, string $productId): void
    {
        $oldDir = $this->uploadRoot . '/' . $oldKey;
        $newDir = $this->uploadRoot . '/' . $newKey;

        if (is_dir($oldDir)) {
            try {
                rename($oldDir, $newDir);
            } catch (Throwable) {
                return; // Leave files in place; URLs remain valid
            }
        }

        // Patch image URLs from old key to new key
        $rewrite = fn (string $url): string => str_replace(
            '/uploads/products/' . $oldKey . '/',
            '/uploads/products/' . $newKey . '/',
            $url
        );

        $images    = array_map($rewrite, (array)($data['images'] ?? []));
        $thumbnail = isset($data['thumbnail']) ? $rewrite($data['thumbnail']) : ($images[0] ?? null);

        try {
            $this->productService->updateProduct($productId, [
                'images'    => $images,
                'thumbnail' => $thumbnail,
            ]);
        } catch (Throwable $e) {
            error_log('[LFS Admin] ProductController::renameUploadDir — ' . $e->getMessage());
        }
    }

    /**
     * Fetch a product by ID without throwing on failure.
     */
    private function safeGetById(string $id): ?array
    {
        try {
            return $this->productService->getProductById($id, ['admin' => true]);
        } catch (Throwable) {
            return null;
        }
    }
}
