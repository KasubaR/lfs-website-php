<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/controllers/GalleryController.php
 *
 * Minimal PHP port of the admin gallery controller.
 * Implements the methods expected by admin/routes/gallery.php,
 * backed by GalleryService and the new PHP admin views.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../services/GalleryService.php';
require_once __DIR__ . '/../../middleware/CsrfMiddleware.php';

class GalleryController
{
    private GalleryService $galleryService;

    public function __construct()
    {
        $this->galleryService = new GalleryService();
    }

    /** GET /admin/gallery/albums */
    public function getAlbums(): void
    {
        $filterYear     = $_GET['year']     ?? '';
        $filterCategory = $_GET['category'] ?? '';
        $searchQuery    = $_GET['search']   ?? '';

        $query = [];
        if ($filterYear !== '') {
            $query['year'] = $filterYear;
        }
        if ($filterCategory !== '') {
            $query['category'] = $filterCategory;
        }
        if ($searchQuery !== '') {
            $query['search'] = $searchQuery;
        }

        try {
            $albums         = $this->galleryService->getAlbums($query);
            $totalAlbums    = count($albums);
            $totalMedia     = $this->galleryService->countMedia();
            $featuredCount  = $this->galleryService->countFeaturedAlbums();
            $stats          = [
                'totalAlbums'   => $totalAlbums,
                'totalMedia'    => $totalMedia,
                'featuredCount' => $featuredCount,
            ];
            $galleryError   = null;
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] getAlbums error: ' . $e->getMessage());
            $albums       = [];
            $stats        = ['totalAlbums' => 0, 'totalMedia' => 0, 'featuredCount' => 0];
            $galleryError = 'Unable to load albums. Please try again later.';
        }

        $categories = ['Race', 'Training', 'LSD', 'Social'];
        $csrfToken  = CsrfMiddleware::token();

