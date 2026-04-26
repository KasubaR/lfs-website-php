<?php
/**
 * Optional PDF upload for event brochure. Runs before create/update.
 * On success sets $_POST['brochure_pdf_stored'] to a public path under /files/event-brochures/
 */

declare(strict_types=1);

class EventBrochureUpload
{
    private const MAX_BYTES    = 25 * 1024 * 1024;
    private const FIELD        = 'brochurePdfFile';
    private const ALLOWED_MIME = ['application/pdf', 'application/x-pdf'];
    private const ALLOWED_EXT  = ['pdf'];

    private static function destDir(): string
    {
        $root = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : realpath(__DIR__ . '/../../..');
        return rtrim($root, '/\\') . '/files/event-brochures';
    }

    public static function handle(): void
    {
        unset($_POST['brochure_pdf_stored']);
        if (empty($_FILES[self::FIELD]['tmp_name'])) {
            return;
        }
        $file = $_FILES[self::FIELD];
        if ((int) ($file['error'] ?? 0) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            self::setError('Brochure upload failed (code ' . (int) $file['error'] . ').');
            return;
        }
        if ($file['size'] > self::MAX_BYTES) {
            self::setError('Brochure PDF must be 25 MB or less.');
            @unlink($file['tmp_name']);
            return;
        }
        $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            self::setError('Brochure must be a PDF file.');
            @unlink($file['tmp_name']);
            return;
        }
        if (class_exists('finfo', false)) {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']) ?: '';
            $m        = strtolower($mimeType);
            $mimeOk   = $mimeType === '' || $m === 'application/pdf' || $m === 'application/octet-stream'
                || $m === 'application/x-pdf' || str_contains($m, 'pdf');
            if (!$mimeOk) {
                self::setError('Brochure must be a PDF file.');
                @unlink($file['tmp_name']);
                return;
            }
        }
        $dir = self::destDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            self::setError('Could not create brochure upload directory.');
            return;
        }
        $hash = sha1_file($file['tmp_name']);
        if ($hash === false) {
            self::setError('Could not read the uploaded brochure file.');
            @unlink($file['tmp_name']);
            return;
        }
        $filename = 'brochure-' . $hash . '.pdf';
        $destPath = $dir . '/' . $filename;
        if (!file_exists($destPath)) {
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                self::setError('Could not save the brochure file.');
                return;
            }
        } else {
            @unlink($file['tmp_name']);
        }
        $_POST['brochure_pdf_stored'] = '/files/event-brochures/' . $filename;
    }

    private static function setError(string $message): void
    {
        $_REQUEST['_brochureUploadError'] = $message;
    }
}
