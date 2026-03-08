<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ContentNormalizer — thin utility for content checks and excerpt generation.
 *
 * Primary content cleaning and normalization now happens in Python
 * (docker/extractor/content_cleaner.py) via the unified enrichment pipeline.
 *
 * This class retains only:
 *  - isSubstantial() — minimum content length check
 *  - excerpt() — clean text excerpt from HTML
 *  - normalize() — light fallback for PHP-only extraction path
 */
final class ContentNormalizer
{
    /**
     * Light normalization for PHP fallback path only.
     * The Python content_cleaner handles the heavy lifting when available.
     */
    public static function normalize(string $html): string
    {
        if (empty(trim($html))) return '';

        // Strip dangerous event handlers
        $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html);

        // Remove <script> and <style>
        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);

        // Remove empty elements (2 passes)
        for ($i = 0; $i < 2; $i++) {
            $html = preg_replace('#<(p|div|span|li|ul|ol|h[2-6])(\s[^>]*)?>\s*</\1>#i', '', $html);
        }

        // Normalize whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);

        return trim($html);
    }

    /** Check if content is substantial (>= minChars of text). */
    public static function isSubstantial(string $html, int $minChars = 200): bool
    {
        return strlen(strip_tags($html)) >= $minChars;
    }

    /** Generate clean excerpt from HTML. */
    public static function excerpt(string $html, int $maxLen = 300): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        if (strlen($text) <= $maxLen) return $text;
        $cut = substr($text, 0, $maxLen);
        $last = strrpos($cut, ' ');
        if ($last !== false && $last > $maxLen * 0.7) $cut = substr($cut, 0, $last);
        return $cut . '…';
    }
}