        ob_start();
        require __DIR__ . '/../views/gallery/albums.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
    }

    /** GET /admin/gallery/albums/create */
    public function getCreateAlbum(): void
    {
        $pageTitle  = 'Create Album';
        $activePage = 'gallery';
        $album      = null;
        $isEdit     = false;
        $csrfToken  = CsrfMiddleware::token();

        ob_start();
        require __DIR__ . '/../views/gallery/album-form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
    }

    /** POST /admin/gallery/albums */
    public function createAlbum(): void
    {
        $data = $this->normaliseAlbumPostData($_POST);

        try {
            $album = $this->galleryService->createAlbum($data);

            // If a cover image was set but the album has no media yet,
            // create a media item for the cover so the album is not "empty".
            if (!empty($album['coverImage']) && (int)($album['mediaCount'] ?? 0) === 0) {
                $this->ensureCoverAsMedia($album);
            }
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] createAlbum error: ' . $e->getMessage());
            header('Location: /admin/gallery/albums?error=album_create_failed');
            exit;
        }

        header('Location: /admin/gallery/albums/' . $album['id'] . '/edit');
        exit;
    }

    /** GET /admin/gallery/albums/:id/edit */
    public function getEditAlbum(string $id): void
    {
        try {
            $album = $this->galleryService->getAlbumById($id);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] getEditAlbum error: ' . $e->getMessage());
            $album = null;
        }

        if (!$album) {
            header('Location: /admin/gallery/albums');
            exit;
        }

        $pageTitle  = 'Edit Album';
        $activePage = 'gallery';
        $isEdit     = true;
        $csrfToken  = CsrfMiddleware::token();

        ob_start();
        require __DIR__ . '/../views/gallery/album-form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
    }

    /** GET /admin/gallery/albums/:id/manage */
    public function getManageAlbum(string $id): void
    {
        try {
            $album = $this->galleryService->getAlbumById($id);
            if (!$album) {
                header('Location: /admin/gallery/albums');
                exit;
            }
            $media    = $this->galleryService->getMediaByAlbumId($id, 'newest');
            $stats    = [
                'mediaCount' => count($media),
            ];
            $csrfToken = CsrfMiddleware::token();
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] getManageAlbum error: ' . $e->getMessage());
            header('Location: /admin/gallery/albums');
            exit;
        }

        $pageTitle  = 'Manage Album — ' . ($album['title'] ?? '');
        $activePage = 'gallery';

        ob_start();
        require __DIR__ . '/../views/gallery/manage.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
    }

    /** POST /admin/gallery/albums/:id */
    public function updateAlbum(string $id): void
    {
        $data = $this->normaliseAlbumPostData($_POST);

        try {
            $album = $this->galleryService->updateAlbum($id, $data);

            // If a cover image exists and the album has no media yet,
            // ensure the cover is also stored as a media item.
            if (is_array($album) && !empty($album['coverImage']) && (int)($album['mediaCount'] ?? 0) === 0) {
                $this->ensureCoverAsMedia($album);
            }
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] updateAlbum error: ' . $e->getMessage());
            // On error, fall back to the edit page so the user stays
            // on the form and can see their inputs.
            header('Location: /admin/gallery/albums/' . $id . '/edit');
            exit;
        }

        // After a successful update, take the admin straight to the
        // "Manage" screen where they can work with media (e.g. set
        // Featured, Homepage slider, Event highlight on the cover).
        header('Location: /admin/gallery/albums/' . $id . '/manage');
        exit;
    }

    /** POST /admin/gallery/albums/:id/delete */
    public function deleteAlbum(string $id): void
    {
        try {
            $this->galleryService->deleteMediaByAlbumId($id);
            $this->galleryService->deleteAlbum($id);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] deleteAlbum error: ' . $e->getMessage());
        }

        header('Location: /admin/gallery/albums');
        exit;
    }

    /** PATCH /admin/gallery/albums/:id/feature */
    public function toggleAlbumFeatured(string $id): void
    {
        try {
            $album = $this->galleryService->getAlbumById($id);
            if ($album) {
                $this->galleryService->updateAlbum($id, [
                    'featured' => !$album['featured'],
                ]);
            }
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] toggleAlbumFeatured error: ' . $e->getMessage());
        }

        http_response_code(204);
    }

    /** GET /admin/gallery/upload */
    public function getUploadPage(): void
    {
        try {
            $albums = $this->galleryService->getAlbumsForUpload();
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] getUploadPage error: ' . $e->getMessage());
            $albums = [];
        }

        $pageTitle  = 'Upload Media';
        $activePage = 'gallery';
        $csrfToken  = CsrfMiddleware::token();

        ob_start();
        require __DIR__ . '/../views/gallery/upload.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
    }

    /** POST /admin/gallery/upload */
    public function handleUpload(): void
    {
        // For now, just respond with a simple JSON placeholder.
        // Implement full upload handling using GalleryService + filesystem later.
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Upload handler not yet implemented in PHP gallery controller.',
        ]);
        exit;
    }

    /** GET /admin/gallery/settings */
    public function getSettings(): void
    {
        $pageTitle   = 'Gallery Settings';
        $activePage  = 'gallery';
        $breadcrumbs = [
            ['label' => 'Gallery', 'url' => '/admin/gallery'],
            ['label' => 'Settings'],
        ];
        $csrfToken   = CsrfMiddleware::token();
        $bannerImage = null;
        $error       = null;

        try {
            $bannerImage = $this->galleryService->getGalleryBanner();
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] getSettings error: ' . $e->getMessage());
            $error = 'Unable to load current settings.';
        }

        ob_start();
        require __DIR__ . '/../views/gallery/settings.php';
        $content = ob_get_clean();
        require __DIR__ . '/../views/layouts/admin.php';
    }

    /** POST /admin/gallery/settings */
    public function updateSettings(): void
    {
        $bodyUrl      = trim($_POST['bannerImageUrl'] ?? '');
        $removeBanner = !empty($_POST['removeBanner']);

        try {
            $banner = $removeBanner ? null : $this->resolveUploadedGalleryBanner($bodyUrl);
            $this->galleryService->setGalleryBanner($banner);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] updateSettings error: ' . $e->getMessage());

            $pageTitle   = 'Gallery Settings';
            $activePage  = 'gallery';
            $breadcrumbs = [
                ['label' => 'Gallery', 'url' => '/admin/gallery'],
                ['label' => 'Settings'],
            ];
            $csrfToken   = CsrfMiddleware::token();
            $bannerImage = $bodyUrl !== '' ? $bodyUrl : null;
            $error       = 'Failed to save gallery settings. Please try again.';

            ob_start();
            require __DIR__ . '/../views/gallery/settings.php';
            $content = ob_get_clean();
            require __DIR__ . '/../views/layouts/admin.php';
            return;
        }

        header('Location: /admin/gallery/settings');
        exit;
    }

    /** PATCH /admin/gallery/media/:id/caption */
    public function updateCaption(string $id): void
    {
        $caption = trim($_POST['caption'] ?? '');

        try {
            $media = $this->galleryService->updateMedia($id, ['caption' => $caption], ['new' => true]);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] updateCaption error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update caption.']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'media' => $media]);
    }

    /** PATCH /admin/gallery/media/:id/feature */
    public function toggleMediaFeatured(string $id): void
    {
        try {
            $media = $this->galleryService->getMediaById($id);
            if (!$media) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'featured' => false]);
                return;
            }
            $newState = !$media['featured'];
            $this->galleryService->updateMedia($id, ['featured' => $newState]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'featured' => $newState]);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] toggleMediaFeatured error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update media.', 'featured' => false]);
        }
    }

    /** PATCH /admin/gallery/media/:id/homepage-slider */
    public function toggleMediaHomepageSlider(string $id): void
    {
        try {
            $media = $this->galleryService->getMediaById($id);
            if (!$media) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'homepageSlider' => false]);
                return;
            }
            $newState = !($media['homepageSlider'] ?? false);
            $this->galleryService->updateMedia($id, ['homepageSlider' => $newState]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'homepageSlider' => $newState]);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] toggleMediaHomepageSlider error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update media.', 'homepageSlider' => false]);
        }
    }

    /** PATCH /admin/gallery/media/:id/event-highlight */
    public function toggleMediaEventHighlight(string $id): void
    {
        try {
            $media = $this->galleryService->getMediaById($id);
            if (!$media) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'eventHighlight' => false]);
                return;
            }
            $newState = !($media['eventHighlight'] ?? false);
            $this->galleryService->updateMedia($id, ['eventHighlight' => $newState]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'eventHighlight' => $newState]);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] toggleMediaEventHighlight error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update media.', 'eventHighlight' => false]);
        }
    }

    /** DELETE /admin/gallery/media/:id */
    public function deleteMedia(string $id): void
    {
        try {
            // Fetch media first so we know which album it belongs to
            $media = $this->galleryService->getMediaById($id);

            $this->galleryService->deleteMedia($id);

            // If this media was being used as the album cover, clear the cover
            if ($media && !empty($media['albumId'])) {
                $album = $this->galleryService->getAlbumById($media['albumId']);
                if ($album && !empty($album['coverImage'])) {
                    $urls = $media['urls'] ?? [];
                    $allUrls = array_values(is_array($urls) ? $urls : []);
                    if (in_array($album['coverImage'], $allUrls, true)) {
                        $this->galleryService->updateAlbum($album['id'], ['coverImage' => '']);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] deleteMedia error: ' . $e->getMessage());
        }

        http_response_code(204);
    }

    /** POST /admin/gallery/media/reorder */
    public function reorderMedia(): void
    {
        // Expect JSON { ids: [id1, id2, ...] }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ids   = $input['ids'] ?? [];

        // Simple implementation: update sortOrder sequentially
        try {
            $order = 0;
            foreach ($ids as $id) {
                $this->galleryService->updateMedia((string)$id, ['sortOrder' => $order]);
                $order++;
            }
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] reorderMedia error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false]);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    /** POST /admin/gallery/media/bulk-delete */
    public function bulkDeleteMedia(): void
    {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];

        try {
            // Capture media rows before deletion so we can update album covers if needed
            $mediaList = $this->galleryService->findMediaByIds($ids);

            $this->galleryService->deleteManyMedia($ids);

            // For each affected album, clear coverImage if it matches any deleted media URL
            $albumsToCheck = [];
            foreach ($mediaList as $m) {
                if (!empty($m['albumId'])) {
                    $albumsToCheck[(string)$m['albumId']][] = $m;
                }
            }

            foreach ($albumsToCheck as $albumId => $mediaItems) {
                $album = $this->galleryService->getAlbumById($albumId);
                if (!$album || empty($album['coverImage'])) {
                    continue;
                }
                $shouldClear = false;
                foreach ($mediaItems as $m) {
                    $urls = $m['urls'] ?? [];
                    $allUrls = array_values(is_array($urls) ? $urls : []);
                    if (in_array($album['coverImage'], $allUrls, true)) {
                        $shouldClear = true;
                        break;
                    }
                }
                if ($shouldClear) {
                    $this->galleryService->updateAlbum($album['id'], ['coverImage' => '']);
                }
            }
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] bulkDeleteMedia error: ' . $e->getMessage());
        }

        $redirectTo = $_SERVER['HTTP_REFERER'] ?? '/admin/gallery/albums';
        header('Location: ' . $redirectTo);
        exit;
    }

    /** POST /admin/gallery/media/bulk-feature */
    public function bulkFeatureMedia(): void
    {
        $ids      = $_POST['ids']      ?? [];
        $featured = (bool)($_POST['featured'] ?? true);
        if (!is_array($ids)) $ids = [];

        try {
            $this->galleryService->updateManyMedia($ids, ['featured' => $featured]);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] bulkFeatureMedia error: ' . $e->getMessage());
        }

        $redirectTo = $_SERVER['HTTP_REFERER'] ?? '/admin/gallery/albums';
        header('Location: ' . $redirectTo);
        exit;
    }

    /** POST /admin/gallery/media/bulk-move */
    public function bulkMoveMedia(): void
    {
        $ids     = $_POST['ids']     ?? [];
        $albumId = $_POST['albumId'] ?? '';
        if (!is_array($ids)) $ids = [];

        if ($albumId === '' || empty($ids)) {
            header('Location: $_SERVER[\'HTTP_REFERER\'] ?? \'/admin/gallery/albums\'');
            exit;
        }

        try {
            $this->galleryService->updateManyMedia($ids, ['albumId' => $albumId]);
        } catch (Throwable $e) {
            error_log('[LFS Admin Gallery] bulkMoveMedia error: ' . $e->getMessage());
        }

        $redirectTo = $_SERVER['HTTP_REFERER'] ?? '/admin/gallery/albums';
        header('Location: ' . $redirectTo);
        exit;
    }

    /**
     * Ensure an album's cover image is also represented as a media item
     * when the album currently has zero media.
     *
     * @param array $album Album array from GalleryService::toAlbum
     */
    private function ensureCoverAsMedia(array $album): void
    {
        try {
            $coverUrl = trim($album['coverImage'] ?? '');
            $albumId  = (string)($album['id'] ?? '');

            if ($coverUrl === '' || $albumId === '') {
                return;
            }

            $filename = basename($coverUrl);
            $urls     = [
                'original'  => $coverUrl,
                'large'     => $coverUrl,
                'medium'    => $coverUrl,
                'thumbnail' => $coverUrl,
            ];

            $this->galleryService->createMedia([
                'albumId'    => $albumId,
                'filename'   => $filename,
                'storedName' => $filename,
                'type'       => 'photo',
                'mimetype'   => null,
                'size'       => null,
                'urls'       => $urls,
                'caption'    => ($album['title'] ?? '') !== ''
                    ? ($album['title'] . ' cover')
                    : 'Album cover',
                'tags'       => [],
                'featured'   => false,
                'sortOrder'  => 0,
            ]);

            // Keep album.mediaCount in sync.
            $this->galleryService->incrementAlbumMediaCount($albumId, 1);
        } catch (Throwable $e) {
            // Do not break the main album flow if cover->media sync fails.
            error_log('[LFS Admin Gallery] ensureCoverAsMedia error: ' . $e->getMessage());
        }
    }

    /** Normalise POST data from the album form into the shape GalleryService expects. */
    private function normaliseAlbumPostData(array $post): array
    {
        $tagsRaw = trim($post['tags'] ?? '');
        $tags    = $tagsRaw !== '' ? preg_split('/\s*,\s*/', $tagsRaw) : [];

        return [
            'title'          => trim($post['title']          ?? ''),
            'description'    => trim($post['description']    ?? ''),
            'category'       => trim($post['category']       ?? ''),
            'date'           => trim($post['date']           ?? ''),
            'location'       => trim($post['location']       ?? ''),
            'event'          => trim($post['event']          ?? ''),
            'tags'           => $tags,
            'coverImage'     => trim($post['coverImage']     ?? ''),
            'externalUrl'    => trim($post['externalUrl']    ?? ''),
            'mediaCount'     => (int)($post['mediaCount']    ?? 0),
            'featured'       => !empty($post['featured']),
            'homepageSlider' => !empty($post['homepageSlider']),
            'eventHighlight' => !empty($post['eventHighlight']),
            'sortPriority'   => (int)($post['sortPriority']  ?? 0),
        ];
    }

    /**
     * Determine the gallery banner image to save:
     *   - uploaded file (stored under /images/gallery/)
     *   - or fall back to URL from the form
     *   - or null if neither.
     */
    private function resolveUploadedGalleryBanner(?string $bodyUrl): ?string
    {
        if (!empty($_FILES['bannerImageFile']['tmp_name'])) {
            $file     = $_FILES['bannerImageFile'];
            $tmp      = $file['tmp_name'];
            $ext      = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
            $safeExt  = in_array($ext, $allowed, true) ? $ext : 'jpg';

            $publicRoot = defined('PUBLIC_ROOT')
                ? PUBLIC_ROOT
                : realpath(__DIR__ . '/../../../public');
            $destDir = rtrim((string)$publicRoot, '/') . '/images/gallery';

            if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new RuntimeException('Could not create gallery images directory.');
            }

            $hash = sha1_file($tmp);
            if ($hash === false) {
                throw new RuntimeException('Could not read uploaded gallery banner.');
            }

            $filename = 'gallery-' . $hash . '.' . $safeExt;
            $destPath = $destDir . '/' . $filename;

            if (!file_exists($destPath)) {
                if (!move_uploaded_file($tmp, $destPath)) {
                    throw new RuntimeException('Could not save the uploaded gallery banner.');
                }
            }

            return '/images/gallery/' . $filename;
        }

        $bodyUrl = trim((string)($bodyUrl ?? ''));
        return $bodyUrl !== '' ? $bodyUrl : null;
    }
}

