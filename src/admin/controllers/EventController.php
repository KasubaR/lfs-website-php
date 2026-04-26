<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/controllers/EventController.php
 *
 * Event admin: list, create, edit, update, delete.
 * Depends on:
 *   - EventService  (src/services/EventService.php)
 *   - Event model   (src/model/Event.php) for EVENT_CATEGORIES constant
 *   - render()      helper defined in your bootstrap/router
 */

declare(strict_types=1);

require_once __DIR__ . '/../../services/EventService.php';
require_once __DIR__ . '/../../model/Event.php';

class EventController
{
    private EventService $eventService;

    /** Absolute path to the public web root — used when deleting old banner files. */
    private string $publicRoot;

    public function __construct()
    {
        $this->eventService = new EventService();
        $this->publicRoot   = defined('PUBLIC_ROOT')
            ? PUBLIC_ROOT
            : realpath(__DIR__ . '/../../..');
    }

    /* ════════════════════════════════════════════════════════════
       LIST EVENTS — GET /admin/events/list
       ════════════════════════════════════════════════════════════ */

    public function getEvents(): void
    {
        $category  = $_GET['category']  ?? '';
        $fromDate  = $_GET['fromDate']  ?? '';
        $toDate    = $_GET['toDate']    ?? '';

        $opts = ['limit' => 100];
        if ($category) $opts['category'] = $category;
        if ($fromDate) $opts['fromDate'] = $fromDate;
        if ($toDate)   $opts['toDate']   = $toDate;

        $events      = [];
        $eventsError = null;
        try {
            $events = $this->eventService->getEvents($opts);
        } catch (Throwable $e) {
            $eventsError = $e->getMessage() ?: 'Could not load events. Check database connection.';
            error_log('[LFS Admin] EventController::getEvents — ' . $e->getMessage());
        }

        $this->render('Events/list', [
            'pageTitle'       => 'Events',
            'activePage'      => 'events',
            'events'          => $events,
            'eventsError'     => $eventsError,
            'eventCategories' => Event::CATEGORIES,
            'filterCategory'  => $category,
            'filterFromDate'  => $fromDate,
            'filterToDate'    => $toDate,
            'breadcrumbs'     => [
                ['label' => 'Admin',  'url' => '/admin'],
                ['label' => 'Events'],
            ],
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       CREATE (GET) — GET /admin/events/create
       ════════════════════════════════════════════════════════════ */

    public function getCreateEvent(): void
    {
        $this->render('Events/event-form', [
            'pageTitle'       => 'New Event',
            'activePage'      => 'events',
            'event'           => null,
            'eventCategories' => Event::CATEGORIES,
            'isEdit'          => false,
            'breadcrumbs'     => [
                ['label' => 'Admin',     'url' => '/admin'],
                ['label' => 'Events',    'url' => '/admin/events'],
                ['label' => 'New Event'],
            ],
            'extraScripts'    => $this->eventFormExtraScripts(),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       CREATE (POST) — POST /admin/events
       ════════════════════════════════════════════════════════════ */

    public function postCreateEvent(): void
    {
        // Banner upload error set by EventBannerUpload middleware
        if (!empty($_REQUEST['_bannerUploadError'])) {
            $this->renderFormWithError(
                null,
                false,
                'New Event',
                $_POST,
                $_REQUEST['_bannerUploadError']
            );
            return;
        }
        if (!empty($_REQUEST['_distanceRouteUploadError'])) {
            $this->renderFormWithError(
                null,
                false,
                'New Event',
                $_POST,
                (string) $_REQUEST['_distanceRouteUploadError']
            );
            return;
        }
        if (!empty($_REQUEST['_brochureUploadError'])) {
            $this->renderFormWithError(
                null,
                false,
                'New Event',
                $_POST,
                (string) $_REQUEST['_brochureUploadError']
            );
            return;
        }

        $body = $_POST;
        $isWeekly = ($body['recurrenceType'] ?? 'none') === 'weekly';
        if (empty($body['title']) || (!$isWeekly && empty($body['eventDate']))) {
            $this->renderFormWithError(null, false, 'New Event', $body, $isWeekly ? 'Title is required.' : 'Title and event date are required.');
            return;
        }

        $bannerImage = $this->resolveUploadedBanner($body['bannerImage'] ?? null);
        $brochurePdf = $this->resolveEventBrochureFromRequest(null);
        $distRoutes  = $this->collectDistanceRoutesFromRequest();
        $distSummary = $this->distanceSummaryFromRoutes($distRoutes);

        try {
            $recDays = isset($body['recurrence_days']) && is_array($body['recurrence_days'])
                ? implode(',', array_map('trim', $body['recurrence_days']))
                : null;

            $featureOnHome = isset($body['featureOnHome']) && (string)($body['featureOnHome'] ?? '') === '1';

            $created = $this->eventService->createEvent([
                'title'             => trim($body['title']),
                'slug'              => isset($body['slug']) ? trim($body['slug']) : null,
                'description'       => $body['description']    ?? '',
                'location'          => $body['location']        ?? '',
                'eventDate'         => $body['eventDate']       ?: null,
                'distance'          => $distSummary,
                'recurrenceType'    => $body['recurrenceType']  ?? 'none',
                'recurrenceDays'    => $recDays,
                'category'          => $body['category']        ?? '',
                'registrationOpen'  => $body['registrationOpen']   ?: null,
                'registrationClose' => $body['registrationClose']  ?: null,
                'registrationType'  => $body['registrationType']   ?? 'open',
                'registrationLink'  => $body['registrationLink']   ?: null,
                'bannerImage'       => $bannerImage,
                'brochurePdf'       => $brochurePdf,
                'featureOnHome'     => $featureOnHome,
            ]);

            $this->eventService->replaceEventDistanceRoutes((string) $created['id'], $distRoutes);

            header('Location: /admin/events');
            exit;
        } catch (Throwable $e) {
            $this->renderFormWithError(null, false, 'New Event', $body, $e->getMessage());
        }
    }

    /* ════════════════════════════════════════════════════════════
       EDIT (GET) — GET /admin/events/:id/edit
       ════════════════════════════════════════════════════════════ */

    public function getEditEvent(string $id): void
    {
        $event = $this->eventService->getEventById($id);

        if (!$event) {
            header('Location: /admin/events');
            exit;
        }

        $this->render('Events/event-form', [
            'pageTitle'       => 'Edit Event',
            'activePage'      => 'events',
            'event'           => $event,
            'eventCategories' => Event::CATEGORIES,
            'isEdit'          => true,
            'breadcrumbs'     => [
                ['label' => 'Admin',  'url' => '/admin'],
                ['label' => 'Events', 'url' => '/admin/events'],
                ['label' => $event['title']],
            ],
            'extraScripts'    => $this->eventFormExtraScripts(),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
       UPDATE (POST) — POST /admin/events/:id
       ════════════════════════════════════════════════════════════ */

    public function postUpdateEvent(string $id): void
    {
        if (!empty($_REQUEST['_bannerUploadError'])) {
            $existing = $this->safeGetById($id);
            $this->renderFormWithError(
                $existing ? array_merge($existing, $_POST) : $_POST,
                true,
                $_POST['title'] ?? ($existing['title'] ?? 'Edit'),
                array_merge($existing ?? [], $_POST),
                $_REQUEST['_bannerUploadError']
            );
            return;
        }
        if (!empty($_REQUEST['_distanceRouteUploadError'])) {
            $existing = $this->safeGetById($id);
            $this->renderFormWithError(
                $existing ? array_merge($existing, $_POST) : $_POST,
                true,
                $_POST['title'] ?? ($existing['title'] ?? 'Edit'),
                array_merge($existing ?? [], $_POST),
                (string) $_REQUEST['_distanceRouteUploadError']
            );
            return;
        }
        if (!empty($_REQUEST['_brochureUploadError'])) {
            $existing = $this->safeGetById($id);
            $this->renderFormWithError(
                $existing ? array_merge($existing, $_POST) : $_POST,
                true,
                $_POST['title'] ?? ($existing['title'] ?? 'Edit'),
                array_merge($existing ?? [], $_POST),
                (string) $_REQUEST['_brochureUploadError']
            );
            return;
        }

        $body = $_POST;
        $isWeekly = ($body['recurrenceType'] ?? 'none') === 'weekly';
        if (empty($body['title']) || (!$isWeekly && empty($body['eventDate']))) {
            $this->renderFormWithError(null, true, $body['title'] ?? 'Edit', $body, $isWeekly ? 'Title is required.' : 'Title and event date are required.');
            return;
        }

        $existing = $this->safeGetById($id);
        if (!$existing) {
            header('Location: /admin/events');
            exit;
        }

        $bannerImage   = $this->resolveUploadedBanner($body['bannerImage'] ?? null);
        $brochurePdf   = $this->resolveEventBrochureFromRequest($existing);
        $featureOnHome = isset($body['featureOnHome']) && (string)($body['featureOnHome'] ?? '') === '1';
        $distRoutes    = $this->collectDistanceRoutesFromRequest();
        $distSummary   = $this->distanceSummaryFromRoutes($distRoutes);
        $oldBrochure   = $existing['brochurePdf'] ?? null;

        try {
            $recDays = isset($body['recurrence_days']) && is_array($body['recurrence_days'])
                ? implode(',', array_map('trim', $body['recurrence_days']))
                : null;

            $updated = $this->eventService->updateEvent($id, [
                'title'             => trim($body['title']),
                'slug'              => isset($body['slug']) ? trim($body['slug']) : null,
                'description'       => $body['description']    ?? '',
                'location'          => $body['location']        ?? '',
                'eventDate'         => $body['eventDate']       ?: null,
                'distance'          => $distSummary,
                'recurrenceType'    => $body['recurrenceType']  ?? 'none',
                'recurrenceDays'    => $recDays,
                'category'          => $body['category']        ?? '',
                'registrationOpen'  => $body['registrationOpen']   ?: null,
                'registrationClose' => $body['registrationClose']  ?: null,
                'registrationType'  => $body['registrationType']   ?? 'open',
                'registrationLink'  => $body['registrationLink']   ?: null,
                'bannerImage'       => $bannerImage,
                'brochurePdf'       => $brochurePdf,
            ]);

            if (!$updated) {
                header('Location: /admin/events');
                exit;
            }

            $this->eventService->setHomePageHeroForEvent($id, $featureOnHome);
            $this->eventService->replaceEventDistanceRoutes($id, $distRoutes);

            // Delete old local banner only after successful DB update, when we saved a new uploaded file
            $hadNewLocalBanner = $bannerImage !== null && str_starts_with($bannerImage, '/images/events/');
            $oldWasLocal        = !empty($existing['bannerImage']) && str_starts_with($existing['bannerImage'], '/images/events/');
            if ($hadNewLocalBanner && $oldWasLocal && $existing['bannerImage'] !== $bannerImage) {
                $oldPath = $this->publicRoot . '/' . ltrim($existing['bannerImage'], '/');
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            if (is_string($oldBrochure) && str_starts_with($oldBrochure, '/files/event-brochures/') && $oldBrochure !== $brochurePdf) {
                $bp = $this->publicRoot . '/' . ltrim($oldBrochure, '/\\');
                if (file_exists($bp)) {
                    @unlink($bp);
                }
            }

            header('Location: /admin/events');
            exit;
        } catch (Throwable $e) {
            $this->renderFormWithError(
                $existing ? array_merge($existing, $body) : $body,
                true,
                $body['title'] ?? ($existing['title'] ?? 'Edit'),
                $existing ? array_merge($existing, $body) : $body,
                $e->getMessage()
            );
        }
    }

    /* ════════════════════════════════════════════════════════════
       DELETE — POST /admin/events/:id/delete
       ════════════════════════════════════════════════════════════ */

    public function postDeleteEvent(string $id): void
    {
        $event = $this->safeGetById($id);
        $this->eventService->deleteEvent($id);

        if ($event && !empty($event['bannerImage']) && str_starts_with($event['bannerImage'], '/images/events/')) {
            $filePath = $this->publicRoot . '/' . ltrim($event['bannerImage'], '/');
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        if ($event && !empty($event['brochurePdf']) && str_starts_with($event['brochurePdf'], '/files/event-brochures/')) {
            $bp = $this->publicRoot . '/' . ltrim($event['brochurePdf'], '/\\');
            if (file_exists($bp)) {
                @unlink($bp);
            }
        }

        header('Location: /admin/events');
        exit;
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Render an admin view through the admin layout.
     * Adjust the path to match your render() helper or include mechanism.
     */
    private function render(string $view, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        $csrfToken = $_SESSION['csrf_token'] ?? '';
        $layout    = 'layouts/admin';

        ob_start();
        require __DIR__ . '/../views/' . $view . '.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/' . $layout . '.php';
    }

    /**
     * Re-render the event form with an error message.
     */
    private function renderFormWithError(
        ?array  $event,
        bool    $isEdit,
        string  $pageTitle,
        array   $formData,
        string  $error
    ): void {
        $this->render('Events/event-form', [
            'pageTitle'       => $pageTitle,
            'activePage'      => 'events',
            'event'           => $event ?? $formData,
            'eventCategories' => Event::CATEGORIES,
            'isEdit'          => $isEdit,
            'error'           => $error,
            'breadcrumbs'     => [
                ['label' => 'Admin',  'url' => '/admin'],
                ['label' => 'Events', 'url' => '/admin/events'],
                ['label' => $pageTitle],
            ],
            'extraScripts'    => $this->eventFormExtraScripts(),
        ]);
    }

    private function eventFormExtraScripts(): string
    {
        if (!function_exists('lfs_public_url')) {
            require_once __DIR__ . '/../../utility/helpers.php';
        }
        $src = lfs_public_url('/admin/js/event-distances.js');
        return '<script defer src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    /**
     * @return list<array{label: string, routeImage: string|null}>
     */
    private function collectDistanceRoutesFromRequest(): array
    {
        $labels   = $_POST['dist_label'] ?? [];
        $existing = $_POST['dist_route_existing'] ?? [];
        $stored   = $_POST['dist_route_stored'] ?? [];
        if (!is_array($labels)) {
            $labels = [];
        }
        if (!is_array($existing)) {
            $existing = [];
        }
        if (!is_array($stored)) {
            $stored = [];
        }
        $n      = count($labels);
        $routes = [];
        for ($i = 0; $i < $n; $i++) {
            $label = trim((string) ($labels[$i] ?? ''));
            if ($label === '') {
                continue;
            }
            $img = $stored[$i] ?? null;
            if (!is_string($img) || $img === '') {
                $img = trim((string) ($existing[$i] ?? ''));
            }
            if ($img === '') {
                $img = null;
            }
            $routes[] = ['label' => $label, 'routeImage' => $img];
        }
        return $routes;
    }

    /**
     * @param list<array{label: string, routeImage: string|null}> $routes
     */
    private function distanceSummaryFromRoutes(array $routes): string
    {
        $labels = [];
        foreach ($routes as $r) {
            $l = trim((string) ($r['label'] ?? ''));
            if ($l !== '') {
                $labels[] = $l;
            }
        }
        return $labels === [] ? '' : implode(', ', $labels);
    }

    /**
     * Brochure PDF: optional upload (brochure_pdf_stored), URL/path field (brochurePdf), or keep existing.
     * Checkbox remove_brochure=1 clears the stored brochure on edit.
     */
    private function resolveEventBrochureFromRequest(?array $existing): ?string
    {
        if (isset($_POST['remove_brochure']) && (string) $_POST['remove_brochure'] === '1') {
            return null;
        }
        $stored = $_POST['brochure_pdf_stored'] ?? '';
        if (is_string($stored) && $stored !== '') {
            return trim($stored);
        }
        $text = trim((string) ($_POST['brochurePdf'] ?? ''));
        if ($text !== '') {
            return $text;
        }
        if ($existing !== null) {
            return $existing['brochurePdf'] ?? null;
        }
        return null;
    }

    /**
     * Return the banner image path:
     *   - uploaded file wins  → /images/events/<filename>
     *   - fallback to URL/path from form body
     *   - null if neither
     */
    private function resolveUploadedBanner(?string $bodyUrl): ?string
    {
        if (!empty($_FILES['bannerImageFile']['tmp_name'])) {
            return '/images/events/' . basename($_FILES['bannerImageFile']['name']);
        }
        return $bodyUrl ?: null;
    }

    /**
     * Fetch an event by ID without throwing on failure.
     */
    private function safeGetById(string $id): ?array
    {
        try {
            return $this->eventService->getEventById($id);
        } catch (Throwable) {
            return null;
        }
    }
}
