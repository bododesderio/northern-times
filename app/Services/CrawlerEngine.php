<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\CrawlLog;
use App\Models\CrawlSource;
use App\Models\Setting;
use App\Services\StoryThreadDetector;
use App\Services\ImageDownloader;
use App\Services\ArticleScraper;
use App\Services\ExtractorClient;
use App\Services\CategoryMatcher;
use App\Services\ContentNormalizer;
use App\Services\Cache;

/**
 * News Aggregator Crawler Engine
 *
 * Fetches RSS/Atom feeds, extracts articles, deduplicates,
 * maps categories, and auto-publishes to the articles table.
 *
 * v2: Unified enrichment pipeline, semantic dedup, story clustering,
 *     NER tagging, adaptive scheduling, parallel crawling.
 */
final class CrawlerEngine
{
    /** Dynamic user-agent — uses site name from admin settings. */
    private static function userAgent(): string
    {
        $name = \get_site_setting('crawler_user_agent_name', \get_site_setting('site_title', 'NewsCrawler'));
        $url  = rtrim(\get_site_setting('site_url', $_ENV['APP_URL'] ?? 'http://localhost'), '/');
        return preg_replace('/[^A-Za-z0-9]/', '', $name) . '/1.0 (+' . $url . ')';
    }
    private const FETCH_TIMEOUT = 8;

    // Semantic dedup thresholds
    private const SEMANTIC_DEDUP_THRESHOLD = 0.82;
    private const STORY_CLUSTER_THRESHOLD  = 0.65;

