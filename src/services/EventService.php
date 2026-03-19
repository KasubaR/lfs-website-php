<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/services/EventService.php — Event data layer (MySQL PDO)
 *
 * Replaces event.service.js (Supabase).
 * Supabase table `events` → MySQL table `events` (same schema, snake_case).
 *
 * All public methods throw on DB error; callers should catch Throwable.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

class EventService
{
    /** Error codes — match the JS constants for consistent controller handling. */
    public const SLUG_TAKEN_CODE        = 'SLUG_TAKEN';
    public const DATE_ORDER_INVALID_CODE = 'DATE_ORDER_INVALID';
    public const INVALID_BANNER_URL_CODE = 'INVALID_BANNER_URL';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* ════════════════════════════════════════════════════════════
       READ
       ════════════════════════════════════════════════════════════ */

    /**
     * List events with optional filters, sorted by event_date DESC.
     *
     * @param array $opts  Keys: category, fromDate, toDate, limit (default 50)
     * @return array<array>
     */
    public function getEvents(array $opts = []): array
    {
        $category  = $opts['category']  ?? null;
        $fromDate  = $opts['fromDate']  ?? null;
        $toDate    = $opts['toDate']    ?? null;
        $limit     = (int)($opts['limit'] ?? 50);

        $where  = [];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[]  = 'category = :category';
            $params[':category'] = $category;
        }
        if ($fromDate !== null && $fromDate !== '') {
            $where[]  = 'event_date >= :fromDate';
            $params[':fromDate'] = $fromDate;
        }
        if ($toDate !== null && $toDate !== '') {
            $where[]  = 'event_date <= :toDate';
            $params[':toDate'] = $toDate;
        }

