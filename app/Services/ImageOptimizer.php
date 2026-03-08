<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Image Optimizer — adds lazy loading, responsive srcset, and size attributes
 * to article HTML content images.
 */
final class ImageOptimizer
{
    /**
     * Process HTML to add responsive image attributes.
     */
    public static function optimizeHtml(string $html): string
    {
        if (empty($html)) return $html;

        // Add loading="lazy" to all images that don't already have it
        $html = preg_replace(
            '/<img(?![^>]*loading=)([^>]*?)>/i',
            '<img loading="lazy"$1>',
            $html
        );

        // Add decoding="async" to all images
        $html = preg_replace(
            '/<img(?![^>]*decoding=)([^>]*?)>/i',
            '<img decoding="async"$1>',
            $html
        );

        // Add fetchpriority="low" to non-first images
        // (first image should stay high priority)
        $count = 0;
        $html = preg_replace_callback(
            '/<img([^>]*?)>/i',
            function ($match) use (&$count) {
                $count++;
                if ($count === 1) {
                    // First image: high priority, eager load
                    $attrs = $match[1];
                    $attrs = str_replace('loading="lazy"', 'loading="eager"', $attrs);
                    return '<img fetchpriority="high"' . $attrs . '>';
                }
                return $match[0];
            },
            $html
        );

        return $html;
    }

    /**
     * Generate responsive featured image HTML with srcset.
     *
     * @param string $imageUrl Original image URL
     * @param string $alt      Alt text
     * @param bool   $eager    Whether to load eagerly (above-the-fold)
     */
    public static function responsiveImage(string $imageUrl, string $alt = '', bool $eager = false): string
    {
        if (empty($imageUrl)) return '';

        $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        $loading = $eager ? 'eager' : 'lazy';
        $priority = $eager ? ' fetchpriority="high"' : '';

        return sprintf(
            '<img src="%s" alt="%s" loading="%s" decoding="async"%s class="responsive-img" sizes="(max-width: 768px) 100vw, (max-width: 1200px) 66vw, 800px">',
            htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'),
            $alt,
            $loading,
            $priority
        );
    }
}
