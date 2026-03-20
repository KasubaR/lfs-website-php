<?php
/**
 * Load app configuration from a .env file into the process environment.
 * Only variables whose names match DB_*, ADMIN_*, or JWT_* are applied.
 * Callers pass the full path (e.g. project root index.php loads __DIR__ . '/.env').
 *
 * @see Database.php — reads DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
 */
declare(strict_types=1);

final class DatabaseEnv
{
    /** Keys are normalized with strtoupper() before matching (so db_host in .env becomes DB_HOST). */
    private const KEY_PATTERN = '/^(DB|ADMIN|JWT)_[A-Z][A-Z0-9_]*$/';

    /**
     * Parse $path and set matching DB_* keys via putenv() and $_ENV.
     * Missing or unreadable file is a no-op (Database.php uses its own defaults).
     */
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        foreach (self::splitLines($content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = strtoupper(trim(substr($line, 0, $eq)));
            if ($key === '' || !preg_match(self::KEY_PATTERN, $key)) {
                continue;
            }
            $value = self::parseValue(trim(substr($line, $eq + 1)));
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $content): array
    {
        return preg_split("/\r\n|\n|\r/", $content) ?: [];
    }

    private static function parseValue(string $raw): string
    {
        $len = strlen($raw);
        if ($len >= 2) {
            $q = $raw[0];
            if (($q === '"' || $q === "'") && $raw[$len - 1] === $q) {
                $inner = substr($raw, 1, -1);
                return $q === '"' ? stripcslashes($inner) : $inner;
            }
        }
        return $raw;
    }
}
