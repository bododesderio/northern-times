<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;

/**
 * BreakingNewsEngine — 4-tier automatic breaking news detection.
 *
 * Tier 1: Manual admin override (100 pts, expires after breaking_until)
 * Tier 2: Velocity detection — article gaining views faster than baseline (0-30 pts)
 * Tier 3: Keyword signals — urgency words in title/content (0-25 pts)
 * Tier 4: Multi-source detection — multiple sources covering same story (0-20 pts)
 * Bonus:  Recency boost — newer articles score higher (0-25 pts)
 *
 * Threshold: 60 = breaking.  Max possible auto score: 100.
 * Scores decay to 0 over 6 hours (BREAKING_DECAY_HOURS).
 */
final class BreakingNewsEngine
{
    /** Minimum score to qualify as breaking */
    private const THRESHOLD = 40;

    /** Hours after which breaking status fully decays */
    private const DECAY_HOURS = 6;

    /** Only consider articles published within this window */
    private const LOOKBACK_HOURS = 12;

    // ── Tier 3: Keyword signals ────────────────────────────────

    /** Keywords and their urgency weight (1-10) */
    private const URGENCY_KEYWORDS = [
        // Highest urgency (10)
        'breaking'        => 10,
        'just in'         => 10,
        'urgent'          => 10,
        'developing'      => 9,
        'breaking news'   => 10,

        // Death/violence (9)
        'killed'          => 9,
        'dead'            => 9,
        'confirmed dead'  => 10,
        'shooting'        => 9,
        'explosion'       => 9,
        'attack'          => 8,
        'massacre'        => 10,
        'assassinated'    => 10,

        // Natural disasters (9)
        'earthquake'      => 9,
        'tsunami'         => 10,
        'flood'           => 8,
        'hurricane'       => 9,
        'cyclone'         => 9,
        'eruption'        => 9,
        'wildfire'        => 8,
        'tornado'         => 9,
        'landslide'       => 8,

        // Political/crisis (8)
        'coup'            => 10,
        'martial law'     => 10,
        'state of emergency' => 10,
        'emergency'       => 7,
        'arrested'        => 7,
        'detained'        => 7,
        'impeached'       => 9,
        'resigned'        => 8,
        'overthrown'      => 10,
        'sanctions'       => 7,
        'ceasefire'       => 8,
        'war'             => 8,
        'invasion'        => 9,
        'airstrike'       => 9,

        // Major events (7)
        'crashes'         => 8,
        'crash'           => 7,
        'collapsed'       => 8,
        'evacuated'       => 8,
        'hostage'         => 9,
        'missing'         => 6,
        'kidnapped'       => 8,
        'abducted'        => 8,
        'recall'          => 6,
        'outbreak'        => 8,
        'pandemic'        => 8,
        'epidemic'        => 8,

        // Moderate urgency (5-6)
        'alert'           => 6,
        'warning'         => 5,
        'suspects'        => 5,
        'investigation'   => 4,
        'announces'       => 4,
        'declares'        => 5,
        'confirms'        => 5,
        'unprecedented'   => 5,
        'historic'        => 4,
        'exclusive'       => 5,
        'first time'      => 4,
    ];

    // ═══════════════════════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════════════════════

