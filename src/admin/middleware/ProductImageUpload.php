<?php
/**
 * LFS — Lusaka Fitness Squad
 * admin/middleware/ProductImageUpload.php
 *
 * Handles up to 5 product image uploads for create / edit forms.
 * Call ProductImageUpload::handle() before your controller action.
 *
 * On success:
 *   $_REQUEST['_productImages']   = ['/uploads/products/{key}/filename.ext', ...]
 *   $_REQUEST['_productUploadKey'] = the directory key used (product id or temp UUID)
 *
 * On validation failure:
 *   $_REQUEST['_imageUploadError'] = human-readable error message
 *   Any temp files already saved are deleted.
 *
 * No upload (field absent / empty):  no-op — controller proceeds normally.
 *
 * Limits:
 *   - Max file size : 15 MB per image
 *   - Max files     : 5
 *   - Allowed types : image/jpeg, image/png, image/webp
 *   - Saves to      : PUBLIC_ROOT/uploads/products/{uploadKey}/
 */

declare(strict_types=1);

class ProductImageUpload
{
    private const MAX_BYTES    = 15 * 1024 * 1024; // 15 MB
    private const MAX_FILES    = 5;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp'];
    private const FIELD        = 'productImages';

    /** Base upload directory under PUBLIC_ROOT. */
    private static function uploadRoot(): string
    {
        $root = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : realpath(__DIR__ . '/../../../public');
        return rtrim((string)$root, '/') . '/uploads/products';
    }

    /**
     * Process all submitted product image files.
     *
     * Returns an array of saved URL paths on success (may be empty if no files
     * were submitted), or null if a hard validation error occurred.
     *
     * @return string[]|null
     */
    public static function handle(): ?array
    {
        // No files submitted at all
        if (empty($_FILES[self::FIELD])) {
            $_REQUEST['_productImages']    = [];
            $_REQUEST['_productUploadKey'] = null;
            return [];
        }

        // Normalise $_FILES[field] from PHP's multi-file structure to a flat list
        $raw   = $_FILES[self::FIELD];
        $files = self::normaliseFiles($raw);

        if (empty($files)) {
            $_REQUEST['_productImages']    = [];
            $_REQUEST['_productUploadKey'] = null;
            return [];
        }

        // Enforce file count
        if (count($files) > self::MAX_FILES) {
            self::setError('You may upload up to ' . self::MAX_FILES . ' product images at once.');
            return null;
        }

        // Determine the upload key: existing product id from URL, or a temp UUID
        $uploadKey = self::resolveUploadKey();

        // Ensure destination directory exists
        $destDir = self::uploadRoot() . '/' . $uploadKey;
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            self::setError('Could not create product upload directory.');
            return null;
        }

        $savedUrls = [];
        $finfo     = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

        foreach ($files as $file) {
            // PHP-level upload error
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $msg = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? 'Each product image must be under 15 MB.'
                    : 'Product image upload failed (error code ' . $file['error'] . ').';
                self::setError($msg);
                self::cleanUp($savedUrls, $uploadKey);
                return null;
            }

            // File size
            if ($file['size'] > self::MAX_BYTES) {
                self::setError('Each product image must be under 15 MB.');
                self::cleanUp($savedUrls, $uploadKey);
                @unlink($file['tmp_name']);
                return null;
            }

            // Extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                self::setError('Product images must be JPEG, PNG or WebP.');
                self::cleanUp($savedUrls, $uploadKey);
                @unlink($file['tmp_name']);
                return null;
            }

            // MIME via finfo when available
            if ($finfo instanceof finfo) {
                $mimeType = $finfo->file($file['tmp_name']);
                if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
                    self::setError('Product images must be JPEG, PNG or WebP.');
                    self::cleanUp($savedUrls, $uploadKey);
                    @unlink($file['tmp_name']);
                    return null;
                }
            }

            // Build safe unique filename
            $safeExt  = in_array($ext, self::ALLOWED_EXT, true) ? $ext : 'jpg';
            $filename = 'product-' . bin2hex(random_bytes(8)) . '.' . $safeExt;
            $destPath = $destDir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                self::setError('Could not save a product image. Please try again.');
                self::cleanUp($savedUrls, $uploadKey);
                return null;
            }

            $savedUrls[] = '/uploads/products/' . $uploadKey . '/' . $filename;
        }

        $_REQUEST['_productImages']    = $savedUrls;
        $_REQUEST['_productUploadKey'] = $uploadKey;
        return $savedUrls;
    }

    // ─── Private helpers ──────────────────────────────────────

    /**
     * Determine the folder key for this upload.
     * Uses the :id URL segment (from the route) when updating an existing product,
     * or generates a random hex key for new-product temporary storage.
     */
    private static function resolveUploadKey(): string
    {
        // Route segment captured by the router and passed via $_REQUEST
        if (!empty($_REQUEST['_routeId'])) {
            return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_REQUEST['_routeId']);
        }
        // Explicit productId in form body
        if (!empty($_POST['productId'])) {
            return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_POST['productId']);
        }
        // New product — use a temporary random key; controller renames after insert
        return 'tmp-' . bin2hex(random_bytes(8));
    }

    /**
     * Convert PHP's multi-file $_FILES structure to a flat list of file arrays.
     *
     * PHP gives:
     *   $_FILES['productImages']['name']     = ['a.jpg', 'b.png']
     *   $_FILES['productImages']['tmp_name'] = ['/tmp/php1', '/tmp/php2']
     *   etc.
     *
     * We want:
     *   [ ['name' => 'a.jpg', 'tmp_name' => '/tmp/php1', ...], ... ]
     */
    private static function normaliseFiles(array $raw): array
    {
        // Already a single-file structure (shouldn't happen for array field, but guard)
        if (is_string($raw['name'])) {
            return ($raw['error'] === UPLOAD_ERR_NO_FILE) ? [] : [$raw];
        }

        $files = [];
        foreach ($raw['name'] as $i => $name) {
            if ($raw['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $files[] = [
                'name'     => $name,
                'type'     => $raw['type'][$i],
                'tmp_name' => $raw['tmp_name'][$i],
                'error'    => $raw['error'][$i],
                'size'     => $raw['size'][$i],
            ];
        }
        return $files;
    }

    /** Delete already-saved files when a later file in the batch fails. */
    private static function cleanUp(array $savedUrls, string $uploadKey): void
    {
        $dir = self::uploadRoot() . '/' . $uploadKey;
        foreach ($savedUrls as $url) {
            $path = self::uploadRoot() . '/' . $uploadKey . '/' . basename($url);
            if (file_exists($path)) @unlink($path);
        }
        // Remove the directory if it is now empty
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            @rmdir($dir);
        }
    }

    private static function setError(string $message): void
    {
        $_REQUEST['_imageUploadError'] = $message;
    }
}
