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
     * AI-powered category classification.
     *
     * @param  string $title           Article title
     * @param  string $content         Article content/description (truncated is fine)
     * @param  array  $rssCategories   RSS feed category tags
     * @param  string $url             Article URL
     * @param  array  $systemCategories List of system category slugs
     * @return array|null  {category_slug, confidence, ai_scores, method, conflict_guard}
     */
    public static function classifyCategory(
        string $title,
        string $content = '',
        array $rssCategories = [],
        string $url = '',
        array $systemCategories = []
    ): ?array {
        return self::post('/classify-category', [
            'title' => $title,
            'content' => mb_substr($content, 0, 1000),
            'rss_categories' => $rssCategories,
            'url' => $url,
            'system_categories' => $systemCategories,
        ]);
    }

    /**
     * Cross-source duplicate detection via fuzzy title matching.
     *
     * @return array|null  {is_duplicate, matched_title, similarity, fingerprint}
     */
    public static function checkDuplicate(string $title, array $existingTitles, float $threshold = 0.55): ?array
    {
        return self::post('/check-duplicate', [
            'title' => $title,
            'existing_titles' => $existingTitles,
            'threshold' => $threshold,
        ]);
    }

    /**
     * Generate a 384-dimensional sentence embedding for semantic dedup.
     *
     * @return array|null  {embedding: float[384]}
     */
    public static function embed(string $text): ?array
    {
        return self::post('/embed', ['text' => $text]);
    }

    /**
     * Generate an AI summary of article content.
     *
     * @return string|null  Summary text
     */
    public static function summarize(string $content, int $maxLength = 130): ?string
    {
        $result = self::post('/summarize', [
            'text' => $content,
            'max_length' => $maxLength,
        ]);
        return $result['summary'] ?? null;
    }

    /**
     * Extract named entities (PERSON, ORG, GPE, EVENT) from text.
     *
     * @return array|null  [{text, type, salience}, ...]
     */
    public static function extractEntities(string $text, int $maxEntities = 20): ?array
    {
        $result = self::post('/ner', [
            'text' => $text,
            'max_entities' => $maxEntities,
        ]);
        return $result['entities'] ?? null;
    }

    /**
     * Analyze article sentiment using DeBERTa zero-shot.
     *
     * @return array|null  {sentiment, sentiment_score}
     */
    public static function analyzeSentiment(string $text): ?array
    {
        return self::post('/sentiment', ['text' => $text]);
    }

    /**
     * Score article content quality (0-100 heuristic).
     *
     * @return int|null  Quality score 0-100
     */
    public static function qualityScore(string $text, string $html = '', int $imageCount = 0): ?int
    {
        $result = self::post('/quality-score', [
            'text' => $text,
            'html' => $html,
            'image_count' => $imageCount,
        ]);
        return $result['quality_score'] ?? null;
    }

    /**
     * Unified enrichment pipeline — single call replaces 6+ individual calls.
     *
     * Returns combined result with extraction, classification, dedup,
     * embedding, summary, entities, sentiment, and quality score.
     *
     * @param  string $url             Article URL
     * @param  array  $params          Additional parameters:
     *   - source_selectors: string
     *   - title: string (RSS title)
     *   - rss_categories: string[]
     *   - system_categories: string[]
     *   - source_region: string
     *   - existing_titles: string[]
     *   - dedup_threshold: float
     *   - options: array (feature flags)
     * @return array|null  Full enrichment result
     */
    public static function enrich(string $url, array $params = []): ?array
    {
        $payload = array_merge(['url' => $url], $params);
        $response = self::post('/enrich', $payload, max(30, self::timeout()));
        if ($response === null) return null;
        if (empty($response['success'])) return null;
        return $response;
    }

    /**
     * Batch enrichment — send N articles in one HTTP call, get N results back.
     *
     * Each article needs: url, title, rss_categories.
     * Shared params (source_selectors, strip_selectors, etc.) apply to all articles.
     *
     * @param  array $articles  [{url, title, rss_categories}, ...]
     * @param  array $params    Shared params (source_selectors, strip_selectors, system_categories, etc.)
     * @return array|null       [{success, extraction, category, dedup, embedding, ...}, ...] or null on failure
     */
    public static function enrichBatch(array $articles, array $params = []): ?array
    {
        $payload = array_merge(['articles' => $articles], $params);

        // Timeout scales with batch size: 30s base + 5s per article, max 300s
        $timeout = min(300, 30 + count($articles) * 5);

        $response = self::post('/enrich-batch', $payload, $timeout);
        if ($response === null) return null;
        if (empty($response['results'])) return null;

        return $response['results'];
    }

    /**
     * Send a POST request to the extractor service.
     */
    private static function post(string $endpoint, array $payload, ?int $timeout = null): ?array
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
                CURLOPT_TIMEOUT        => $timeout ?? self::timeout(),
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
