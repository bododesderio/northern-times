<?php
declare(strict_types=1);
namespace App\Models;

final class Tag extends BaseModel
{
    protected static string $table   = 'tags';
    protected static string $orderBy = 'name ASC';

    private const STOPWORDS = [
        'the','a','an','and','or','but','in','on','at','to','for','of','with','by','from','as','is','was',
        'are','were','be','been','being','have','has','had','do','does','did','will','would','shall','should',
        'may','might','must','can','could','this','that','these','those','it','its','we','you','he','she',
        'they','me','us','him','her','them','my','our','your','his','their','what','which','who','whom','how',
        'when','where','why','all','each','every','both','few','more','most','other','some','such','nor',
        'not','only','own','same','than','too','very','just','about','above','after','again','also',
        'before','below','between','during','into','through','under','until','out','over','then','once',
        'here','there','because','while','although','since','even','still','already','new','said',
        'says','one','two','three','first','last','many','much','now','year','years','people','time',
        'day','days','week','month','according','like','get','got','make','made','know','take','come',
        'think','look','want','give','use','find','tell','work','seem','feel','try','leave','call',
        'going','back','well','long','part','news','report','reports','source','sources',
        'reuters','associated','press','staff','editor','updated','published','read','share','comment',
    ];

    public static function findOrCreate(string $name): array
    {
        $name = trim($name);
        if ($name === '') return ['id' => 0, 'name' => '', 'slug' => ''];
        $slug = self::makeSlug($name);
        if ($slug === '') return ['id' => 0, 'name' => $name, 'slug' => ''];
        $existing = self::findBy('slug', $slug);
        if ($existing) return $existing;
        $stmt = self::pdo()->prepare(
            "INSERT INTO tags (name, slug) VALUES (:name, :slug)
             ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name RETURNING *"
        );
        $stmt->execute([':name' => $name, ':slug' => $slug]);
        return $stmt->fetch() ?: ['id' => 0, 'name' => $name, 'slug' => $slug];
    }

    public static function forArticle(string|int $articleId): array
    {
        return self::query(
            "SELECT t.id, t.name, t.slug FROM tags t
             JOIN article_tags at ON at.tag_id = t.id
             WHERE at.article_id = :aid ORDER BY t.name ASC",
            [':aid' => $articleId]
        );
    }

    public static function syncForArticle(string|int $articleId, array $tagNames): void
    {
        $tagNames = array_slice(array_unique(array_filter(array_map('trim', $tagNames))), 0, 10);
        $stmt = self::pdo()->prepare("DELETE FROM article_tags WHERE article_id = :aid");
        $stmt->execute([':aid' => $articleId]);
        if (empty($tagNames)) return;
        $insert = self::pdo()->prepare(
            "INSERT INTO article_tags (article_id, tag_id) VALUES (:aid, :tid) ON CONFLICT DO NOTHING"
        );
        foreach ($tagNames as $name) {
            $tag = self::findOrCreate($name);
            if ((int)($tag['id'] ?? 0) > 0) {
                $insert->execute([':aid' => $articleId, ':tid' => $tag['id']]);
            }
        }
    }

    /** Auto-tag crawled article from title + content. Extracts top 3-5 keywords. */
    public static function autoTagFromContent(string|int $articleId, string $content, string $title = ''): void
    {
        $text = strtolower(strip_tags($title . ' ' . $content));
        preg_match_all('/\b([a-z]{4,})\b/', $text, $matches);
        $words = $matches[1] ?? [];
        if (empty($words)) return;
        $freq = [];
        foreach ($words as $w) {
            if (in_array($w, self::STOPWORDS, true)) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
        arsort($freq);
        $tags = [];
        foreach ($freq as $word => $count) {
            if (count($tags) >= 5) break;
            if ($count >= 2 || count($tags) < 3) $tags[] = ucfirst($word);
        }
        if (!empty($tags)) self::syncForArticle($articleId, $tags);
    }

    public static function articles(string $tagSlug, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $countStmt = self::pdo()->prepare(
            "SELECT COUNT(*) FROM articles a
             JOIN article_tags at ON at.article_id = a.id JOIN tags t ON t.id = at.tag_id
             WHERE t.slug = :slug AND a.status = 'published'
               AND (a.published_at IS NULL OR a.published_at <= NOW())"
        );
        $countStmt->execute([':slug' => $tagSlug]);
        $total = (int)$countStmt->fetchColumn();
        $stmt = self::pdo()->prepare(
            "SELECT a.id, a.title, a.slug, a.excerpt, a.featured_image,
                    a.published_at, a.reading_time, a.views,
                    c.name AS category, c.slug AS category_slug,
                    COALESCE(NULLIF(a.display_author,''), u.username, 'Staff') AS author
             FROM articles a
             JOIN article_tags at ON at.article_id = a.id JOIN tags t ON t.id = at.tag_id
             JOIN categories c ON c.id = a.category_id LEFT JOIN users u ON u.id = a.author_id
             WHERE t.slug = :slug AND a.status = 'published'
               AND (a.published_at IS NULL OR a.published_at <= NOW())
             ORDER BY a.published_at DESC NULLS LAST LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':slug', $tagSlug);
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        return ['rows' => $rows, 'total' => $total, 'pages' => (int)ceil($total / max(1,$perPage)), 'page' => $page];
    }

    public static function trending(int $limit = 10): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT t.id, t.name, t.slug, COUNT(at.article_id) AS article_count
             FROM tags t JOIN article_tags at ON at.tag_id = t.id
             JOIN articles a ON a.id = at.article_id
             WHERE a.status = 'published' AND a.published_at >= NOW() - INTERVAL '7 days'
             GROUP BY t.id, t.name, t.slug ORDER BY article_count DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function allNames(): array
    {
        return self::query("SELECT id, name, slug FROM tags ORDER BY name ASC");
    }

    public static function search(string $q, int $limit = 10): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT id, name, slug FROM tags WHERE name ILIKE :q ORDER BY name ASC LIMIT :lim"
        );
        $stmt->bindValue(':q', '%' . $q . '%');
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private static function makeSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        $slug = preg_replace('/[\s\-]+/', '-', $slug);
        return substr(trim($slug, '-'), 0, 120);
    }
}