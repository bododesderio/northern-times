<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\BaseModel;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Models\Setting;

/**
 * SEO Audit Engine
 *
 * Crawls published articles and runs 26 checks covering:
 * meta data, content quality, images, links, and structure.
 * Produces a 0–100 health score with critical/warning/passed breakdown.
 */
final class SeoAuditEngine
{
    /** All check definitions: name, severity, description. */
    private const CHECKS = [
        // Meta checks
        ['name' => 'missing_meta_title',        'severity' => 'critical', 'label' => 'Missing page title'],
        ['name' => 'long_meta_title',           'severity' => 'warning',  'label' => 'Title exceeds 60 chars'],
        ['name' => 'short_meta_title',          'severity' => 'warning',  'label' => 'Title under 30 chars'],
        ['name' => 'missing_meta_description',  'severity' => 'critical', 'label' => 'Missing excerpt/meta description'],
        ['name' => 'long_meta_description',     'severity' => 'warning',  'label' => 'Excerpt exceeds 160 chars'],
        ['name' => 'duplicate_title',           'severity' => 'warning',  'label' => 'Duplicate article title'],
        ['name' => 'duplicate_slug',            'severity' => 'critical', 'label' => 'Duplicate slug detected'],
        // Content checks
        ['name' => 'thin_content',              'severity' => 'warning',  'label' => 'Article under 200 words'],
        ['name' => 'very_thin_content',         'severity' => 'critical', 'label' => 'Article under 50 words'],
        ['name' => 'no_headings',               'severity' => 'info',     'label' => 'No headings in content'],
        ['name' => 'no_internal_links',         'severity' => 'info',     'label' => 'No internal links in content'],
        ['name' => 'broken_html',               'severity' => 'warning',  'label' => 'Broken/unclosed HTML tags'],
        // Image checks
        ['name' => 'missing_featured_image',    'severity' => 'warning',  'label' => 'No featured image'],
        ['name' => 'missing_alt_text',          'severity' => 'warning',  'label' => 'Images missing alt text'],
        ['name' => 'large_image_count',         'severity' => 'info',     'label' => 'Article has 10+ images'],
        // URL/Link checks
        ['name' => 'long_slug',                 'severity' => 'info',     'label' => 'Slug exceeds 80 chars'],
        ['name' => 'uppercase_slug',            'severity' => 'warning',  'label' => 'Slug contains uppercase'],
        ['name' => 'special_chars_slug',        'severity' => 'warning',  'label' => 'Slug contains special characters'],
        // Category/Structure checks
        ['name' => 'uncategorized',             'severity' => 'warning',  'label' => 'Article has no category'],
        ['name' => 'missing_author',            'severity' => 'info',     'label' => 'No display author set'],
        ['name' => 'no_publish_date',           'severity' => 'warning',  'label' => 'Published without date'],
        ['name' => 'future_publish_date',       'severity' => 'info',     'label' => 'Publish date is in the future'],
        // Crawled article checks
        ['name' => 'crawled_no_source_url',     'severity' => 'warning',  'label' => 'Crawled article missing source URL'],
        ['name' => 'crawled_empty_content',     'severity' => 'critical', 'label' => 'Crawled article with empty body'],
        // Global checks
        ['name' => 'orphan_category',           'severity' => 'info',     'label' => 'Category with zero articles'],
        ['name' => 'no_articles_7d',            'severity' => 'info',     'label' => 'No articles published in 7 days'],
    ];

