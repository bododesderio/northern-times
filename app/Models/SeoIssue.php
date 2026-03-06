<?php
declare(strict_types=1);

namespace App\Models;

final class SeoIssue extends BaseModel
{
    protected static string $table   = 'seo_issues';
    protected static string $orderBy = 'created_at DESC';

    public const CRITICAL = 'critical';
    public const WARNING  = 'warning';
    public const INFO     = 'info';

    /** Bulk insert issues for an audit. */
    public static function bulkInsert(int $auditId, array $issues): void
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO seo_issues (audit_id, severity, check_name, page_url, article_id, description, suggestion)
             VALUES (:audit_id, :severity, :check_name, :page_url, :article_id, :description, :suggestion)"
        );

        foreach ($issues as $issue) {
            $stmt->execute([
                ':audit_id'    => $auditId,
                ':severity'    => $issue['severity'] ?? self::WARNING,
                ':check_name'  => $issue['check_name'],
                ':page_url'    => $issue['page_url'] ?? null,
                ':article_id'  => $issue['article_id'] ?? null,
                ':description' => $issue['description'],
                ':suggestion'  => $issue['suggestion'] ?? null,
            ]);
        }
    }

    /** Issues for a specific audit, grouped by severity. */
    public static function forAudit(int $auditId): array
    {
        return self::query(
            "SELECT * FROM seo_issues
             WHERE audit_id = :id
             ORDER BY CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END, check_name",
            [':id' => $auditId]
        );
    }

    /** All issues with article context for export. */
    public static function forExport(int $auditId): array
    {
        return self::query(
            "SELECT si.*, a.title AS article_title
             FROM seo_issues si LEFT JOIN articles a ON a.id = si.article_id
             WHERE si.audit_id = :id ORDER BY si.severity, si.check_name",
            [':id' => $auditId]
        );
    }

    /** Issue counts by check_name for a specific audit. */
    public static function summaryForAudit(int $auditId): array
    {
        return self::query(
            "SELECT check_name, severity, COUNT(*) AS count
             FROM seo_issues WHERE audit_id = :id
             GROUP BY check_name, severity
             ORDER BY severity, check_name",
            [':id' => $auditId]
        );
    }
}