<?php
/**
 * LFS — Lusaka Fitness Squad
 * src/admin/middleware/AlbumCoverUpload.php
 *
 * Minimal PHP middleware for handling gallery album cover uploads.
 * This is a simple implementation that:
 *   - Accepts a single file input named "cover"
 *   - Saves it into /uploads/gallery/covers (under PUBLIC_ROOT / project web root)
 *   - Returns the stored filename (not full path) on success
 *
 * The admin/routes/gallery.php file then turns that into a public URL:
 *   /uploads/gallery/covers/{filename}
 */

declare(strict_types=1);

class AlbumCoverUpload
{
    /** Handle the upload and return the stored filename, or null on error. */
    public static function handle(): ?string
    {
        if (!isset($_FILES['cover']) || !is_array($_FILES['cover'])) {
            $_REQUEST['_coverUploadError'] = 'No cover file uploaded.';
            return null;
        }

        $file = $_FILES['cover'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_REQUEST['_coverUploadError'] = 'Upload error code: ' . (string)$file['error'];
            return null;
        }

        $tmpName = $file['tmp_name'] ?? '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $_REQUEST['_coverUploadError'] = 'Invalid uploaded file.';
            return null;
        }

        // Basic MIME/type check
        $mime = mime_content_type($tmpName) ?: '';
        if (!str_starts_with($mime, 'image/')) {
            $_REQUEST['_coverUploadError'] = 'Cover image must be a valid image file.';
            return null;
        }

        $base    = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : dirname(__DIR__, 3);
        $destDir = $base . '/uploads/gallery/covers';

        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            $_REQUEST['_coverUploadError'] = 'Could not create covers upload directory.';
            return null;
        }

        // Compute a content hash so identical images share the same filename.
        $hash = sha1_file($tmpName);
        if ($hash === false) {
            $_REQUEST['_coverUploadError'] = 'Could not read uploaded file.';
            return null;
        }

        // Preserve extension if possible
        $originalName = $file['name'] ?? 'cover.jpg';
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'jpg';
        }

        // Use the hash in the filename to de-duplicate
        $filename = 'cover_' . $hash . '.' . $ext;
        $destPath = $destDir . '/' . $filename;

        // If a file with the same content already exists, just reuse it
        if (file_exists($destPath)) {
            return $filename;
        }

        if (!move_uploaded_file($tmpName, $destPath)) {
            $_REQUEST['_coverUploadError'] = 'Failed to move uploaded file.';
            return null;
        }

        return $filename;
    }
}

