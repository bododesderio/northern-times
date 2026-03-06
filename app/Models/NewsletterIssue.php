<?php
declare(strict_types=1);

namespace App\Models;

/**
 * NewsletterIssue — tracks each newsletter send campaign.
 */
final class NewsletterIssue extends BaseModel
{
    protected static string $table   = 'newsletter_issues';
    protected static string $orderBy = 'created_at DESC';

    /**
     * Create a new newsletter issue.
     */
    public static function createIssue(string $subject, string $bodyHtml, array $articleIds, string $sentBy): ?array
    {
        $stmt = self::pdo()->prepare(
            "INSERT INTO newsletter_issues (subject, body_html, article_ids, sent_by)
             VALUES (:subj, :html, :aids, :by) RETURNING *"
        );
        $stmt->execute([
            ':subj' => $subject,
            ':html' => $bodyHtml,
            ':aids' => json_encode($articleIds),
            ':by'   => $sentBy,
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Mark issue as sending / sent with counts.
     */
    public static function markSending(string $id): void
    {
        self::execute("UPDATE newsletter_issues SET status = 'sending', sent_at = NOW() WHERE id = :id", [':id' => $id]);
    }

    public static function markSent(string $id, int $sent, int $failed): void
    {
        self::execute(
            "UPDATE newsletter_issues SET status = 'sent', sent_count = :s, failed_count = :f, updated_at = NOW() WHERE id = :id",
            [':id' => $id, ':s' => $sent, ':f' => $failed]
        );
    }

    /**
     * Admin listing (excludes deleted).
     */
    public static function adminList(int $page = 1, int $perPage = 20): array
    {
        return self::paginate(
            page: $page,
            perPage: $perPage,
            orderBy: 'ni.created_at DESC',
            selectSql: "ni.*, u.username AS sent_by_name",
            fromSql: "newsletter_issues ni LEFT JOIN users u ON u.id = ni.sent_by",
            whereSql: "ni.status != 'deleted'"
        );
    }

    /**
     * Permanently delete an issue.
     */
    public static function deleteIssue(string $id): bool
    {
        return self::execute(
            "DELETE FROM newsletter_issues WHERE id = :id",
            [':id' => $id]
        ) > 0;
    }

    /**
     * Soft delete — mark as deleted (can be restored later).
     */
    public static function softDelete(string $id): void
    {
        self::execute(
            "UPDATE newsletter_issues SET status = 'deleted', updated_at = NOW() WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Restore a soft-deleted issue.
     */
    public static function restore(string $id): void
    {
        self::execute(
            "UPDATE newsletter_issues SET status = 'sent', updated_at = NOW() WHERE id = :id AND status = 'deleted'",
            [':id' => $id]
        );
    }

    /**
     * List deleted (trash) issues.
     */
    public static function trash(int $page = 1, int $perPage = 20): array
    {
        return self::paginate(
            page: $page,
            perPage: $perPage,
            orderBy: 'ni.updated_at DESC',
            selectSql: "ni.*, u.username AS sent_by_name",
            fromSql: "newsletter_issues ni LEFT JOIN users u ON u.id = ni.sent_by",
            whereSql: "ni.status = 'deleted'"
        );
    }

    /**
     * Save a new draft issue.
     */
    public static function saveDraft(string $subject, array $articleIds, string $savedBy, string $intro = ''): ?array
    {
        $stmt = self::pdo()->prepare(
            "INSERT INTO newsletter_issues (subject, body_html, article_ids, sent_by, status)
             VALUES (:subj, :html, :aids, :by, 'draft') RETURNING *"
        );
        $stmt->execute([
            ':subj' => $subject,
            ':html' => $intro,
            ':aids' => json_encode($articleIds),
            ':by'   => $savedBy,
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update an existing draft.
     */
    public static function updateDraft(string $id, string $subject, array $articleIds, string $intro = ''): void
    {
        self::execute(
            "UPDATE newsletter_issues SET subject=:subj, body_html=:html, article_ids=:aids, updated_at=NOW()
             WHERE id=:id AND status='draft'",
            [':subj' => $subject, ':html' => $intro, ':aids' => json_encode($articleIds), ':id' => $id]
        );
    }

    /**
     * List draft issues.
     */
    public static function drafts(int $page = 1, int $perPage = 20): array
    {
        return self::paginate(
            page: $page,
            perPage: $perPage,
            orderBy: 'ni.updated_at DESC',
            selectSql: "ni.*, u.username AS sent_by_name",
            fromSql: "newsletter_issues ni LEFT JOIN users u ON u.id = ni.sent_by",
            whereSql: "ni.status = 'draft'"
        );
    }

    /**
     * Save a new issue as 'scheduled' with a send datetime.
     */
    public static function scheduleIssue(string $subject, array $articleIds, string $savedBy, string $scheduledAt, string $intro = ''): ?array
    {
        $stmt = self::pdo()->prepare(
            "INSERT INTO newsletter_issues (subject, body_html, article_ids, sent_by, status, scheduled_at)
             VALUES (:subj, :html, :aids, :by, 'scheduled', :sat) RETURNING *"
        );
        $stmt->execute([
            ':subj' => $subject,
            ':html' => $intro,
            ':aids' => json_encode($articleIds),
            ':by'   => $savedBy,
            ':sat'  => $scheduledAt,
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List all scheduled issues due for sending (scheduled_at <= NOW()).
     */
    public static function dueForSend(): array
    {
        return self::query(
            "SELECT * FROM newsletter_issues WHERE status = 'scheduled' AND scheduled_at <= NOW() ORDER BY scheduled_at ASC"
        );
    }

    /**
     * List upcoming scheduled issues (not yet due).
     */
    public static function scheduled(int $page = 1, int $perPage = 20): array
    {
        return self::paginate(
            page: $page,
            perPage: $perPage,
            orderBy: 'ni.scheduled_at ASC',
            selectSql: "ni.*, u.username AS sent_by_name",
            fromSql: "newsletter_issues ni LEFT JOIN users u ON u.id = ni.sent_by",
            whereSql: "ni.status = 'scheduled'"
        );
    }

    /**
     * Dashboard stats.
     */
    public static function stats(): array
    {
        $pdo = self::pdo();
        return [
            'total'   => (int)$pdo->query("SELECT COUNT(*) FROM newsletter_issues WHERE status != 'deleted'")->fetchColumn(),
            'sent'    => (int)$pdo->query("SELECT COUNT(*) FROM newsletter_issues WHERE status = 'sent'")->fetchColumn(),
            'draft'   => (int)$pdo->query("SELECT COUNT(*) FROM newsletter_issues WHERE status = 'draft'")->fetchColumn(),
            'deleted' => (int)$pdo->query("SELECT COUNT(*) FROM newsletter_issues WHERE status = 'deleted'")->fetchColumn(),
        ];
    }
}