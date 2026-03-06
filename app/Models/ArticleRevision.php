<?php
declare(strict_types=1);
namespace App\Models;

final class ArticleRevision extends BaseModel
{
    protected static string $table   = 'article_revisions';
    protected static string $orderBy = 'revision_number DESC';

    public static function createRevision(string|int $articleId, string|int|null $userId, string $title, string $content, string $excerpt = ''): ?array
    {
        $nextNum = self::nextRevisionNumber($articleId);
        $wordCount = str_word_count(strip_tags($content));
        $stmt = self::pdo()->prepare(
            "INSERT INTO article_revisions (article_id, user_id, title, content, excerpt, revision_number, word_count)
             VALUES (:aid, :uid, :title, :content, :excerpt, :num, :wc) RETURNING *"
        );
        $stmt->execute([
            ':aid' => $articleId, ':uid' => $userId, ':title' => $title,
            ':content' => $content, ':excerpt' => $excerpt,
            ':num' => $nextNum, ':wc' => $wordCount,
        ]);
        return $stmt->fetch() ?: null;
    }

    public static function forArticle(string|int $articleId): array
    {
        return self::query(
            "SELECT r.id, r.revision_number, r.word_count, r.created_at,
                    COALESCE(u.username, 'System') AS editor_name
             FROM article_revisions r LEFT JOIN users u ON u.id = r.user_id
             WHERE r.article_id = :aid ORDER BY r.revision_number DESC",
            [':aid' => $articleId]
        );
    }

    public static function findFull(int $revisionId): ?array
    {
        return self::queryOne(
            "SELECT r.*, COALESCE(u.username, 'System') AS editor_name
             FROM article_revisions r LEFT JOIN users u ON u.id = r.user_id
             WHERE r.id = :id",
            [':id' => $revisionId]
        );
    }

    public static function countForArticle(string|int $articleId): int
    {
        return (int)self::queryColumn(
            "SELECT COUNT(*) FROM article_revisions WHERE article_id = :aid",
            [':aid' => $articleId]
        );
    }

    private static function nextRevisionNumber(string|int $articleId): int
    {
        $max = self::queryColumn(
            "SELECT COALESCE(MAX(revision_number), 0) FROM article_revisions WHERE article_id = :aid",
            [':aid' => $articleId]
        );
        return ((int)$max) + 1;
    }
}