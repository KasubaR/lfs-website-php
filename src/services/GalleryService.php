<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/GalleryService.php — Gallery data layer (MySQL PDO)
 *
 * Replaces gallery.service.js (Supabase).
 * Supabase tables `albums` and `media` → MySQL tables with the same schema.
 *
 * JSON columns (tags, urls) are stored as MySQL JSON and decoded automatically.
 * All public methods throw on DB error; callers should catch Throwable.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

class GalleryService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* ════════════════════════════════════════════════════════════
       ALBUMS — READ
       ════════════════════════════════════════════════════════════ */

    /**
     * List albums with optional search, category, year.
     * Sorted by date DESC (nulls last), then created_at DESC.
     *
     * @param array $query  Keys: search, category, year
     * @return array<array>
     */
    public function getAlbums(array $query = []): array
    {
        $search   = isset($query['search'])   ? trim($query['search'])   : '';
        $category = $query['category'] ?? '';
        $year     = $query['year']     ?? '';

        $where  = [];
        $params = [];

        if ($category !== '') {
            $where[]             = 'category = :category';
            $params[':category'] = $category;
        }
        if ($year !== '') {
            $where[]           = 'date >= :yearStart AND date <= :yearEnd';
            $params[':yearStart'] = "$year-01-01 00:00:00";
            $params[':yearEnd']   = "$year-12-31 23:59:59";
        }
        if ($search !== '') {
            $where[]         = '(title LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // ISNULL(date) sorts nulls last on both ASC and DESC
        $sql  = "SELECT * FROM albums $whereSql
                 ORDER BY ISNULL(date) ASC, date DESC, created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'toAlbum'], $stmt->fetchAll());
    }

    /**
     * Single album by id. Returns null if not found.
     */
    public function getAlbumById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM albums WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->toAlbum($row) : null;
    }

    /**
     * Count albums where featured = true.
     */
    public function countFeaturedAlbums(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM albums WHERE featured = 1');
        return (int)$stmt->fetchColumn();
    }

    /**
     * All albums for the upload-page dropdown. Sorted by date DESC (nulls last).
     */
    public function getAlbumsForUpload(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM albums ORDER BY ISNULL(date) ASC, date DESC'
        );
        return array_map([$this, 'toAlbum'], $stmt->fetchAll());
    }

    /* ════════════════════════════════════════════════════════════
       ALBUMS — MUTATIONS
       ════════════════════════════════════════════════════════════ */

    /**
     * Create an album. Data in camelCase.
     */
    public function createAlbum(array $data): array
    {
        $sql = '
            INSERT INTO albums
              (title, description, category, date, location, event, tags,
               cover_image, external_url, media_count,
               featured, homepage_slider, event_highlight, sort_priority)
            VALUES
              (:title, :description, :category, :date, :location, :event, :tags,
               :cover_image, :external_url, :media_count,
               :featured, :homepage_slider, :event_highlight, :sort_priority)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title'           => $data['title'],
            ':description'     => $data['description']    ?? null,
            ':category'        => $data['category']       ?? null,
            ':date'            => $data['date']           ?: null,
            ':location'        => $data['location']       ?? null,
            ':event'           => $data['event']          ?? null,
            ':tags'            => $this->encodeJson($data['tags'] ?? []),
            ':cover_image'     => $data['coverImage']     ?? null,
            ':external_url'    => $data['externalUrl']    ?: null,
            ':media_count'     => (int)($data['mediaCount']    ?? 0),
            ':featured'        => (int)(bool)($data['featured']        ?? false),
            ':homepage_slider' => (int)(bool)($data['homepageSlider']  ?? false),
            ':event_highlight' => (int)(bool)($data['eventHighlight']  ?? false),
            ':sort_priority'   => (int)($data['sortPriority']  ?? 0),
        ]);

        $id = $this->db->lastInsertId();
        return $this->getAlbumById($id) ?? [];
    }

    /**
     * Update album by id. Partial updates supported.
     */
    public function updateAlbum(string $id, array $data): ?array
    {
        $map = [
            // camelCase       =>  [snake_case,       type]
            'title'            => ['title',            'string'],
            'description'      => ['description',      'string'],
            'category'         => ['category',         'string'],
            'date'             => ['date',             'string_null'],
            'location'         => ['location',         'string'],
            'event'            => ['event',            'string'],
            'tags'             => ['tags',             'json'],
            'coverImage'       => ['cover_image',      'string'],
            'externalUrl'      => ['external_url',     'string_null'],
            'mediaCount'       => ['media_count',      'int'],
            'sortPriority'     => ['sort_priority',    'int'],
        ];

        $sets   = ['updated_at = UTC_TIMESTAMP()'];
        $params = [':id' => $id];

        foreach ($map as $camel => [$snake, $type]) {
            if (!array_key_exists($camel, $data)) continue;
            $ph  = ':' . $camel;
            $val = $data[$camel];
            $params[$ph] = match ($type) {
                'string'      => (string)$val,
                'string_null' => ($val !== null && $val !== '') ? (string)$val : null,
                'int'         => (int)$val,
                'bool'        => (int)(bool)$val,
                'json'        => $this->encodeJson(is_array($val) ? $val : []),
                default       => $val,
            };
            $sets[] = $snake . ' = ' . $ph;
        }

        $sql  = 'UPDATE albums SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getAlbumById($id);
    }

    /**
     * Increment (or decrement) album media_count by delta.
     */
    public function incrementAlbumMediaCount(string $albumId, int $delta): void
    {
        // Use GREATEST to prevent negative counts
        $stmt = $this->db->prepare(
            'UPDATE albums
             SET media_count = GREATEST(0, media_count + :delta),
                 updated_at  = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        $stmt->execute([':delta' => $delta, ':id' => $albumId]);
    }

    /**
     * Delete album by id.
     */
    public function deleteAlbum(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM albums WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /* ════════════════════════════════════════════════════════════
       MEDIA — READ
       ════════════════════════════════════════════════════════════ */

    /**
     * Total media count across all albums.
     */
    public function countMedia(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM media')->fetchColumn();
    }

    /**
     * Media for one album. sort: 'newest' | 'oldest' | 'featured'
     */
    public function getMediaByAlbumId(string $albumId, string $sort = 'newest'): array
    {
        $orderBy = match ($sort) {
            'oldest'   => 'created_at ASC',
            'featured' => 'featured DESC, created_at DESC',
            default    => 'created_at DESC',
        };

        $stmt = $this->db->prepare(
            "SELECT * FROM media WHERE album_id = :albumId ORDER BY $orderBy"
        );
        $stmt->execute([':albumId' => $albumId]);
        return array_map([$this, 'toMedia'], $stmt->fetchAll());
    }

    /**
     * Single media by id.
     */
    public function getMediaById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM media WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->toMedia($row) : null;
    }

    /**
     * Find media by a list of ids.
     */
    public function findMediaByIds(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM media WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        return array_map([$this, 'toMedia'], $stmt->fetchAll());
    }

    /**
     * Photos flagged as homepage slider, ordered by sort_order then newest.
     */
    public function getHomepageSliderMedia(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, album_id, urls, caption, featured, homepage_slider, event_highlight, type
             FROM media
             WHERE homepage_slider = 1 AND type = 'photo'
             ORDER BY sort_order ASC, created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'toMedia'], $stmt->fetchAll());
    }

    /**
     * Up to $limit featured-first photos for the homepage preview grid.
     */
    public function getHomepageMedia(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, album_id, urls, caption, featured, homepage_slider, event_highlight, type
             FROM media
             WHERE type = \'photo\'
             ORDER BY homepage_slider DESC, featured DESC, created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'toMedia'], $stmt->fetchAll());
    }

    /**
     * Get the global gallery banner image URL, or null if not set.
     */
    public function getGalleryBanner(): ?string
    {
        $stmt = $this->db->query('SELECT banner_image FROM gallery_settings WHERE id = 1');
        $row  = $stmt->fetch();
        $val  = $row['banner_image'] ?? null;
        $val  = $val !== null ? trim((string)$val) : '';
        return $val !== '' ? $val : null;
    }

    /**
     * Set (or clear) the global gallery banner image URL.
     */
    public function setGalleryBanner(?string $bannerImage): void
    {
        $val  = $bannerImage !== null ? trim((string)$bannerImage) : null;
        $stmt = $this->db->prepare(
            'INSERT INTO gallery_settings (id, banner_image)
             VALUES (1, :banner)
             ON DUPLICATE KEY UPDATE banner_image = :banner2'
        );
        $b = $val !== '' ? $val : null;
        $stmt->bindValue(':banner', $b);
        $stmt->bindValue(':banner2', $b);
        $stmt->execute();
    }

    /* ════════════════════════════════════════════════════════════
       MEDIA — MUTATIONS
       ════════════════════════════════════════════════════════════ */

    /**
     * Create a media item. Data in camelCase.
     */
    public function createMedia(array $data): array
    {
        $sql = '
            INSERT INTO media
              (album_id, filename, stored_name, type, mimetype, size,
               urls, caption, tags, featured, homepage_slider, event_highlight, sort_order)
            VALUES
              (:album_id, :filename, :stored_name, :type, :mimetype, :size,
               :urls, :caption, :tags, :featured, :homepage_slider, :event_highlight, :sort_order)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':album_id'         => $data['albumId'],
            ':filename'         => $data['filename']   ?? null,
            ':stored_name'      => $data['storedName'] ?? null,
            ':type'             => $data['type'],
            ':mimetype'         => $data['mimetype']   ?? null,
            ':size'             => $data['size']       ?? null,
            ':urls'             => $this->encodeJson($data['urls'] ?? []),
            ':caption'          => $data['caption']    ?? '',
            ':tags'             => $this->encodeJson($data['tags'] ?? []),
            ':featured'         => (int)(bool)($data['featured']         ?? false),
            ':homepage_slider'  => (int)(bool)($data['homepageSlider']   ?? false),
            ':event_highlight'  => (int)(bool)($data['eventHighlight']   ?? false),
            ':sort_order'       => (int)($data['sortOrder'] ?? 0),
        ]);

        $id = $this->db->lastInsertId();
        return $this->getMediaById($id) ?? [];
    }

    /**
     * Update a media item. Partial updates supported.
     * Pass $opts['new'] = true to return the updated row.
     */
    public function updateMedia(string $id, array $data, array $opts = []): ?array
    {
        $map = [
            'caption'        => ['caption',           'string'],
            'featured'       => ['featured',          'bool'],
            'homepageSlider' => ['homepage_slider',   'bool'],
            'eventHighlight' => ['event_highlight',   'bool'],
            'sortOrder'      => ['sort_order',       'int'],
            'albumId'        => ['album_id',         'string'],
        ];

        $sets   = ['updated_at = UTC_TIMESTAMP()'];
        $params = [':id' => $id];

        foreach ($map as $camel => [$snake, $type]) {
            if (!array_key_exists($camel, $data)) continue;
            $ph  = ':' . $camel;
            $val = $data[$camel];
            $params[$ph] = match ($type) {
                'bool'   => (int)(bool)$val,
                'int'    => (int)$val,
                default  => (string)$val,
            };
            $sets[] = $snake . ' = ' . $ph;
        }

        $sql  = 'UPDATE media SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return !empty($opts['new']) ? $this->getMediaById($id) : null;
    }

    /**
     * Delete a media item by id.
     */
    public function deleteMedia(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM media WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Delete all media in an album.
     */
    public function deleteMediaByAlbumId(string $albumId): void
    {
        $stmt = $this->db->prepare('DELETE FROM media WHERE album_id = :albumId');
        $stmt->execute([':albumId' => $albumId]);
    }

    /**
     * Delete multiple media items by id array.
     */
    public function deleteManyMedia(array $ids): void
    {
        if (empty($ids)) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM media WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
    }

    /**
     * Bulk-update media items (e.g. set featured = true, or move to another album).
     * Accepted keys in $data: featured, albumId.
     */
    public function updateManyMedia(array $ids, array $data): void
    {
        if (empty($ids)) return;

        $sets   = ['updated_at = UTC_TIMESTAMP()'];
        $params = [];

        if (array_key_exists('featured', $data)) {
            $sets[]              = 'featured = :featured';
            $params[':featured'] = (int)(bool)$data['featured'];
        }
        if (array_key_exists('homepageSlider', $data)) {
            $sets[]                   = 'homepage_slider = :homepage_slider';
            $params[':homepage_slider'] = (int)(bool)$data['homepageSlider'];
        }
        if (array_key_exists('eventHighlight', $data)) {
            $sets[]                   = 'event_highlight = :event_highlight';
            $params[':event_highlight'] = (int)(bool)$data['eventHighlight'];
        }
        if (array_key_exists('albumId', $data)) {
            $sets[]            = 'album_id = :albumId';
            $params[':albumId'] = $data['albumId'];
        }

        if (count($sets) === 1) return; // nothing to update

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql  = 'UPDATE media SET ' . implode(', ', $sets)
              . " WHERE id IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        // Named params first, then positional ids
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        foreach (array_values($ids) as $i => $id) {
            $stmt->bindValue($i + 1, $id);
        }
        $stmt->execute();
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Map a DB row (snake_case) to the camelCase shape used by controllers/views.
     * Includes both `id` and `_id` for backward compatibility.
     */
    private function toAlbum(?array $row): ?array
    {
        if ($row === null) return null;
        return [
            '_id'            => $row['id'],
            'id'             => $row['id'],
            'title'          => $row['title'],
            'description'    => $row['description'] ?? null,
            'category'       => $row['category']    ?? null,
            'date'           => $row['date']         ?? null,
            'location'       => $row['location']     ?? null,
            'event'          => $row['event']        ?? null,
            'tags'           => $this->decodeJsonArray($row['tags']),
            'coverImage'     => $row['cover_image']  ?? null,
            'externalUrl'    => $row['external_url'] ?? '',
            'mediaCount'     => (int)($row['media_count']    ?? 0),
            'featured'       => (bool)($row['featured']        ?? false),
            'homepageSlider' => (bool)($row['homepage_slider'] ?? false),
            'eventHighlight' => (bool)($row['event_highlight'] ?? false),
            'sortPriority'   => (int)($row['sort_priority']   ?? 0),
            'createdAt'      => $row['created_at'],
            'updatedAt'      => $row['updated_at'],
        ];
    }

    /**
     * Map a media DB row to the camelCase shape used by controllers/views.
     */
    private function toMedia(?array $row): ?array
    {
        if ($row === null) return null;
        return [
            '_id'        => $row['id'] ?? null,
            'id'         => $row['id'] ?? null,
            'albumId'    => $row['album_id'] ?? null,
            'filename'   => $row['filename']   ?? null,
            'storedName' => $row['stored_name'] ?? null,
            'type'       => $row['type']       ?? 'photo',
            'mimetype'   => $row['mimetype']   ?? null,
            'size'       => $row['size']       ?? null,
            'urls'       => $this->decodeJsonMap($row['urls'] ?? null),
            'caption'    => $row['caption']    ?? '',
            'tags'       => $this->decodeJsonArray($row['tags'] ?? null),
            'featured'       => (bool)($row['featured']         ?? false),
            'homepageSlider' => (bool)($row['homepage_slider'] ?? false),
            'eventHighlight' => (bool)($row['event_highlight'] ?? false),
            'sortOrder'      => (int)($row['sort_order']  ?? 0),
            'createdAt'      => $row['created_at'] ?? null,
            'updatedAt'      => $row['updated_at'] ?? null,
        ];
    }

    /** Decode a JSON column to a PHP array; returns [] on null/invalid. */
    private function decodeJsonArray(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        if (is_array($value)) return $value;
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Decode a JSON column to a PHP associative array (object); returns {} on null/invalid. */
    private function decodeJsonMap(mixed $value): array
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
}
