<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;
use App\Models\ImageHealthLog;
use App\Models\Setting;

/**
 * Image Health Checker
 *
 * Daily scan of all hotlinked images in published articles.
 * Uses HEAD requests to detect broken images (4xx/5xx/timeout).
 * Auto-replaces broken images with a placeholder SVG.
 */
final class ImageHealthChecker
{
    private const HEAD_TIMEOUT = 8;

    private static function userAgent(): string
    {
        $name = \get_site_setting('site_title', 'ImageChecker');
        return preg_replace('/[^A-Za-z0-9]/', '', $name) . 'ImageChecker/1.0';
    }
    private const BATCH_SIZE   = 50;

    /**
     * Run a full image health check on published articles.
     * Returns [checked, broken, replaced].
     */
    public static function run(): array
    {
        $placeholderUrl = Setting::get('image_placeholder_url', '/assets/img/image-unavailable.svg');

        // Get all published articles with content containing <img> or featured_image
        $articles = BaseModel::query(
            "SELECT id, content, featured_image, slug, title
             FROM articles
             WHERE status = 'published'
               AND (content LIKE '%<img%' OR featured_image IS NOT NULL)
             ORDER BY published_at DESC NULLS LAST
             LIMIT 5000"
        );

        $results     = [];
        $checked     = 0;
        $brokenCount = 0;
        $replaced    = 0;
        $seenUrls    = []; // Avoid checking same URL twice

        foreach ($articles as $article) {
            $images = self::extractImageUrls($article['content'] ?? '', $article['featured_image']);

            foreach ($images as $url) {
                if (isset($seenUrls[$url])) continue;
                $seenUrls[$url] = true;

                $checked++;
                $checkResult = self::checkImage($url);

                $result = [
                    'article_id'   => $article['id'],
                    'image_url'    => $url,
                    'http_status'  => $checkResult['status'],
                    'is_broken'    => $checkResult['broken'],
                    'error_message'=> $checkResult['error'],
                ];
                $results[] = $result;

                if ($checkResult['broken']) {
                    $brokenCount++;

                    // Auto-replace in content
                    $updated = self::replaceInContent($article['id'], $url, $placeholderUrl);
                    if ($updated) {
                        $replaced++;
                        $result['replaced'] = true;
                    }
                }

                // Batch insert logs
                if (count($results) >= self::BATCH_SIZE) {
                    ImageHealthLog::logBatch($results);
                    $results = [];
                }
            }
        }

        // Insert remaining
        if (!empty($results)) {
            ImageHealthLog::logBatch($results);
        }

        return [$checked, $brokenCount, $replaced];
    }

    /**
     * Check a single image URL via HEAD request.
     */
    private static function checkImage(string $url): array
    {
        // Skip data URIs and relative paths
        if (str_starts_with($url, 'data:') || !str_starts_with($url, 'http')) {
            return ['status' => null, 'broken' => false, 'error' => null];
        }

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'HEAD',
                'header'          => 'User-Agent: ' . self::userAgent(),
                'timeout'         => self::HEAD_TIMEOUT,
                'follow_location' => 1,
                'max_redirects'   => 3,
            ],
            'ssl' => [
                'verify_peer'      => false, // Many image CDNs have loose SSL
                'verify_peer_name' => false,
            ],
        ]);

        $headers = @get_headers($url, true, $ctx);

        if ($headers === false) {
            return ['status' => null, 'broken' => true, 'error' => 'Connection failed or timeout'];
        }

        // Get the final status code (handle redirects)
        $statusLine = is_array($headers[0] ?? null) ? end($headers[0]) : ($headers[0] ?? '');
        preg_match('/\d{3}/', $statusLine, $matches);
        $status = (int)($matches[0] ?? 0);

        $broken = $status === 0 || $status >= 400;

        return [
            'status' => $status ?: null,
            'broken' => $broken,
            'error'  => $broken ? "HTTP {$status}" : null,
        ];
    }

    /**
     * Extract all image URLs from article content + featured image.
     */
    private static function extractImageUrls(string $html, ?string $featuredImage): array
    {
        $urls = [];

        // Extract from <img> tags
        preg_match_all('/src=["\']([^"\']+)/i', $html, $matches);
        foreach ($matches[1] as $src) {
            $src = trim($src);
            if ($src !== '' && str_starts_with($src, 'http')) {
                $urls[] = $src;
            }
        }

        // Add featured image
        if ($featuredImage && str_starts_with($featuredImage, 'http')) {
            $urls[] = $featuredImage;
        }

        return array_unique($urls);
    }

    /**
     * Replace a broken image URL with the placeholder in article content.
     */
    private static function replaceInContent(string $articleId, string $brokenUrl, string $placeholderUrl): bool
    {
        $article = BaseModel::queryOne(
            "SELECT content, featured_image FROM articles WHERE id = :id",
            [':id' => $articleId]
        );

        if (!$article) return false;

        $updated = false;
        $content = $article['content'] ?? '';

        // Replace in content body
        if (str_contains($content, $brokenUrl)) {
            $newContent = str_replace($brokenUrl, $placeholderUrl, $content);
            BaseModel::execute(
                "UPDATE articles SET content = :content, updated_at = NOW() WHERE id = :id",
                [':content' => $newContent, ':id' => $articleId]
            );
            $updated = true;

            // Mark as replaced in log
            ImageHealthLog::markReplaced($brokenUrl);
        }

        // Replace featured image
        if ($article['featured_image'] === $brokenUrl) {
            BaseModel::execute(
                "UPDATE articles SET featured_image = :placeholder, updated_at = NOW() WHERE id = :id",
                [':placeholder' => $placeholderUrl, ':id' => $articleId]
            );
            $updated = true;
        }

        return $updated;
    }
}