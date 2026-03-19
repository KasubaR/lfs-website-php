<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/ProductService.php — Product data layer (MySQL PDO)
 *
 * Replaces product.service.js (Supabase).
 * Supabase table `products` → MySQL table `products` (same schema, snake_case).
 *
 * JSON columns (images, tags, sizes) are stored as MySQL JSON and
 * decoded automatically by toProduct().
 *
 * All public methods throw on DB error; callers should catch Throwable.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

class ProductService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* ════════════════════════════════════════════════════════════
       READ
       ════════════════════════════════════════════════════════════ */

    /**
     * List products with filtering, sorting and pagination.
     *
     * @param array $opts
     *   Keys: category, gender, size, minPrice, maxPrice,
     *         sort (latest|popular|price-asc|price-desc),
     *         page (default 1), limit (default 12)
     * @param array $options  Keys: admin (bool, default false)
     * @return array{products: array, total: int, page: int, limit: int, pages: int}
     */
    public function getProducts(array $opts = [], array $options = []): array
    {
        $admin    = (bool)($options['admin'] ?? false);
        $category = $opts['category'] ?? null;
        $gender   = $opts['gender']   ?? null;
        $size     = $opts['size']     ?? null;
        $minPrice = isset($opts['minPrice']) && $opts['minPrice'] !== '' ? (float)$opts['minPrice'] : null;
        $maxPrice = isset($opts['maxPrice']) && $opts['maxPrice'] !== '' ? (float)$opts['maxPrice'] : null;
        $sort     = $opts['sort']  ?? 'latest';
        $page     = max(1, (int)($opts['page']  ?? 1));
        $limit    = max(1, (int)($opts['limit'] ?? 12));

        $where  = [];
        $params = [];

        if (!$admin) {
            $where[] = 'is_active = 1';
        }
        if ($category !== null && $category !== '') {
            $where[]           = 'category = :category';
            $params[':category'] = $category;
        }
        if ($gender !== null && $gender !== '') {
            $where[]         = 'gender = :gender';
            $params[':gender'] = $gender;
        }
        if ($minPrice !== null) {
            $where[]           = 'price >= :minPrice';
            $params[':minPrice'] = $minPrice;
        }
        if ($maxPrice !== null) {
            $where[]           = 'price <= :maxPrice';
            $params[':maxPrice'] = $maxPrice;
        }
        // Size filter: JSON_CONTAINS on sizes column
        // sizes is stored as [{"size":"S","stock":10}, ...]
        if ($size !== null && $size !== '') {
            $where[]          = 'JSON_CONTAINS(sizes, :sizeJson, \'$\')';
            $params[':sizeJson'] = json_encode(['size' => (string)$size]);
        }

        $sortMap = [
            'latest'     => 'created_at DESC',
            'popular'    => 'sort_order DESC',
            'price-asc'  => 'price ASC',
            'price-desc' => 'price DESC',
        ];
        $orderBy = $sortMap[$sort] ?? 'created_at DESC';

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countSql  = "SELECT COUNT(*) FROM products $whereSql";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));

        // Data page
        $offset   = ($page - 1) * $limit;
        $dataSql  = "SELECT * FROM products $whereSql ORDER BY $orderBy LIMIT :limit OFFSET :offset";
        $dataStmt = $this->db->prepare($dataSql);
        foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
        $dataStmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $rows = $dataStmt->fetchAll();

        return [
            'products' => array_map([$this, 'toProduct'], $rows),
            'total'    => $total,
            'page'     => $page,
            'limit'    => $limit,
            'pages'    => $pages,
        ];
    }

    /**
     * Backwards-compatible public listing. Prefer getProducts($opts, ['admin' => false]).
     * @deprecated
     */
    public function findPublic(array $opts = []): array
    {
        return $this->getProducts($opts, ['admin' => false]);
    }

    /**
     * Find one product by slug.
     * Pass ['admin' => true] to include inactive products.
     */
    public function getProductBySlug(string $slug, array $options = []): ?array
    {
        $admin = (bool)($options['admin'] ?? false);
        $sql   = 'SELECT * FROM products WHERE slug = :slug'
               . ($admin ? '' : ' AND is_active = 1')
               . ' LIMIT 1';
        $stmt  = $this->db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ? $this->toProduct($row) : null;
    }

    /**
     * Find one product by id.
     * Pass ['admin' => true] to include inactive products.
     */
    public function getProductById(string $id, array $options = []): ?array
    {
        $admin = (bool)($options['admin'] ?? false);
        $sql   = 'SELECT * FROM products WHERE id = :id'
               . ($admin ? '' : ' AND is_active = 1')
               . ' LIMIT 1';
        $stmt  = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->toProduct($row) : null;
    }

    /** @deprecated Use getProductBySlug($slug, ['admin' => false]) */
    public function findOneBySlug(string $slug): ?array
    {
        return $this->getProductBySlug($slug, ['admin' => false]);
    }

    /** @deprecated Use getProductById($id, ['admin' => false]) */
    public function findById(string $id): ?array
    {
        return $this->getProductById($id, ['admin' => false]);
    }

    /**
     * Find related products in the same category, excluding a given id.
     */
    public function findRelatedByCategory(string $category, string $excludeId, int $limit = 4): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM products
              WHERE category = :category AND is_active = 1 AND id != :excludeId
              ORDER BY created_at DESC
              LIMIT :limit'
        );
        $stmt->bindValue(':category',  $category);
        $stmt->bindValue(':excludeId', $excludeId);
        $stmt->bindValue(':limit',     $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'toProduct'], $stmt->fetchAll());
    }

    /* ════════════════════════════════════════════════════════════
       MUTATIONS (Admin)
       ════════════════════════════════════════════════════════════ */

    /**
     * Create a product. Data in camelCase.
     */
    public function createProduct(array $data): array
    {
        $sql = '
            INSERT INTO products
              (name, slug, price, compare_price, description, short_description,
               images, thumbnail, category, gender, tags, sizes,
               total_stock, featured, is_active, sort_order)
            VALUES
              (:name, :slug, :price, :compare_price, :description, :short_description,
               :images, :thumbnail, :category, :gender, :tags, :sizes,
               :total_stock, :featured, :is_active, :sort_order)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name'              => $data['name'],
            ':slug'              => $data['slug'],
            ':price'             => $data['price'],
            ':compare_price'     => $data['comparePrice']     ?? null,
            ':description'       => $data['description']      ?? '',
            ':short_description' => $data['shortDescription'] ?? '',
            ':images'            => $this->encodeJson($data['images']    ?? []),
            ':thumbnail'         => $data['thumbnail']        ?? '/images/products/placeholder.webp',
            ':category'          => $data['category'],
            ':gender'            => $data['gender']           ?? 'unisex',
            ':tags'              => $this->encodeJson($data['tags']      ?? []),
            ':sizes'             => $this->encodeJson($data['sizes']     ?? []),
            ':total_stock'       => (int)($data['totalStock']  ?? 0),
            ':featured'          => (int)(bool)($data['featured']  ?? false),
            ':is_active'         => (int)(($data['isActive'] ?? true) !== false),
            ':sort_order'        => (int)($data['sortOrder']  ?? 0),
        ]);

        $id = $this->db->lastInsertId();
        return $this->getProductById($id, ['admin' => true]) ?? [];
    }

    /**
     * Update a product by id. Partial updates supported.
     *
     * @return array|null  Updated product, or null if id not found.
     */
    public function updateProduct(string $id, array $data): ?array
    {
        $map = [
            // camelCase key  =>  [column, type]
            'name'             => ['name',              'string'],
            'slug'             => ['slug',              'string'],
            'price'            => ['price',             'float'],
            'comparePrice'     => ['compare_price',     'null_float'],
            'description'      => ['description',       'string'],
            'shortDescription' => ['short_description', 'string'],
            'images'           => ['images',            'json'],
            'thumbnail'        => ['thumbnail',         'string'],
            'category'         => ['category',          'string'],
            'gender'           => ['gender',            'string'],
            'tags'             => ['tags',              'json'],
            'sizes'            => ['sizes',             'json'],
            'totalStock'       => ['total_stock',       'int'],
            'featured'         => ['featured',          'bool'],
            'isActive'         => ['is_active',         'bool'],
            'sortOrder'        => ['sort_order',        'int'],
        ];

        $sets   = ['updated_at = UTC_TIMESTAMP()'];
        $params = [':id' => $id];

        foreach ($map as $camel => [$snake, $type]) {
            if (!array_key_exists($camel, $data)) continue;
            $ph  = ':' . $camel;
            $val = $data[$camel];

            $params[$ph] = match ($type) {
                'string'     => (string)$val,
                'float'      => (float)$val,
                'null_float' => $val !== null ? (float)$val : null,
                'int'        => (int)$val,
                'bool'       => (int)(bool)$val,
                'json'       => $this->encodeJson(is_array($val) ? $val : []),
                default      => $val,
            };

            $sets[] = $snake . ' = ' . $ph;
        }

        if (count($sets) === 1) {
            return $this->getProductById($id, ['admin' => true]);
        }

        $sql  = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0
            ? $this->getProductById($id, ['admin' => true])
            : null;
    }

    /**
     * Hard-delete a product by id.
     * For soft delete: updateProduct($id, ['isActive' => false]).
     */
    public function deleteProduct(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Map a DB row (snake_case, JSON strings) to the camelCase shape
     * used by controllers and views.
     * Includes both `id` and `_id` for backward compatibility.
     */
    private function toProduct(?array $row): ?array
    {
        if ($row === null) return null;

        return [
            '_id'             => $row['id'],
            'id'              => $row['id'],
            'name'            => $row['name'],
            'slug'            => $row['slug'],
            'price'           => (float)($row['price'] ?? 0),
            'comparePrice'    => $row['compare_price'] !== null ? (float)$row['compare_price'] : null,
            'description'     => $row['description']      ?? '',
            'shortDescription'=> $row['short_description'] ?? '',
            'images'          => $this->decodeJsonArray($row['images']),
            'thumbnail'       => $this->sanitiseImageUrl(
                $row['thumbnail'],
                '/images/products/placeholder.webp'
            ),
            'category'        => $row['category'],
            'gender'          => $row['gender']     ?? 'unisex',
            'tags'            => $this->decodeJsonArray($row['tags']),
            'sizes'           => $this->decodeJsonArray($row['sizes']),
            'totalStock'      => (int)($row['total_stock'] ?? 0),
            'featured'        => (bool)($row['featured']   ?? false),
            'isActive'        => ($row['is_active'] ?? 1) != 0,
            'sortOrder'       => (int)($row['sort_order']  ?? 0),
            'createdAt'       => $row['created_at'],
            'updatedAt'       => $row['updated_at'],
        ];
    }

    /** Decode a JSON column value to a PHP array; returns [] on null/invalid. */
    private function decodeJsonArray(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        if (is_array($value)) return $value;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Encode a PHP array to a JSON string for storage. */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Return the url if non-empty, otherwise return the fallback. */
    public function sanitiseImageUrl(mixed $url, string $fallback = ''): string
    {
        $v = trim((string)($url ?? ''));
        return $v !== '' ? $v : $fallback;
    }
}
