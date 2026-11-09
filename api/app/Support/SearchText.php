<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Lowercase, accent-free, single-spaced text for the *_search columns,
 * so "Gómez" and "gomez" match (queries go through the same function).
 */
final class SearchText
{
    public static function normalize(?string $text): string
    {
        $ascii = Str::lower(Str::ascii((string) $text));

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }

    /**
     * @return list<string>
     */
    public static function words(?string $text): array
    {
        $normalized = self::normalize($text);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    /** Escape LIKE wildcards in user input. */
    public static function like(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