        $sql  = 'SELECT * FROM events';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY event_date DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'toEvent'], $stmt->fetchAll());
    }

    /**
     * Get a single event by id. Returns null if not found.
     */
    public function getEventById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->toEvent($row) : null;
    }

    /**
     * Get a single event by slug. Returns null if not found.
     */
    public function getEventBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM events WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ? $this->toEvent($row) : null;
    }

    /**
     * Get upcoming events (event_date >= now), sorted ASC.
     */
    public function getUpcomingEvents(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM events WHERE event_date >= UTC_TIMESTAMP()
             ORDER BY event_date ASC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'toEvent'], $stmt->fetchAll());
    }

    /**
     * Get most recently created events (by created_at DESC), for activity feed.
     *
     * @param  int $limit  Max number of events to return (default 10)
     * @return array<array>
     */
    public function getRecentEvents(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $stmt  = $this->db->prepare('SELECT * FROM events ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'toEvent'], $stmt->fetchAll());
    }

    /* ════════════════════════════════════════════════════════════
       MUTATIONS
       ════════════════════════════════════════════════════════════ */

    /**
     * Create an event. Data in camelCase; mapped to snake_case for the DB.
     *
     * @throws RuntimeException  with code SLUG_TAKEN / DATE_ORDER_INVALID / INVALID_BANNER_URL
     */
    public function createEvent(array $data): array
    {
        $this->validateDateOrder(
            $data['eventDate']         ?? null,
            $data['registrationOpen']  ?? null,
            $data['registrationClose'] ?? null
        );
        $this->validateBannerUrl($data['bannerImage'] ?? null);

        $slug = ($data['slug'] ?? '') !== ''
            ? trim((string)$data['slug'])
            : $this->slugify($data['title'] ?? 'event');

        if ($this->getEventBySlug($slug) !== null) {
            throw $this->codeException('This slug is already in use. Choose another.', self::SLUG_TAKEN_CODE);
        }

        $sql = '
            INSERT INTO events
              (title, slug, description, location, event_date, distance,
               recurrence_type, recurrence_days,
               category, registration_open, registration_close, registration_type,
               banner_image, created_by)
            VALUES
              (:title, :slug, :description, :location, :event_date, :distance,
               :recurrence_type, :recurrence_days,
               :category, :registration_open, :registration_close, :registration_type,
               :banner_image, :created_by)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title'              => $data['title'],
            ':slug'               => $slug,
            ':description'        => $data['description']       ?? '',
            ':location'           => $data['location']          ?? '',
            ':event_date'         => $this->normaliseDatetime($data['eventDate'] ?? null),
            ':distance'           => $data['distance']          ?? '',
            ':recurrence_type'    => $data['recurrenceType']    ?? 'none',
            ':recurrence_days'    => $data['recurrenceDays']    ?? null,
            ':category'           => $data['category']          ?? '',
            ':registration_open'  => $this->normaliseDatetime($data['registrationOpen']  ?? null),
            ':registration_close' => $this->normaliseDatetime($data['registrationClose'] ?? null),
            ':registration_type'  => $data['registrationType']  ?? 'open',
            ':banner_image'       => $data['bannerImage']       ?? null,
            ':created_by'         => $data['createdBy']         ?? null,
        ]);

        $id = $this->db->lastInsertId();
        return $this->getEventById($id) ?? [];
    }

    /**
     * Update an event by id. Supports partial updates (only provided keys).
     *
     * @throws RuntimeException  with code SLUG_TAKEN / DATE_ORDER_INVALID / INVALID_BANNER_URL
     * @return array|null  Updated event, or null if id not found.
     */
    public function updateEvent(string $id, array $data): ?array
    {
        $this->validateDateOrder(
            $data['eventDate']         ?? null,
            $data['registrationOpen']  ?? null,
            $data['registrationClose'] ?? null
        );
        if (array_key_exists('bannerImage', $data)) {
            $this->validateBannerUrl($data['bannerImage']);
        }

        if (isset($data['slug'])) {
            $existing = $this->getEventBySlug($data['slug']);
            if ($existing !== null && $existing['id'] !== $id) {
                throw $this->codeException('This slug is already in use. Choose another.', self::SLUG_TAKEN_CODE);
            }
        }

        // Build SET clause dynamically — only update provided keys
        $map = [
            'title'           => 'title',
            'slug'            => 'slug',
            'description'     => 'description',
            'location'        => 'location',
            'eventDate'       => 'event_date',
            'distance'        => 'distance',
            'recurrenceType'   => 'recurrence_type',
            'recurrenceDays'   => 'recurrence_days',
            'category'         => 'category',
            'registrationOpen'  => 'registration_open',
            'registrationClose' => 'registration_close',
            'registrationType'  => 'registration_type',
            'bannerImage'      => 'banner_image',
            'createdBy'       => 'created_by',
        ];

        $sets   = ['updated_at = UTC_TIMESTAMP()'];
        $params = [':id' => $id];

        foreach ($map as $camel => $snake) {
            if (!array_key_exists($camel, $data)) continue;
            $placeholder = ':' . $camel;
            $sets[]      = $snake . ' = ' . $placeholder;
            $value       = in_array($camel, ['eventDate', 'registrationOpen', 'registrationClose'], true)
                ? $this->normaliseDatetime($data[$camel])
                : $data[$camel];
            $params[$placeholder] = $value;
        }

        if (count($sets) === 1) {
            // Nothing to update besides timestamp
            return $this->getEventById($id);
        }

        $sql  = 'UPDATE events SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0 ? $this->getEventById($id) : null;
    }

    /**
     * Delete an event by id.
     */
    public function deleteEvent(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Map a DB row (snake_case) to the camelCase shape used by controllers/views.
     * Includes both `id` and `_id` for backward compatibility with any EJS-era code.
     */
    private function toEvent(?array $row): ?array
    {
        if ($row === null) return null;
        return [
            '_id'               => $row['id'],
            'id'                => $row['id'],
            'title'             => $row['title'],
            'slug'              => $row['slug']               ?? null,
            'description'       => $row['description']        ?? '',
            'location'          => $row['location']           ?? '',
            'eventDate'         => $row['event_date'],
            'distance'        => $row['distance']          ?? '',
            'recurrenceType'  => $row['recurrence_type']   ?? 'none',
            'recurrenceDays'  => $row['recurrence_days']   ?? null,
            'category'        => $row['category']          ?? '',
            'registrationOpen'  => $row['registration_open']   ?? null,
            'registrationClose' => $row['registration_close']  ?? null,
            'registrationType'  => $row['registration_type']   ?? 'open',
            'bannerImage'       => $this->sanitiseBannerUrl($row['banner_image'] ?? null),
            'createdBy'         => $row['created_by']         ?? null,
            'createdAt'         => $row['created_at'],
            'updatedAt'         => $row['updated_at'],
        ];
    }

    /**
     * Return the URL if it is a valid relative path or http(s) URL; otherwise null.
     */
    private function sanitiseBannerUrl(mixed $url): ?string
    {
        $v = trim((string)($url ?? ''));
        if ($v === '') return null;
        // Relative path: starts with / and has no double-slash (protocol-relative)
        if (str_starts_with($v, '/') && !str_contains($v, '//')) return $v;
        // Absolute: must be http or https
        $parsed = parse_url($v);
        $scheme = strtolower($parsed['scheme'] ?? '');
        return in_array($scheme, ['http', 'https'], true) ? $v : null;
    }

    /**
     * Validate banner image URL: empty is fine; non-empty must be relative or http(s).
     *
     * @throws RuntimeException  code INVALID_BANNER_URL
     */
    private function validateBannerUrl(mixed $bannerImage): void
    {
        $v = trim((string)($bannerImage ?? ''));
        if ($v === '') return;
        if (str_starts_with($v, '/') && !str_contains($v, '//')) return;
        $scheme = strtolower(parse_url($v, PHP_URL_SCHEME) ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw $this->codeException('Banner image must be an http or https URL.', self::INVALID_BANNER_URL_CODE);
        }
    }

    /**
     * Enforce registrationOpen < registrationClose < eventDate.
     *
     * @throws RuntimeException  code DATE_ORDER_INVALID
     */
    private function validateDateOrder(mixed $eventDate, mixed $regOpen, mixed $regClose): void
    {
        $tEvent = $this->parseTimestamp($eventDate);
        $tOpen  = $this->parseTimestamp($regOpen);
        $tClose = $this->parseTimestamp($regClose);

        if ($tOpen !== null && $tClose !== null && $tOpen >= $tClose) {
            throw $this->codeException(
                'Registration open date must be before registration close date.',
                self::DATE_ORDER_INVALID_CODE
            );
        }
        if ($tClose !== null && $tEvent !== null && $tClose >= $tEvent) {
            throw $this->codeException(
                'Registration close date must be before the event date.',
                self::DATE_ORDER_INVALID_CODE
            );
        }
        if ($tOpen !== null && $tEvent !== null && $tOpen >= $tEvent) {
            throw $this->codeException(
                'Registration open date must be before the event date.',
                self::DATE_ORDER_INVALID_CODE
            );
        }
    }

    /**
     * Normalise a datetime-local string (YYYY-MM-DDTHH:mm, no TZ) to
     * a MySQL-compatible UTC datetime string (YYYY-MM-DD HH:mm:ss).
     * Already-qualified strings or nulls pass through unchanged.
     */
    private function normaliseDatetime(mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        $s = trim((string)$v);
        // datetime-local format from HTML input (no timezone)
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $s)) {
            return str_replace('T', ' ', $s) . ':00';
        }
        // Already has seconds or Z suffix — convert T separator for MySQL
        return str_replace('T', ' ', rtrim($s, 'Z'));
    }

    /** Parse a date/datetime string to a Unix timestamp, or return null. */
    private function parseTimestamp(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        $ts = strtotime((string)$v);
        return $ts !== false ? $ts : null;
    }

    /**
     * Convert a string to a URL-safe slug.
     *   "LFS Half Marathon 2025!" → "lfs-half-marathon-2025"
     */
    private function slugify(string $str): string
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9\s\-]/', '', $str);
        $str = preg_replace('/[\s\-]+/', '-', $str);
        return trim($str, '-');
    }

    /** Build a RuntimeException with a custom `code` property via a wrapper. */
    private function codeException(string $message, string $code): RuntimeException
    {
        // PHP exceptions don't have a free-form code property like JS errors do,
        // but we can carry the code in the message prefix for controller inspection,
        // and also expose it via a custom method using anonymous class extension.
        return new class($message, 0, null, $code) extends RuntimeException {
            public readonly string $errorCode;
            public function __construct(string $msg, int $c, ?Throwable $prev, string $errorCode) {
                parent::__construct($msg, $c, $prev);
                $this->errorCode = $errorCode;
            }
        };
    }
}
