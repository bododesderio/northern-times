<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Article model — articles table + joins to categories, users.
 *
 * Extracts every raw SQL query that touched the articles table
 * from FrontendController, AdminController, and dashboard.php.
 */
final class Article extends BaseModel
{
    protected static string $table   = 'articles';
    protected static string $orderBy = 'created_at DESC';

    // ── Status constants ─────────────────────────────────────────

    public const STATUS_DRAFT          = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PUBLISHED      = 'published';
    public const STATUS_ARCHIVED       = 'archived';
    public const STATUS_SCHEDULED      = 'scheduled';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
        self::STATUS_SCHEDULED,
    ];

    // ── Published-article filter (reused everywhere) ─────────────

    private const PUBLISHED_FILTER = "a.status = 'published' AND (a.published_at IS NULL OR a.published_at <= NOW())";

    /**
     * White-label crawl author name — resolved once per request.
     * Replaces all hardcoded 'NT Newsroom' strings in SQL CASE expressions.
     */
    /**
     * Returns a SQL subquery expression that resolves to the super_admin's
     * display_name at query-time — never a baked-in PHP string.
     * For crawled articles this means the author is ALWAYS the live super_admin
     * profile from the DB, matching the article detail page behaviour.
     */
    private static function crawlAuthorName(): string
    {
        return "(SELECT COALESCE(NULLIF(su.display_name,''), su.username, 'Staff')
                   FROM users su WHERE su.role = 'super_admin'
                   ORDER BY su.created_at ASC LIMIT 1)";
    }

    // ── Admin queries ────────────────────────────────────────────

    /**
     * Paginated article listing for admin panel (with filters).
     */
    public static function adminList(
        int $page = 1,
        int $perPage = 20,
        string $q = '',
        string $status = '',
        string $categorySlug = '',
        ?string $authorId = null
    ): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]      = "(a.title ILIKE :q OR a.slug ILIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($status !== '' && in_array($status, self::VALID_STATUSES, true)) {
            $where[]           = "a.status = :status";
            $params[':status'] = $status;
        } else {
            // Exclude archived from default listing (archive has its own page)
            $where[] = "a.status != 'archived'";
        }
        if ($categorySlug !== '') {
            $where[]        = "c.slug = :cat";
            $params[':cat'] = $categorySlug;
        }
        if ($authorId !== null) {
            $where[]       = "a.author_id = :me";
            $params[':me'] = $authorId;
        }

        $whereSql = $where ? implode(' AND ', $where) : '1=1';

        return self::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: $whereSql,
            params: $params,
            orderBy: 'a.created_at DESC',
            selectSql: "a.id, a.title, a.slug, a.status, a.published_at, a.author_id,
                        CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Admin') END AS author,
                        a.display_author, a.is_breaking_manual, a.breaking_score,
                        c.name AS category, c.slug AS category_slug",
            fromSql: "articles a
                      LEFT JOIN users u ON u.id = a.author_id
                      JOIN categories c ON c.id = a.category_id"
        );
    }

    /**
     * Store a new article. Returns the inserted row.
     */
    public static function store(array $data): ?array
    {
        $readingTime = max(1, (int)ceil(str_word_count(strip_tags((string)($data['content'] ?? ''))) / 200));

        $sql = "INSERT INTO articles
            (title, slug, content, excerpt, author_id, category_id,
             featured_image, status, published_at, created_by,
             display_author, story_thread_id, is_breaking, breaking_headline, reading_time)
            VALUES
            (:title, :slug, :content, :excerpt, :author_id, :category_id,
             :featured_image, :status, :published_at, :created_by,
             :display_author, :story_thread_id, :is_breaking, :breaking_headline, :reading_time)
            RETURNING *";

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute([
            ':title'             => $data['title'],
            ':slug'              => $data['slug'],
            ':content'           => $data['content'],
            ':excerpt'           => $data['excerpt'] ?? '',
            ':author_id'         => $data['author_id'],
            ':category_id'       => $data['category_id'],
            ':featured_image'    => $data['featured_image'] ?? '',
            ':status'            => $data['status'] ?? self::STATUS_DRAFT,
            ':published_at'      => $data['published_at'] ?? null,
            ':created_by'        => $data['created_by'] ?? $data['author_id'],
            ':display_author'    => $data['display_author'] ?? 'Admin',
            ':story_thread_id'   => $data['story_thread_id'] ?? null,
            ':is_breaking'       => ($data['is_breaking'] ?? false) ? 'true' : 'false',
            ':breaking_headline' => $data['breaking_headline'] ?? '',
            ':reading_time'      => $readingTime,
        ]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Update an existing article. Returns the updated row.
     */
    public static function updateArticle(string $id, array $data): ?array
    {
        $readingTime = max(1, (int)ceil(str_word_count(strip_tags((string)($data['content'] ?? ''))) / 200));

        $sql = "UPDATE articles SET
            title=:title, content=:content, excerpt=:excerpt,
            category_id=:category_id, featured_image=:featured_image,
            status=:status, published_at=:published_at, display_author=:display_author,
            story_thread_id=:story_thread_id, is_breaking=:is_breaking,
            breaking_headline=:breaking_headline, reading_time=:reading_time, updated_at=NOW()
            WHERE id=:id RETURNING *";

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute([
            ':id'                => $id,
            ':title'             => $data['title'],
            ':content'           => $data['content'],
            ':excerpt'           => $data['excerpt'] ?? '',
            ':category_id'       => $data['category_id'],
            ':featured_image'    => $data['featured_image'] ?? '',
            ':status'            => $data['status'] ?? self::STATUS_DRAFT,
            ':published_at'      => $data['published_at'] ?? null,
            ':display_author'    => $data['display_author'] ?? 'Admin',
            ':story_thread_id'   => $data['story_thread_id'] ?? null,
            ':is_breaking'       => ($data['is_breaking'] ?? false) ? 'true' : 'false',
            ':breaking_headline' => $data['breaking_headline'] ?? '',
            ':reading_time'      => $readingTime,
        ]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Increment view counter by 1.
     */
    public static function incrementViews(string $id): void
    {
        self::execute(
            "UPDATE articles SET views = views + 1 WHERE id = :id",
            [':id' => $id]
        );
    }

    // ── Frontend queries ─────────────────────────────────────────

    /**
     * Breaking news ticker — scored + manually flagged articles.
     */
    public static function breaking(int $limit = 8): array
    {
        $rows = self::query("
            SELECT a.id, a.title, a.slug, a.breaking_headline,
                   a.is_breaking, a.is_breaking_manual, a.breaking_score,
                   a.featured_image, a.excerpt, a.published_at, a.views,
                   c.name AS category, c.slug AS category_slug,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.status = 'published'
              AND (a.published_at IS NULL OR a.published_at <= NOW())
              AND a.breaking_score >= 60
            ORDER BY
              a.breaking_score DESC,
              a.published_at DESC NULLS LAST
            LIMIT " . (int)$limit . "
        ");
        return array_values($rows);
    }

    /**
     * Breaking news for homepage — dynamic, returns ALL qualifying articles.
     */
    public static function breakingForHomepage(): array
    {
        return self::query("
            SELECT a.id, a.title, a.slug, a.breaking_headline,
                   a.is_breaking, a.is_breaking_manual, a.breaking_score,
                   a.featured_image, a.excerpt, a.published_at, a.views,
                   c.name AS category, c.slug AS category_slug,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.status = 'published'
              AND (a.published_at IS NULL OR a.published_at <= NOW())
              AND a.breaking_score >= 60
            ORDER BY
              CASE WHEN a.is_breaking_manual = TRUE THEN 0 ELSE 1 END,
              a.breaking_score DESC,
              a.published_at DESC NULLS LAST
            LIMIT 12
        ");
    }

        /**
     * Hero carousel — top-scored published articles.
     */
    public static function heroSlides(int $limit = 6): array
    {
        return self::query("
            SELECT a.id, a.title, a.slug, a.excerpt, a.content,
                   a.featured_image, a.published_at, a.views,
                   c.name AS category, c.slug AS category_slug,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                   (
                     CASE
                       WHEN a.views > 5000 THEN 30
                       WHEN a.views > 2000 THEN 20
                       WHEN a.views > 500  THEN 10
                       ELSE 5
                     END
                     + CASE
                         WHEN EXTRACT(EPOCH FROM (NOW() - a.published_at))/3600 > 0 THEN
                           LEAST(25, (a.views / GREATEST(EXTRACT(EPOCH FROM (NOW() - a.published_at))/3600, 1))::int)
                         ELSE 20
                       END
                     + CASE
                         WHEN a.published_at >= NOW() - INTERVAL '6 hours'  THEN 20
                         WHEN a.published_at >= NOW() - INTERVAL '12 hours' THEN 16
                         WHEN a.published_at >= NOW() - INTERVAL '24 hours' THEN 12
                         WHEN a.published_at >= NOW() - INTERVAL '48 hours' THEN 8
                         WHEN a.published_at >= NOW() - INTERVAL '72 hours' THEN 4
                         ELSE 0
                       END
                     + CASE
                         WHEN LOWER(a.title) LIKE '%uganda%' OR LOWER(a.excerpt) LIKE '%kampala%'
                           OR LOWER(a.title) LIKE '%kampala%' OR LOWER(a.excerpt) LIKE '%uganda%'
                           OR LOWER(c.slug) LIKE '%uganda%'
                         THEN 10 ELSE 0
                       END
                   ) AS top_score
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE " . self::PUBLISHED_FILTER . "
            ORDER BY top_score DESC, a.published_at DESC NULLS LAST
            LIMIT " . (int)$limit . "
        ");
    }

    /**
     * Top stories sidebar — next N scored articles excluding given IDs.
     */
    public static function topStories(array $excludeIds = [], int $limit = 4): array
    {
        // Build parameterized placeholders for exclude IDs
        $excludeParams = [];
        $excludePlaceholders = [];
        if (!empty($excludeIds)) {
            foreach ($excludeIds as $i => $eid) {
                $key = ":exclude{$i}";
                $excludePlaceholders[] = $key;
                $excludeParams[$key] = $eid;
            }
        }
        $excludeClause = !empty($excludePlaceholders)
            ? "AND a.id NOT IN (" . implode(',', $excludePlaceholders) . ")"
            : "";

        return self::query("
            SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                   a.published_at, a.views,
                   c.name AS category, c.slug AS category_slug,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE " . self::PUBLISHED_FILTER . "
              {$excludeClause}
            ORDER BY
              (
                CASE WHEN a.views > 5000 THEN 30 WHEN a.views > 2000 THEN 20 WHEN a.views > 500 THEN 10 ELSE 5 END
                + CASE WHEN a.published_at >= NOW() - INTERVAL '6 hours' THEN 20
                       WHEN a.published_at >= NOW() - INTERVAL '12 hours' THEN 16
                       WHEN a.published_at >= NOW() - INTERVAL '24 hours' THEN 12
                       WHEN a.published_at >= NOW() - INTERVAL '48 hours' THEN 8
                       WHEN a.published_at >= NOW() - INTERVAL '72 hours' THEN 4
                       ELSE 0 END
                + CASE WHEN LOWER(a.title) LIKE '%uganda%' OR LOWER(a.excerpt) LIKE '%uganda%' THEN 10 ELSE 0 END
              ) DESC,
              a.published_at DESC NULLS LAST
            LIMIT " . (int)$limit . "
        ", $excludeParams);
    }

    /**
     * Latest published articles — feeds hero zone + top stories section.
     * Pure chronological, no scoring. Ensures hero zone is always full.
     * Falls back gracefully: tries last 24h first, expands to 72h, then no limit.
     */
    public static function latestPublished(int $limit = 30): array
    {
        return self::query("
            SELECT a.id, a.title, a.slug, a.excerpt, a.content,
                   a.featured_image, a.published_at, a.views,
                   c.name AS category, c.slug AS category_slug,
                   CASE WHEN a.is_crawled = TRUE " .
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE " . self::PUBLISHED_FILTER . "
            ORDER BY a.published_at DESC NULLS LAST, a.created_at DESC
            LIMIT " . (int)$limit . "
        ");
    }

    /**
     * Latest headlines — sidebar list.
     */
    public static function latestHeadlines(int $limit = 12): array
    {
        return self::query("
            SELECT a.title, a.slug, a.published_at,
                   c.name AS category, c.slug AS category_slug
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            WHERE " . self::PUBLISHED_FILTER . "
            ORDER BY a.published_at DESC NULLS LAST, a.created_at DESC
            LIMIT " . (int)$limit . "
        ");
    }

    /**
     * Most read — velocity-based trending.
     */
    public static function mostRead(int $limit = 8): array
    {
        return self::query("
            SELECT a.title, a.slug, a.published_at, a.views
            FROM articles a
            WHERE " . self::PUBLISHED_FILTER . "
            ORDER BY
              (a.views / GREATEST(EXTRACT(EPOCH FROM (NOW() - a.published_at))/3600, 1)) DESC,
              a.views DESC
            LIMIT " . (int)$limit . "
        ");
    }

    /**
     * Articles by category for homepage section.
     */
    public static function byCategoryId(string $categoryId, int $limit = 11): array
    {
        return self::query("
            SELECT a.title, a.slug, a.excerpt, a.featured_image,
                   a.published_at, a.reading_time,
                   CASE WHEN a.is_crawled = TRUE " .
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                   c.name AS category, c.slug AS category_slug
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE " . self::PUBLISHED_FILTER . " AND c.id = :cid
            ORDER BY a.published_at DESC NULLS LAST, a.created_at DESC
            LIMIT " . (int)$limit . "
        ", [':cid' => $categoryId]);
    }

    /**
     * Full article detail (for frontend article page).
     */
    public static function findPublished(string $slug): ?array
    {
        return self::queryOne("
            SELECT a.*,
                   c.name AS category, c.slug AS category_slug,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                   u.username AS author_username,
                   u.role AS author_role,
                   u.bio AS author_bio,
                   u.avatar_url AS author_avatar,
                   u.twitter_handle AS author_twitter,
                   u.facebook_url AS author_facebook,
                   u.linkedin_url AS author_linkedin,
                   u.instagram_handle AS author_instagram,
                   u.whatsapp_number AS author_whatsapp,
                   u.website_url AS author_website
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.slug = :slug AND " . self::PUBLISHED_FILTER . "
            LIMIT 1
        ", [':slug' => $slug]);
    }

    /**
     * Related articles (same category, excluding current).
     */
    public static function related(string $articleId, string $categoryId, int $limit = 6): array
    {
        return self::query("
            SELECT a.title, a.slug, a.excerpt, a.featured_image, a.published_at,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                   c.name AS category, c.slug AS category_slug
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.category_id = :cid AND a.id != :aid AND " . self::PUBLISHED_FILTER . "
            ORDER BY a.published_at DESC NULLS LAST
            LIMIT " . (int)$limit . "
        ", [':cid' => $categoryId, ':aid' => $articleId]);
    }

    /**
     * Articles in same story thread.
     */
    public static function storyThreadArticles(string $threadId, string $excludeId, int $limit = 10): array
    {
        return self::query("
            SELECT a.title, a.slug, a.published_at,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author
            FROM articles a
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.story_thread_id = :tid AND a.id != :aid AND " . self::PUBLISHED_FILTER . "
            ORDER BY a.published_at DESC NULLS LAST
            LIMIT " . (int)$limit . "
        ", [':tid' => $threadId, ':aid' => $excludeId]);
    }

    /**
     * Articles for category page (paginated).
     */
    public static function byCategorySlug(string $categorySlug, int $page = 1, int $perPage = 20): array
    {
        return self::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: self::PUBLISHED_FILTER . " AND c.slug = :slug",
            params: [':slug' => $categorySlug],
            orderBy: "a.published_at DESC NULLS LAST, a.created_at DESC",
            selectSql: "a.id, a.title, a.slug, a.excerpt, a.featured_image, a.published_at,
                        CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                        c.name AS category, c.slug AS category_slug",
            fromSql: "articles a
                      JOIN categories c ON c.id = a.category_id
                      LEFT JOIN users u ON u.id = a.author_id"
        );
    }

    /**
     * Full-text search (paginated).
     */
    public static function search(string $query, int $page = 1, int $perPage = 20): array
    {
        return self::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: self::PUBLISHED_FILTER . " AND (a.title ILIKE :q OR a.content ILIKE :q OR a.excerpt ILIKE :q)",
            params: [':q' => '%' . $query . '%'],
            orderBy: "a.published_at DESC NULLS LAST",
            selectSql: "a.id, a.title, a.slug, a.excerpt, a.featured_image, a.published_at,
                        CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                        c.name AS category, c.slug AS category_slug",
            fromSql: "articles a
                      JOIN categories c ON c.id = a.category_id
                      LEFT JOIN users u ON u.id = a.author_id"
        );
    }

    /**
     * AJAX search API (lightweight, no pagination).
     */
    public static function searchApi(string $query, int $limit = 10): array
    {
        return self::query("
            SELECT a.title, a.slug, a.excerpt, a.featured_image,
                   c.name AS category, c.slug AS category_slug
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            WHERE " . self::PUBLISHED_FILTER . "
              AND (a.title ILIKE :q OR a.excerpt ILIKE :q)
            ORDER BY a.published_at DESC NULLS LAST
            LIMIT " . (int)$limit . "
        ", [':q' => '%' . $query . '%']);
    }

    /**
     * RSS feed items.
     */
    public static function forRss(int $limit = 50): array
    {
        return self::query("
            SELECT a.title, a.slug, a.excerpt, a.content, a.published_at,
                   a.updated_at, a.featured_image,
                   CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author,
                   c.name AS category
            FROM articles a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.author_id
            WHERE " . self::PUBLISHED_FILTER . "
            ORDER BY a.published_at DESC NULLS LAST
            LIMIT " . (int)$limit . "
        ");
    }

    /**
     * Sitemap entries.
     */
    public static function forSitemap(int $limit = 2000): array
    {
        $stmt = self::pdo()->prepare("
            SELECT slug, updated_at, published_at
            FROM articles
            WHERE status = 'published'
              AND (published_at IS NULL OR published_at <= NOW())
            ORDER BY published_at DESC NULLS LAST
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // ── Dashboard stats ──────────────────────────────────────────

    /**
     * Dashboard counts for articles.
     */
    public static function dashboardCounts(): array
    {
        $pdo = self::pdo();
        return [
            'total'     => (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
            'published' => (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn(),
            'drafts'    => (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='draft'")->fetchColumn(),
            'archived'  => (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='archived'")->fetchColumn(),
            'pending'   => (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='pending_review'")->fetchColumn(),
            'views'     => (int)$pdo->query("SELECT COALESCE(SUM(views),0) FROM articles WHERE status='published'")->fetchColumn(),
        ];
    }

    // ── Editorial workflow ───────────────────────────────────────

    /**
     * Submit an article for review.
     */
    public static function submitForReview(string $id): void
    {
        self::execute(
            "UPDATE articles SET status = 'pending_review', review_notes = NULL, reviewed_by = NULL, reviewed_at = NULL, updated_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Approve an article (set to published).
     */
    public static function approveArticle(string $id, string $reviewerId, ?string $notes = null): void
    {
        self::execute(
            "UPDATE articles SET status = 'published', published_at = COALESCE(published_at, NOW()),
             reviewed_by = :reviewer, reviewed_at = NOW(), review_notes = :notes, updated_at = NOW()
             WHERE id = :id",
            [':id' => $id, ':reviewer' => $reviewerId, ':notes' => $notes]
        );
    }

    /**
     * Reject an article (send back to draft).
     */
    public static function rejectArticle(string $id, string $reviewerId, ?string $notes = null): void
    {
        self::execute(
            "UPDATE articles SET status = 'draft',
             reviewed_by = :reviewer, reviewed_at = NOW(), review_notes = :notes, updated_at = NOW()
             WHERE id = :id",
            [':id' => $id, ':reviewer' => $reviewerId, ':notes' => $notes]
        );
    }

    /**
     * List articles pending review (for review queue).
     */
    public static function pendingReview(int $page = 1, int $perPage = 20): array
    {
        return self::paginate(
            page: $page,
            perPage: $perPage,
            selectSql: "a.*, c.name AS category, CASE WHEN a.is_crawled = TRUE " . 
                    "THEN " . self::crawlAuthorName() . " ELSE COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') END AS author_name",
            fromSql: "articles a
                      LEFT JOIN categories c ON c.id = a.category_id
                      LEFT JOIN users u ON u.id = a.author_id",
            whereSql: "a.status = 'pending_review'",
            orderBy: 'a.updated_at ASC'
        );
    }

    /**
     * Count of articles pending review.
     */
    public static function pendingCount(): int
    {
        return (int)self::queryOne("SELECT COUNT(*) AS cnt FROM articles WHERE status = 'pending_review'")['cnt'];
    }

    /**
     * My drafts (for current author).
     */
    public static function myDrafts(string $authorId, int $limit = 10): array
    {
        return self::query(
            "SELECT a.title, a.slug, a.id, a.status, a.updated_at, c.name AS category
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             WHERE a.author_id = :aid AND a.status IN ('draft','pending_review')
             ORDER BY a.updated_at DESC
             LIMIT " . (int)$limit . "",
            [':aid' => $authorId]
        );
    }

    // ── Scheduling ──────────────────────────────────────────────

    /**
     * Find articles ready for auto-publish:
     * status = 'scheduled' AND published_at <= NOW()
     */
    public static function findDueForPublish(): array
    {
        return self::query(
            "SELECT id, title, slug FROM articles
             WHERE status = 'scheduled'
               AND published_at IS NOT NULL
               AND published_at <= NOW()
             ORDER BY published_at ASC"
        );
    }

    /**
     * Publish all scheduled articles whose published_at has passed.
     * Returns the count of articles published.
     */
    public static function publishScheduled(): int
    {
        $due = self::findDueForPublish();
        if (empty($due)) return 0;

        $pdo = \App\Services\DB::pdo();
        $stmt = $pdo->prepare(
            "UPDATE articles SET status = 'published', updated_at = NOW()
            WHERE id = :id AND status = 'scheduled'"
        );

        $count = 0;
        foreach ($due as $article) {
            $stmt->execute([':id' => $article['id']]);
            if ($stmt->rowCount() > 0) {
                $count++;
            }
        }

        return $count;
    }
}