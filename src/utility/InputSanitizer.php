<?php
/**
 * LFS — shared input sanitization helpers.
 */
declare(strict_types=1);

final class InputSanitizer
{
    private function __construct() {}

    public static function text(?string $value, int $maxLen = 255): string
    {
        $clean = trim((string)$value);
        $clean = strip_tags($clean);
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        return self::truncate($clean, $maxLen);
    }

    public static function email(?string $value): string
    {
        $clean = trim((string)$value);
        $clean = filter_var($clean, FILTER_SANITIZE_EMAIL) ?: '';
        return self::truncate($clean, 254);
    }

    public static function phone(?string $value): string
    {
        $clean = trim((string)$value);
        // Keep digits, spaces, +, -, parentheses only.
        $clean = preg_replace('/[^0-9+\-\s()]/', '', $clean) ?? '';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
        return self::truncate($clean, 30);
    }

    public static function textarea(?string $value, int $maxLen = 5000): string
    {
        $clean = trim((string)$value);
        $clean = strip_tags($clean);
        $clean = preg_replace("/\r\n?/", "\n", $clean) ?? $clean;
        // Trim trailing spaces per line while preserving line breaks.
        $clean = preg_replace('/[ \t]+$/m', '', $clean) ?? $clean;
        return self::truncate($clean, $maxLen);
    }

    private static function truncate(string $value, int $maxLen): string
    {
        if ($maxLen <= 0) return '';
        $len = mb_strlen($value, 'UTF-8');
        if ($len <= $maxLen) return $value;
        return mb_substr($value, 0, $maxLen, 'UTF-8');
    }
}

