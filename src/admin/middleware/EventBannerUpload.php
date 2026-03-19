<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/middleware/EventBannerUpload.php
 *
 * Handles the optional banner image upload for event create/edit forms.
 * Call EventBannerUpload::handle() before your controller action.
 *
 * On success:  $_FILES['bannerImageFile'] is populated as normal.
 * On error:    $_REQUEST['_bannerUploadError'] is set with the message;
 *              the uploaded temp file (if any) is deleted.
 * No upload:   no-op — controller treats $bannerImage as null/URL.
 *
 * Limits:
 *   - Max file size : 15 MB
 *   - Allowed types : image/jpeg, image/png, image/webp
 *   - Saves to      : PUBLIC_ROOT/images/events/
 */

declare(strict_types=1);

class EventBannerUpload
{
    private const MAX_BYTES    = 15 * 1024 * 1024; // 15 MB
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp'];
    private const FIELD        = 'bannerImageFile';

    /** Destination directory for saved banners. */
    private static function destDir(): string
    {
        $root = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : realpath(__DIR__ . '/../../..');
        return rtrim($root, '/') . '/images/events';
    }

    /**
     * Process the upload.
     * Returns the saved filename on success, null when no file was submitted.
     * On validation failure, sets $_REQUEST['_bannerUploadError'] and returns null.
     */
    public static function handle(): ?string
    {
        // No file submitted — field missing or empty
        if (empty($_FILES[self::FIELD]['tmp_name'])) {
            return null;
        }

        $file = $_FILES[self::FIELD];

        // PHP-level upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Banner image must be under 15 MB.'
                : 'Banner upload failed (error code ' . $file['error'] . ').';
            self::setError($msg);
            return null;
        }

        // File size guard
        if ($file['size'] > self::MAX_BYTES) {
            self::setError('Banner image must be under 15 MB.');
            @unlink($file['tmp_name']);
            return null;
        }

        // Extension check
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            self::setError('Banner must be a JPEG, PNG or WebP image.');
            @unlink($file['tmp_name']);
            return null;
        }

        // MIME check via finfo (more reliable than browser-supplied type)
        if (class_exists('finfo')) {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
                self::setError('Banner must be a JPEG, PNG or WebP image.');
                @unlink($file['tmp_name']);
                return null;
            }
        }

        // Ensure destination directory exists
        $destDir = self::destDir();
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            self::setError('Could not create upload directory.');
            return null;
        }

        // Compute a content hash so identical banners share the same filename.
        $hash = sha1_file($file['tmp_name']);
        if ($hash === false) {
            self::setError('Could not read uploaded banner image.');
            return null;
        }

        // Build a safe deterministic filename based on the hash
        $safeExt  = in_array($ext, self::ALLOWED_EXT, true) ? $ext : 'jpg';
        $filename = 'event-' . $hash . '.' . $safeExt;
        $destPath = $destDir . '/' . $filename;

        // If a file with the same content already exists, reuse it
        if (!file_exists($destPath)) {
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                self::setError('Could not save the uploaded banner image.');
                return null;
            }
        }

        // Overwrite $_FILES so the controller can read it the standard way
        $_FILES[self::FIELD]['name']     = $filename;
        $_FILES[self::FIELD]['tmp_name'] = $destPath;

        return $filename;
    }

    private static function setError(string $message): void
    {
        $_REQUEST['_bannerUploadError'] = $message;
    }
}
