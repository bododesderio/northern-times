<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\StoryThread;
use App\Models\BaseModel;

/**
 * Automatically detects whether a crawled article belongs to an existing
 * story thread by comparing title keywords and named entities against
 * active threads and their articles.
 *
 * Strategy:
 *  1. Extract significant keywords from the incoming title (stop-words removed).
 *  2. Query active threads whose title shares ≥ 2 significant words with the article.
 *  3. If no thread match, check recent articles (last 14 days) for title overlap ≥ 3 words.
 *     If found and that article belongs to a thread, use that thread.
 *  4. If overlap is found but no thread exists yet, auto-create a new thread.
 *  5. Returns the thread UUID or null.
 */
final class StoryThreadDetector
{
    /** Minimum keyword overlap to match an existing thread title */
    private const THREAD_TITLE_MIN_OVERLAP = 2;

    /** Minimum keyword overlap to match a sibling article title */
    private const ARTICLE_TITLE_MIN_OVERLAP = 3;

    /** How far back to look for sibling articles (days) */
    private const LOOKBACK_DAYS = 14;

    /** Common English + regional stop words to ignore */
    private const STOP_WORDS = [
        'the','a','an','and','or','but','in','on','at','to','for','of','with',
        'by','from','is','are','was','were','be','been','being','have','has',
        'had','do','does','did','will','would','could','should','may','might',
        'shall','can','not','no','so','if','then','than','that','this','these',
        'those','it','its','he','she','they','we','you','i','my','our','your',
        'his','her','their','me','him','us','them','what','which','who','whom',
        'how','when','where','why','all','each','every','both','few','more',
        'most','other','some','such','only','very','just','also','now','new',
        'says','said','over','after','before','about','up','out','into','back',
        'uganda','northern','times','source','via','news','report','reports',
    ];

    /**
     * Attempt to find or create a matching story thread for the given article.
     *
     * @param string $title   The article title
     * @param string $content The article body (used for entity extraction)
     * @return string|null     Thread UUID or null if no match
     */
    public static function detect(string $title, string $content = ''): ?string
    {
        $keywords = self::extractKeywords($title);

        if (count($keywords) < 2) {
            return null; // Title too generic to match
        }

        // Step 1: Match against existing active thread titles
        $threadMatch = self::matchAgainstThreads($keywords);
        if ($threadMatch) {
            return $threadMatch;
        }

        // Step 2: Match against recent article titles
        $siblingMatch = self::matchAgainstRecentArticles($keywords, $title);
        if ($siblingMatch) {
            return $siblingMatch;
        }

        return null;
    }

    /**
     * Extract significant keywords from a title.
     * Removes stop words, short words, and numbers.
     */
    public static function extractKeywords(string $title): array
    {
        // Normalize: lowercase, strip punctuation
        $clean = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $title));
        $words = array_filter(explode(' ', $clean), fn($w) => strlen($w) > 2);

        // Remove stop words
        $stopSet = array_flip(self::STOP_WORDS);
        $keywords = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w !== '' && !isset($stopSet[$w]) && !is_numeric($w)) {
                $keywords[] = $w;
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Check if keywords overlap enough with any active thread title.
     */
    private static function matchAgainstThreads(array $keywords): ?string
    {
        $threads = StoryThread::activeDropdown();

        foreach ($threads as $thread) {
            $threadKeywords = self::extractKeywords($thread['title']);
            $overlap = array_intersect($keywords, $threadKeywords);

            if (count($overlap) >= self::THREAD_TITLE_MIN_OVERLAP) {
                return $thread['id'];
            }
        }

        return null;
    }

    /**
     * Check if keywords overlap with any recent article's title.
     * If that article has a thread, return it. If not, auto-create one.
     */
    private static function matchAgainstRecentArticles(array $keywords, string $incomingTitle): ?string
    {
        $days = self::LOOKBACK_DAYS;

        // Fetch recent published articles with their titles + thread IDs
        $recent = BaseModel::query(
            "SELECT id, title, story_thread_id
             FROM articles
             WHERE status = 'published'
               AND created_at > NOW() - INTERVAL '" . (int)$days . " days'
             ORDER BY created_at DESC
             LIMIT 200"
        );

        $bestMatch = null;
        $bestOverlap = 0;

        foreach ($recent as $article) {
            $articleKeywords = self::extractKeywords($article['title']);
            $overlap = array_intersect($keywords, $articleKeywords);
            $overlapCount = count($overlap);

            if ($overlapCount >= self::ARTICLE_TITLE_MIN_OVERLAP && $overlapCount > $bestOverlap) {
                $bestOverlap = $overlapCount;
                $bestMatch = $article;
            }
        }

        if (!$bestMatch) {
            return null;
        }

        // If the sibling already has a thread, use it
        if (!empty($bestMatch['story_thread_id'])) {
            return $bestMatch['story_thread_id'];
        }

        // Auto-create a thread from the shared keywords
        $sharedKeywords = array_intersect($keywords, self::extractKeywords($bestMatch['title']));
        $threadTitle = self::buildThreadTitle($sharedKeywords, $incomingTitle);
        $threadSlug  = Slug::unique(Slug::make($threadTitle), null, 'story_threads');

        $row = BaseModel::queryOne(
            "INSERT INTO story_threads (title, slug, description, is_active)
             VALUES (:title, :slug, :desc, TRUE)
             RETURNING id",
            [
                ':title' => mb_substr($threadTitle, 0, 255),
                ':slug'  => $threadSlug,
                ':desc'  => 'Auto-detected story thread from crawler',
            ]
        );

        if (!$row) {
            return null;
        }

        $threadId = $row['id'];

        // Assign the sibling article to this new thread too
        BaseModel::execute(
            "UPDATE articles SET story_thread_id = :tid WHERE id = :aid AND story_thread_id IS NULL",
            [':tid' => $threadId, ':aid' => $bestMatch['id']]
        );

        return $threadId;
    }

    /**
     * Build a human-readable thread title from shared keywords.
     * E.g., ["gulu", "road", "construction"] → "Gulu Road Construction"
     */
    private static function buildThreadTitle(array $keywords, string $fallbackTitle): string
    {
        if (count($keywords) >= 2) {
            // Capitalize each word
            return implode(' ', array_map('ucfirst', array_slice($keywords, 0, 5)));
        }

        // Fallback: use first 6 words of the title
        $words = explode(' ', $fallbackTitle);
        return implode(' ', array_slice($words, 0, 6));
    }
}