    /**
     * Run a full SEO audit. Returns the audit ID.
     */
    public static function run(): int
    {
        $auditId = SeoAudit::start();
        $issues  = [];

        try {
            $articles = BaseModel::query(
                "SELECT a.id, a.title, a.slug, a.content, a.excerpt,
                        a.featured_image, a.category_id, a.status,
                        a.published_at, a.is_crawled, a.source_url,
                        COALESCE(NULLIF(a.display_author,''), 'unset') AS display_author
                 FROM articles a
                 WHERE a.status = 'published'
                 ORDER BY a.published_at DESC NULLS LAST
                 LIMIT 2000"
            );

            $pagesScanned = count($articles);
            $titleIndex   = [];
            $slugIndex    = [];

            foreach ($articles as $a) {
                // ── Meta checks ──────────────────────────────────────
                if (empty(trim($a['title']))) {
                    $issues[] = self::issue('missing_meta_title', 'critical', $a, 'Article has no title.', 'Add a descriptive title.');
                } elseif (mb_strlen($a['title']) > 60) {
                    $issues[] = self::issue('long_meta_title', 'warning', $a, "Title is " . mb_strlen($a['title']) . " chars (recommended ≤60).", 'Shorten to under 60 characters for optimal SEO display.');
                } elseif (mb_strlen($a['title']) < 30) {
                    $issues[] = self::issue('short_meta_title', 'warning', $a, "Title is only " . mb_strlen($a['title']) . " chars.", 'Aim for 30–60 characters.');
                }

                if (empty(trim($a['excerpt'] ?? ''))) {
                    $issues[] = self::issue('missing_meta_description', 'critical', $a, 'No excerpt/meta description.', 'Add a 120–160 character excerpt.');
                } elseif (mb_strlen($a['excerpt']) > 160) {
                    $issues[] = self::issue('long_meta_description', 'warning', $a, "Excerpt is " . mb_strlen($a['excerpt']) . " chars.", 'Trim to 160 characters.');
                }

                // Duplicate tracking
                $titleLower = strtolower(trim($a['title']));
                if (isset($titleIndex[$titleLower])) {
                    $issues[] = self::issue('duplicate_title', 'warning', $a, "Same title as another article.", 'Make titles unique for SEO.');
                }
                $titleIndex[$titleLower] = true;

                if (isset($slugIndex[$a['slug']])) {
                    $issues[] = self::issue('duplicate_slug', 'critical', $a, "Duplicate slug: {$a['slug']}.", 'Slugs must be unique.');
                }
                $slugIndex[$a['slug']] = true;

                // ── Content checks ───────────────────────────────────
                $plainText = strip_tags($a['content'] ?? '');
                $wordCount = str_word_count($plainText);

                if ($wordCount < 50) {
                    $issues[] = self::issue('very_thin_content', 'critical', $a, "Only {$wordCount} words.", 'Articles should have at least 200 words.');
                } elseif ($wordCount < 200) {
                    $issues[] = self::issue('thin_content', 'warning', $a, "Only {$wordCount} words.", 'Aim for 300+ words for better SEO.');
                }

                if (!preg_match('/<h[1-6]/i', $a['content'] ?? '')) {
                    $issues[] = self::issue('no_headings', 'info', $a, 'No heading tags in content.', 'Use H2/H3 headings to structure content.');
                }

                // Check for internal links
                if (!preg_match('/href=["\']\/((?!\/)|(?:admin|article|category))/i', $a['content'] ?? '')) {
                    $issues[] = self::issue('no_internal_links', 'info', $a, 'No internal links found.', 'Link to related articles for better SEO.');
                }

                // Basic broken HTML check (unclosed tags)
                $tags = [];
                preg_match_all('/<(\/?)(div|p|span|table|tr|td|ul|ol|li|blockquote|h[1-6])[^>]*>/i', $a['content'] ?? '', $tags);
                $openCount = 0;
                foreach ($tags[1] as $slash) {
                    $openCount += ($slash === '/') ? -1 : 1;
                }
                if (abs($openCount) > 3) {
                    $issues[] = self::issue('broken_html', 'warning', $a, abs($openCount) . ' unclosed/extra HTML tags.', 'Fix HTML structure.');
                }

                // ── Image checks ─────────────────────────────────────
                if (empty($a['featured_image'])) {
                    $issues[] = self::issue('missing_featured_image', 'warning', $a, 'No featured image set.', 'Add a featured image for social sharing.');
                }

                preg_match_all('/<img[^>]*>/i', $a['content'] ?? '', $imgTags);
                $missingAlt = 0;
                foreach ($imgTags[0] as $img) {
                    if (!preg_match('/alt=["\']/i', $img) || preg_match('/alt=["\'][\s]*["\']/i', $img)) {
                        $missingAlt++;
                    }
                }
                if ($missingAlt > 0) {
                    $issues[] = self::issue('missing_alt_text', 'warning', $a, "{$missingAlt} image(s) missing alt text.", 'Add descriptive alt text for accessibility.');
                }
                if (count($imgTags[0]) > 10) {
                    $issues[] = self::issue('large_image_count', 'info', $a, count($imgTags[0]) . ' images in article.', 'Consider lazy loading or pagination.');
                }

                // ── URL checks ───────────────────────────────────────
                if (mb_strlen($a['slug']) > 80) {
                    $issues[] = self::issue('long_slug', 'info', $a, "Slug is " . mb_strlen($a['slug']) . " chars.", 'Shorter slugs are easier to share.');
                }
                if ($a['slug'] !== strtolower($a['slug'])) {
                    $issues[] = self::issue('uppercase_slug', 'warning', $a, 'Slug contains uppercase characters.', 'Use lowercase slugs consistently.');
                }
                if (preg_match('/[^a-z0-9\-]/', $a['slug'])) {
                    $issues[] = self::issue('special_chars_slug', 'warning', $a, 'Slug has special characters.', 'Use only letters, numbers, and hyphens.');
                }

                // ── Structure checks ─────────────────────────────────
                if (empty($a['category_id'])) {
                    $issues[] = self::issue('uncategorized', 'warning', $a, 'No category assigned.', 'Assign a category for navigation.');
                }
                if ($a['display_author'] === 'unset') {
                    $issues[] = self::issue('missing_author', 'info', $a, 'No display author set.', 'Set an author byline.');
                }
                if (empty($a['published_at'])) {
                    $issues[] = self::issue('no_publish_date', 'warning', $a, 'Published without a date.', 'Set a publish date.');
                } elseif (strtotime($a['published_at']) > time()) {
                    $issues[] = self::issue('future_publish_date', 'info', $a, 'Publish date is in the future.', 'Verify this is intentional (scheduled post).');
                }

                // ── Crawled article checks ───────────────────────────
                if ($a['is_crawled'] ?? false) {
                    if (empty($a['source_url'])) {
                        $issues[] = self::issue('crawled_no_source_url', 'warning', $a, 'Crawled article has no source URL.', 'Source URL needed for attribution.');
                    }
                    if ($wordCount < 10) {
                        $issues[] = self::issue('crawled_empty_content', 'critical', $a, 'Crawled article body is essentially empty.', 'Check crawler extraction for this source.');
                    }
                }
            }

            // ── Global checks ────────────────────────────────────
            $orphanCats = BaseModel::query(
                "SELECT c.name FROM categories c
                 LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
                 GROUP BY c.id, c.name HAVING COUNT(a.id) = 0"
            );
            foreach ($orphanCats as $cat) {
                $issues[] = [
                    'severity'    => 'info',
                    'check_name'  => 'orphan_category',
                    'page_url'    => null,
                    'article_id'  => null,
                    'description' => "Category \"{$cat['name']}\" has no published articles.",
                    'suggestion'  => 'Add articles or remove unused categories.',
                ];
            }

            $recentCount = (int)BaseModel::queryColumn(
                "SELECT COUNT(*) FROM articles WHERE status = 'published' AND published_at > NOW() - INTERVAL '7 days'"
            );
            if ($recentCount === 0) {
                $issues[] = [
                    'severity'    => 'info',
                    'check_name'  => 'no_articles_7d',
                    'page_url'    => null,
                    'article_id'  => null,
                    'description' => 'No articles published in the last 7 days.',
                    'suggestion'  => 'Regular publishing improves SEO rankings.',
                ];
            }

            // ── Calculate score ──────────────────────────────────
            $critical = count(array_filter($issues, fn($i) => $i['severity'] === 'critical'));
            $warnings = count(array_filter($issues, fn($i) => $i['severity'] === 'warning'));
            $infos    = count(array_filter($issues, fn($i) => $i['severity'] === 'info'));

            $totalChecks = $pagesScanned * 18 + 2; // 18 per-article checks + 2 global
            $passed      = $totalChecks - count($issues);
            $score       = self::calculateScore($totalChecks, $critical, $warnings, $infos);

            // Save issues
            SeoIssue::bulkInsert($auditId, $issues);

            // Build per-check summary for details
            $checkSummary = [];
            foreach (self::CHECKS as $check) {
                $count = count(array_filter($issues, fn($i) => $i['check_name'] === $check['name']));
                $checkSummary[$check['name']] = [
                    'label'    => $check['label'],
                    'severity' => $check['severity'],
                    'count'    => $count,
                    'status'   => $count === 0 ? 'passed' : ($check['severity'] === 'critical' ? 'critical' : 'warning'),
                ];
            }

            SeoAudit::finish($auditId, $pagesScanned, count($issues), $score, $critical, $warnings, max(0, $passed), $checkSummary);
            Setting::set('seo_last_run', date('Y-m-d H:i:s'), 'seo');

            return $auditId;

        } catch (\Throwable $e) {
            SeoAudit::finish($auditId, 0, 0, 0, 0, 0, 0, null, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate a 0–100 health score.
     * Critical = -5 points each, Warning = -2, Info = -0.5
     * Score can't go below 0.
     */
    private static function calculateScore(int $totalChecks, int $critical, int $warnings, int $infos): int
    {
        if ($totalChecks === 0) return 100;

        $penalty = ($critical * 5) + ($warnings * 2) + ($infos * 0.5);
        $maxPenalty = $totalChecks * 3; // rough normalization
        $score = 100 - (int)(($penalty / max($maxPenalty, 1)) * 100);

        return max(0, min(100, $score));
    }

    /**
     * Build an issue array for a specific article.
     */
    private static function issue(string $checkName, string $severity, array $article, string $desc, string $suggestion): array
    {
        return [
            'severity'    => $severity,
            'check_name'  => $checkName,
            'page_url'    => '/' . ($article['slug'] ?? ''),
            'article_id'  => $article['id'] ?? null,
            'description' => $desc,
            'suggestion'  => $suggestion,
        ];
    }
}