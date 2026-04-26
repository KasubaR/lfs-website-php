<?php
/**
 * Processes dist_route_file[] uploads before event create/update.
 * Saves to PUBLIC_ROOT/images/event-routes/ and sets $_POST['dist_route_stored'][i] = public path.
 */

declare(strict_types=1);

class EventDistanceRouteUpload
{
    private const MAX_BYTES    = 15 * 1024 * 1024;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp'];
    private const FIELD        = 'dist_route_file';

    private static function destDir(): string
    {
        $root = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : realpath(__DIR__ . '/../../..');
        return rtrim($root, '/') . '/images/event-routes';
    }

    public static function handle(): void
    {
        $_POST['dist_route_stored'] = [];
        if (empty($_FILES[self::FIELD]) || !isset($_FILES[self::FIELD]['name'])) {
            return;
        }
        if (!is_array($_FILES[self::FIELD]['name'])) {
            $f = $_FILES[self::FIELD];
            $_FILES[self::FIELD] = [
                'name'     => [$f['name'] ?? ''],
                'type'     => [$f['type'] ?? ''],
                'tmp_name' => [$f['tmp_name'] ?? ''],
                'error'    => [isset($f['error']) ? (int) $f['error'] : UPLOAD_ERR_NO_FILE],
                'size'     => [isset($f['size']) ? (int) $f['size'] : 0],
            ];
        }
        $names     = $_FILES[self::FIELD]['name'];
        $tmpNames  = $_FILES[self::FIELD]['tmp_name'];
        $errors    = $_FILES[self::FIELD]['error'];
        $sizes     = $_FILES[self::FIELD]['size'] ?? [];
        $destDir   = self::destDir();
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            self::setError('Could not create route image upload directory.');
            return;
        }
        foreach ($names as $i => $name) {
            if ($errors[$i] ?? 0) {
                if (($errors[$i] ?? 0) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                self::setError('A route image upload failed (row ' . ((int) $i + 1) . ').');
                return;
            }
            $tmp = $tmpNames[$i] ?? '';
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            if (($sizes[$i] ?? 0) > self::MAX_BYTES) {
                self::setError('Each route image must be under 15 MB.');
                @unlink($tmp);
                return;
            }
            $ext = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                self::setError('Route images must be JPEG, PNG, or WebP.');
                @unlink($tmp);
                return;
            }
            if (class_exists('finfo', false)) {
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($tmp);
                if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
                    self::setError('Route images must be JPEG, PNG, or WebP.');
                    @unlink($tmp);
                    return;
                }
            }
            $hash     = sha1_file($tmp);
            if ($hash === false) {
                self::setError('Could not read a route image upload.');
                @unlink($tmp);
                return;
            }
            $filename = 'route-' . $hash . '.' . $ext;
            $destPath = $destDir . '/' . $filename;
            if (!file_exists($destPath)) {
                if (!move_uploaded_file($tmp, $destPath)) {
                    self::setError('Could not save a route image.');
                    return;
                }
            } else {
                @unlink($tmp);
            }
            $_POST['dist_route_stored'][(int) $i] = '/images/event-routes/' . $filename;
        }
    }

    private static function setError(string $message): void
    {
        $_REQUEST['_distanceRouteUploadError'] = $message;
    }
}
