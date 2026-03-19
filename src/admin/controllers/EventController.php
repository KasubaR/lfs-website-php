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
            : realpath(__DIR__ . '/../../../public');
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

        $body = $_POST;
        $isWeekly = ($body['recurrenceType'] ?? 'none') === 'weekly';
        if (empty($body['title']) || (!$isWeekly && empty($body['eventDate']))) {
            $this->renderFormWithError(null, false, 'New Event', $body, $isWeekly ? 'Title is required.' : 'Title and event date are required.');
            return;
        }

        $bannerImage = $this->resolveUploadedBanner($body['bannerImage'] ?? null);

        try {
            $recDays = isset($body['recurrence_days']) && is_array($body['recurrence_days'])
                ? implode(',', array_map('trim', $body['recurrence_days']))
                : null;

            $this->eventService->createEvent([
                'title'             => trim($body['title']),
                'slug'              => isset($body['slug']) ? trim($body['slug']) : null,
                'description'       => $body['description']    ?? '',
                'location'          => $body['location']        ?? '',
                'eventDate'         => $body['eventDate']       ?: null,
                'distance'          => $body['distance']        ?? '',
                'recurrenceType'    => $body['recurrenceType']  ?? 'none',
                'recurrenceDays'    => $recDays,
                'category'          => $body['category']        ?? '',
                'registrationOpen'  => $body['registrationOpen']   ?: null,
                'registrationClose' => $body['registrationClose']  ?: null,
                'registrationType'  => $body['registrationType']   ?? 'open',
                'bannerImage'       => $bannerImage,
            ]);

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

        $bannerImage = $this->resolveUploadedBanner($body['bannerImage'] ?? null);

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
                'distance'          => $body['distance']        ?? '',
                'recurrenceType'    => $body['recurrenceType']  ?? 'none',
                'recurrenceDays'    => $recDays,
                'category'          => $body['category']        ?? '',
                'registrationOpen'  => $body['registrationOpen']   ?: null,
                'registrationClose' => $body['registrationClose']  ?: null,
                'registrationType'  => $body['registrationType']   ?? 'open',
                'bannerImage'       => $bannerImage,
            ]);

            if (!$updated) {
                header('Location: /admin/events');
                exit;
            }

            // Delete old local banner only after successful DB update, when we saved a new uploaded file
            $hadNewLocalBanner = $bannerImage !== null && str_starts_with($bannerImage, '/images/events/');
            $oldWasLocal        = !empty($existing['bannerImage']) && str_starts_with($existing['bannerImage'], '/images/events/');
            if ($hadNewLocalBanner && $oldWasLocal && $existing['bannerImage'] !== $bannerImage) {
                $oldPath = $this->publicRoot . '/' . ltrim($existing['bannerImage'], '/');
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
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
        ]);
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
