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

            foreach ($items as $item) {
                try {
                    // Memory safety: skip if critically low (<10MB free)
                    $memUsed = memory_get_usage(true);
                    $memLimit = self::getMemoryLimitBytes();
                    if ($memLimit > 0 && ($memLimit - $memUsed) < 10 * 1024 * 1024) {
                        error_log("CrawlerEngine: memory critical ({$memUsed}/{$memLimit}), stopping source {$source['name']}");
                        break;
                    }

                    // Skip if too old
                    if ($item['published_at'] && $maxAgeHours > 0) {
                        $ts = strtotime($item['published_at']);
                        if ($ts !== false) {
                            $age = (time() - $ts) / 3600;
                            if ($age > $maxAgeHours) continue;
                        }
                    }

                    // Keyword filtering
                    if (!self::passesKeywordFilter($item, $source)) {
                        continue;
                    }

                    // Region-based relevance filtering (non-Ugandan sources only)
                    $sourceRegion = $source['region'] ?? 'international';
                    if ($sourceRegion !== 'ugandan') {
                        $classifyResult = ExtractorClient::classify(
                            $item['title'],
                            $item['description'] ?? '',
                            $sourceRegion
                        );
                        if ($classifyResult && empty($classifyResult['accept'])) {
                            $details[] = ['title' => $item['title'], 'status' => 'filtered', 'reason' => 'region:' . ($classifyResult['dominated_region'] ?? 'unknown')];
                            continue;
                        }
                    }

                    // Dedup check: hash of source URL
                    $hash = hash('sha256', $item['link']);
                    if (self::isDuplicate($hash, $item['title'])) {
                        $dupes++;
                        $details[] = ['title' => $item['title'], 'status' => 'duplicate'];
                        continue;
                    }

                    // Smart category matching (90% confidence threshold)
                    $catResult = CategoryMatcher::match(
                        $item, $categories,
                        $source['default_category_id'] ?? self::getFirstCategoryId()
                    );
                    $categoryId = $catResult['category_id'];

                    // Story thread auto-detection
                    $threadId = StoryThreadDetector::detect(
                        $item['title'],
                        $item['content'] ?: $item['description'] ?: ''
                    );

                    // Always extract full article via Python extractor (primary)
                    $scrapedData = null;
                    if (!empty($item['link'])) {
                        $scrapedData = ExtractorClient::extract($item['link'], $source);
                        // Fallback: existing PHP scraper
                        if ($scrapedData === null) {
                            $scrapedData = ArticleScraper::scrape($item['link'], $source);
                        }
                    }

                    // Prefer extracted full article; fall back to RSS content
                    $rssContent = $item['content'] ?: $item['description'] ?: '';
                    if ($scrapedData && !empty($scrapedData['content'])) {
                        $rawContent = $scrapedData['content'];
                    } else {
                        $rawContent = $rssContent;
                    }
                    $content = self::cleanContent($rawContent, $source);

                    // Ensure images are hotlinked with lazy loading
                    $content = self::processImages($content);

                    // Normalize HTML structure (headings, orphan text, whitespace)
                    $content = ContentNormalizer::normalize($content);

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

                    // Generate slug
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($item['title'])));
                    $slug = trim($slug, '-');
                    $slug = mb_substr($slug, 0, 200);
                    $baseSlug = $slug;
                    $suffix = 0;
                    $pdo = \App\Models\BaseModel::pdo();
                    while (true) {
                        $check = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = :s");
                        $check->execute([':s' => $slug]);
                        if ((int)$check->fetchColumn() === 0) break;
                        $suffix++;
                        $slug = $baseSlug . '-' . $suffix;
                    }

                    // Insert article
                    $status = $autoPublish ? 'published' : 'draft';
                    $stmt = $pdo->prepare(
                        "INSERT INTO articles
                            (title, slug, content, excerpt, author_id, category_id,
                             featured_image, status, published_at, created_by,
                             display_author, story_thread_id,
                             is_crawled, crawl_source_id, source_url, source_name, source_hash)
                         VALUES
                            (:title, :slug, :content, :excerpt, :author_id, :category_id,
                             :featured_image, :status, :published_at, :created_by,
                             :display_author, :thread_id,
                             TRUE, :source_id, :source_url, :source_name, :source_hash)
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
                        ':source_id'      => $source['id'],
                        ':source_url'     => $item['link'],
                        ':source_name'    => $source['name'],
                        ':source_hash'    => $hash,
                    ]);

                    $newId = $stmt->fetchColumn();
                    $new++;
                    $details[] = ['title' => $item['title'], 'status' => 'published'];

                    // Score for breaking news detection
            if ($newId) {
                    try { BreakingNewsEngine::scoreAndUpdate($newId); } catch (\Throwable $e) {}
                    try { \App\Models\Tag::autoTagFromContent((int)$newId, $content, $item['title']); } catch (\Throwable) {}
        }

                } catch (\Throwable $e) {
                    $errors++;
                    $details[] = ['title' => $item['title'] ?? '?', 'status' => 'error', 'error' => $e->getMessage()];
                }
            }

            $logStatus = $errors > 0 ? ($new > 0 ? 'partial' : 'failed') : 'success';
            CrawlLog::finish($logId, $logStatus, $found, $new, $dupes, null, $details);
            CrawlSource::markCrawled($source['id'], $logStatus !== 'failed');
            CrawlSource::incrementStats($source['id'], $found, $new, $errors);

            return [$found, $new, $dupes];

        } catch (\Throwable $e) {
            CrawlLog::finish($logId, 'failed', 0, 0, 0, $e->getMessage());
            CrawlSource::markCrawled($source['id'], false, $e->getMessage());
            CrawlSource::incrementStats($source['id'], 0, 0, 1);
            throw $e;
        }
    }

    /** Run all due sources. */
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

    private static function cleanContent(string $html, array $source): string
    {
        if (trim($html) === '') return '<p>No content available.</p>';

        // Strip WordPress "The post [title] appeared first on [site]." boilerplate
        $html = preg_replace('#<p[^>]*>\s*The post\s+<a[^>]*>.*?</a>\s+appeared first on\s+<a[^>]*>.*?</a>\.\s*</p>#is', '', $html);
        // Also strip plain text version without links
        $html = preg_replace('#<p[^>]*>\s*The post\s+.{5,300}\s+appeared first on\s+.{3,100}\.\s*</p>#is', '', $html);

        // WordPress "Share this:" / "Like this:" / "Related" sections
        $html = preg_replace('#<h[2-6][^>]*>\s*Share\s+this\s*:?\s*</h[2-6]>.*?(?=<h[2-6]|$)#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Like\s+this\s*:?\s*</h[2-6]>.*?(?=<h[2-6]|$)#is', '', $html);
        $html = preg_replace('#<h[2-6][^>]*>\s*Related\s*:?\s*</h[2-6]>.*$#is', '', $html);
        $html = preg_replace('#<ul[^>]*>\s*(?:<li[^>]*>\s*<a[^>]*>Share\s+on\s+\w+[^<]*</a>\s*</li>\s*){2,}</ul>#is', '', $html);
        $html = preg_replace('#<p[^>]*>\s*Like\s+Loading\s*\.{0,3}\s*</p>#is', '', $html);
        $html = preg_replace('#<p[^>]*>\s*<a[^>]*>\s*See\s+also\s+[^<]+</a>\s*</p>#is', '', $html);
        $html = preg_replace('#<a[^>]*>Share\s+on\s+\w+\s*\([^)]*\)\s*\w*</a>#is', '', $html);

        // If content is plain text (no HTML tags), wrap in paragraph
        if (strip_tags($html) === $html) {
            $html = '<p>' . nl2br(htmlspecialchars($html)) . '</p>';
        }

        // Remove <script>, <style>, <iframe> (except video embeds)
        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);

        // Keep video/social embeds, remove other iframes
        $html = preg_replace_callback('#<iframe[^>]*>.*?</iframe>#is', function ($m) {
            if (preg_match('/youtube\.com|youtu\.be|vimeo\.com|dailymotion\.com|twitter\.com|x\.com|instagram\.com|facebook\.com|fb\.watch|tiktok\.com|rumble\.com|bitchute\.com|odysee\.com|spotify\.com|soundcloud\.com|streamable\.com|jwplatform\.com|brightcove|kaltura|vidyard|wistia/i', $m[0])) {
                return $m[0];
            }
            return '';
        }, $html);

        // Strip CSS selectors specified by source
        if (!empty($source['strip_selectors'])) {
            // Simple class/id stripping (not a full DOM parser but covers common cases)
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

        // Remove empty paragraphs
        $html = preg_replace('#<p>\s*</p>#i', '', $html);

        // Remove inline styles
        $html = preg_replace('/\sstyle="[^"]*"/i', '', $html);

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