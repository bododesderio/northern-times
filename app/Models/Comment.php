<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Comment model — comments table.
 * Statuses: visible, hidden, deleted.
 */
final class Comment extends BaseModel
{
    protected static string $table   = 'comments';
    protected static string $orderBy = 'created_at DESC';

    public const STATUS_VISIBLE = 'visible';
    public const STATUS_HIDDEN  = 'hidden';
    public const STATUS_DELETED = 'deleted';

    /**
     * Comments for an article (frontend display).
     */
    public static function forArticle(string $articleId): array
    {
        return self::query("
            SELECT id, author_name, content, created_at
            FROM comments
            WHERE article_id = :aid AND status = 'visible'
            ORDER BY created_at ASC
        ", [':aid' => $articleId]);
    }

    /**
     * Post a new comment. Returns the inserted row.
     */
    public static function post(array $data): ?array
    {
        return self::queryOne("
            INSERT INTO comments (article_id, author_name, author_email, content, status, ip_address, created_at)
            VALUES (:aid, :name, :email, :body, 'visible', :ip, NOW())
            RETURNING id, created_at
        ", [
            ':aid'   => $data['article_id'],
            ':name'  => $data['author_name'],
            ':email' => $data['author_email'] ?? '',
            ':body'  => $data['content'],
            ':ip'    => $data['ip_address'] ?? '0.0.0.0',
        ]);
    }

    /**
     * Rate limit check — count recent comments from IP.
     */
    public static function recentCountByIp(string $ip, string $interval = '1 hour'): int
    {
        // Whitelist valid interval values to prevent SQL injection
        $validIntervals = ['1 hour', '30 minutes', '15 minutes', '2 hours', '24 hours'];
        if (!in_array($interval, $validIntervals, true)) {
            $interval = '1 hour';
        }

        return (int)self::queryColumn("
            SELECT COUNT(*) FROM comments
            WHERE ip_address = :ip AND created_at > NOW() - INTERVAL '{$interval}'
        ", [':ip' => $ip]);
    }

    /**
     * Admin listing with filters and pagination.
     */
    public static function adminList(int $page = 1, int $perPage = 30, string $filter = ''): array
    {
        $where  = [];
        $params = [];

        if ($filter === 'hidden') {
            $where[] = "c.status = 'hidden'";
        } elseif ($filter === 'deleted') {
            $where[] = "c.status = 'deleted'";
        } elseif ($filter === 'visible') {
            $where[] = "c.status = 'visible'";
        }

        $whereSql = $where ? implode(' AND ', $where) : '1=1';

        return self::paginate(
            page: $page,
            perPage: $perPage,
            whereSql: $whereSql,
            params: $params,
            orderBy: 'c.created_at DESC',
            selectSql: "c.*, a.title AS article_title, a.slug AS article_slug",
            fromSql: "comments c LEFT JOIN articles a ON a.id = c.article_id"
        );
    }

    /**
     * Status counts for admin filter tabs.
     */
    public static function statusCounts(): array
    {
        $rows = self::query("
            SELECT status, COUNT(*) AS cnt
            FROM comments
            GROUP BY status
        ");
        $counts = ['visible' => 0, 'hidden' => 0, 'deleted' => 0];
        foreach ($rows as $r) {
            $counts[$r['status']] = (int)$r['cnt'];
        }
        return $counts;
    }

    /**
     * Change status of a comment.
     */
    public static function setStatus(string $id, string $status): void
    {
        self::update($id, ['status' => $status]);
    }

    /**
     * Permanently remove a comment.
     */
    public static function destroy(string $id): void
    {
        self::delete($id);
    }

    /**
     * Bulk action on multiple comments.
     */
    public static function bulkAction(array $ids, string $action): void
    {
        if (empty($ids)) return;

        $placeholders = implode(',', array_map(fn($i) => ":id{$i}", array_keys($ids)));
        $params = [];
        foreach ($ids as $i => $id) {
            $params[":id{$i}"] = $id;
        }

        $sql = match ($action) {
            'hide'    => "UPDATE comments SET status='hidden' WHERE id IN ({$placeholders}) AND status='visible'",
            'restore' => "UPDATE comments SET status='visible' WHERE id IN ({$placeholders})",
            'delete'  => "UPDATE comments SET status='deleted' WHERE id IN ({$placeholders})",
            'destroy' => "DELETE FROM comments WHERE id IN ({$placeholders})",
            default   => null,
        };

        if ($sql) {
            self::execute($sql, $params);
        }
    }
}