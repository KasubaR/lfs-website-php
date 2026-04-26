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
    public const SLUG_TAKEN_CODE         = 'SLUG_TAKEN';
    public const DATE_ORDER_INVALID_CODE  = 'DATE_ORDER_INVALID';
    public const INVALID_BANNER_URL_CODE  = 'INVALID_BANNER_URL';
    public const FEATURE_ON_HOME_NO_BANNER_CODE = 'FEATURE_ON_HOME_NO_BANNER';

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
        return $row ? $this->hydrateEventDistanceRoutes($this->toEvent($row)) : null;
    }

    /**
     * Get a single event by slug. Returns null if not found.
     */
    public function getEventBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM events WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ? $this->hydrateEventDistanceRoutes($this->toEvent($row)) : null;
    }

    /**
     * @return list<array{id: string, label: string, routeImage: ?string, sortOrder: int}>
     */
    public function fetchDistanceRoutes(string $eventId): array
    {
        try {
            $st = $this->db->prepare(
                'SELECT id, label, route_image, sort_order
                 FROM event_distance_routes
                 WHERE event_id = :eid
                 ORDER BY sort_order ASC, label ASC'
            );
            $st->execute([':eid' => $eventId]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id'         => (string) $row['id'],
                'label'      => (string) $row['label'],
                'routeImage' => $this->sanitiseRouteImageForDisplay($row['route_image'] ?? null),
                'sortOrder'  => (int) ($row['sort_order'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Replace all distance route rows and sync the legacy `events.distance` text (comma‑separated labels).
     *
     * @param list<array{label: string, routeImage: string|null}> $routes
     */
    public function replaceEventDistanceRoutes(string $eventId, array $routes): void
    {
        $prev = $this->fetchDistanceRoutes($eventId);
        $prevPaths = [];
        foreach ($prev as $p) {
            if (!empty($p['routeImage'])) {
                $prevPaths[] = $p['routeImage'];
            }
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM event_distance_routes WHERE event_id = :eid')
                ->execute([':eid' => $eventId]);
            $sort   = 0;
            $labels = [];
            foreach ($routes as $r) {
                $label = trim((string) ($r['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $labels[] = $label;
                $img = $r['routeImage'] ?? null;
                $img = (is_string($img) && $img !== '') ? $this->sanitiseRouteImageForStorage($img) : null;
                $rid = $this->newUuidV4();
                $this->db->prepare(
                    'INSERT INTO event_distance_routes (id, event_id, label, route_image, sort_order)
                     VALUES (:id, :eid, :label, :img, :so)'
                )->execute([
                    ':id'    => $rid,
                    ':eid'   => $eventId,
                    ':label' => $label,
                    ':img'   => $img,
                    ':so'    => $sort++,
                ]);
            }
            $summary = $labels !== [] ? implode(', ', $labels) : '';
            $this->db->prepare('UPDATE events SET distance = :d, updated_at = UTC_TIMESTAMP() WHERE id = :eid')
                ->execute([':d' => $summary, ':eid' => $eventId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $newPaths = [];
        foreach ($routes as $r) {
            $img = (is_string($r['routeImage'] ?? null) && $r['routeImage'] !== '')
                ? $this->sanitiseRouteImageForStorage($r['routeImage']) : null;
            if ($img) {
                $newPaths[] = $img;
            }
        }
        $newPaths = array_unique($newPaths);
        foreach ($prevPaths as $old) {
            if ($old && str_starts_with($old, '/images/event-routes/') && !in_array($old, $newPaths, true)) {
                $full = $this->publicWebRoot() . '/' . ltrim($old, '/\\');
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }
    }

    private function hydrateEventDistanceRoutes(array $event): array
    {
        $event['distanceRoutes'] = $this->fetchDistanceRoutes($event['id']);
        if (!empty($event['distanceRoutes'])) {
            $event['distance'] = implode(
                ', ',
                array_map(static fn (array $r): string => $r['label'], $event['distanceRoutes'])
            );
        }
        return $event;
    }

    private function newUuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function publicWebRoot(): string
    {
        if (defined('PUBLIC_ROOT')) {
            return rtrim((string) PUBLIC_ROOT, '/\\');
        }
        return dirname(__DIR__, 2);
    }

    /** Like banner: relative /path or http(s) URL. */
    private function sanitiseRouteImageForStorage(mixed $url): ?string
    {
        $v = trim((string) ($url ?? ''));
        if ($v === '') {
            return null;
        }
        if (str_starts_with($v, '/') && !str_contains($v, '//')) {
            return $v;
        }
        $scheme = strtolower((string) (parse_url($v, PHP_URL_SCHEME) ?? ''));
        return in_array($scheme, ['http', 'https'], true) ? $v : null;
    }

    private function sanitiseRouteImageForDisplay(mixed $url): ?string
    {
        return $this->sanitiseRouteImageForStorage($url);
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
     * All upcoming events marked for the home hero, with a non-empty banner, soonest first.
     *
     * @return list<array>
     */
    public function getHomeHeroFeaturedEvents(int $limit = 20): array
    {
        $limit = max(1, min(30, $limit));
        $sql   = "SELECT * FROM events
            WHERE feature_on_home = 1
              AND banner_image IS NOT NULL
              AND TRIM(banner_image) <> ''
              AND event_date >= UTC_TIMESTAMP()
            ORDER BY event_date ASC
            LIMIT " . (int) $limit;
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();
        return array_map([$this, 'toEvent'], $rows);
    }

    /**
     * First matching upcoming home-hero event, or null. Convenience for callers that only need one row.
     */
    public function getHomeHeroFeaturedEvent(): ?array
    {
        $a = $this->getHomeHeroFeaturedEvents(1);
        return $a[0] ?? null;
    }

    /**
     * Turn home-hero feature on or off for one event. Several events may be featured at once.
     *
     * @throws RuntimeException  code FEATURE_ON_HOME_NO_BANNER if $on and event has no banner
     */
    public function setHomePageHeroForEvent(string $id, bool $on): void
    {
        $ev = $this->getEventById($id);
        if ($ev === null) {
            throw new RuntimeException('Event not found.');
        }
        if ($on) {
            if (empty($ev['bannerImage'])) {
                throw $this->codeException(
                    'A banner image is required to feature an event on the home page.',
                    self::FEATURE_ON_HOME_NO_BANNER_CODE
                );
            }
        }
        $u = $this->db->prepare('UPDATE events SET feature_on_home = :v WHERE id = :id');
        $u->execute([':v' => $on ? 1 : 0, ':id' => $id]);
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
        $this->validateBrochureUrl($data['brochurePdf'] ?? null);

        $slug = ($data['slug'] ?? '') !== ''
            ? trim((string)$data['slug'])
            : $this->slugify($data['title'] ?? 'event');

        if ($this->getEventBySlug($slug) !== null) {
            throw $this->codeException('This slug is already in use. Choose another.', self::SLUG_TAKEN_CODE);
        }

        $wantsHomeHero = !empty($data['featureOnHome']);
        if ($wantsHomeHero) {
            $b = trim((string)($data['bannerImage'] ?? ''));
            if ($b === '') {
                throw $this->codeException(
                    'A banner image is required to feature an event on the home page.',
                    self::FEATURE_ON_HOME_NO_BANNER_CODE
                );
            }
        }

        $sql = '
            INSERT INTO events
              (title, slug, description, location, event_date, distance,
               recurrence_type, recurrence_days,
               category, registration_open, registration_close, registration_type,
               registration_link, banner_image, brochure_pdf, created_by)
            VALUES
              (:title, :slug, :description, :location, :event_date, :distance,
               :recurrence_type, :recurrence_days,
               :category, :registration_open, :registration_close, :registration_type,
               :registration_link, :banner_image, :brochure_pdf, :created_by)
        ';

        $this->db->beginTransaction();
        try {
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
                ':registration_link'  => $data['registrationLink']  ?? null,
                ':banner_image'       => $data['bannerImage']       ?? null,
                ':brochure_pdf'       => $this->sanitiseBrochureUrl($data['brochurePdf'] ?? null),
                ':created_by'         => $data['createdBy']         ?? null,
            ]);

            $id = (string) $this->db->lastInsertId();
            if ($wantsHomeHero) {
                $u = $this->db->prepare('UPDATE events SET feature_on_home = 1 WHERE id = :id');
                $u->execute([':id' => $id]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

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
        if (array_key_exists('brochurePdf', $data)) {
            $this->validateBrochureUrl($data['brochurePdf'] ?? null);
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
            'registrationLink'  => 'registration_link',
            'bannerImage'      => 'banner_image',
            'brochurePdf'     => 'brochure_pdf',
            'createdBy'       => 'created_by',
        ];

        $sets   = ['updated_at = UTC_TIMESTAMP()'];
        $params = [':id' => $id];

        foreach ($map as $camel => $snake) {
            if (!array_key_exists($camel, $data)) continue;
            $placeholder = ':' . $camel;
            $sets[]      = $snake . ' = ' . $placeholder;
            if (in_array($camel, ['eventDate', 'registrationOpen', 'registrationClose'], true)) {
                $value = $this->normaliseDatetime($data[$camel]);
            } elseif ($camel === 'brochurePdf') {
                $value = $this->sanitiseBrochureUrl($data[$camel] ?? null);
            } else {
                $value = $data[$camel];
            }
            $params[$placeholder] = $value;
        }

        if (count($sets) === 1) {
            // Nothing to update besides timestamp
            return $this->getEventById($id);
        }

        $sql  = 'UPDATE events SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getEventById($id);
    }

    /**
     * Delete an event by id.
     */
    public function deleteEvent(string $id): void
    {
        foreach ($this->fetchDistanceRoutes($id) as $r) {
            $p = $r['routeImage'] ?? null;
            if (is_string($p) && str_starts_with($p, '/images/event-routes/')) {
                $full = $this->publicWebRoot() . '/' . ltrim($p, '/\\');
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }
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
            'registrationLink'  => $row['registration_link']   ?? null,
            'bannerImage'       => $this->sanitiseBannerUrl($row['banner_image'] ?? null),
            'brochurePdf'      => $this->sanitiseBrochureUrl($row['brochure_pdf'] ?? null),
            'featureOnHome'     => (bool)($row['feature_on_home'] ?? 0),
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

    /** Brochure PDF path/URL: same rules as banner image. */
    private function sanitiseBrochureUrl(mixed $url): ?string
    {
        return $this->sanitiseBannerUrl($url);
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

    private function validateBrochureUrl(mixed $url): void
    {
        $v = trim((string)($url ?? ''));
        if ($v === '') return;
        if (str_starts_with($v, '/') && !str_contains($v, '//')) return;
        $scheme = strtolower(parse_url($v, PHP_URL_SCHEME) ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw $this->codeException('Event brochure must be a site path (starting with /) or an http(s) URL.', self::INVALID_BANNER_URL_CODE);
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