    /**
     * Recalculate breaking scores for all recent articles.
     * Called every 5 minutes by cron.
     */
    public static function recalculateAll(): array
    {
        $pdo = BaseModel::pdo();
        $stats = ['scored' => 0, 'breaking' => 0, 'expired' => 0, 'snapshots' => 0];

        // 1. Expire manual overrides past their breaking_until
        $stats['expired'] = self::expireManualOverrides($pdo);

        // 2. Take view snapshots for velocity detection
        $stats['snapshots'] = self::snapshotViews($pdo);

        // 3. Clean old snapshots (>48h)
        self::cleanOldSnapshots($pdo);

        // 4. Get all candidate articles (published in last LOOKBACK_HOURS, or manually breaking)
        $candidates = $pdo->prepare("
            SELECT a.id, a.title, a.excerpt, a.views, a.published_at,
                   a.is_breaking, a.is_breaking_manual, a.breaking_until,
                   a.breaking_score AS old_score,
                   a.story_thread_id, a.is_crawled, a.source_name
            FROM articles a
            WHERE a.status = 'published'
              AND (
                a.published_at >= NOW() - INTERVAL '" . self::LOOKBACK_HOURS . " hours'
                OR a.is_breaking_manual = TRUE
                OR a.breaking_score >= " . self::THRESHOLD . "
              )
        ");
        $candidates->execute();
        $articles = $candidates->fetchAll(\PDO::FETCH_ASSOC);

        // 5. Get velocity baselines
        $baselines = self::getVelocityBaselines($pdo);

        // 6. Get multi-source counts (articles sharing a story thread)
        $threadCounts = self::getThreadCounts($pdo);

        // 7. Get similar-title clusters for non-threaded articles
        $titleClusters = self::buildTitleClusters($articles);

        // 8. Score each article
        $updateStmt = $pdo->prepare("
            UPDATE articles
            SET breaking_score = :score,
                is_breaking = :is_breaking
            WHERE id = :id
        ");

        foreach ($articles as $article) {
            $score = self::scoreArticle($article, $baselines, $threadCounts, $titleClusters, $pdo);
            $isBreaking = $score >= self::THRESHOLD;

            $updateStmt->execute([
                ':score'       => $score,
                ':is_breaking' => $isBreaking ? 'true' : 'false',
                ':id'          => $article['id'],
            ]);

            $stats['scored']++;
            if ($isBreaking) $stats['breaking']++;
        }

        // 9. Zero out scores for articles no longer in window
        $pdo->exec("
            UPDATE articles SET breaking_score = 0, is_breaking = FALSE
            WHERE breaking_score > 0
              AND is_breaking_manual = FALSE
              AND published_at < NOW() - INTERVAL '" . self::LOOKBACK_HOURS . " hours'
        ");

        // 10. Update velocity baselines (self-calibrating)
        self::updateVelocityBaselines($pdo);

        return $stats;
    }

    /**
     * Score a single article immediately (e.g., on publish or admin toggle).
     */
    public static function scoreAndUpdate(string $articleId): int
    {
        $pdo = BaseModel::pdo();

        $stmt = $pdo->prepare("
            SELECT a.id, a.title, a.excerpt, a.views, a.published_at,
                   a.is_breaking, a.is_breaking_manual, a.breaking_until,
                   a.breaking_score AS old_score,
                   a.story_thread_id, a.is_crawled, a.source_name
            FROM articles a
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $articleId]);
        $article = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$article) return 0;

        $baselines = self::getVelocityBaselines($pdo);
        $threadCounts = self::getThreadCounts($pdo);

        // Fetch ALL recent articles for cluster comparison (not just the one being scored)
        $recentStmt = $pdo->prepare("
            SELECT a.id, a.title, a.source_id
            FROM articles a
            WHERE a.status = 'published'
              AND a.published_at >= NOW() - INTERVAL '24 hours'
              AND a.deleted_at IS NULL
        ");
        $recentStmt->execute();
        $recentArticles = $recentStmt->fetchAll(\PDO::FETCH_ASSOC);
        $titleClusters = self::buildTitleClusters($recentArticles ?: [$article]);

        $score = self::scoreArticle($article, $baselines, $threadCounts, $titleClusters, $pdo);
        $isBreaking = $score >= self::THRESHOLD;

        $pdo->prepare("
            UPDATE articles SET breaking_score = :score, is_breaking = :brk WHERE id = :id
        ")->execute([':score' => $score, ':brk' => $isBreaking ? 'true' : 'false', ':id' => $articleId]);

        return $score;
    }

    /**
     * Admin: toggle manual breaking status.
     */
    public static function setManualBreaking(string $articleId, bool $enable, ?int $hours = null): void
    {
        $pdo = BaseModel::pdo();
        $hours = $hours ?? self::DECAY_HOURS;

        if ($enable) {
            $pdo->prepare("
                UPDATE articles
                SET is_breaking_manual = TRUE,
                    is_breaking = TRUE,
                    breaking_score = 100,
                    breaking_until = NOW() + INTERVAL '" . (int)$hours . " hours',
                    breaking_headline = COALESCE(NULLIF(breaking_headline, ''), title)
                WHERE id = :id
            ")->execute([':id' => $articleId]);
        } else {
            $pdo->prepare("
                UPDATE articles
                SET is_breaking_manual = FALSE,
                    breaking_until = NULL
                WHERE id = :id
            ")->execute([':id' => $articleId]);

            // Recalculate natural score
            self::scoreAndUpdate($articleId);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  SCORING ENGINE
    // ═══════════════════════════════════════════════════════════

    /**
     * Calculate breaking score for a single article (0-100).
     */
    private static function scoreArticle(
        array $article,
        array $baselines,
        array $threadCounts,
        array $titleClusters,
        \PDO $pdo
    ): int {
        // ── Tier 1: Manual override ────────────────────────────
        if (!empty($article['is_breaking_manual'])) {
            $until = $article['breaking_until'] ?? null;
            if ($until && strtotime($until) > time()) {
                return 100; // Manual override active
            }
            // Manual expired — fall through to auto scoring
        }

        $publishedAt = $article['published_at'] ?? null;
        if (!$publishedAt) return 0;

        $ageHours = (time() - strtotime($publishedAt)) / 3600;
        if ($ageHours > self::LOOKBACK_HOURS) return 0;

        // Decay multiplier: 1.0 at 0h → 0.0 at DECAY_HOURS
        $decay = max(0, 1 - ($ageHours / self::DECAY_HOURS));

        $score = 0;

        // ── Tier 2: Velocity detection (0-30) ─────────────────
        $velocityScore = self::calcVelocityScore($article, $baselines, $pdo);
        $score += $velocityScore;

        // ── Tier 3: Keyword signals (0-25) ────────────────────
        $keywordScore = self::calcKeywordScore($article);
        $score += $keywordScore;

        // ── Tier 4: Multi-source detection (0-20) ─────────────
        $multiSourceScore = self::calcMultiSourceScore($article, $threadCounts, $titleClusters);
        $score += $multiSourceScore;

        // ── Recency boost (0-25) ──────────────────────────────
        if ($ageHours < 1)       $score += 25;
        elseif ($ageHours < 2)   $score += 22;
        elseif ($ageHours < 3)   $score += 18;
        elseif ($ageHours < 4)   $score += 12;
        elseif ($ageHours < 6)   $score += 6;

        // ── Source/category boost (0-15) ─────────────────────
        // Ugandan sources get a boost for national relevance
        if (!empty($article['is_crawled'])) {
            $sourceRegion = self::getSourceRegion($article['id'], $pdo);
            if ($sourceRegion === 'ugandan') {
                $score += 10;
            } elseif ($sourceRegion === 'east_african') {
                $score += 5;
            }
        }
        // Uganda keywords in title get extra boost
        $titleLower = strtolower($article['title'] ?? '');
        if (preg_match('/\b(uganda|kampala|museveni|parliament|gulu|lira|acholi|northern\s+uganda)\b/i', $titleLower)) {
            $score += 5;
        }

        // Apply decay to non-manual scores
        $score = (int)round($score * $decay);

        return min(100, max(0, $score));
    }

    /**
     * Tier 2: Is this article gaining views faster than normal?
     */
    private static function calcVelocityScore(array $article, array $baselines, \PDO $pdo): int
    {
        $articleId = $article['id'];
        $currentViews = (int)($article['views'] ?? 0);

        // Get view count 1 hour ago
        $snap = $pdo->prepare("
            SELECT views FROM article_view_snapshots
            WHERE article_id = :id AND snapshot_at <= NOW() - INTERVAL '50 minutes'
            ORDER BY snapshot_at DESC LIMIT 1
        ");
        $snap->execute([':id' => $articleId]);
        $oldSnap = $snap->fetch(\PDO::FETCH_ASSOC);

        if (!$oldSnap) return 0; // No history yet

        $viewsGained = $currentViews - (int)$oldSnap['views'];
        if ($viewsGained <= 0) return 0;

        // Compare to baseline for this hour
        $hour = (int)date('G');
        $baseline1h = $baselines[$hour]['avg_views_1h'] ?? 10;
        $baseline1h = max($baseline1h, 5); // Floor at 5

        $ratio = $viewsGained / $baseline1h;

        // Score based on how many multiples above baseline
        if ($ratio >= 10)  return 30;  // 10x normal = max
        if ($ratio >= 7)   return 27;
        if ($ratio >= 5)   return 24;
        if ($ratio >= 4)   return 20;
        if ($ratio >= 3)   return 16;
        if ($ratio >= 2)   return 12;
        if ($ratio >= 1.5) return 8;
        if ($ratio >= 1)   return 4;

        return 0;
    }

    /**
     * Tier 3: Does the title contain urgency keywords?
     */
    private static function calcKeywordScore(array $article): int
    {
        $title = strtolower($article['title'] ?? '');
        $excerpt = strtolower($article['excerpt'] ?? '');
        $text = $title . ' ' . $excerpt;

        $maxWeight = 0;
        $totalWeight = 0;
        $matchCount = 0;

        foreach (self::URGENCY_KEYWORDS as $keyword => $weight) {
            if (str_contains($text, $keyword)) {
                $maxWeight = max($maxWeight, $weight);
                $totalWeight += $weight;
                $matchCount++;

                // Title match is stronger
                if (str_contains($title, $keyword)) {
                    $totalWeight += (int)($weight * 0.5);
                }
            }
        }

        if ($matchCount === 0) return 0;

        // Score: dominant keyword weight + bonus for multiple matches
        $score = min(25, (int)(
            ($maxWeight * 1.5) +             // Primary keyword (up to 15)
            min(10, $matchCount * 2.5)        // Multiple match bonus (up to 10)
        ));

        return $score;
    }

    /**
     * Tier 4: Are multiple sources covering the same story?
     */
    private static function calcMultiSourceScore(array $article, array $threadCounts, array $titleClusters): int
    {
        $score = 0;

        // Check story thread (if article is part of a tracked thread)
        $threadId = $article['story_thread_id'] ?? null;
        if ($threadId && isset($threadCounts[$threadId])) {
            $count = $threadCounts[$threadId];
            if ($count >= 5)     $score = 20;
            elseif ($count >= 4) $score = 17;
            elseif ($count >= 3) $score = 14;
            elseif ($count >= 2) $score = 10;
        }

        // Check title similarity clusters (for articles without threads)
        if ($score < 10) {
            $articleId = $article['id'];
            foreach ($titleClusters as $cluster) {
                if (in_array($articleId, $cluster)) {
                    $clusterSize = count($cluster);
                    if ($clusterSize >= 4)     $score = max($score, 20);
                    elseif ($clusterSize >= 3) $score = max($score, 15);
                    elseif ($clusterSize >= 2) $score = max($score, 10);
                    break;
                }
            }
        }

        return min(20, $score);
    }

    // ═══════════════════════════════════════════════════════════
    //  HELPER METHODS
    // ═══════════════════════════════════════════════════════════

    /**
     * Build clusters of articles with similar titles (for multi-source detection).
     * Uses trigram-like comparison: if 60%+ of significant words overlap, they're similar.
     */
    private static function buildTitleClusters(array $articles): array
    {
        $stopwords = ['the', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'and', 'or',
                       'is', 'are', 'was', 'were', 'has', 'have', 'had', 'be', 'been',
                       'with', 'from', 'by', 'as', 'it', 'its', 'this', 'that', 'but',
                       'not', 'no', 'so', 'if', 'up', 'out', 'all', 'can', 'will',
                       'new', 'says', 'said', 'how', 'why', 'what', 'who', 'when', 'where'];

        $tokenized = [];
        foreach ($articles as $a) {
            $title = strtolower(preg_replace('/[^a-z0-9\s]/', '', strtolower($a['title'] ?? '')));
            $words = array_diff(explode(' ', $title), $stopwords);
            $words = array_filter($words, fn($w) => strlen($w) > 2);
            if (count($words) >= 2) {
                $tokenized[] = ['id' => $a['id'], 'words' => array_values($words)];
            }
        }

        $clusters = [];
        $assigned = [];

        for ($i = 0; $i < count($tokenized); $i++) {
            if (isset($assigned[$tokenized[$i]['id']])) continue;

            $cluster = [$tokenized[$i]['id']];
            $assigned[$tokenized[$i]['id']] = true;
            $baseWords = $tokenized[$i]['words'];

            for ($j = $i + 1; $j < count($tokenized); $j++) {
                if (isset($assigned[$tokenized[$j]['id']])) continue;

                $overlap = count(array_intersect($baseWords, $tokenized[$j]['words']));
                $maxLen = max(count($baseWords), count($tokenized[$j]['words']));
                $similarity = $maxLen > 0 ? $overlap / $maxLen : 0;

                if ($similarity >= 0.6) {
                    $cluster[] = $tokenized[$j]['id'];
                    $assigned[$tokenized[$j]['id']] = true;
                }
            }

            if (count($cluster) >= 2) {
                $clusters[] = $cluster;
            }
        }

        return $clusters;
    }

    /**
     * Take a snapshot of current view counts for velocity tracking.
     */
    private static function snapshotViews(\PDO $pdo): int
    {
        // Only snapshot articles published in the last 24h
        $result = $pdo->exec("
            INSERT INTO article_view_snapshots (article_id, views, snapshot_at)
            SELECT id, views, NOW()
            FROM articles
            WHERE status = 'published'
              AND published_at >= NOW() - INTERVAL '24 hours'
              AND views > 0
        ");
        return (int)$result;
    }

    /**
     * Clean snapshots older than 48 hours.
     */
    private static function cleanOldSnapshots(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM article_view_snapshots WHERE snapshot_at < NOW() - INTERVAL '48 hours'");
    }

    /**
     * Expire manual breaking overrides past their breaking_until time.
     */
    private static function expireManualOverrides(\PDO $pdo): int
    {
        $stmt = $pdo->exec("
            UPDATE articles
            SET is_breaking_manual = FALSE, breaking_until = NULL
            WHERE is_breaking_manual = TRUE
              AND breaking_until IS NOT NULL
              AND breaking_until < NOW()
        ");
        return (int)$stmt;
    }

    /**
     * Get velocity baselines by hour.
     */
    private static function getVelocityBaselines(\PDO $pdo): array
    {
        $rows = $pdo->query("SELECT * FROM breaking_velocity_baseline ORDER BY hour_bucket")->fetchAll(\PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['hour_bucket']] = $r;
        }
        return $map;
    }

    /**
     * Get article counts per story thread (for multi-source detection).
     */
    private static function getThreadCounts(\PDO $pdo): array
    {
        $rows = $pdo->query("
            SELECT story_thread_id, COUNT(*) AS cnt
            FROM articles
            WHERE story_thread_id IS NOT NULL
              AND status = 'published'
              AND published_at >= NOW() - INTERVAL '24 hours'
            GROUP BY story_thread_id
            HAVING COUNT(*) >= 2
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) {
            $map[$r['story_thread_id']] = (int)$r['cnt'];
        }
        return $map;
    }

    /**
     * Get the source region for a crawled article.
     */
    private static function getSourceRegion(string $articleId, \PDO $pdo): ?string
    {
        $stmt = $pdo->prepare("
            SELECT cs.region FROM crawl_sources cs
            JOIN articles a ON a.crawl_source_id = cs.id
            WHERE a.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $articleId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['region'] ?? null;
    }

    /**
     * Self-calibrating baseline: update average view velocity per hour.
     */
    private static function updateVelocityBaselines(\PDO $pdo): void
    {
        $hour = (int)date('G');

        // Calculate average views gained in last hour for articles published today
        $row = $pdo->query("
            SELECT AVG(sub.gained) AS avg_gained, COUNT(*) AS cnt
            FROM (
                SELECT a.id,
                       a.views - COALESCE(
                         (SELECT s.views FROM article_view_snapshots s
                          WHERE s.article_id = a.id
                            AND s.snapshot_at <= NOW() - INTERVAL '50 minutes'
                          ORDER BY s.snapshot_at DESC LIMIT 1),
                         0
                       ) AS gained
                FROM articles a
                WHERE a.status = 'published'
                  AND a.published_at >= NOW() - INTERVAL '24 hours'
                  AND a.views > 0
            ) sub
            WHERE sub.gained > 0
        ")->fetch(\PDO::FETCH_ASSOC);

        $avgGained = (float)($row['avg_gained'] ?? 0);
        $cnt = (int)($row['cnt'] ?? 0);

        if ($cnt >= 3 && $avgGained > 0) {
            // Exponential moving average: new = 0.3 * current + 0.7 * old
            $pdo->prepare("
                UPDATE breaking_velocity_baseline
                SET avg_views_1h = ROUND((0.3 * :avg + 0.7 * avg_views_1h)::numeric, 2),
                    sample_count = sample_count + :cnt,
                    updated_at = NOW()
                WHERE hour_bucket = :hour
            ")->execute([':avg' => $avgGained, ':cnt' => $cnt, ':hour' => $hour]);
        }
    }
}