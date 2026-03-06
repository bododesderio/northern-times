<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ContentNormalizer — ensures all article HTML has consistent, clean structure.
 *
 * Pipeline: strip dangerous attrs → demote h1 → normalize images →
 * wrap video/audio embeds → clean empty elements → WP boilerplate → whitespace
 */
final class ContentNormalizer
{
    // Comprehensive video/audio embed whitelist
    private const EMBED_WHITELIST = '/youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com|twitter\.com|x\.com|instagram\.com|facebook\.com|fb\.watch|tiktok\.com|rumble\.com|bitchute\.com|odysee\.com|spotify\.com|soundcloud\.com|streamable\.com|jwplatform\.com|brightcove|kaltura|vidyard|wistia|twitch\.tv|reddit\.com|threads\.net|mastodon/i';

    /**
     * Normalize article HTML content.
     */
    public static function normalize(string $html): string
    {
        if (empty(trim($html))) return '';

        // 1a. Convert lazy-load data-src to real src BEFORE stripping data attrs
        // Many news sites use data-src, data-lazy-src, data-original for lazy loading
        $html = preg_replace_callback('#<img([^>]*)>#i', function($m) {
            $attrs = $m[1];
            // If no real src (or src is a placeholder), swap in data-src
            $hasSrc = preg_match('/\bsrc=["\']([^"\']+)["\']/', $attrs, $srcM);
            $srcVal = $hasSrc ? $srcM[1] : '';
            $isPlaceholder = empty($srcVal) || str_contains($srcVal, 'data:') || str_contains($srcVal, 'placeholder') || str_contains($srcVal, '1x1') || str_contains($srcVal, 'blank');

            if ($isPlaceholder) {
                // Try data-src, data-lazy-src, data-original, data-srcset
                foreach (['data-src', 'data-lazy-src', 'data-original', 'data-full-src'] as $dataAttr) {
                    if (preg_match('/' . preg_quote($dataAttr) . '=["\']([^"\']+)["\']/', $attrs, $dm)) {
                        if ($hasSrc) {
                            $attrs = preg_replace('/\bsrc=["\'][^"\']+["\']/', 'src="' . $dm[1] . '"', $attrs);
                        } else {
                            $attrs = 'src="' . $dm[1] . '" ' . $attrs;
                        }
                        break;
                    }
                }
            }
            return '<img' . $attrs . '>';
        }, $html);

        // 1b. Strip dangerous attrs
        $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html);  // event handlers
        $html = preg_replace('/\s+data-[\w-]+="[^"]*"/i', '', $html);  // data attrs
        $html = preg_replace('/\s+class="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+id="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+aria-[\w-]+="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+role="[^"]*"/i', '', $html);
        $html = preg_replace('/\s+tabindex="[^"]*"/i', '', $html);

        // Strip inline styles ONLY on non-media elements (preserve on video/iframe/figure)
        $html = preg_replace_callback('/(<(?!iframe|video|audio|figure|source)[a-z][^>]*)\s+style="[^"]*"/i', function($m) {
            return $m[1];
        }, $html);

        // 2. Demote h1 → h2
        $html = preg_replace('#<h1([^>]*)>#i', '<h2$1>', $html);
        $html = preg_replace('#</h1>#i', '</h2>', $html);

        // 3. Normalize images
        $html = self::normalizeImages($html);

        // 4. Wrap video iframes responsively
        $html = preg_replace_callback('#<iframe[^>]*>.*?</iframe>#is', function ($m) {
            $iframe = $m[0];
            if (preg_match(self::EMBED_WHITELIST, $iframe)) {
                return '<figure class="embed-responsive"><div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%">'
                     . $iframe . '</div></figure>';
            }
            return ''; // Strip non-video iframes
        }, $html);

        // 5. Wrap native <video> and <audio> tags responsively
        $html = preg_replace_callback('#<video([^>]*)>(.*?)</video>#is', function ($m) {
            $attrs = $m[1];
            $inner = $m[2];
            // Ensure controls and responsive sizing
            if (!str_contains($attrs, 'controls')) $attrs .= ' controls';
            return '<figure class="embed-responsive"><video' . $attrs
                 . ' style="max-width:100%;height:auto" preload="metadata">'
                 . $inner . '</video></figure>';
        }, $html);

        $html = preg_replace_callback('#<audio([^>]*)>(.*?)</audio>#is', function ($m) {
            $attrs = $m[1];
            $inner = $m[2];
            if (!str_contains($attrs, 'controls')) $attrs .= ' controls';
            return '<figure class="embed-audio"><audio' . $attrs
                 . ' style="width:100%" preload="metadata">'
                 . $inner . '</audio></figure>';
        }, $html);

        // 6. Clean empty elements (3 passes)
        for ($i = 0; $i < 3; $i++) {
            $html = preg_replace('#<(p|div|span|li|ul|ol|h[2-6]|figure|figcaption|section|aside)(\s[^>]*)?>\s*</\1>#i', '', $html);
        }

        // 7. WordPress boilerplate
        $html = preg_replace('#<p[^>]*>\s*The post\s+<a[^>]*>.*?</a>\s+appeared first on\s+<a[^>]*>.*?</a>\.\s*</p>#is', '', $html);
        $html = preg_replace('#<p[^>]*>\s*The post\s+.{5,300}\s+appeared first on\s+.{3,100}\.\s*</p>#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Share\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Like\s+this\s*:?\s*</h[2-6]>.*?(?=<h[1-2]|$)#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Related\s*:?\s*</h[2-6]>.*$#is', '', $html);
        $html = preg_replace('#<ul[^>]*>\s*(?:<li[^>]*>\s*<a[^>]*>Share\s+on\s+\w+[^<]*</a>\s*</li>\s*){2,}</ul>#is', '', $html);
        $html = preg_replace('#<p[^>]*>\s*Like\s+Loading\s*\.{0,3}\s*</p>#is', '', $html);

        // 8. Normalize whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        $html = preg_replace('/[ \t]+/', ' ', $html);

        return trim($html);
    }

    /**
     * Normalize images: add loading=lazy, clean attrs, skip tracking pixels.
     */
    private static function normalizeImages(string $html): string
    {
        return preg_replace_callback('#<img\s+([^>]*?)/?>#i', function ($m) {
            $attrs = $m[1];

            $src = '';
            if (preg_match('/src=["\']([^"\']+)["\']/', $attrs, $sm)) $src = $sm[1];
            if (empty($src) || str_contains($src, 'data:image/svg')) return '';

            // Skip tracking pixels/icons (but keep content images)
            if (preg_match('/\b(pixel|track|beacon|spacer|blank|1x1)\b/i', $src)) return '';

            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/', $attrs, $am)) $alt = $am[1];

            $dims = '';
            if (preg_match('/width=["\']([\d.]+)["\']/', $attrs, $wm)) $dims .= ' width="' . $wm[1] . '"';
            if (preg_match('/height=["\']([\d.]+)["\']/', $attrs, $hm)) $dims .= ' height="' . $hm[1] . '"';

            $srcset = '';
            if (preg_match('/srcset=["\']([^"\']+)["\']/', $attrs, $ssm)) {
                $srcset = ' srcset="' . htmlspecialchars($ssm[1], ENT_QUOTES) . '"';
            }

            return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '"'
                 . ' alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"'
                 . ' loading="lazy"' . $dims . $srcset
                 . ' referrerpolicy="no-referrer"'
                 . ' style="max-width:100%;height:auto">';
        }, $html);
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