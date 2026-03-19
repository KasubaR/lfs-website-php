<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/controllers/GalleryPublicController.php — Public gallery (read-only)
 *
 * GET /gallery       → album listing grid
 * GET /gallery/:id   → single album with media grid
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/GalleryService.php';

class GalleryPublicController
{
    private GalleryService $galleryService;

    /** Folder name inside images/ (under PUBLIC_ROOT) used as offline fallback. */
    private const FALLBACK_FOLDER = '21.02.2026-LSD';

    /** Recognised image extensions for the fallback scanner. */
    private const IMAGE_EXTS = ['webp', 'jpg', 'jpeg', 'png'];

    public function __construct()
    {
        $this->galleryService = new GalleryService();
    }

    /* ════════════════════════════════════════════════════════════
       GET /gallery — Public album listing
       ════════════════════════════════════════════════════════════ */

    public function getIndex(): void
    {
        $albums        = [];
        $galleryError  = null;
        $fallbackMedia = [];
        $galleryBanner = null;

        try {
            $albums = $this->galleryService->getAlbums([]);
            // Optional global gallery banner (may be null)
            $galleryBanner = $this->galleryService->getGalleryBanner();
        } catch (Throwable $e) {
            // Degrade gracefully on DB errors rather than returning a 500
            $galleryError = 'Gallery is temporarily unavailable. Please try again later.';
            error_log('[LFS Gallery] Error loading albums: ' . $e->getMessage());
        }

        if (empty($albums)) {
            $fallbackMedia = $this->getFallbackMedia();
        }

        $title         = 'Gallery';
        $description   = 'Photos and videos from LFS runs, races and community events.';
        $extraStyles   = '<link rel="stylesheet" href="/css/gallery.css">';

        ob_start();
        require __DIR__ . '/../../src/views/pages/gallery.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../src/views/layouts/main.php';
    }

    /* ════════════════════════════════════════════════════════════
       GET /gallery/:id — Single album with media
       ════════════════════════════════════════════════════════════ */

    public function getAlbum(string $id): void
    {
        try {
            $album = $this->galleryService->getAlbumById($id);

            if (!$album) {
                http_response_code(404);
                $title       = 'Album not found';
                $description = 'This album may have been removed or the link is invalid.';
                ob_start();
                require __DIR__ . '/../../src/views/pages/404.php';
                $content = ob_get_clean();
                require __DIR__ . '/../../src/views/layouts/main.php';
                return;
            }

            $media       = $this->galleryService->getMediaByAlbumId($id, 'newest');
            $title       = htmlspecialchars($album['title'], ENT_QUOTES, 'UTF-8');
            $description = !empty($album['description'])
                ? htmlspecialchars($album['description'], ENT_QUOTES, 'UTF-8')
                : 'Photos and videos from ' . $title . '.';
            $extraStyles = '<link rel="stylesheet" href="/css/events.css">';

            ob_start();
            require __DIR__ . '/../../src/views/pages/gallery-album.php';
            $content = ob_get_clean();
            require __DIR__ . '/../../src/views/layouts/main.php';
        } catch (Throwable $e) {
            error_log('[LFS Gallery] Error loading album ' . $id . ': ' . $e->getMessage());
            http_response_code(500);
            // Let the front router's error handler take over if defined,
            // otherwise output a minimal message
            echo 'An unexpected error occurred. Please try again later.';
        }
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    /**
     * Scan the fallback image folder and return an array of
     * [src, alt] entries for use when the DB is unavailable.
     *
     * @return array<array{src: string, alt: string}>
     */
    private function getFallbackMedia(): array
    {
        $publicRoot   = defined('PUBLIC_ROOT')
            ? PUBLIC_ROOT
            : realpath(__DIR__ . '/../..');
        $folderPath   = rtrim((string)$publicRoot, '/') . '/images/' . self::FALLBACK_FOLDER;
        $baseUrl      = '/images/' . self::FALLBACK_FOLDER;

        if (!is_dir($folderPath)) return [];

        $files = array_filter(
            scandir($folderPath) ?: [],
            fn (string $f): bool =>
                $f !== '.' && $f !== '..'
                && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::IMAGE_EXTS, true)
        );
        sort($files);

        return array_values(array_map(
            fn (string $f): array => [
                'src' => $baseUrl . '/' . $f,
                'alt' => 'LFS — ' . self::FALLBACK_FOLDER,
            ],
            $files
        ));
    }
}
