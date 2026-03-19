<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/middleware/BlogImageUpload.php
 *
 * Handles the optional featured image upload for blog post create/edit forms.
 * Call BlogImageUpload::handle() before the controller action.
 *
 * Result contract:
 *   BlogImageUpload::handle() always returns an array:
 *     [
 *       'path'  => ?string, // web path like "/images/blog/blog-abc123.jpg"
 *       'error' => ?string, // human-readable error message on failure
 *     ]
 *
 *   - No upload:   ['path' => null, 'error' => null]
 *   - Success:     ['path' => '/images/blog/…', 'error' => null]
 *   - Validation / IO error: ['path' => null, 'error' => '…message…']
 *
 * The middleware no longer mutates $_FILES or $_REQUEST; controllers and routes
 * must pass the returned values explicitly.
 *
 * Limits:
 *   - Max file size : 10 MB
 *   - Allowed types : image/jpeg, image/png, image/webp
 *   - Saves to      : PUBLIC_ROOT/images/blog/
 */

declare(strict_types=1);

class BlogImageUpload
{
    private const MAX_BYTES    = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp'];
    private const FIELD        = 'featuredImageFile';

    private static function destDir(): string
    {
        $root = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : realpath(__DIR__ . '/../../../public');
        return rtrim((string) $root, '/') . '/images/blog';
    }

    /**
     * Process the upload.
     *
     * @return array{path: ?string, error: ?string}
     */
    public static function handle(): array
    {
        if (empty($_FILES[self::FIELD]['tmp_name'])) {
            return ['path' => null, 'error' => null];
        }

        $file = $_FILES[self::FIELD];

        // PHP-level upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Featured image must be under 10 MB.'
                : 'Image upload failed (error code ' . $file['error'] . ').';
            return ['path' => null, 'error' => $msg];
        }

        // Size guard
        if ($file['size'] > self::MAX_BYTES) {
            @unlink($file['tmp_name']);
            return [
                'path'  => null,
                'error' => 'Featured image must be under 10 MB.',
            ];
        }

        // Extension check
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            @unlink($file['tmp_name']);
            return [
                'path'  => null,
                'error' => 'Featured image must be a JPEG, PNG or WebP.',
            ];
        }

        // MIME check via finfo
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            @unlink($file['tmp_name']);
            return [
                'path'  => null,
                'error' => 'Featured image must be a JPEG, PNG or WebP.',
            ];
        }

        // Ensure destination directory
        $destDir = self::destDir();
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            return [
                'path'  => null,
                'error' => 'Could not create upload directory.',
            ];
        }

        // Deterministic filename based on content hash
        $hash = sha1_file($file['tmp_name']);
        if ($hash === false) {
            self::setError('Could not read uploaded image.');
            return null;
        }

        $safeExt  = in_array($ext, self::ALLOWED_EXT, true) ? $ext : 'jpg';
        $filename = 'blog-' . $hash . '.' . $safeExt;
        $destPath = $destDir . '/' . $filename;

        if (!file_exists($destPath)) {
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                return [
                    'path'  => null,
                    'error' => 'Could not save the uploaded image.',
                ];
            }
        }

        return [
            'path'  => '/images/blog/' . $filename,
            'error' => null,
        ];
    }
}