    /** Run a single crawl for one source. Returns [found, new, dupes]. */
    public static function crawlSource(array $source): array
    {
        $logId = CrawlLog::start($source['id']);

        try {
            $xml = self::fetchFeed($source['feed_url']);
            if (!$xml) {
                throw new \RuntimeException('Failed to fetch or parse feed');
            }

            $items  = self::extractFeedItems($xml, $source['source_type']);
            $found  = count($items);
            $new    = 0;
            $dupes  = 0;
            $errors = 0;
            $details = [];

            // Apply max articles limit
            $items = array_slice($items, 0, (int)($source['max_articles'] ?? 20));

            // Load categories for mapping
            $categories = Category::nameSlugList();
            $catMap     = json_decode($source['category_map'] ?? '{}', true) ?: [];

            // Get global settings
            $autoPublish   = Setting::get('crawler_auto_publish', 'true') === 'true';
            $maxAgeHours   = (int)Setting::get('crawler_max_age_hours', '72');
            $defaultAuthor = Setting::get('crawler_default_author', '');

            // Get the first admin user as fallback author
            $authorId = $defaultAuthor ?: self::getDefaultAuthorId();

            // Build system category slugs + lookup
            $systemSlugs = array_map(fn($c) => $c['slug'], $categories);
            $slugToId = [];
            foreach ($categories as $cat) {
                $slugToId[strtolower($cat['slug'])] = $cat['id'];
            }

            // Pre-fetch existing titles for cross-source dedup
            $existingTitles = self::getRecentTitles();

            // ── Phase 1: Collect eligible items (cheap PHP-side filters) ──
            $sourceRegion = $source['region'] ?? 'international';
            $eligible = []; // items that pass pre-filters, indexed for batch
            $eligibleHashes = []; // parallel array of hashes

            foreach ($items as $item) {
                // Skip if too old
                if ($item['published_at'] && $maxAgeHours > 0) {
                    $ts = strtotime($item['published_at']);
                    if ($ts !== false && (time() - $ts) / 3600 > $maxAgeHours) continue;
                }

                // Keyword filtering
                if (!self::passesKeywordFilter($item, $source)) continue;

                // Fast dedup: hash of source URL + exact title match (PHP-side, no HTTP)
                $hash = hash('sha256', $item['link']);
                if (self::isDuplicate($hash, $item['title'])) {
                    $dupes++;
                    $details[] = ['title' => $item['title'], 'status' => 'duplicate'];
                    continue;
                }

                $eligible[] = $item;
                $eligibleHashes[] = $hash;
            }

            // ── Phase 2: Batch enrichment (1 HTTP call for all eligible) ──
            $enrichResults = [];
            if (!empty($eligible)) {
                $batchArticles = [];
                foreach ($eligible as $item) {
                    $batchArticles[] = [
                        'url'            => $item['link'],
                        'title'          => $item['title'],
                        'rss_categories' => $item['categories'] ?? [],
                    ];
                }

                $batchResponse = ExtractorClient::enrichBatch($batchArticles, [
                    'source_selectors'  => $source['content_selector'] ?? null,
                    'strip_selectors'   => $source['strip_selectors'] ?? null,
                    'system_categories' => $systemSlugs,
                    'source_region'     => $sourceRegion,
                    'existing_titles'   => $existingTitles,
                    'dedup_threshold'   => 0.55,
                    'options'           => [
                        'classify_region'   => ($sourceRegion !== 'ugandan'),
                        'classify_category' => true,
                        'check_dedup'       => true,
                        'embed'             => true,
                        'summarize'         => true,
                        'ner'               => true,
                        'sentiment'         => true,
                        'quality'           => true,
                    ],
                ]);

                if ($batchResponse !== null) {
                    $enrichResults = $batchResponse;
                }
            }

            // ── Phase 3: Process enrichment results + insert articles ──
            foreach ($eligible as $idx => $item) {
                try {
                    // Memory safety
                    $memUsed = memory_get_usage(true);
                    $memLimit = self::getMemoryLimitBytes();
                    if ($memLimit > 0 && ($memLimit - $memUsed) < 10 * 1024 * 1024) {
                        error_log("CrawlerEngine: memory critical, stopping source {$source['name']}");
                        break;
                    }

                    $hash = $eligibleHashes[$idx];

                    // Get enrichment result for this article
                    $enrichResult = $enrichResults[$idx] ?? null;

                    // Fallback: individual calls if batch failed for this article
                    if ($enrichResult === null || empty($enrichResult['success'])) {
                        $enrichResult = self::fallbackEnrich($item, $source, $sourceRegion, $systemSlugs, $existingTitles);
                    }

                    // Region-based filtering (non-Ugandan sources)
                    if ($sourceRegion !== 'ugandan' && !empty($enrichResult['region_classify'])) {
                        if (empty($enrichResult['region_classify']['accept'])) {
                            $details[] = [
                                'title' => $item['title'],
                                'status' => 'filtered',
                                'reason' => 'region:' . ($enrichResult['region_classify']['dominated_region'] ?? 'unknown'),
                            ];
                            continue;
                        }
                    }

                    // Cross-source fuzzy dedup
                    if (!empty($enrichResult['dedup']['is_duplicate'])) {
                        $dupes++;
                        $details[] = ['title' => $item['title'], 'status' => 'similar-story'];
                        continue;
                    }

                    // ── Category determination ───────────────────────
                    $categoryId = $source['default_category_id'] ?? self::getFirstCategoryId();
                    if (!empty($enrichResult['category']['category_slug']) && ($enrichResult['category']['confidence'] ?? 0) >= 40) {
                        $aiSlug = strtolower($enrichResult['category']['category_slug']);
                        if (isset($slugToId[$aiSlug])) {
                            $categoryId = $slugToId[$aiSlug];
                        }
                    } else {
                        // Fallback: keyword-based CategoryMatcher
                        $catResult = CategoryMatcher::match($item, $categories, $categoryId);
                        $categoryId = $catResult['category_id'];
                    }

                    // Story thread auto-detection (existing)
                    $threadId = StoryThreadDetector::detect(
                        $item['title'],
                        $item['content'] ?: $item['description'] ?: ''
                    );

                    // ── Content from extraction ──────────────────────
                    $scrapedData = null;
                    $contentFromPython = false;
                    if (!empty($enrichResult['extraction'])) {
                        $scrapedData = $enrichResult['extraction'];
                        $contentFromPython = true;
                    }

                    // If enrichment didn't extract, try fallback
                    if ($scrapedData === null && !empty($item['link'])) {
                        $scrapedData = ArticleScraper::scrape($item['link'], $source);
                        $contentFromPython = false;
                    }

                    // Prefer extracted full article; fall back to RSS content
                    $rssContent = $item['content'] ?: $item['description'] ?: '';
                    if ($scrapedData && !empty($scrapedData['content'])) {
                        $rawContent = $scrapedData['content'];
                    } else {
                        $rawContent = $rssContent;
                        $contentFromPython = false;
                    }

                    if ($contentFromPython) {
                        // Python content_cleaner.py already handled: ad stripping,
                        // WP boilerplate, image normalization, attribute sanitization,
                        // h1 demotion, iframe handling, URL resolution, empty elements.
                        // Only apply processImages as safety net for lazy loading.
                        $content = self::processImages($rawContent);
                    } else {
                        // PHP fallback path: full cleaning pipeline needed
                        $content = self::cleanContent($rawContent, $source);
                        $content = self::processImages($content);
                        $content = ContentNormalizer::normalize($content);
                    }

                    // Use extractor metadata for better author/date/title
                    $extractorAuthors = $scrapedData['authors'] ?? [];
                    $extractorDate    = $scrapedData['published_date'] ?? null;
                    $extractorTitle   = $scrapedData['title'] ?? null;

                    // Use extractor title if RSS title looks truncated (ends with ...)
                    if ($extractorTitle && preg_match('/\.{3}$|\x{2026}$/u', $item['title'])) {
                        $item['title'] = $extractorTitle;
                    }

                    // Featured image: scraped full-size hero > RSS image
                    $featuredImage = null;
                    $heroOriginalUrl = null;
                    if ($scrapedData && !empty($scrapedData['hero_image'])) {
                        $featuredImage = $scrapedData['hero_image'];
                        $heroOriginalUrl = $scrapedData['hero_image'];
                    } elseif (!empty($item['image'])) {
                        $featuredImage = $item['image'];
                        $heroOriginalUrl = $item['image'];
                    }

                    // Strip the hero image from content BEFORE downloading (URLs still original)
                    if ($heroOriginalUrl) {
                        $content = self::removeHeroFromContent($content, $heroOriginalUrl);
                    }

                    // Download images locally if enabled for this source
                    $downloadImages = ($source['download_images'] ?? true);
                    if ($downloadImages) {
                        $content = ImageDownloader::processContentImages($content, $authorId);
                    }

                    // Download featured image locally if enabled
                    if ($downloadImages && $featuredImage) {
                        $featuredImage = ImageDownloader::download($featuredImage, $authorId);
                    }

                    // Add attribution — tiny ⓘ icon, tooltip only on hover
                    $attribution = $source['attribution_text'] ?: ('Source: ' . $source['name']);
                    $nofollow    = $source['nofollow'] ? ' rel="nofollow noopener"' : ' rel="noopener"';
                    $attrHtml    = '<p class="crawled-attribution" style="text-align:right;margin-top:16px">'
                        . '<a href="' . htmlspecialchars($item['link']) . '" target="_blank"' . $nofollow
                        . ' title="' . htmlspecialchars($attribution) . '"'
                        . ' style="color:#999;text-decoration:none;opacity:0.5;transition:opacity .2s"'
                        . ' onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">'
                        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
                        . '</a></p>';

                    $content .= "\n" . $attrHtml;

                    // Build excerpt: scraped > RSS description
                    $excerpt = '';
                    if ($scrapedData && !empty($scrapedData['excerpt'])) {
                        $excerpt = $scrapedData['excerpt'];
                    } elseif (!empty($item['description'])) {
                        $excerpt = $item['description'];
                    }
                    $excerpt = strip_tags($excerpt);
                    $excerpt = mb_substr($excerpt, 0, 280);

                    // Generate slug (uses INSERT ON CONFLICT to avoid TOCTOU race)
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($item['title'])));
                    $slug = trim($slug, '-');
                    $slug = mb_substr($slug, 0, 200);

                    // ── Semantic dedup via embedding ─────────────────
                    $pdo = \App\Models\BaseModel::pdo();
                    $embedding = $enrichResult['embedding'] ?? null;
                    $storyClusterId = null;

                    if ($embedding) {
                        $semanticMatch = self::findSemanticMatch($pdo, $embedding);
                        if ($semanticMatch) {
                            if ($semanticMatch['similarity'] >= self::SEMANTIC_DEDUP_THRESHOLD) {
                                $dupes++;
                                $details[] = [
                                    'title' => $item['title'],
                                    'status' => 'semantic-duplicate',
                                    'matched' => $semanticMatch['title'],
                                    'similarity' => $semanticMatch['similarity'],
                                ];
                                continue;
                            }
                            // Story clustering: similar but not duplicate
                            if ($semanticMatch['similarity'] >= self::STORY_CLUSTER_THRESHOLD) {
                                $storyClusterId = self::getOrCreateStoryCluster(
                                    $pdo,
                                    $semanticMatch['id'],
                                    $semanticMatch['story_cluster_id'],
                                    $item['title']
                                );
                            }
                        }
                    }

                    // AI-generated summary
                    $aiSummary = $enrichResult['summary'] ?? null;

                    // Sentiment
                    $sentiment      = $enrichResult['sentiment'] ?? null;
                    $sentimentScore = $enrichResult['sentiment_score'] ?? null;

                    // Quality score
                    $qualityScore = $enrichResult['quality_score'] ?? null;

                    // ── Insert article (ON CONFLICT avoids slug race) ──
                    $slug = self::resolveUniqueSlug($pdo, $slug);
                    $status = $autoPublish ? 'published' : 'draft';
                    $stmt = $pdo->prepare(
                        "INSERT INTO articles
                            (title, slug, content, excerpt, author_id, category_id,
                             featured_image, status, published_at, created_by,
                             display_author, story_thread_id, story_cluster_id,
                             ai_summary, sentiment, sentiment_score, quality_score,
                             is_crawled, crawl_source_id, source_url, source_name, source_hash)
                         VALUES
                            (:title, :slug, :content, :excerpt, :author_id, :category_id,
                             :featured_image, :status, :published_at, :created_by,
                             :display_author, :thread_id, :cluster_id,
                             :ai_summary, :sentiment, :sentiment_score, :quality_score,
                             TRUE, :source_id, :source_url, :source_name, :source_hash)
                         ON CONFLICT (slug) DO NOTHING
                         RETURNING id"
                    );
                    $stmt->execute([
                        ':title'          => mb_substr($item['title'], 0, 255),
                        ':slug'           => $slug,
                        ':content'        => $content,
                        ':excerpt'        => $excerpt,
                        ':author_id'      => $authorId,
                        ':category_id'    => $categoryId,
                        ':featured_image' => $featuredImage,
                        ':status'         => $status,
                        ':published_at'   => $status === 'published'
                            ? ($extractorDate ? date('Y-m-d H:i:s', strtotime($extractorDate)) ?: gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s'))
                            : null,
                        ':created_by'     => $authorId,
                        ':display_author' => self::cleanAuthorName(
                            $extractorAuthors,
                            function_exists('get_site_setting')
                                ? get_site_setting('default_crawl_author', get_site_setting('site_title', 'Newsroom'))
                                : 'Newsroom'
                        ),
                        ':thread_id'      => $threadId,
                        ':cluster_id'     => $storyClusterId,
                        ':ai_summary'     => $aiSummary,
                        ':sentiment'      => $sentiment,
                        ':sentiment_score' => $sentimentScore,
                        ':quality_score'  => $qualityScore,
                        ':source_id'      => $source['id'],
                        ':source_url'     => $item['link'],
                        ':source_name'    => $source['name'],
                        ':source_hash'    => $hash,
                    ]);

                    $newId = $stmt->fetchColumn();
                    if (!$newId) {
                        $dupes++;
                        $details[] = ['title' => $item['title'], 'status' => 'slug-conflict'];
                        continue;
                    }
                    $new++;
                    $details[] = ['title' => $item['title'], 'status' => 'published'];

                    // ── Post-insert enrichment storage ───────────────
                    if ($newId) {
                        // Store embedding
                        if ($embedding) {
                            self::storeEmbedding($pdo, $newId, $embedding);
                        }

                        // Store NER entities
                        $entities = $enrichResult['entities'] ?? null;
                        if ($entities) {
                            self::storeEntities($pdo, $newId, $entities);
                            // NER-powered auto-tagging
                            self::autoTagFromEntities($newId, $entities);
                        } else {
                            // Fallback to keyword-based tagging
                            try { \App\Models\Tag::autoTagFromContent((int)$newId, $content, $item['title']); } catch (\Throwable) {}
                        }

                        // Score for breaking news detection
                        try { BreakingNewsEngine::scoreAndUpdate($newId); } catch (\Throwable $e) {}
                    }

                    // Add new title to existing titles for subsequent dedup in this batch
                    $existingTitles[] = $item['title'];

                } catch (\Throwable $e) {
                    $errors++;
                    $details[] = ['title' => $item['title'] ?? '?', 'status' => 'error', 'error' => $e->getMessage()];
                }
            }

            $logStatus = $errors > 0 ? ($new > 0 ? 'partial' : 'failed') : 'success';
            CrawlLog::finish($logId, $logStatus, $found, $new, $dupes, null, $details);
            CrawlSource::markCrawled($source['id'], $logStatus !== 'failed');
            CrawlSource::incrementStats($source['id'], $found, $new, $errors);

            // Update adaptive scheduling
            self::updateAdaptiveSchedule($source, $new);

            return [$found, $new, $dupes];

        } catch (\Throwable $e) {
            CrawlLog::finish($logId, 'failed', 0, 0, 0, $e->getMessage());
            CrawlSource::markCrawled($source['id'], false, $e->getMessage());
            CrawlSource::incrementStats($source['id'], 0, 0, 1);
            throw $e;
        }
    }

    /** Run all due sources sequentially. */
    public static function crawlAll(): array
    {
        if (Setting::get('crawler_enabled', 'false') !== 'true') {
            return ['skipped' => true, 'reason' => 'Crawler disabled'];
        }

        $sources = CrawlSource::dueForCrawl();
        $results = [];

        foreach ($sources as $source) {
            try {
                [$found, $new, $dupes] = self::crawlSource($source);
                $results[] = [
                    'source' => $source['name'],
                    'found'  => $found,
                    'new'    => $new,
                    'dupes'  => $dupes,
                    'status' => 'ok',
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'source' => $source['name'],
                    'status' => 'error',
                    'error'  => $e->getMessage(),
                ];
            }
        }

        // Flush frontend caches if any new articles were added
        $totalNew = array_sum(array_column($results, 'new'));
        if ($totalNew > 0) {
            Cache::flush('home:*');
            Cache::flush('cat:*');
        }

        return $results;
    }

    /**
     * Run all due sources with parallel feed fetching.
     * Article processing remains sequential per source.
     */
    public static function crawlAllParallel(int $batchSize = 6): array
    {
        if (Setting::get('crawler_enabled', 'false') !== 'true') {
            return ['skipped' => true, 'reason' => 'Crawler disabled'];
        }

        $sources = CrawlSource::dueForCrawl();
        if (empty($sources)) return [];

        $results = [];

        // Batch feed fetching with curl_multi
        $batches = array_chunk($sources, $batchSize);
        foreach ($batches as $batch) {
            // Fetch all feeds in parallel
            $feedData = self::fetchFeedsParallel($batch);

            // Process each source sequentially (article inserts must be sequential)
            foreach ($batch as $i => $source) {
                try {
                    $xml = $feedData[$i] ?? null;
                    if ($xml === null) {
                        throw new \RuntimeException('Failed to fetch or parse feed');
                    }

                    // Temporarily replace fetchFeed by injecting pre-fetched XML
                    [$found, $new, $dupes] = self::crawlSourceWithXml($source, $xml);
                    $results[] = [
                        'source' => $source['name'],
                        'found'  => $found,
                        'new'    => $new,
                        'dupes'  => $dupes,
                        'status' => 'ok',
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'source' => $source['name'],
                        'status' => 'error',
                        'error'  => $e->getMessage(),
                    ];
                }
            }
        }

        $totalNew = array_sum(array_column($results, 'new'));
        if ($totalNew > 0) {
            Cache::flush('home:*');
            Cache::flush('cat:*');
        }

        return $results;
    }

    // ── Fallback enrichment (individual calls) ───────────────

    /**
     * When unified /enrich endpoint is unavailable, fall back to individual calls.
     */
    private static function fallbackEnrich(
        array $item,
        array $source,
        string $sourceRegion,
        array $systemSlugs,
        array $existingTitles
    ): array {
        $result = [
            'success'         => false,
            'extraction'      => null,
            'region_classify' => null,
            'category'        => null,
            'dedup'           => null,
            'embedding'       => null,
            'summary'         => null,
            'entities'        => null,
            'sentiment'       => null,
            'sentiment_score' => null,
            'quality_score'   => null,
        ];

        // Region classification
        if ($sourceRegion !== 'ugandan') {
            $result['region_classify'] = ExtractorClient::classify(
                $item['title'],
                $item['description'] ?? '',
                $sourceRegion
            );
        }

        // Cross-source dedup
        if (!empty($existingTitles)) {
            $result['dedup'] = ExtractorClient::checkDuplicate($item['title'], $existingTitles);
        }

        // Extract article
        if (!empty($item['link'])) {
            $scrapedData = ExtractorClient::extract($item['link'], $source);
            if ($scrapedData === null) {
                $scrapedData = ArticleScraper::scrape($item['link'], $source);
            }
            if ($scrapedData) {
                $result['extraction'] = $scrapedData;
                $result['success'] = true;
            }
        }

        // Category classification
        $result['category'] = ExtractorClient::classifyCategory(
            $item['title'] ?? '',
            $item['content'] ?: ($item['description'] ?? ''),
            $item['categories'] ?? [],
            $item['link'] ?? '',
            $systemSlugs
        );

        return $result;
    }

    /**
     * Resolve a unique slug by appending a suffix if the base slug already exists.
     * Uses a single query to find the next available suffix atomically.
     */
    private static function resolveUniqueSlug(\PDO $pdo, string $baseSlug): string
    {
        $stmt = $pdo->prepare(
            "SELECT slug FROM articles WHERE slug = :slug OR slug LIKE :pattern ORDER BY slug DESC LIMIT 1"
        );
        $stmt->execute([':slug' => $baseSlug, ':pattern' => $baseSlug . '-%']);
        $existing = $stmt->fetchColumn();

        if (!$existing) return $baseSlug;

        // Extract the highest suffix number
        if ($existing === $baseSlug) {
            return $baseSlug . '-1';
        }
        $suffix = (int)substr($existing, strlen($baseSlug) + 1);
        return $baseSlug . '-' . ($suffix + 1);
    }

    // ── Semantic dedup & story clustering ─────────────────────

    /**
     * Find the most similar article by embedding cosine similarity.
     */
    private static function findSemanticMatch(\PDO $pdo, array $embedding): ?array
    {
        try {
            $embStr = '[' . implode(',', $embedding) . ']';
            $stmt = $pdo->prepare(
                "SELECT id, title, story_cluster_id,
                        1 - (embedding <=> :emb::vector) AS similarity
                 FROM articles
                 WHERE created_at > NOW() - INTERVAL '3 days'
                   AND embedding IS NOT NULL
                 ORDER BY embedding <=> :emb::vector
                 LIMIT 1"
            );
            $stmt->execute([':emb' => $embStr]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && (float)$row['similarity'] >= self::STORY_CLUSTER_THRESHOLD) {
                return $row;
            }
        } catch (\Throwable $e) {
            error_log("CrawlerEngine::findSemanticMatch: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Store embedding vector for an article.
     */
    private static function storeEmbedding(\PDO $pdo, string $articleId, array $embedding): void
    {
        try {
            $embStr = '[' . implode(',', $embedding) . ']';
            $stmt = $pdo->prepare("UPDATE articles SET embedding = :emb::vector WHERE id = :id");
            $stmt->execute([':emb' => $embStr, ':id' => $articleId]);
        } catch (\Throwable $e) {
            error_log("CrawlerEngine::storeEmbedding: " . $e->getMessage());
        }
    }

    /**
     * Get or create a story cluster for related articles.
     */
    private static function getOrCreateStoryCluster(
        \PDO $pdo,
        string $matchedArticleId,
        ?string $existingClusterId,
        string $title
    ): string {
        // If matched article already has a cluster, join it
        if ($existingClusterId) {
            return $existingClusterId;
        }

        // Create new cluster with matched article as canonical
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO story_clusters (canonical_article_id, title) VALUES (:aid, :title) RETURNING id"
            );
            $stmt->execute([':aid' => $matchedArticleId, ':title' => mb_substr($title, 0, 255)]);
            $clusterId = $stmt->fetchColumn();

            // Update the matched article to belong to this cluster
            $pdo->prepare("UPDATE articles SET story_cluster_id = :cid WHERE id = :aid")
                ->execute([':cid' => $clusterId, ':aid' => $matchedArticleId]);

            return $clusterId;
        } catch (\Throwable $e) {
            error_log("CrawlerEngine::getOrCreateStoryCluster: " . $e->getMessage());
            return '';
        }
    }

    // ── NER entity storage & auto-tagging ────────────────────

    /**
     * Bulk-insert NER entities for an article.
     */
    private static function storeEntities(\PDO $pdo, string $articleId, array $entities): void
    {
        if (empty($entities)) return;
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO article_entities (article_id, entity_text, entity_type, salience)
                 VALUES (:aid, :text, :type, :salience)"
            );
            foreach ($entities as $entity) {
                $stmt->execute([
                    ':aid'      => $articleId,
                    ':text'     => mb_substr($entity['text'] ?? '', 0, 255),
                    ':type'     => $entity['type'] ?? 'UNKNOWN',
                    ':salience' => $entity['salience'] ?? 0,
                ]);
            }
        } catch (\Throwable $e) {
            error_log("CrawlerEngine::storeEntities: " . $e->getMessage());
        }
    }

    /**
     * Auto-tag article from NER entities instead of keyword frequency.
     * Maps entity types: PERSON→person, ORG→org, GPE→location, EVENT→topic
     */
    private static function autoTagFromEntities(string $articleId, array $entities): void
    {
        try {
            $typeMap = [
                'PERSON' => 'person',
                'ORG'    => 'org',
                'GPE'    => 'location',
                'EVENT'  => 'topic',
            ];

            $tagNames = [];
            foreach ($entities as $entity) {
                if (($entity['salience'] ?? 0) < 0.05) continue;
                if (count($tagNames) >= 8) break;
                $name = trim($entity['text'] ?? '');
                if (strlen($name) < 2 || strlen($name) > 80) continue;
                $tagNames[] = $name;
            }

            if (!empty($tagNames)) {
                \App\Models\Tag::syncForArticle($articleId, $tagNames);
            }
        } catch (\Throwable $e) {
            error_log("CrawlerEngine::autoTagFromEntities: " . $e->getMessage());
        }
    }

    // ── Adaptive scheduling ──────────────────────────────────

    /**
     * Update adaptive scheduling stats after a crawl completes.
     */
    private static function updateAdaptiveSchedule(array $source, int $newCount): void
    {
        try {
            $pdo = \App\Models\BaseModel::pdo();
            if ($newCount > 0) {
                // Calculate articles-per-day rate from this crawl
                $interval = max(1, (int)($source['crawl_interval'] ?? 30));
                $rate = $newCount * (1440.0 / $interval);

                $pdo->prepare(
                    "UPDATE crawl_sources SET
                        consecutive_empty = 0,
                        last_new_content_at = NOW(),
                        avg_articles_per_day = COALESCE(avg_articles_per_day * 0.7 + :rate * 0.3, :rate)
                     WHERE id = :id"
                )->execute([':rate' => $rate, ':id' => $source['id']]);
            } else {
                $pdo->prepare(
                    "UPDATE crawl_sources SET consecutive_empty = COALESCE(consecutive_empty, 0) + 1 WHERE id = :id"
                )->execute([':id' => $source['id']]);
            }
        } catch (\Throwable $e) {
            error_log("CrawlerEngine::updateAdaptiveSchedule: " . $e->getMessage());
        }
    }

    /**
     * Calculate the effective crawl interval for a source based on activity.
     *
     * @return int Interval in minutes
     */
    public static function effectiveInterval(array $source): int
    {
        $avg   = (float)($source['avg_articles_per_day'] ?? 0);
        $empty = (int)($source['consecutive_empty'] ?? 0);

        // Base interval from activity level
        if ($avg > 5) {
            $interval = 5;     // High frequency
        } elseif ($avg > 2) {
            $interval = 15;    // Moderate
        } elseif ($avg > 0.5) {
            $interval = 30;    // Slow
        } else {
            $interval = 60;    // Dormant
        }

        // Backoff for consecutive empty crawls
        if ($empty >= 5) {
            $interval = min(120, $interval * 2);
        }

        return $interval;
    }

    // ── Parallel feed fetching ───────────────────────────────

    /**
     * Fetch multiple RSS feeds in parallel using curl_multi.
     *
     * @return array<int, \SimpleXMLElement|null> Indexed same as input $sources
     */
    private static function fetchFeedsParallel(array $sources): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

        foreach ($sources as $i => $source) {
            $ch = curl_init($source['feed_url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::FETCH_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml, */*'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }

        // Execute all requests
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 1);
            }
        } while ($running > 0);

        // Collect results
        $results = [];
        foreach ($handles as $i => $ch) {
            $body = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($body === false || $body === null || $body === '') {
                $results[$i] = null;
                continue;
            }

            // Strip BOM and invalid XML chars
            $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
            $body = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $body);

            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($body);
            libxml_clear_errors();

            $results[$i] = $xml ?: null;
        }

        curl_multi_close($mh);
        return $results;
    }

    /**
     * Crawl a source with pre-fetched XML (for parallel mode).
     * Reuses crawlSource logic but skips feed fetching.
     */
    private static function crawlSourceWithXml(array $source, \SimpleXMLElement $xml): array
    {
        $logId = CrawlLog::start($source['id']);

        try {
            $items  = self::extractFeedItems($xml, $source['source_type']);
            $found  = count($items);
            $new    = 0;
            $dupes  = 0;
            $errors = 0;
            $details = [];

            $items = array_slice($items, 0, (int)($source['max_articles'] ?? 20));

            $categories = Category::nameSlugList();
            $autoPublish   = Setting::get('crawler_auto_publish', 'true') === 'true';
            $maxAgeHours   = (int)Setting::get('crawler_max_age_hours', '72');
            $defaultAuthor = Setting::get('crawler_default_author', '');
            $authorId = $defaultAuthor ?: self::getDefaultAuthorId();

            $systemSlugs = array_map(fn($c) => $c['slug'], $categories);
            $slugToId = [];
            foreach ($categories as $cat) {
                $slugToId[strtolower($cat['slug'])] = $cat['id'];
            }

            $existingTitles = self::getRecentTitles();
            $sourceRegion = $source['region'] ?? 'international';

            // ── Phase 1: Collect eligible items (cheap PHP pre-filters) ──
            $eligible = [];
            $eligibleHashes = [];

            foreach ($items as $item) {
                if ($item['published_at'] && $maxAgeHours > 0) {
                    $ts = strtotime($item['published_at']);
                    if ($ts !== false && (time() - $ts) / 3600 > $maxAgeHours) continue;
                }

                if (!self::passesKeywordFilter($item, $source)) continue;

                $hash = hash('sha256', $item['link']);
                if (self::isDuplicate($hash, $item['title'])) {
                    $dupes++;
                    $details[] = ['title' => $item['title'], 'status' => 'duplicate'];
                    continue;
                }

                $eligible[] = $item;
                $eligibleHashes[] = $hash;
            }

            // ── Phase 2: Batch enrichment (1 HTTP call for all eligible) ──
            $enrichResults = [];
            if (!empty($eligible)) {
                $batchArticles = [];
                foreach ($eligible as $item) {
                    $batchArticles[] = [
                        'url'            => $item['link'],
                        'title'          => $item['title'],
                        'rss_categories' => $item['categories'] ?? [],
                    ];
                }

                $batchResponse = ExtractorClient::enrichBatch($batchArticles, [
                    'source_selectors'  => $source['content_selector'] ?? null,
                    'strip_selectors'   => $source['strip_selectors'] ?? null,
                    'system_categories' => $systemSlugs,
                    'source_region'     => $sourceRegion,
                    'existing_titles'   => $existingTitles,
                    'dedup_threshold'   => 0.55,
                    'options'           => [
                        'classify_region'   => ($sourceRegion !== 'ugandan'),
                        'classify_category' => true,
                        'check_dedup'       => true,
                        'embed'             => true,
                        'summarize'         => true,
                        'ner'               => true,
                        'sentiment'         => true,
                        'quality'           => true,
                    ],
                ]);

                if ($batchResponse !== null) {
                    $enrichResults = $batchResponse;
                }
            }

            // ── Phase 3: Process enrichment results + insert articles ──
            foreach ($eligible as $idx => $item) {
                try {
                    $memUsed = memory_get_usage(true);
                    $memLimit = self::getMemoryLimitBytes();
                    if ($memLimit > 0 && ($memLimit - $memUsed) < 10 * 1024 * 1024) {
                        error_log("CrawlerEngine: memory critical, stopping source {$source['name']}");
                        break;
                    }

                    $hash = $eligibleHashes[$idx];

                    $enrichResult = $enrichResults[$idx] ?? null;

                    if ($enrichResult === null || empty($enrichResult['success'])) {
                        $enrichResult = self::fallbackEnrich($item, $source, $sourceRegion, $systemSlugs, $existingTitles);
                    }

                    if ($sourceRegion !== 'ugandan' && !empty($enrichResult['region_classify'])) {
                        if (empty($enrichResult['region_classify']['accept'])) {
                            $details[] = [
                                'title' => $item['title'],
                                'status' => 'filtered',
                                'reason' => 'region:' . ($enrichResult['region_classify']['dominated_region'] ?? 'unknown'),
                            ];
                            continue;
                        }
                    }

                    if (!empty($enrichResult['dedup']['is_duplicate'])) {
                        $dupes++;
                        $details[] = ['title' => $item['title'], 'status' => 'similar-story'];
                        continue;
                    }

                    $categoryId = $source['default_category_id'] ?? self::getFirstCategoryId();
                    if (!empty($enrichResult['category']['category_slug']) && ($enrichResult['category']['confidence'] ?? 0) >= 40) {
                        $aiSlug = strtolower($enrichResult['category']['category_slug']);
                        if (isset($slugToId[$aiSlug])) {
                            $categoryId = $slugToId[$aiSlug];
                        }
                    } else {
                        $catResult = CategoryMatcher::match($item, $categories, $categoryId);
                        $categoryId = $catResult['category_id'];
                    }

                    $threadId = StoryThreadDetector::detect(
                        $item['title'],
                        $item['content'] ?: $item['description'] ?: ''
                    );

                    $scrapedData = null;
                    $contentFromPython = false;
                    if (!empty($enrichResult['extraction'])) {
                        $scrapedData = $enrichResult['extraction'];
                        $contentFromPython = true;
                    }

                    if ($scrapedData === null && !empty($item['link'])) {
                        $scrapedData = ArticleScraper::scrape($item['link'], $source);
                    }

                    $rssContent = $item['content'] ?: $item['description'] ?: '';
                    if ($scrapedData && !empty($scrapedData['content'])) {
                        $rawContent = $scrapedData['content'];
                    } else {
                        $rawContent = $rssContent;
                        $contentFromPython = false;
                    }

                    if ($contentFromPython) {
                        $content = self::processImages($rawContent);
                    } else {
                        $content = self::cleanContent($rawContent, $source);
                        $content = self::processImages($content);
                        $content = ContentNormalizer::normalize($content);
                    }

                    $extractorAuthors = $scrapedData['authors'] ?? [];
                    $extractorDate    = $scrapedData['published_date'] ?? null;
                    $extractorTitle   = $scrapedData['title'] ?? null;

                    if ($extractorTitle && preg_match('/\.{3}$|\x{2026}$/u', $item['title'])) {
                        $item['title'] = $extractorTitle;
                    }

                    $featuredImage = null;
                    $heroOriginalUrl = null;
                    if ($scrapedData && !empty($scrapedData['hero_image'])) {
                        $featuredImage = $scrapedData['hero_image'];
                        $heroOriginalUrl = $scrapedData['hero_image'];
                    } elseif (!empty($item['image'])) {
                        $featuredImage = $item['image'];
                        $heroOriginalUrl = $item['image'];
                    }

                    if ($heroOriginalUrl) {
                        $content = self::removeHeroFromContent($content, $heroOriginalUrl);
                    }

                    $downloadImages = ($source['download_images'] ?? true);
                    if ($downloadImages) {
                        $content = ImageDownloader::processContentImages($content, $authorId);
                    }
                    if ($downloadImages && $featuredImage) {
                        $featuredImage = ImageDownloader::download($featuredImage, $authorId);
                    }

                    $attribution = $source['attribution_text'] ?: ('Source: ' . $source['name']);
                    $nofollow    = $source['nofollow'] ? ' rel="nofollow noopener"' : ' rel="noopener"';
                    $attrHtml    = '<p class="crawled-attribution" style="text-align:right;margin-top:16px">'
                        . '<a href="' . htmlspecialchars($item['link']) . '" target="_blank"' . $nofollow
                        . ' title="' . htmlspecialchars($attribution) . '"'
                        . ' style="color:#999;text-decoration:none;opacity:0.5;transition:opacity .2s"'
                        . ' onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">'
                        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
                        . '</a></p>';
                    $content .= "\n" . $attrHtml;

                    $excerpt = '';
                    if ($scrapedData && !empty($scrapedData['excerpt'])) {
                        $excerpt = $scrapedData['excerpt'];
                    } elseif (!empty($item['description'])) {
                        $excerpt = $item['description'];
                    }
                    $excerpt = strip_tags($excerpt);
                    $excerpt = mb_substr($excerpt, 0, 280);

                    // Quality gate: reject articles with insufficient content
                    $plainText = strip_tags($content);
                    if (mb_strlen($plainText) < 200) {
                        $details[] = ['title' => $item['title'], 'status' => 'too-short'];
                        continue;
                    }

                    // Generate slug (uses INSERT ON CONFLICT to avoid TOCTOU race)
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($item['title'])));
                    $slug = trim($slug, '-');
                    $slug = mb_substr($slug, 0, 200);

                    $pdo = \App\Models\BaseModel::pdo();
                    $embedding = $enrichResult['embedding'] ?? null;
                    $storyClusterId = null;

                    if ($embedding) {
                        $semanticMatch = self::findSemanticMatch($pdo, $embedding);
                        if ($semanticMatch) {
                            if ($semanticMatch['similarity'] >= self::SEMANTIC_DEDUP_THRESHOLD) {
                                $dupes++;
                                $details[] = [
                                    'title' => $item['title'],
                                    'status' => 'semantic-duplicate',
                                    'matched' => $semanticMatch['title'],
                                    'similarity' => $semanticMatch['similarity'],
                                ];
                                continue;
                            }
                            if ($semanticMatch['similarity'] >= self::STORY_CLUSTER_THRESHOLD) {
                                $storyClusterId = self::getOrCreateStoryCluster(
                                    $pdo,
                                    $semanticMatch['id'],
                                    $semanticMatch['story_cluster_id'],
                                    $item['title']
                                );
                            }
                        }
                    }

                    // Resolve unique slug atomically
                    $slug = self::resolveUniqueSlug($pdo, $slug);
                    $status = $autoPublish ? 'published' : 'draft';
                    $stmt = $pdo->prepare(
                        "INSERT INTO articles
                            (title, slug, content, excerpt, author_id, category_id,
                             featured_image, status, published_at, created_by,
                             display_author, story_thread_id, story_cluster_id,
                             ai_summary, sentiment, sentiment_score, quality_score,
                             is_crawled, crawl_source_id, source_url, source_name, source_hash)
                         VALUES
                            (:title, :slug, :content, :excerpt, :author_id, :category_id,
                             :featured_image, :status, :published_at, :created_by,
                             :display_author, :thread_id, :cluster_id,
                             :ai_summary, :sentiment, :sentiment_score, :quality_score,
                             TRUE, :source_id, :source_url, :source_name, :source_hash)
                         ON CONFLICT (slug) DO NOTHING
                         RETURNING id"
                    );
                    $stmt->execute([
                        ':title'          => mb_substr($item['title'], 0, 255),
                        ':slug'           => $slug,
                        ':content'        => $content,
                        ':excerpt'        => $excerpt,
                        ':author_id'      => $authorId,
                        ':category_id'    => $categoryId,
                        ':featured_image' => $featuredImage,
                        ':status'         => $status,
                        ':published_at'   => $status === 'published'
                            ? ($extractorDate ? date('Y-m-d H:i:s', strtotime($extractorDate)) ?: gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s'))
                            : null,
                        ':created_by'     => $authorId,
                        ':display_author' => self::cleanAuthorName(
                            $extractorAuthors,
                            function_exists('get_site_setting')
                                ? get_site_setting('default_crawl_author', get_site_setting('site_title', 'Newsroom'))
                                : 'Newsroom'
                        ),
                        ':thread_id'      => $threadId,
                        ':cluster_id'     => $storyClusterId,
                        ':ai_summary'     => $enrichResult['summary'] ?? null,
                        ':sentiment'      => $enrichResult['sentiment'] ?? null,
                        ':sentiment_score' => $enrichResult['sentiment_score'] ?? null,
                        ':quality_score'  => $enrichResult['quality_score'] ?? null,
                        ':source_id'      => $source['id'],
                        ':source_url'     => $item['link'],
                        ':source_name'    => $source['name'],
                        ':source_hash'    => $hash,
                    ]);

                    $newId = $stmt->fetchColumn();
                    if (!$newId) {
                        $dupes++;
                        $details[] = ['title' => $item['title'], 'status' => 'slug-conflict'];
                        continue;
                    }
                    $new++;
                    $details[] = ['title' => $item['title'], 'status' => 'published'];

                    if ($newId) {
                        if ($embedding) self::storeEmbedding($pdo, $newId, $embedding);
                        $entities = $enrichResult['entities'] ?? null;
                        if ($entities) {
                            self::storeEntities($pdo, $newId, $entities);
                            self::autoTagFromEntities($newId, $entities);
                        } else {
                            try { \App\Models\Tag::autoTagFromContent((int)$newId, $content, $item['title']); } catch (\Throwable) {}
                        }
                        try { BreakingNewsEngine::scoreAndUpdate($newId); } catch (\Throwable) {}
                        try {
                            \App\Services\WebhookDispatcher::dispatch('article.published', [
                                'id' => $newId,
                                'title' => $item['title'] ?? '',
                                'slug' => $slug,
                                'source' => 'crawler',
                                'url' => ($_ENV['APP_URL'] ?? '') . '/article/' . $slug,
                            ]);
                        } catch (\Throwable) {}
                    }

                    $existingTitles[] = $item['title'];

                } catch (\Throwable $e) {
                    $errors++;
                    $details[] = ['title' => $item['title'] ?? '?', 'status' => 'error', 'error' => $e->getMessage()];
                }
            }

            $logStatus = $errors > 0 ? ($new > 0 ? 'partial' : 'failed') : 'success';
            CrawlLog::finish($logId, $logStatus, $found, $new, $dupes, null, $details);
            CrawlSource::markCrawled($source['id'], $logStatus !== 'failed');
            CrawlSource::incrementStats($source['id'], $found, $new, $errors);
            self::updateAdaptiveSchedule($source, $new);

            return [$found, $new, $dupes];

        } catch (\Throwable $e) {
            CrawlLog::finish($logId, 'failed', 0, 0, 0, $e->getMessage());
            CrawlSource::markCrawled($source['id'], false, $e->getMessage());
            CrawlSource::incrementStats($source['id'], 0, 0, 1);
            throw $e;
        }
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Fetch recent article titles for cross-source dedup.
     */
    private static function getRecentTitles(): array
    {
        try {
            $pdo = \App\Models\BaseModel::pdo();
            $stmt = $pdo->query(
                "SELECT title FROM articles
                 WHERE created_at > NOW() - INTERVAL '3 days' AND is_crawled = TRUE
                 ORDER BY created_at DESC LIMIT 200"
            );
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Feed fetching & parsing ────────────────────────────────

    private static function fetchFeed(string $url): ?\SimpleXMLElement
    {
        // Use browser-like UA to avoid 403s from sites that block bots
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => "User-Agent: {$ua}\r\nAccept: application/rss+xml, application/xml, text/xml, */*",
                'timeout'         => self::FETCH_TIMEOUT,
                'follow_location' => 1,
                'max_redirects'   => 5,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return null;

        // Strip BOM and invalid XML chars
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $body = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $body);

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);
        libxml_clear_errors();

        return $xml ?: null;
    }

    private static function extractFeedItems(\SimpleXMLElement $xml, string $type): array
    {
        $items = [];

        // RSS 2.0
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = self::parseRSSItem($item);
            }
            return $items;
        }

        // Atom
        if (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $items[] = self::parseAtomEntry($entry);
            }
            return $items;
        }

        // RSS 1.0 / RDF
        $ns = $xml->getNamespaces(true);
        if (isset($ns['']) && isset($xml->item)) {
            foreach ($xml->item as $item) {
                $items[] = self::parseRSSItem($item);
            }
        }

        return $items;
    }

    private static function parseRSSItem(\SimpleXMLElement $item): array
    {
        $ns = $item->getNamespaces(true);
        $content = '';

        // Try content:encoded first
        if (isset($ns['content'])) {
            $contentNs = $item->children($ns['content']);
            if (isset($contentNs->encoded)) {
                $content = (string)$contentNs->encoded;
            }
        }

        // Try media:content for image
        $image = null;
        if (isset($ns['media'])) {
            $media = $item->children($ns['media']);
            if (isset($media->content)) {
                $attrs = $media->content->attributes();
                if ($attrs && isset($attrs['url'])) {
                    $image = (string)$attrs['url'];
                }
            }
            if (!$image && isset($media->thumbnail)) {
                $attrs = $media->thumbnail->attributes();
                if ($attrs && isset($attrs['url'])) {
                    $image = (string)$attrs['url'];
                }
            }
        }

        // Enclosure image fallback
        if (!$image && isset($item->enclosure)) {
            $enc = $item->enclosure->attributes();
            if ($enc && str_starts_with((string)($enc['type'] ?? ''), 'image/')) {
                $image = (string)$enc['url'];
            }
        }

        // Categories
        $cats = [];
        foreach ($item->category as $cat) {
            $cats[] = trim((string)$cat);
        }

        return [
            'title'        => trim((string)($item->title ?? 'Untitled')),
            'link'         => trim((string)($item->link ?? '')),
            'description'  => trim((string)($item->description ?? '')),
            'content'      => $content,
            'published_at' => (string)($item->pubDate ?? ''),
            'image'        => $image,
            'categories'   => $cats,
            'author'       => trim((string)($item->author ?? '')),
        ];
    }

    private static function parseAtomEntry(\SimpleXMLElement $entry): array
    {
        $link = '';
        foreach ($entry->link as $l) {
            $rel = (string)($l->attributes()['rel'] ?? 'alternate');
            if ($rel === 'alternate') {
                $link = (string)$l->attributes()['href'];
                break;
            }
        }
        if (!$link && isset($entry->link[0])) {
            $link = (string)$entry->link[0]->attributes()['href'];
        }

        $content = (string)($entry->content ?? '');

        $cats = [];
        foreach ($entry->category as $cat) {
            $cats[] = trim((string)($cat->attributes()['term'] ?? ''));
        }

        return [
            'title'        => trim((string)($entry->title ?? 'Untitled')),
            'link'         => $link,
            'description'  => trim((string)($entry->summary ?? '')),
            'content'      => $content,
            'published_at' => (string)($entry->published ?? $entry->updated ?? ''),
            'image'        => null,
            'categories'   => $cats,
            'author'       => trim((string)($entry->author->name ?? '')),
        ];
    }

    // ── Content processing ─────────────────────────────────────

    /**
     * Light PHP content cleaning — safety net for when Python extractor is unavailable.
     *
     * Primary cleaning now happens in Python content_cleaner.py (DOM-based, more thorough).
     * This method only handles: script/style removal, plain text wrapping, empty paragraphs,
     * and source-specific strip_selectors as a fallback.
     */
    private static function cleanContent(string $html, array $source): string
    {
        if (trim($html) === '') return '<p>No content available.</p>';

        // Remove <script>, <style>, <noscript> tags
        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);
        $html = preg_replace('#<noscript[^>]*>.*?</noscript>#is', '', $html);

        // Remove non-article structural elements (nav, header, footer, sidebar, forms)
        $html = preg_replace('#<nav[^>]*>.*?</nav>#is', '', $html);
        $html = preg_replace('#<header[^>]*>.*?</header>#is', '', $html);
        $html = preg_replace('#<footer[^>]*>.*?</footer>#is', '', $html);
        $html = preg_replace('#<aside[^>]*>.*?</aside>#is', '', $html);
        $html = preg_replace('#<form[^>]*>.*?</form>#is', '', $html);
        $html = preg_replace('#<iframe[^>]*>.*?</iframe>#is', '', $html);

        // Strip all class, style, onclick, data-* attributes (keep src, href, alt, title)
        $html = preg_replace('#\s+(class|style|onclick|onload|onerror|data-[a-z\-]+)="[^"]*"#i', '', $html);
        $html = preg_replace("#\s+(class|style|onclick|onload|onerror|data-[a-z\-]+)='[^']*'#i", '', $html);

        // If content is plain text (no HTML tags), wrap in paragraph
        if (strip_tags($html) === $html) {
            $html = '<p>' . nl2br(htmlspecialchars($html)) . '</p>';
        }

        // Strip CSS selectors specified by source (fallback — Python handles this too)
        if (!empty($source['strip_selectors'])) {
            $selectors = array_map('trim', explode(',', $source['strip_selectors']));
            foreach ($selectors as $sel) {
                if (str_starts_with($sel, '.')) {
                    $class = substr($sel, 1);
                    $html = preg_replace('#<[^>]+class="[^"]*\b' . preg_quote($class, '#') . '\b[^"]*"[^>]*>.*?</[^>]+>#is', '', $html);
                } elseif (str_starts_with($sel, '#')) {
                    $id = substr($sel, 1);
                    $html = preg_replace('#<[^>]+id="' . preg_quote($id, '#') . '"[^>]*>.*?</[^>]+>#is', '', $html);
                }
            }
        }

        // Remove empty paragraphs, divs, spans
        $html = preg_replace('#<p>\s*</p>#i', '', $html);
        $html = preg_replace('#<div>\s*</div>#i', '', $html);
        $html = preg_replace('#<span>\s*</span>#i', '', $html);

        // Reject junk: if content > 100KB after cleaning, it's a full-page scrape
        if (strlen($html) > 102400) {
            // Try to extract just <p> tags as a last resort
            preg_match_all('#<p[^>]*>(.+?)</p>#is', $html, $pMatches);
            if (!empty($pMatches[0])) {
                $html = implode("\n", $pMatches[0]);
            } else {
                $html = '<p>' . mb_substr(strip_tags($html), 0, 2000) . '</p>';
            }
        }

        return trim($html);
    }

    private static function processImages(string $html): string
    {
        // Add lazy loading and referrerpolicy to all images
        return preg_replace_callback('#<img([^>]*)>#i', function ($m) {
            $attrs = $m[1];
            // Add loading="lazy" if not present
            if (!str_contains($attrs, 'loading=')) {
                $attrs .= ' loading="lazy"';
            }
            // Add referrerpolicy
            if (!str_contains($attrs, 'referrerpolicy=')) {
                $attrs .= ' referrerpolicy="no-referrer"';
            }
            return '<img' . $attrs . '>';
        }, $html);
    }

    /** Remove the hero/featured image from content to prevent duplication. */
    private static function removeHeroFromContent(string $html, string $heroUrl): string
    {
        // Normalize URL for matching: strip protocol, query, fragment
        $heroPath = preg_replace('#^https?://#', '', strtok($heroUrl, '?#'));

        return preg_replace_callback('#<(?:figure|div)[^>]*>.*?</(?:figure|div)>|<img[^>]+>#is', function ($m) use ($heroPath) {
            // Extract src from the matched block
            if (preg_match('/src=["\']([^"\']+)["\']/', $m[0], $srcMatch)) {
                $srcPath = preg_replace('#^https?://#', '', strtok($srcMatch[1], '?#'));
                // Match if the image paths are the same (ignoring size suffixes like -300x200)
                $heroBase = preg_replace('#-\d+x\d+(?=\.\w+$)#', '', $heroPath);
                $srcBase  = preg_replace('#-\d+x\d+(?=\.\w+$)#', '', $srcPath);
                if ($heroBase === $srcBase || $heroPath === $srcPath) {
                    return '';
                }
            }
            return $m[0];
        }, $html);
    }

    // ── Dedup ──────────────────────────────────────────────────

    private static function isDuplicate(string $hash, string $title): bool
    {
        // Check by URL hash
        $existing = Article::queryOne(
            "SELECT id FROM articles WHERE source_hash = :hash LIMIT 1",
            [':hash' => $hash]
        );
        if ($existing) return true;

        // Check by exact title match (within last 7 days)
        $existing = Article::queryOne(
            "SELECT id FROM articles
             WHERE title = :title AND created_at > NOW() - INTERVAL '7 days'
             LIMIT 1",
            [':title' => $title]
        );

        return (bool)$existing;
    }

    // ── Keyword filtering ──────────────────────────────────────

    private static function passesKeywordFilter(array $item, array $source): bool
    {
        $text = strtolower($item['title'] . ' ' . ($item['description'] ?? ''));

        // Must include at least one include keyword (if set)
        if (!empty($source['keyword_include'])) {
            $includes = array_map('trim', explode(',', strtolower($source['keyword_include'])));
            $found = false;
            foreach ($includes as $kw) {
                if ($kw !== '' && str_contains($text, $kw)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) return false;
        }

        // Must not contain any exclude keyword
        if (!empty($source['keyword_exclude'])) {
            $excludes = array_map('trim', explode(',', strtolower($source['keyword_exclude'])));
            foreach ($excludes as $kw) {
                if ($kw !== '' && str_contains($text, $kw)) {
                    return false;
                }
            }
        }

        return true;
    }

    // ── Helpers ─────────────────────────────────────────────────

    private static function getDefaultAuthorId(): string
    {
        $admin = \App\Models\User::queryOne(
            "SELECT id FROM users WHERE is_active = TRUE ORDER BY created_at ASC LIMIT 1"
        );
        return $admin['id'] ?? '';
    }

    private static function getFirstCategoryId(): string
    {
        $cat = Category::queryOne("SELECT id FROM categories ORDER BY name ASC LIMIT 1");
        return $cat['id'] ?? '';
    }

    /** Clean author names: remove URLs, junk, keep only real names. */
    private static function cleanAuthorName(array $authors, string $fallback): string
    {
        if (empty($authors)) return $fallback;

        $clean = [];
        foreach ($authors as $name) {
            $name = trim($name);
            // Skip URLs, emails, very short names
            if (preg_match('#^https?://#i', $name)) continue;
            if (str_contains($name, '@')) continue;
            if (strlen($name) < 2) continue;
            // Remove "Https" or trailing URLs from names like "John Doe, Https"
            $name = preg_replace('/,?\s*Https?\b.*/i', '', $name);
            $name = preg_replace('/,?\s*Reporter Whose Work.*/i', '', $name);
            $name = trim($name, " ,\t\n\r");
            if (strlen($name) >= 2) {
                $clean[] = $name;
            }
        }

        if (empty($clean)) return $fallback;
        return implode(', ', array_slice(array_unique($clean), 0, 3));
    }

    /** Parse PHP memory_limit into bytes. Returns -1 if unlimited. */
    private static function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return -1;
        $val = (int)$limit;
        $unit = strtolower(substr(trim($limit), -1));
        return match ($unit) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }
}
