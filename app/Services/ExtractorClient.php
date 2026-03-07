<?php
declare(strict_types=1);

namespace App\Services;

/**
 * HTTP client for the Python article extractor microservice.
 *
 * Falls back gracefully: returns null if the service is unavailable,
 * allowing CrawlerEngine to use the PHP ArticleScraper instead.
 */
final class ExtractorClient
{
    private static function baseUrl(): string
    {
        return rtrim($_ENV['EXTRACTOR_URL'] ?? 'http://extractor:5000', '/');
    }

    private static function timeout(): int
    {
        return (int)($_ENV['EXTRACTOR_TIMEOUT'] ?? 15);
    }

    /**
     * Check if the extractor service is reachable.
     */
    public static function isHealthy(): bool
    {
        try {
            $ch = curl_init(self::baseUrl() . '/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200) return false;
            $data = json_decode($body, true);
            return ($data['status'] ?? '') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Extract article data from a URL.
     *
     * @param  string $url    The article URL to extract
     * @param  array  $source The crawl source config (for strip_selectors)
     * @return array|null     Extracted data compatible with CrawlerEngine, or null on failure
     */
    public static function extract(string $url, array $source = []): ?array
    {
        $payload = ['url' => $url];
        if (!empty($source['content_selector'])) {
            $payload['source_selectors'] = $source['content_selector'];
        }

        $response = self::post('/extract', $payload);
        if ($response === null) return null;

        if (empty($response['success']) || empty($response['data'])) {
            return null;
        }

        $data = $response['data'];

        // Map to the format CrawlerEngine expects
        return [
            'content'        => $data['content'] ?? '',
            'hero_image'     => $data['hero_image'] ?? null,
            'excerpt'        => $data['excerpt'] ?? '',
            'text_length'    => $data['text_length'] ?? 0,
            // Extended metadata from Python extractor
            'title'          => $data['title'] ?? null,
            'authors'        => $data['authors'] ?? [],
            'published_date' => $data['published_date'] ?? null,
            'images'         => $data['images'] ?? [],
            'language'       => $data['language'] ?? null,
            'source_domain'  => $data['source_domain'] ?? null,
        ];
    }

    /**
     * Classify article relevance for non-Ugandan sources.
     *
     * @return array|null  {dominated_region, relevance_score, is_sports, accept}
     */
    public static function classify(string $title, string $excerpt = '', string $sourceRegion = 'international'): ?array
    {
        return self::post('/classify', [
            'title' => $title,
            'excerpt' => $excerpt,
            'source_region' => $sourceRegion,
        ]);
    }

    /**
     * Send a POST request to the extractor service.
     */
    private static function post(string $endpoint, array $payload): ?array
    {
        try {
            $ch = curl_init(self::baseUrl() . $endpoint);
            $json = json_encode($payload);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json),
                ],
                CURLOPT_TIMEOUT        => self::timeout(),
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body === false || $code >= 400) {
                error_log("ExtractorClient: HTTP {$code} from {$endpoint}: {$err}");
                return null;
            }

            return json_decode($body, true);
        } catch (\Throwable $e) {
            error_log("ExtractorClient: " . $e->getMessage());
            return null;
        }
    }
}
