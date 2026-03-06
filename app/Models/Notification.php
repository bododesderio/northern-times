<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Notification — in-app notifications for editorial workflow.
 */
final class Notification extends BaseModel
{
    protected static string $table = 'notifications';

    // ── Notification types ──────────────────────────────────────
    public const TYPE_ARTICLE_SUBMITTED = 'article.submitted';
    public const TYPE_ARTICLE_APPROVED  = 'article.approved';
    public const TYPE_ARTICLE_REJECTED  = 'article.rejected';
    public const TYPE_COMMENT_NEW       = 'comment.new';
    public const TYPE_SYSTEM            = 'system';

    /**
     * Create a notification for a specific user.
     */
    public static function send(string $userId, string $type, string $title, ?string $body = null, ?string $link = null, ?array $metadata = null): void
    {
        self::execute(
            "INSERT INTO notifications (user_id, type, title, body, link, metadata)
             VALUES (:uid, :type, :title, :body, :link, :meta)",
            [
                ':uid'   => $userId,
                ':type'  => $type,
                ':title' => $title,
                ':body'  => $body,
                ':link'  => $link,
                ':meta'  => $metadata ? json_encode($metadata) : null,
            ]
        );
    }

    /**
     * Notify all editors/admins (users who can review articles).
     */
    public static function notifyEditors(string $type, string $title, ?string $body = null, ?string $link = null, ?array $metadata = null): void
    {
        $editors = self::query(
            "SELECT u.id FROM users u
             WHERE u.role IN ('editor','super_admin')
               AND u.is_active = TRUE"
        );
        foreach ($editors as $editor) {
            self::send($editor['id'], $type, $title, $body, $link, $metadata);
        }
    }

    /**
     * Get notifications for a user (newest first).
     */
    public static function forUser(string $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $filter = $unreadOnly ? 'AND is_read = FALSE' : '';
        $stmt = self::pdo()->prepare(
            "SELECT * FROM notifications
             WHERE user_id = :uid {$filter}
             ORDER BY created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Count unread notifications for a user.
     */
    public static function unreadCount(string $userId): int
    {
        $row = self::queryOne(
            "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = :uid AND is_read = FALSE",
            [':uid' => $userId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Mark a single notification as read.
     */
    public static function markRead(string $id, string $userId): void
    {
        self::execute(
            "UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :uid",
            [':id' => $id, ':uid' => $userId]
        );
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllRead(string $userId): void
    {
        self::execute(
            "UPDATE notifications SET is_read = TRUE WHERE user_id = :uid AND is_read = FALSE",
            [':uid' => $userId]
        );
    }

    /**
     * Delete old read notifications (cleanup — keep last 30 days).
     */
    public static function cleanup(int $days = 30): int
    {
        return self::execute(
            "DELETE FROM notifications WHERE is_read = TRUE AND created_at < NOW() - INTERVAL '" . (int)$days . " days'"
        );
    }
}