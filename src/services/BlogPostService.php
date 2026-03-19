<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/BlogPostService.php — Blog post data layer
 *
 * All public methods throw on DB error; callers should catch Throwable.
 * Returned arrays use camelCase keys matching BlogPost model field docs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../model/BlogPost.php';

class BlogPostService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    /* ════════════════════════════════════════════════════════════
       LIST
       ════════════════════════════════════════════════════════════ */

    /**
     * @param  array $opts { status?, category?, featured?, search?, limit?, offset? }
     * @return array{ posts: array, total: int }
     *
     * Result caching (APCu)
     * ─────────────────────
     * Set BLOG_LIST_CACHE_TTL to a positive integer (seconds) in your bootstrap
     * or environment config to enable APCu caching of list results:
     *
     *   define('BLOG_LIST_CACHE_TTL', 120); // cache for 2 minutes
     *
     * Cache is bypassed automatically when any filter option is set (status,
     * category, featured, search) so filtered/admin views always see live data.
     * Call BlogPostService::bustListCache() after create/update/delete to
     * invalidate immediately rather than waiting for TTL expiry.
     */
    public function getPosts(array $opts = []): array
    {
        // ── Optional APCu cache (unfiltered list only) ────────────────────────
        $ttl       = defined('BLOG_LIST_CACHE_TTL') ? (int) BLOG_LIST_CACHE_TTL : 0;
        $cacheable = $ttl > 0
            && function_exists('apcu_fetch')
            && empty($opts['status'])
            && empty($opts['category'])
            && !isset($opts['featured'])
            && empty($opts['search']);

        if ($cacheable) {
            $cacheKey = 'lfs_blog_list_' . ($opts['limit'] ?? 50) . '_' . ($opts['offset'] ?? 0);
            $success  = false;
            $cached   = apcu_fetch($cacheKey, $success);
            if ($success) {
                return $cached;
            }
        }
        // ─────────────────────────────────────────────────────────────────────
        $where  = ['1=1'];
        $params = [];

        if (!empty($opts['status'])) {
            $where[]           = 'status = :status';
            $params[':status'] = $opts['status'];
        }
        if (!empty($opts['category'])) {
            $where[]             = 'category = :category';
            $params[':category'] = $opts['category'];
        }
        if (isset($opts['featured'])) {
            $where[]             = 'featured = :featured';
            $params[':featured'] = (int) $opts['featured'];
        }
        if (!empty($opts['search'])) {
            $where[]           = '(title LIKE :search OR excerpt LIKE :search)';
            $params[':search'] = '%' . $opts['search'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        // Total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Paginated rows
        $limit  = max(1, (int) ($opts['limit']  ?? 50));
        $offset = max(0, (int) ($opts['offset'] ?? 0));

        // Paginated rows — exclude `content` (rich HTML, potentially many KB per
        // post) because the list view never renders it.  getPostById() still
        // fetches every column for the edit/delete/preview flows.
        $cols = 'id, title, slug, excerpt, author, category, tags, '
              . 'status, featured, views, publish_date, created_at, updated_at';

        $sql  = "SELECT $cols FROM blog_posts WHERE $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $posts = array_map([$this, 'toPost'], $stmt->fetchAll());

        $result = ['posts' => $posts, 'total' => $total];

        // Store in APCu cache if enabled and this was a cacheable call.
        if ($cacheable) {
            apcu_store($cacheKey, $result, $ttl);
        }

        return $result;
    }

    /**
     * Invalidate all cached blog list pages.
     * Call this after any create, update, or delete so visitors never see stale
     * post counts or titles.  Safe to call even when APCu is not available.
     */
    public function bustListCache(): void
    {
        if (!function_exists('apcu_delete')) {
            return;
        }
        // APCu doesn't support wildcard deletes, so we iterate the key prefix.
        $info = apcu_cache_info(false);
        foreach (($info['cache_list'] ?? []) as $entry) {
            $key = $entry['info'] ?? '';
            if (str_starts_with($key, 'lfs_blog_list_')) {
                apcu_delete($key);
            }
        }
    }

    /* ════════════════════════════════════════════════════════════
       SINGLE
       ════════════════════════════════════════════════════════════ */

    public function getPostById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $this->toPost($row) : null;
    }

    public function getPostBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blog_posts WHERE slug = :slug LIMIT 1');
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $this->toPost($row) : null;
    }

    /* ════════════════════════════════════════════════════════════
       CREATE
       ════════════════════════════════════════════════════════════ */

    public function createPost(array $data): array
    {
        $id   = $this->generateUuid();
        $slug = trim($data['slug'] ?? '');
        if ($slug === '') {
            $slug = $this->slugify($data['title'] ?? 'post');
        }
        $slug = $this->ensureUniqueSlug($slug, null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO blog_posts
               (id, title, slug, excerpt, content, featured_image, author,
                category, tags, status, featured, views, publish_date)
             VALUES
               (:id, :title, :slug, :excerpt, :content, :featuredImage, :author,
                :category, :tags, :status, :featured, 0, :publishDate)'
        );
        $stmt->execute([
            ':id'           => $id,
            ':title'        => $data['title']        ?? '',
            ':slug'         => $slug,
            ':excerpt'      => $data['excerpt']       ?? null,
            ':content'      => $data['content']       ?? null,
            ':featuredImage'=> $data['featuredImage'] ?? null,
            ':author'       => $data['author']        ?? 'LFS Admin',
            ':category'     => $data['category']      ?? '',
            ':tags'         => json_encode($data['tags'] ?? []),
            ':status'       => $data['status']        ?? 'draft',
            ':featured'     => (int) ($data['featured'] ?? false),
            ':publishDate'  => $data['publishDate']   ?? null,
        ]);

        $this->bustListCache();
        return $this->getPostById($id);
    }

    /* ════════════════════════════════════════════════════════════
       UPDATE
       ════════════════════════════════════════════════════════════ */

    /**
     * Update a post by ID.
     *
     * Returns false when no post with $id exists (caller may redirect to list).
     * Returns true when the UPDATE executed successfully.
     *
     * The method intentionally does NOT re-fetch the row after the UPDATE.
     * Callers that need the refreshed record (e.g. an API endpoint) should call
     * getPostById() themselves. The admin controller always redirects on success
     * so it never needs the post-update snapshot, making that SELECT pure waste.
     */
    public function updatePost(string $id, array $data): bool
    {
        $existing = $this->getPostById($id);
        if (!$existing) return false;

        if (array_key_exists('slug', $data)) {
            $slug = trim((string) ($data['slug'] ?? ''));
            if ($slug === '') {
                $slug = $this->slugify($data['title'] ?? $existing['title']);
            }
            $data['slug'] = $this->ensureUniqueSlug($slug, $id);
        }

        // camelCase key → DB column name
        $map = [
            'title'        => 'title',
            'slug'         => 'slug',
            'excerpt'      => 'excerpt',
            'content'      => 'content',
            'featuredImage'=> 'featured_image',
            'author'       => 'author',
            'category'     => 'category',
            'tags'         => 'tags',
            'status'       => 'status',
            'featured'     => 'featured',
            'publishDate'  => 'publish_date',
        ];

        $sets   = [];
        $params = [':id' => $id];

        foreach ($map as $camel => $col) {
            if (!array_key_exists($camel, $data)) continue;
            $val = $data[$camel];
            if ($camel === 'tags')     $val = json_encode((array) $val);
            if ($camel === 'featured') $val = (int) (bool) $val;
            $sets[]            = "$col = :$camel";
            $params[":$camel"] = $val;
        }

        if (empty($sets)) return true; // nothing to change — not an error

        $sql = 'UPDATE blog_posts SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);

        $this->bustListCache();
        return true;
    }

    /* ════════════════════════════════════════════════════════════
       DELETE
       ════════════════════════════════════════════════════════════ */

    public function deletePost(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        $deleted = $stmt->rowCount() > 0;

        if ($deleted) {
            $this->bustListCache();
        }

        return $deleted;
    }

    /* ════════════════════════════════════════════════════════════
       INCREMENT VIEWS
       ════════════════════════════════════════════════════════════ */

    public function incrementViews(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE blog_posts SET views = views + 1 WHERE id = :id');
        $stmt->bindValue(':id', $id);
        $stmt->execute();
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    private function toPost(array $row): array
    {
        return [
            'id'            => $row['id'],
            'title'         => $row['title'],
            'slug'          => $row['slug'],
            'excerpt'       => $row['excerpt']       ?? '',
            'content'       => $row['content']       ?? '',
            'featuredImage' => $row['featured_image'] ?? '',
            'author'        => $row['author']         ?? 'LFS Admin',
            'category'      => $row['category']       ?? '',
            'tags'          => json_decode($row['tags'] ?? 'null', true) ?? [],
            'status'        => $row['status']         ?? 'draft',
            'featured'      => (bool) ($row['featured'] ?? false),
            'views'         => (int)  ($row['views']    ?? 0),
            'publishDate'   => $row['publish_date']   ?? null,
            'createdAt'     => $row['created_at']     ?? null,
            'updatedAt'     => $row['updated_at']     ?? null,
        ];
    }

    private function slugify(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', trim($slug));
        return substr($slug, 0, 200) ?: 'post';
    }

    private function ensureUniqueSlug(string $slug, ?string $excludeId): string
    {
        $base   = $slug;
        $suffix = 1;
        while (true) {
            $sql  = 'SELECT COUNT(*) FROM blog_posts WHERE slug = :slug'
                  . ($excludeId ? ' AND id != :id' : '');
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':slug', $slug);
            if ($excludeId) $stmt->bindValue(':id', $excludeId);
            $stmt->execute();
            if ((int) $stmt->fetchColumn() === 0) return $slug;
            $slug = $base . '-' . $suffix++;
        }
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
